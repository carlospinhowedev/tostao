<?php

namespace App\Services;

use App\Brokers\BrokerRegistry;
use App\Models\BrokerLink;
use App\Models\Category;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Brings an investment account up to date: today's value, and the money that
 * moved in and out. Movements are filed as transfers, so putting 500 into a
 * broker never shows up as spending.
 */
class BrokerSync
{
    /** Days re-read before the last sync; duplicates are ignored by their reference. */
    private const OVERLAP_DAYS = 5;

    public function __construct(private BrokerRegistry $brokers, private ImportService $import) {}

    /** @return int new transactions created across all links */
    public function syncAll(): int
    {
        $total = 0;

        BrokerLink::with('account')->each(function (BrokerLink $link) use (&$total) {
            $total += $this->sync($link);
        });

        return $total;
    }

    /** @return int new transactions created */
    public function sync(BrokerLink $link): int
    {
        try {
            $provider = $this->brokers->get($link->provider);
            $account = $link->account;

            $from = $link->last_synced_at
                ? $link->last_synced_at->copy()->subDays(self::OVERLAP_DAYS)->startOfDay()
                : Carbon::parse($link->sync_from ?? now()->subDays((int) config('tostao.brokers.history_days')))->startOfDay();

            $rows = [];
            foreach ($provider->cashMovements($from) as $i => $tx) {
                $rows[] = [
                    'line' => $i + 1, 'date' => $tx->date, 'description' => $tx->description,
                    'amount_cents' => $tx->amountCents, 'external_id' => $tx->externalId,
                ];
            }

            $transfer = Category::where('kind', 'transfer')->value('id');
            $items = [];
            foreach ($this->import->prepare($account, $rows) as $row) {
                if ($row['status'] === 'duplicate') {
                    continue;
                }
                $row['category_id'] = $transfer;
                $row['owner_id'] = $account->owner_id;
                $items[] = $row;
            }

            $created = $this->import->commit($account, $items);

            $account->recordValuation(now()->format('Y-m-d'), $provider->valueCents(), $provider->key());
        } catch (Throwable $e) {
            report($e);
            $link->update(['last_error' => $e->getMessage()]);

            return 0;
        }

        $link->update(['last_synced_at' => now(), 'last_error' => null]);

        return $created;
    }
}
