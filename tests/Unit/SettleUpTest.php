<?php

use App\Services\SettleUp;

it('splits in whole cents and always adds up', function () {
    $shares = (new SettleUp)->split(1001, [1, 2, 3], payer: 2);

    expect(array_sum($shares))->toBe(1001)
        ->and($shares[2])->toBe(334)
        ->and($shares[1])->toBe(334)
        ->and($shares[3])->toBe(333);
});

it('clears all balances with the fewest payments', function () {
    $payments = (new SettleUp)->minimise([1 => 3000, 2 => -1000, 3 => -2000]);

    expect($payments)->toBe([
        ['from' => 3, 'to' => 1, 'amount_cents' => 2000],
        ['from' => 2, 'to' => 1, 'amount_cents' => 1000],
    ]);
});

it('returns no payments when everyone is even', function () {
    expect((new SettleUp)->minimise([1 => 0, 2 => 0]))->toBe([]);
});
