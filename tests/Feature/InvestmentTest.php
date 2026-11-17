<?php

use App\Brokers\BrokerRegistry;
use App\Brokers\Trading212Provider;
use App\Models\Account;
use App\Models\AccountValuation;
use App\Models\BrokerLink;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\BrokerSync;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['tostao.brokers.providers.trading212' => [
        'class' => Trading212Provider::class,
        'api_key' => 'key-1',
        'api_secret' => 'secret-1',
        'environment' => 'live',
    ]]);
});

function investmentAccount(): Account
{
    return Account::create(['name' => 'Trading 212', 'type' => 'investment']);
}

function fakeTrading212(array $items = [], array $summary = []): void
{
    Http::fake([
        'live.trading212.com/api/v0/equity/account/summary' => Http::response($summary ?: [
            'currency' => 'EUR', 'totalValue' => 1.0,
            'cash' => ['availableToTrade' => 100.50, 'inPies' => 0, 'reservedForOrders' => 0],
            'investments' => ['currentValue' => 1899.50],
        ]),
        'live.trading212.com/api/v0/equity/history/transactions*' => Http::response(['items' => $items, 'nextPagePath' => null]),
    ]);
}

it('keeps a Trading 212 key in .env and says so when there is none', function () {
    config(['tostao.brokers.providers.trading212.api_key' => null]);
    household();

    $this->get('/investments')->assertOk()->assertSee('Ligar uma corretora');
    expect(app(BrokerRegistry::class)->available())->toBe([]);
});

it('lets an investment account hold a typed-in value, replacing the same day', function () {
    household();
    $account = investmentAccount();

    $this->post("/accounts/{$account->id}/valuation", ['value' => '1.234,50'])->assertRedirect();
    $this->post("/accounts/{$account->id}/valuation", ['value' => '1.300,00'])->assertRedirect();

    expect($account->valuations()->count())->toBe(1)
        ->and($account->fresh()->balanceCents())->toBe(130000);
    $this->get('/investments')->assertOk()->assertSee("1\u{00A0}300,00");
});

it('refuses a typed-in value for an account that is not an investment', function () {
    ['account' => $bank] = household();

    $this->post("/accounts/{$bank->id}/valuation", ['value' => '10'])->assertNotFound();
});

it('links Trading 212 to an account, imports deposits as transfers and records the value', function () {
    household();
    $account = investmentAccount();
    fakeTrading212([
        ['type' => 'DEPOSIT', 'amount' => 500, 'currency' => 'EUR', 'dateTime' => '2026-11-10T09:30:00Z', 'reference' => 'ref-1'],
        ['type' => 'WITHDRAW', 'amount' => -100, 'currency' => 'EUR', 'dateTime' => '2026-11-12T09:30:00Z', 'reference' => 'ref-2'],
        ['type' => 'FEE', 'amount' => 1, 'currency' => 'EUR', 'dateTime' => '2026-11-13T09:30:00Z', 'reference' => 'ref-3'],
        ['type' => 'INTEREST_ON_FREE_CASH', 'amount' => 0.2, 'currency' => 'EUR', 'dateTime' => '2026-11-14T09:30:00Z', 'reference' => 'ref-4'],
    ]);

    $this->post('/investments/trading212/link', ['account_id' => $account->id, 'sync_from' => '2026-01-01'])
        ->assertRedirect()->assertSessionHas('status');

    $moves = Transaction::where('account_id', $account->id)->orderBy('date')->get();
    $transfer = Category::where('kind', 'transfer')->first();

    expect($moves->pluck('amount_cents')->all())->toBe([50000, -10000]) // fees and interest are left out
        ->and($moves->pluck('category_id')->unique()->all())->toBe([$transfer?->id])
        ->and($account->fresh()->balanceCents())->toBe(200000) // 100,50 cash + 1899,50 invested
        ->and(BrokerLink::first()->last_synced_at)->not->toBeNull();
});

it('decides deposit or withdrawal from the type, not from the sign of the amount', function () {
    household();
    $account = investmentAccount();
    fakeTrading212([
        ['type' => 'WITHDRAW', 'amount' => 80, 'currency' => 'EUR', 'dateTime' => '2026-11-12T09:30:00Z', 'reference' => 'w'],
    ]);

    $this->post('/investments/trading212/link', ['account_id' => $account->id, 'sync_from' => '2026-01-01']);

    expect(Transaction::first()->amount_cents)->toBe(-8000);
});

it('never duplicates when it syncs again', function () {
    household();
    $account = investmentAccount();
    fakeTrading212([['type' => 'DEPOSIT', 'amount' => 500, 'currency' => 'EUR', 'dateTime' => '2026-11-10T09:30:00Z', 'reference' => 'ref-1']]);
    $this->post('/investments/trading212/link', ['account_id' => $account->id, 'sync_from' => '2026-01-01']);

    expect(app(BrokerSync::class)->sync(BrokerLink::first()))->toBe(0)
        ->and(Transaction::count())->toBe(1)
        ->and(AccountValuation::count())->toBe(1);
});

it('only links to an investment account', function () {
    ['account' => $bank] = household();
    fakeTrading212();

    $this->post('/investments/trading212/link', ['account_id' => $bank->id])->assertSessionHasErrors('account_id');
    expect(BrokerLink::count())->toBe(0);
});

it('shows the broker message when the key is refused', function () {
    household();
    $account = investmentAccount();
    Http::fake(['live.trading212.com/*' => Http::response(['message' => 'Bad API key'], 401)]);

    $this->post('/investments/trading212/link', ['account_id' => $account->id])->assertSessionHasErrors('broker');
    expect(BrokerLink::first()->last_error)->toContain('refused access');
});

it('sends the key and secret as HTTP Basic and follows the next page', function () {
    household();
    $account = investmentAccount();
    Http::fake([
        'live.trading212.com/api/v0/equity/account/summary' => Http::response(['totalValue' => 10, 'cash' => ['availableToTrade' => 1, 'inPies' => 0, 'reservedForOrders' => 0], 'investments' => ['currentValue' => 9]]),
        'live.trading212.com/api/v0/equity/history/transactions?limit=50' => Http::response([
            'items' => [['type' => 'DEPOSIT', 'amount' => 10, 'dateTime' => '2026-11-10T00:00:00Z', 'reference' => 'a']],
            'nextPagePath' => '/api/v0/equity/history/transactions?limit=50&cursor=123',
        ]),
        'live.trading212.com/api/v0/equity/history/transactions?limit=50&cursor=123' => Http::response([
            'items' => [['type' => 'DEPOSIT', 'amount' => 20, 'dateTime' => '2026-11-09T00:00:00Z', 'reference' => 'b']],
            'nextPagePath' => null,
        ]),
    ]);

    $this->post('/investments/trading212/link', ['account_id' => $account->id, 'sync_from' => '2026-01-01']);

    expect(Transaction::count())->toBe(2);
    Http::assertSent(fn ($r) => $r->header('Authorization')[0] === 'Basic '.base64_encode('key-1:secret-1'));
});

it('shows investment accounts by their value on the dashboard list', function () {
    household();
    $account = investmentAccount();
    $account->valuations()->create(['date' => now(), 'value_cents' => 555500, 'source' => 'manual']);

    expect($account->balanceCents())->toBe(555500);
    $this->get('/dashboard')->assertOk();
});
