<?php

use App\Models\User;
use Illuminate\Support\Facades\URL;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('login attempts are rate limited per email and ip', function () {
    $user = User::factory()->create();

    for ($attempt = 0; $attempt < 6; $attempt++) {
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(302);
    }

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertStatus(429);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $logoutUrl = URL::temporarySignedRoute('central.logout', now()->addMinutes(5));
    $response = $this->actingAs($user)->post($logoutUrl);

    $this->assertGuest();
    $response->assertRedirect('/');
});
