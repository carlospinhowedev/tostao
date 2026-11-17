<?php

namespace App\Banking\Providers;

use App\Banking\BankProvider;
use App\Banking\BankTransaction;
use App\Banking\Consent;
use App\Banking\ConsentStart;
use App\Banking\Institution;
use App\Banking\RemoteAccount;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * A pretend bank, for trying the whole connection flow without credentials and
 * for the tests. Enable it with TOSTAO_BANK_DEMO=true. It invents a few
 * transactions per week, always the same for the same date, so syncing twice
 * never duplicates anything.
 */
class DemoProvider implements BankProvider
{
    private const SHOPS = [
        ['Pingo Doce Lisboa', -4730],
        ['Galp Combustivel', -5200],
        ['EDP Comercial', -6480],
        ['Cafe Central', -250],
        ['Farmacia Saude', -1315],
        ['Transferencia recebida', 12000],
    ];

    public function key(): string
    {
        return 'demo';
    }

    public function label(): string
    {
        return 'Demo bank';
    }

    public function isConfigured(): bool
    {
        return (bool) config('tostao.banks.providers.demo.enabled');
    }

    public function institutions(string $country): array
    {
        return [new Institution('demo', 'Demo Bank')];
    }

    public function startConsent(string $institutionId, string $state, string $redirectUrl, CarbonInterface $validUntil): ConsentStart
    {
        return new ConsentStart($redirectUrl.(str_contains($redirectUrl, '?') ? '&' : '?').http_build_query(['code' => 'demo-code', 'state' => $state]));
    }

    public function completeConsent(string $code): Consent
    {
        return new Consent('demo-session', [
            new RemoteAccount('demo-current', 'Demo current account', 'PT50000000000000000000000'),
            new RemoteAccount('demo-card', 'Demo card', null),
        ], now()->addDays(180));
    }

    public function transactions(Consent $consent, string $accountId, CarbonInterface $from): array
    {
        $out = [];

        for ($day = Carbon::parse($from)->startOfDay(); $day->lte(now()->startOfDay()); $day->addDay()) {
            // Counting days from a fixed point makes the output depend on the date alone.
            $n = intdiv(strtotime($day->format('Y-m-d').' UTC'), 86400) + strlen($accountId);

            // Three days out of four have something, and every shop comes round.
            if ($n % 4 === 0) {
                continue;
            }

            [$name, $cents] = self::SHOPS[$n % count(self::SHOPS)];
            $out[] = new BankTransaction($day->format('Y-m-d'), $cents, $name, 'demo-'.$accountId.'-'.$day->format('Ymd'));
        }

        return $out;
    }
}
