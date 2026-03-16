<?php

use App\Models\User;

describe('POST /api/auth/login', function () {
    it('returns a token on valid credentials', function () {
        User::factory()->create([
            'email' => 'freelancer@example.com',
            'password' => bcrypt('secret'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'freelancer@example.com',
            'password' => 'secret',
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'expires_at']);
    });

    it('returns 401 on invalid password', function () {
        User::factory()->create(['email' => 'freelancer@example.com']);

        $this->postJson('/api/auth/login', [
            'email' => 'freelancer@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized()->assertJsonPath('message', 'Invalid credentials.');
    });

    it('returns 401 for unknown email', function () {
        $this->postJson('/api/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'secret',
        ])->assertUnauthorized();
    });

    dataset('invalid login payloads', [
        'missing email' => [['password' => 'secret'], 'email'],
        'missing password' => [['email' => 'a@b.com'], 'password'],
        'invalid email format' => [['email' => 'not-an-email', 'password' => 'secret'], 'email'],
    ]);

    it('rejects invalid payload with 422', function (array $payload, string $errorField) {
        $this->postJson('/api/auth/login', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($errorField);
    })->with('invalid login payloads');
});

describe('POST /api/auth/logout', function () {
    it('revokes the current token', function () {
        $user = User::factory()->create();
        $token = $user->createToken('api-token');

        $this->withToken($token->plainTextToken)
            ->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out successfully.');

        expect($user->tokens()->count())->toBe(0);
    });

    it('returns 401 when not authenticated', function () {
        $this->postJson('/api/auth/logout')->assertUnauthorized();
    });
});

describe('Protected endpoints require authentication', function () {
    it('returns 401 on POST /api/clients without a token', function () {
        $this->postJson('/api/clients', ['name' => 'Test', 'email' => 'test@test.com'])
            ->assertUnauthorized();
    });

    it('returns 401 on GET /api/clients/{client}/projects without a token', function () {
        $this->getJson('/api/clients/1/projects')->assertUnauthorized();
    });

    it('returns 401 on GET /api/invoices/{invoice} without a token', function () {
        $this->getJson('/api/invoices/1')->assertUnauthorized();
    });
});
