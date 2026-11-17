<?php

namespace App\Banking\Providers;

use App\Banking\BankAuthException;
use App\Banking\BankException;
use App\Banking\BankProvider;
use App\Banking\BankTransaction;
use App\Banking\Consent;
use App\Banking\ConsentStart;
use App\Banking\Institution;
use App\Banking\RemoteAccount;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Enable Banking (https://enablebanking.com/docs/api/reference/).
 *
 * Authentication is a short-lived JWT signed with the private key of your
 * own application, so no secret is ever sent over the wire. Institution ids
 * are "<country>|<bank name>" because the API identifies a bank by both.
 */
class EnableBankingProvider implements BankProvider
{
    private const BASE = 'https://api.enablebanking.com';

    public function key(): string
    {
        return 'enablebanking';
    }

    public function label(): string
    {
        return 'Enable Banking';
    }

    public function isConfigured(): bool
    {
        return filled($this->appId()) && $this->privateKey() !== null;
    }

    public function institutions(string $country): array
    {
        $country = strtoupper($country);

        // The list changes rarely; a day of caching keeps the page fast.
        // A failed request throws, so errors are never cached.
        $banks = Cache::remember("tostao.banks.enablebanking.{$country}", now()->addDay(), function () use ($country) {
            $response = $this->request()->get('/aspsps', ['country' => $country]);
            $this->check($response);

            return $response->json('aspsps', []);
        });

        return $this->mapInstitutions($banks, $country);
    }

    public function startConsent(string $institutionId, string $state, string $redirectUrl, CarbonInterface $validUntil): ConsentStart
    {
        [$country, $name] = array_pad(explode('|', $institutionId, 2), 2, '');

        $response = $this->request()->post('/auth', [
            'access' => ['valid_until' => $validUntil->copy()->utc()->format('Y-m-d\TH:i:s\Z')],
            'aspsp' => ['name' => $name, 'country' => $country],
            'state' => $state,
            'redirect_url' => $redirectUrl,
            'psu_type' => 'personal',
        ]);
        $this->check($response);

        $url = $response->json('url');
        if (! is_string($url) || $url === '') {
            throw new BankException('Enable Banking did not return an authorisation URL.');
        }

        return new ConsentStart($url);
    }

    public function completeConsent(string $code): Consent
    {
        $response = $this->request()->post('/sessions', ['code' => $code]);
        $this->check($response);

        $accounts = collect($response->json('accounts', []))
            ->map(fn (array $a) => new RemoteAccount(
                id: (string) ($a['uid'] ?? ''),
                name: (string) ($a['name'] ?? $a['product'] ?? $a['account_id']['iban'] ?? __('Account')),
                iban: $a['account_id']['iban'] ?? null,
                currency: (string) ($a['currency'] ?? 'EUR'),
            ))
            ->filter(fn (RemoteAccount $a) => $a->id !== '')
            ->values()
            ->all();

        $until = $response->json('access.valid_until');

        return new Consent(
            sessionId: (string) $response->json('session_id'),
            accounts: $accounts,
            validUntil: is_string($until) ? Carbon::parse($until) : null,
        );
    }

    public function transactions(Consent $consent, string $accountId, CarbonInterface $from): array
    {
        $out = [];
        $key = null;

        // The API pages its answers; follow the continuation key, with a hard stop.
        for ($page = 0; $page < 50; $page++) {
            $query = ['date_from' => $from->format('Y-m-d')] + ($key ? ['continuation_key' => $key] : []);
            $response = $this->request()->get("/accounts/{$accountId}/transactions", $query);
            $this->check($response);

            foreach ($response->json('transactions', []) as $tx) {
                $mapped = $this->map($tx);
                if ($mapped) {
                    $out[] = $mapped;
                }
            }

            $key = $response->json('continuation_key');
            if (! $key) {
                break;
            }
        }

        return $out;
    }

    /** @param  array<string, mixed>  $tx */
    private function map(array $tx): ?BankTransaction
    {
        // Pending items can change or vanish; only booked ones are final.
        if (($tx['status'] ?? 'BOOK') !== 'BOOK') {
            return null;
        }

        $date = $tx['booking_date'] ?? $tx['value_date'] ?? $tx['transaction_date'] ?? null;
        $amount = $tx['transaction_amount']['amount'] ?? null;
        if (! $date || $amount === null) {
            return null;
        }

        $cents = (int) round(abs((float) $amount) * 100);
        $outgoing = ($tx['credit_debit_indicator'] ?? 'DBIT') === 'DBIT';

        $remittance = $tx['remittance_information'] ?? [];
        $text = trim(implode(' ', array_filter(array_map('strval', (array) $remittance))));
        $counterparty = $outgoing ? ($tx['creditor']['name'] ?? null) : ($tx['debtor']['name'] ?? null);

        return new BankTransaction(
            date: substr((string) $date, 0, 10),
            amountCents: $outgoing ? -$cents : $cents,
            description: $text !== '' ? $text : (string) ($counterparty ?? __('Bank transaction')),
            externalId: ($tx['entry_reference'] ?? null) ? (string) $tx['entry_reference'] : null,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $banks
     * @return list<Institution>
     */
    private function mapInstitutions(array $banks, string $country): array
    {
        return collect($banks)
            ->filter(fn ($b) => ! empty($b['name']))
            ->map(fn ($b) => new Institution($country.'|'.$b['name'], (string) $b['name'], $b['logo'] ?? null))
            ->sortBy(fn (Institution $i) => mb_strtolower($i->name))
            ->values()
            ->all();
    }

    private function check(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $message = $response->json('message') ?? $response->json('error') ?? $response->reason();

        if (in_array($response->status(), [401, 403], true)) {
            throw new BankAuthException("Enable Banking refused access: {$message}");
        }

        throw new BankException("Enable Banking error {$response->status()}: {$message}");
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(self::BASE)
            ->withToken($this->jwt())
            ->acceptJson()
            ->timeout(30)
            ->retry(2, 500, fn ($e) => $e instanceof ConnectionException, throw: false);
    }

    private function appId(): ?string
    {
        return config('tostao.banks.providers.enablebanking.app_id');
    }

    /** The PEM text, from a file path or, if you prefer, straight from the environment. */
    private function privateKey(): ?string
    {
        $inline = config('tostao.banks.providers.enablebanking.private_key');
        if (filled($inline)) {
            return str_replace('\n', "\n", $inline);
        }

        $path = config('tostao.banks.providers.enablebanking.private_key_path');
        if (filled($path) && is_readable($path)) {
            return file_get_contents($path) ?: null;
        }

        return null;
    }

    /** A JWT valid for an hour, signed RS256 with the application's private key. */
    private function jwt(): string
    {
        $key = $this->privateKey();
        if (! $key || ! filled($this->appId())) {
            throw new BankException('Enable Banking is not configured.');
        }

        $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $now = time();

        $signing = $b64(json_encode(['typ' => 'JWT', 'alg' => 'RS256', 'kid' => $this->appId()]))
            .'.'.$b64(json_encode(['iss' => 'enablebanking.com', 'aud' => 'api.enablebanking.com', 'iat' => $now, 'exp' => $now + 3600]));

        if (! openssl_sign($signing, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new BankException('Could not sign the Enable Banking token. Is the private key a valid PEM file?');
        }

        return $signing.'.'.$b64($signature);
    }
}
