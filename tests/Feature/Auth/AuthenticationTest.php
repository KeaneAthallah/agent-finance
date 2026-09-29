<?php

use App\Enums\UserRole;
use function Pest\Laravel\post;
use function Pest\Laravel\actingAs;

it('redirects guests from the dashboard to login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('shows the login page to guests', function () {
    $this->get(route('login'))->assertOk();
});

it('allows an active user to log in', function () {
    $user = testUser(UserRole::Viewer->value, testOpd());

    post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

it('rejects an inactive account at login', function () {
    $user = testUser(UserRole::Viewer->value, testOpd());
    $user->forceFill(['is_active' => false])->save();

    post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('rejects invalid credentials', function () {
    $user = testUser(UserRole::Viewer->value, testOpd());

    post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('allows an authenticated user to log out', function () {
    $user = testUser(UserRole::Viewer->value, testOpd());

    actingAs($user)->post(route('logout'))->assertRedirect('/');

    $this->assertGuest();
});
