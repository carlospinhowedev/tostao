<?php

namespace App\Services;

use App\Banking\BankAuthException;
use App\Banking\BankProvider;
use App\Banking\BankRegistry;
use App\Banking\Consent;
use App\Models\BankAccount;
use App\Models\BankConnection;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Pulls new transactions from the banks and feeds them through the same
 * ImportService the CSV import uses, so duplicate protection and the rules
 * work identically whichever way a movement arrives.
 */
class BankSync
{
    /** Days re-read before the last sync. The bank may book late; duplicates are ignored. */
    private const OVERLAP_DAYS = 5;

    public function __construct(
        private BankRegistry $banks,
        private ImportService $import,
    ) {}

    /** @return int new transactions created */
    public function syncAll(): int
    {
        $total = 0;

        BankConnection::where('status', 'active')->each(function (BankConnection $connection) use (&$total) {
            $total += $this->sync($connection);
        });

        return $total;
    }

    /** @return int new transactions created */
    public function sync(BankConnection $connection): int
    {
        if ($connection->status !== 'active') {
            return 0;
        }

        if ($connection->isExpired()) {
            $this->fail($connection, 'expired', __('The consent expired. Renew the connection to keep syncing.'));

            return 0;
        }

        try {
            $provider = $this->banks->get($connection->provider);
            $consent = new Consent((string) $connection->session_id, [], $connection->valid_until);
            $created = 0;

            foreach ($connection->accounts()->whereNotNull('account_id')->with('account')->get() as $link) {
                $created += $this->syncAccount($provider, $consent, $link);
            }
        } catch (BankAuthException $e) {
            $this->fail($connection, 'expired', $e->getMessage());

            return 0;
        } catch (Throwable $e) {
            report($e);
            $connection->update(['last_error' => $e->getMessage()]);

            return 0;
        }

        $connection->update(['last_synced_at' => now(), 'last_error' => null]);

        return $created;
    }

    private function syncAccount(BankProvider $provider, Consent $consent, BankAccount $link): int
    {
        $account = $link->account;
        $from = $link->last_synced_at
            ? $link->last_synced_at->copy()->subDays(self::OVERLAP_DAYS)->startOfDay()
            : Carbon::parse($link->sync_from ?? now()->subDays((int) config('tostao.banks.history_days')))->startOfDay();

        $rows = [];
        foreach ($provider->transactions($consent, $link->remote_id, $from) as $i => $tx) {
            $rows[] = [
                'line' => $i + 1,
                'date' => $tx->date,
                'description' => $tx->description,
                'amount_cents' => $tx->amountCents,
                'external_id' => $tx->externalId,
            ];
        }

        $items = [];
        foreach ($this->import->prepare($account, $rows) as $row) {
            // A "maybe" is a manual entry with the same date and amount. Skipping it
            // would silently lose real movements, so keep it; the person can merge later.
            if ($row['status'] === 'duplicate') {
                continue;
            }

            // With no rule saying otherwise, a movement belongs to the account's owner.
            if (! $row['set_owner']) {
                $row['owner_id'] = $account->owner_id;
            }

            $items[] = $row;
        }

        $created = $this->import->commit($account, $items);
        $link->update(['last_synced_at' => now()]);

        return $created;
    }

    private function fail(BankConnection $connection, string $status, string $message): void
    {
        $connection->update(['status' => $status, 'last_error' => $message]);
    }
}
