<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\User;

test('the first-run screen can be rendered', function () {
    $this->get('/register')->assertOk();
});

test('the first user creates the household and gets starter data', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
    expect(Category::count())->toBeGreaterThan(10)
        ->and(Account::count())->toBe(1)
        ->and(Category::where('kind', 'transfer')->exists())->toBeTrue();
});

test('nobody else can sign up once the household exists', function () {
    User::factory()->create();

    $this->get('/register')->assertNotFound();
    $this->post('/register', [
        'name' => 'Stranger',
        'email' => 'stranger@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    expect(User::count())->toBe(1);
});
