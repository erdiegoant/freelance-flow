<?php

use App\Enums\ProjectStatus;
use App\Models\Client;
use App\Models\Project;

describe('GET /api/clients/{client}/projects', function () {
    it('returns all projects for a client', function () {
        $client = Client::factory()->create();
        Project::factory(3)->for($client)->create();

        $this->getJson("/api/clients/{$client->id}/projects")
            ->assertSuccessful()
            ->assertJsonCount(3);
    });

    it('does not return projects from other clients', function () {
        $client = Client::factory()->create();
        Project::factory(2)->for($client)->create();
        Project::factory(5)->create(); // belongs to other clients

        $this->getJson("/api/clients/{$client->id}/projects")
            ->assertSuccessful()
            ->assertJsonCount(2);
    });

    it('returns 404 for unknown client', function () {
        $this->getJson('/api/clients/999/projects')->assertNotFound();
    });
});

describe('POST /api/clients/{client}/projects', function () {
    it('creates a project for a client and returns 201', function () {
        $client = Client::factory()->create();

        $this->postJson("/api/clients/{$client->id}/projects", [
            'name' => 'Website Redesign',
            'hourly_rate' => 75.00,
            'description' => 'Full redesign of the corporate website.',
        ])
            ->assertCreated()
            ->assertJsonPath('name', 'Website Redesign')
            ->assertJsonPath('hourly_rate', '75.00');

        $this->assertDatabaseHas('projects', [
            'client_id' => $client->id,
            'name' => 'Website Redesign',
        ]);
    });

    it('defaults status to active', function () {
        $client = Client::factory()->create();

        $this->postJson("/api/clients/{$client->id}/projects", [
            'name' => 'API Integration',
            'hourly_rate' => 100,
        ])
            ->assertCreated()
            ->assertJsonPath('status', 'active');
    });

    it('accepts an explicit status', function () {
        $client = Client::factory()->create();

        $this->postJson("/api/clients/{$client->id}/projects", [
            'name' => 'Legacy Cleanup',
            'hourly_rate' => 80,
            'status' => ProjectStatus::Paused->value,
        ])->assertCreated()->assertJsonPath('status', 'paused');
    });

    dataset('invalid project payloads', [
        'missing name' => [['hourly_rate' => 75], 'name'],
        'missing hourly_rate' => [['name' => 'X'], 'hourly_rate'],
        'negative hourly_rate' => [['name' => 'X', 'hourly_rate' => -1], 'hourly_rate'],
        'invalid status' => [['name' => 'X', 'hourly_rate' => 50, 'status' => 'invalid'], 'status'],
    ]);

    it('rejects invalid payload with 422', function (array $payload, string $errorField) {
        $client = Client::factory()->create();

        $this->postJson("/api/clients/{$client->id}/projects", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($errorField);
    })->with('invalid project payloads');
});
