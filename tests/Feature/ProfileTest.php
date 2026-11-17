<?php

use App\Models\Transaction;
use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('test@example.com', $user->email);
    $this->assertNull($user->email_verified_at);
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertNotNull($user->refresh()->email_verified_at);
});

test('user can delete their account', function () {
    $user = User::factory()->create();
    User::factory()->create(); // the household keeps at least one other member

    $response = $this
        ->actingAs($user)
        ->delete('/profile', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    $this->assertNull($user->fresh());
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->delete('/profile', [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrorsIn('userDeletion', 'password')
        ->assertRedirect('/profile');

    $this->assertNotNull($user->fresh());
});

test('the only member cannot delete their account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->from('/profile')->delete('/profile', ['password' => 'password'])
        ->assertSessionHasErrorsIn('userDeletion', 'password');

    expect($user->fresh())->not->toBeNull();
});

test('a member who still owns transactions cannot delete their account', function () {
    ['ana' => $ana, 'account' => $account] = household();
    Transaction::create(['account_id' => $account->id, 'owner_id' => $ana->id, 'date' => '2026-11-01', 'description' => 'x', 'amount_cents' => -100]);

    $this->from('/profile')->delete('/profile', ['password' => 'password'])
        ->assertSessionHasErrorsIn('userDeletion', 'password');

    expect($ana->fresh())->not->toBeNull();
});
