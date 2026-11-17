<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class ImportService
{
    public function __construct(private RuleEngine $rules) {}

    /**
     * Decorate parsed rows with a stable hash, a duplicate status and the
     * category/owner suggested by the rules.
     *
     * Status: "new", "duplicate" (this exact row was imported before) or "maybe"
     * (the account already has a transaction with the same date and amount that
     * no row of this file accounts for: typed in by hand, or imported from
     * another file or format where the bank worded the description differently).
     * A "maybe" is never ticked by default, so a statement that overlaps an
     * earlier import does not silently double up.
     *
     * @param  list<array{line: int, date: string, description: string, amount_cents: int}>  $rows
     * @return list<array<string, mixed>>
     */
    public function prepare(Account $account, array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $dates = array_column($rows, 'date');
        $existing = Transaction::where('account_id', $account->id)
            ->betweenDates(min($dates), max($dates))
            ->get(['import_hash', 'date', 'amount_cents']);

        $hashes = $existing->pluck('import_hash')->filter()->flip();

        // How many transactions already sit on each date and amount, whatever their source.
        $slots = $existing->countBy(fn ($t) => $t->date->format('Y-m-d').'|'.$t->amount_cents);

        $seen = [];
        $prepared = [];
        $taken = [];

        // First pass: exact repeats, which use up their own slot.
        foreach ($rows as $row) {
            $key = $row['date'].'|'.$row['amount_cents'].'|'.mb_strtolower($row['description']);
            $seen[$key] = ($seen[$key] ?? 0) + 1;
            // Identical rows on the same day are legitimate (three coffees), so the
            // occurrence number is part of the hash.
            $hash = hash('sha256', $account->id.'|'.$key.'|'.$seen[$key]);
            // A bank that gives every movement its own reference is a better identity
            // than guessing from the text, and survives the bank rewording a description.
            if (! empty($row['external_id'])) {
                $hash = hash('sha256', $account->id.'|ref|'.$row['external_id']);
            }

            $slot = $row['date'].'|'.$row['amount_cents'];
            $duplicate = $hashes->has($hash);
            if ($duplicate) {
                $taken[$slot] = ($taken[$slot] ?? 0) + 1;
            }

            $prepared[] = [$row, $hash, $slot, $duplicate];
        }

        // Second pass: what is left of an occupied slot makes the next rows "maybe".
        $out = [];
        foreach ($prepared as [$row, $hash, $slot, $duplicate]) {
            $status = 'new';
            if ($duplicate) {
                $status = 'duplicate';
            } elseif (($slots[$slot] ?? 0) - ($taken[$slot] ?? 0) > 0) {
                $status = 'maybe';
                $taken[$slot] = ($taken[$slot] ?? 0) + 1;
            }

            $out[] = $row + ['hash' => $hash, 'status' => $status] + $this->rules->suggest($row['description'], $row['amount_cents']);
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $items  prepared rows the user chose to keep
     */
    public function commit(Account $account, array $items): int
    {
        return DB::transaction(function () use ($account, $items) {
            $count = 0;
            foreach ($items as $item) {
                $created = Transaction::firstOrCreate(
                    ['import_hash' => $item['hash']],
                    [
                        'account_id' => $account->id,
                        'category_id' => $item['category_id'] ?? null,
                        'owner_id' => $item['owner_id'] ?? null,
                        'date' => $item['date'],
                        'description' => $item['description'],
                        'amount_cents' => $item['amount_cents'],
                    ],
                );
                $count += $created->wasRecentlyCreated ? 1 : 0;
            }

            return $count;
        });
    }
}
