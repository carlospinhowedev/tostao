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
     * Status: "new", "duplicate" (already imported) or "maybe" (a manual
     * transaction with the same date and amount exists).
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
        $manual = $existing->whereNull('import_hash')
            ->map(fn ($t) => $t->date->format('Y-m-d').'|'.$t->amount_cents)->flip();

        $seen = [];
        $out = [];

        foreach ($rows as $row) {
            $key = $row['date'].'|'.$row['amount_cents'].'|'.mb_strtolower($row['description']);
            $seen[$key] = ($seen[$key] ?? 0) + 1;
            // Identical rows on the same day are legitimate (three coffees), so the
            // occurrence number is part of the hash.
            $hash = hash('sha256', $account->id.'|'.$key.'|'.$seen[$key]);

            $status = 'new';
            if ($hashes->has($hash)) {
                $status = 'duplicate';
            } elseif ($manual->has($row['date'].'|'.$row['amount_cents'])) {
                $status = 'maybe';
            }

            $out[] = $row + ['hash' => $hash, 'status' => $status] + $this->rules->suggest($row['description']);
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
