<?php

use App\Banking\BankAuthException;
use App\Banking\BankRegistry;
use App\Banking\Consent;
use App\Banking\Providers\DemoProvider;
use App\Banking\Providers\EnableBankingProvider;
use App\Models\Account;
use App\Models\BankConnection;
use App\Models\Rule;
use App\Models\Transaction;
use App\Services\BankSync;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['tostao.banks.providers.demo.enabled' => true]);
});

/** Run the whole consent dance against the demo bank and land on the mapping page. */
function connectDemo($test): BankConnection
{
    $test->post('/banks/connect', ['provider' => 'demo', 'institution' => 'demo', 'institution_name' => 'Demo Bank'])
        ->assertRedirect();

    $connection = BankConnection::firstOrFail();
    $test->get('/banks/callback?code=demo-code&state='.$connection->state)
        ->assertRedirect(route('banks.show', $connection));

    return $connection->fresh();
}

it('explains itself when no provider is configured', function () {
    config(['tostao.banks.providers.demo.enabled' => false, 'tostao.banks.providers.enablebanking.app_id' => null]);
    household();

    $this->get('/banks')->assertOk()->assertSee('Ainda não está configurado');
});

it('connects a bank, links its accounts and imports transactions', function () {
    ['account' => $joint] = household();
    $connection = connectDemo($this);

    expect($connection->status)->toBe('active')
        ->and($connection->accounts)->toHaveCount(2)
        ->and($connection->valid_until->isFuture())->toBeTrue();

    [$current, $card] = $connection->accounts;

    $this->post("/banks/{$connection->id}/link", ['links' => [
        $current->id => ['account' => (string) $joint->id, 'from' => now()->subDays(30)->format('Y-m-d')],
        $card->id => ['account' => 'new', 'name' => 'Cartão'],
    ]])->assertRedirect('/banks');

    expect(Account::where('name', 'Cartão')->exists())->toBeTrue()
        ->and(Transaction::where('account_id', $joint->id)->count())->toBeGreaterThan(10)
        ->and(Transaction::whereNull('import_hash')->count())->toBe(0);
});

it('never duplicates when syncing again', function () {
    ['account' => $joint] = household();
    $connection = connectDemo($this);
    $this->post("/banks/{$connection->id}/link", ['links' => [
        $connection->accounts[0]->id => ['account' => (string) $joint->id, 'from' => now()->subDays(20)->format('Y-m-d')],
        $connection->accounts[1]->id => ['account' => 'skip'],
    ]]);

    $first = Transaction::count();
    expect(app(BankSync::class)->sync($connection->fresh()))->toBe(0)
        ->and(Transaction::count())->toBe($first);
});

it('applies the rules and defaults the owner to the account owner', function () {
    ['ana' => $ana, 'account' => $joint] = household();
    $personal = Account::create(['name' => 'Ana personal', 'type' => 'bank', 'owner_id' => $ana->id]);
    $groceries = category('Groceries');
    Rule::create(['pattern' => 'Pingo Doce', 'category_id' => $groceries->id, 'priority' => 1]);

    $connection = connectDemo($this);
    $this->post("/banks/{$connection->id}/link", ['links' => [
        $connection->accounts[0]->id => ['account' => (string) $personal->id, 'from' => now()->subDays(30)->format('Y-m-d')],
        $connection->accounts[1]->id => ['account' => 'skip'],
    ]]);

    $shop = Transaction::where('description', 'Pingo Doce Lisboa')->first();

    expect($shop)->not->toBeNull()
        ->and($shop->category_id)->toBe($groceries->id)
        ->and($shop->owner_id)->toBe($ana->id)
        ->and($shop->account_id)->toBe($personal->id);
});

it('keeps manual entries that look like a bank movement', function () {
    ['account' => $joint] = household();
    Transaction::create(['account_id' => $joint->id, 'date' => now()->format('Y-m-d'), 'description' => 'manual', 'amount_cents' => -250]);

    $connection = connectDemo($this);
    $this->post("/banks/{$connection->id}/link", ['links' => [
        $connection->accounts[0]->id => ['account' => (string) $joint->id, 'from' => now()->subDays(1)->format('Y-m-d')],
        $connection->accounts[1]->id => ['account' => 'skip'],
    ]]);

    expect(Transaction::where('description', 'manual')->exists())->toBeTrue();
});

it('marks a connection expired when its consent has run out', function () {
    ['account' => $joint] = household();
    $connection = connectDemo($this);
    $connection->accounts[0]->update(['account_id' => $joint->id]);
    $connection->update(['valid_until' => now()->subDay()]);

    expect(app(BankSync::class)->sync($connection))->toBe(0)
        ->and($connection->fresh()->status)->toBe('expired');

    $this->get('/banks')->assertSee('Expirou');
});

it('carries the account mapping over when a consent is renewed', function () {
    ['account' => $joint] = household();
    $old = connectDemo($this);
    $old->accounts->firstWhere('remote_id', 'demo-current')->update(['account_id' => $joint->id, 'sync_from' => '2026-10-01']);

    $this->post('/banks/connect', ['provider' => 'demo', 'institution' => 'demo', 'institution_name' => 'Demo Bank']);
    $new = BankConnection::where('status', 'pending')->firstOrFail();
    $this->get('/banks/callback?code=demo-code&state='.$new->state);

    expect(BankConnection::count())->toBe(1)
        ->and(BankConnection::first()->accounts->firstWhere('remote_id', 'demo-current')->account_id)->toBe($joint->id);
});

it('rejects a callback with an unknown state', function () {
    household();

    $this->get('/banks/callback?code=x&state=nope')->assertRedirect('/banks')->assertSessionHasErrors('bank');
});

it('forgets a connection but keeps the transactions', function () {
    ['account' => $joint] = household();
    $connection = connectDemo($this);
    $this->post("/banks/{$connection->id}/link", ['links' => [
        $connection->accounts[0]->id => ['account' => (string) $joint->id, 'from' => now()->subDays(10)->format('Y-m-d')],
        $connection->accounts[1]->id => ['account' => 'skip'],
    ]]);
    $count = Transaction::count();

    $this->delete("/banks/{$connection->id}")->assertRedirect('/banks');

    expect(BankConnection::count())->toBe(0)->and(Transaction::count())->toBe($count);
});

it('lets a provider be added through config alone', function () {
    config(['tostao.banks.providers.other' => ['class' => DemoProvider::class, 'enabled' => true]]);

    expect(app(BankRegistry::class)->get('other'))->toBeInstanceOf(DemoProvider::class)
        ->and(fn () => app(BankRegistry::class)->get('missing'))->toThrow(InvalidArgumentException::class);
});

/* Enable Banking, against recorded-shape HTTP responses. */

function enableBankingKey(): string
{
    static $pem = null;

    if ($pem === null) {
        // A tiny config file keeps this working on machines (Windows) with no system openssl.cnf.
        $cnf = tempnam(sys_get_temp_dir(), 'ssl');
        file_put_contents($cnf, "[req]\ndistinguished_name = req_dn\n[req_dn]\n");
        $options = ['private_key_bits' => 2048, 'config' => $cnf];
        openssl_pkey_export(openssl_pkey_new($options), $pem, null, $options);
        unlink($cnf);
    }

    return $pem;
}

beforeEach(function () {
    config(['tostao.banks.providers.enablebanking' => [
        'class' => EnableBankingProvider::class,
        'app_id' => 'app-123',
        'private_key' => enableBankingKey(),
        'private_key_path' => null,
    ]]);
    Cache::flush();
});

it('lists banks from Enable Banking and signs a valid RS256 token', function () {
    Http::fake(['api.enablebanking.com/aspsps*' => Http::response(['aspsps' => [
        ['name' => 'Millennium bcp', 'country' => 'PT'],
        ['name' => 'Crédito Agrícola', 'country' => 'PT'],
    ]])]);

    $banks = app(EnableBankingProvider::class)->institutions('pt');

    expect(array_map(fn ($b) => $b->name, $banks))->toBe(['Crédito Agrícola', 'Millennium bcp'])
        ->and($banks[1]->id)->toBe('PT|Millennium bcp');

    Http::assertSent(function ($request) {
        [$h, $p, $sig] = explode('.', str_replace('Bearer ', '', $request->header('Authorization')[0]));
        $header = json_decode(base64_decode(strtr($h, '-_', '+/')), true);
        $payload = json_decode(base64_decode(strtr($p, '-_', '+/')), true);

        return $header['kid'] === 'app-123' && $header['alg'] === 'RS256'
            && $payload['aud'] === 'api.enablebanking.com' && $payload['exp'] > $payload['iat'];
    });
});

it('starts a consent with the bank name and country', function () {
    Http::fake(['api.enablebanking.com/auth' => Http::response(['url' => 'https://bank.example/auth?x=1'])]);

    $start = app(EnableBankingProvider::class)->startConsent('PT|Millennium bcp', 'st4te', 'https://tostao.test/banks/callback', now()->addDays(180));

    expect($start->url)->toBe('https://bank.example/auth?x=1');
    Http::assertSent(fn ($r) => $r['aspsp'] === ['name' => 'Millennium bcp', 'country' => 'PT']
        && $r['state'] === 'st4te' && $r['psu_type'] === 'personal' && $r['redirect_url'] === 'https://tostao.test/banks/callback');
});

it('turns a session response into accounts and a consent end date', function () {
    Http::fake(['api.enablebanking.com/sessions' => Http::response([
        'session_id' => 'sess-1',
        'accounts' => [['uid' => 'acc-1', 'name' => 'Conta Ordenado', 'currency' => 'EUR', 'account_id' => ['iban' => 'PT50123']]],
        'access' => ['valid_until' => '2027-05-01T00:00:00Z'],
    ])]);

    $consent = app(EnableBankingProvider::class)->completeConsent('the-code');

    expect($consent->sessionId)->toBe('sess-1')
        ->and($consent->accounts[0]->id)->toBe('acc-1')
        ->and($consent->accounts[0]->iban)->toBe('PT50123')
        ->and($consent->validUntil->format('Y-m-d'))->toBe('2027-05-01');
});

it('reads booked transactions, signs the amount and follows the continuation key', function () {
    Http::fake(['api.enablebanking.com/accounts/acc-1/transactions*' => Http::sequence()
        ->push(['transactions' => [
            ['entry_reference' => 'r1', 'booking_date' => '2026-11-10', 'status' => 'BOOK', 'credit_debit_indicator' => 'DBIT',
                'transaction_amount' => ['amount' => '12.50', 'currency' => 'EUR'], 'remittance_information' => ['Compra', 'Lidl']],
            ['entry_reference' => 'r2', 'booking_date' => '2026-11-11', 'status' => 'PDNG', 'credit_debit_indicator' => 'DBIT',
                'transaction_amount' => ['amount' => '3.00']],
        ], 'continuation_key' => 'next'])
        ->push(['transactions' => [
            ['entry_reference' => 'r3', 'value_date' => '2026-11-12', 'status' => 'BOOK', 'credit_debit_indicator' => 'CRDT',
                'transaction_amount' => ['amount' => '1000.00'], 'debtor' => ['name' => 'Empresa Lda']],
        ]]),
    ]);

    $txs = app(EnableBankingProvider::class)->transactions(new Consent('s', []), 'acc-1', now()->subDays(30));

    expect($txs)->toHaveCount(2)
        ->and($txs[0]->amountCents)->toBe(-1250)
        ->and($txs[0]->description)->toBe('Compra Lidl')
        ->and($txs[0]->externalId)->toBe('r1')
        ->and($txs[1]->amountCents)->toBe(100000)
        ->and($txs[1]->description)->toBe('Empresa Lda')
        ->and($txs[1]->date)->toBe('2026-11-12');
});

it('treats a 401 from the bank as an expired consent', function () {
    Http::fake(['api.enablebanking.com/*' => Http::response(['message' => 'consent expired'], 401)]);

    expect(fn () => app(EnableBankingProvider::class)->transactions(new Consent('s', []), 'acc-1', now()))
        ->toThrow(BankAuthException::class);
});

it('is not available without credentials', function () {
    config(['tostao.banks.providers.enablebanking.app_id' => null]);

    expect(app(EnableBankingProvider::class)->isConfigured())->toBeFalse()
        ->and(array_keys(app(BankRegistry::class)->available()))->toBe(['demo']);
});
