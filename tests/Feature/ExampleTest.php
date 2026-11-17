<?php

use App\Models\User;

it('sends a fresh install to the first-run screen', function () {
    $this->get('/')->assertRedirect(route('register'));
});

it('sends an installed household to the dashboard, which asks for a login', function () {
    User::factory()->create();

    $this->get('/')->assertRedirect(route('dashboard'));
    $this->get('/dashboard')->assertRedirect(route('login'));
});

it('keeps every app page behind a login', function (string $uri) {
    $this->get($uri)->assertRedirect(route('login'));
})->with(['/transactions', '/import', '/accounts', '/categories', '/rules', '/recurring', '/settle-up', '/members', '/profile']);
