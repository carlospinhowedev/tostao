<?php

namespace App\Brokers;

use App\Banking\BankAuthException;
use App\Banking\BankException;
use App\Banking\BankTransaction;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Trading 212 public API (https://docs.trading212.com/api, beta, Invest and
 * Stocks ISA accounts only).
 *
 * Authentication is HTTP Basic with the API key as the user and the API secret
 * as the password. Create both in the Trading 212 app: Settings, API (Beta).
 * A key limited to the permissions "account" and "history:transactions" is all
 * this class needs, and it cannot place orders.
 */
class Trading212Provider implements BrokerProvider
{
    /** Pages fetched per run. The endpoint allows 20 requests a minute. */
    private const MAX_PAGES = 15;

    public function key(): string
    {
        return 'trading212';
    }

    public function label(): string
    {
        return 'Trading 212';
    }

    public function isConfigured(): bool
    {
        return filled($this->setting('api_key')) && filled($this->setting('api_secret'));
    }

    public function valueCents(): int
    {
        $response = $this->request()->get('/equity/account/summary');
        $this->check($response);

        // Add up the parts rather than trust one total whose docs do not say if it includes cash.
        $parts = [
            $response->json('cash.availableToTrade'),
            $response->json('cash.inPies'),
            $response->json('cash.reservedForOrders'),
            $response->json('investments.currentValue'),
        ];

        if (collect($parts)->every(fn ($p) => is_numeric($p))) {
            return (int) round(array_sum($parts) * 100);
        }

        $total = $response->json('totalValue');
        if (! is_numeric($total)) {
            throw new BankException('Trading 212 did not return the account value.');
        }

        return (int) round($total * 100);
    }

    public function cashMovements(CarbonInterface $from): array
    {
        $out = [];
        $path = '/equity/history/transactions?limit=50';

        for ($page = 0; $page < self::MAX_PAGES && $path; $page++) {
            $response = $this->request()->get($path);
            $this->check($response);

            $items = $response->json('items', []);
            foreach ($items as $item) {
                $mapped = $this->map($item, $from);
                if ($mapped) {
                    $out[] = $mapped;
                }
            }

            // Newest first: once a whole page is older than the start date, the rest is too.
            $dates = array_filter(array_map(fn ($i) => $this->date($i['dateTime'] ?? null), $items));
            $descending = $dates !== [] && reset($dates) >= end($dates);
            if ($descending && $dates !== [] && max($dates) < $from->format('Y-m-d')) {
                break;
            }

            // The server hands back a full path; ours already carries the /api/v0 prefix.
            $next = $response->json('nextPagePath');
            $path = is_string($next) && $next !== '' ? preg_replace('#^/api/v0#', '', $next) : null;
        }

        return $out;
    }

    /**
     * Only deposits and withdrawals: the money that crosses the border between
     * the household and the broker. Fees, interest and other kinds are ignored.
     *
     * @param  array<string, mixed>  $item
     */
    private function map(array $item, CarbonInterface $from): ?BankTransaction
    {
        $type = strtoupper((string) ($item['type'] ?? ''));
        if (! in_array($type, ['DEPOSIT', 'WITHDRAW'], true)) {
            return null;
        }

        $date = $this->date($item['dateTime'] ?? null);
        if ($date === null || $date < $from->format('Y-m-d') || ! is_numeric($item['amount'] ?? null)) {
            return null;
        }

        // The docs do not give the sign of a withdrawal, so the type decides, not the sign.
        $cents = (int) round(abs((float) $item['amount']) * 100);
        if ($cents === 0) {
            return null;
        }

        return new BankTransaction(
            date: $date,
            amountCents: $type === 'DEPOSIT' ? $cents : -$cents,
            description: $type === 'DEPOSIT' ? 'Trading 212 deposit' : 'Trading 212 withdrawal',
            externalId: isset($item['reference']) ? 'trading212:'.$item['reference'] : null,
        );
    }

    private function date(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    private function check(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $message = $response->json('message') ?? $response->json('error') ?? $response->reason();

        if (in_array($response->status(), [401, 403], true)) {
            throw new BankAuthException("Trading 212 refused access ({$response->status()}): check the API key, the secret and the key permissions.");
        }
        if ($response->status() === 429) {
            throw new BankException('Trading 212 is limiting requests. Try again in a minute.');
        }

        throw new BankException("Trading 212 error {$response->status()}: {$message}");
    }

    private function request(): PendingRequest
    {
        $host = $this->setting('environment') === 'demo' ? 'https://demo.trading212.com' : 'https://live.trading212.com';

        return Http::baseUrl($host.'/api/v0')
            ->withBasicAuth((string) $this->setting('api_key'), (string) $this->setting('api_secret'))
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 500, fn ($e) => $e instanceof ConnectionException, throw: false);
    }

    private function setting(string $key): ?string
    {
        return config("tostao.brokers.providers.trading212.{$key}");
    }
}
