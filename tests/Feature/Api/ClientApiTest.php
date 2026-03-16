<?php

use App\Models\Client;

describe('POST /api/clients', function () {
    it('creates a client and returns 201', function () {
        $this->postJson('/api/clients', [
            'name' => 'Jane Smith',
            'email' => 'jane@acme.com',
            'company_name' => 'Acme Corp',
            'address' => '123 Main St, New York, NY 10001',
            'tax_id' => '900123456',
        ])
            ->assertCreated()
            ->assertJsonPath('name', 'Jane Smith')
            ->assertJsonPath('email', 'jane@acme.com')
            ->assertJsonPath('company_name', 'Acme Corp');

        expect(Client::count())->toBe(1);
    });

    it('stores the client in the database', function () {
        $this->postJson('/api/clients', [
            'name' => 'Bob Builder',
            'email' => 'bob@builder.com',
        ])->assertCreated();

        $this->assertDatabaseHas('clients', [
            'name' => 'Bob Builder',
            'email' => 'bob@builder.com',
        ]);
    });

    it('allows nullable optional fields', function () {
        $this->postJson('/api/clients', [
            'name' => 'Minimal Client',
            'email' => 'minimal@client.com',
        ])
            ->assertCreated()
            ->assertJsonPath('company_name', null)
            ->assertJsonPath('tax_id', null);
    });

    dataset('invalid client payloads', [
        'missing name' => [['email' => 'a@b.com'], 'name'],
        'missing email' => [['name' => 'John'], 'email'],
        'invalid email' => [['name' => 'John', 'email' => 'not-an-email'], 'email'],
        'name too long' => [['name' => str_repeat('x', 256), 'email' => 'a@b.com'], 'name'],
    ]);

    it('rejects invalid payload with 422', function (array $payload, string $errorField) {
        $this->postJson('/api/clients', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($errorField);
    })->with('invalid client payloads');
});
