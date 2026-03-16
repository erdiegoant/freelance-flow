<?php

use App\Models\Project;
use App\Models\TimeLog;

describe('POST /api/projects/{project}/time-logs', function () {
    it('logs time on a project and returns 201', function () {
        $project = Project::factory()->create();

        $this->postJson("/api/projects/{$project->id}/time-logs", [
            'description' => 'Built the homepage layout',
            'hours' => 2.5,
            'logged_at' => '2026-03-10',
        ])
            ->assertCreated()
            ->assertJsonPath('description', 'Built the homepage layout')
            ->assertJsonPath('hours', '2.50')
            ->assertJsonPath('project_id', $project->id);

        expect(TimeLog::count())->toBe(1);
    });

    it('stores the time log in the database', function () {
        $project = Project::factory()->create();

        $this->postJson("/api/projects/{$project->id}/time-logs", [
            'description' => 'API endpoint development',
            'hours' => 4,
            'logged_at' => '2026-03-01',
        ])->assertCreated();

        // Verify via model query (avoids SQLite date format differences)
        $timeLog = TimeLog::where('project_id', $project->id)->sole();
        expect((float) $timeLog->hours)->toBe(4.0)
            ->and($timeLog->logged_at->toDateString())->toBe('2026-03-01');
    });

    it('returns 404 for unknown project', function () {
        $this->postJson('/api/projects/999/time-logs', [
            'description' => 'Work',
            'hours' => 1,
            'logged_at' => '2026-03-01',
        ])->assertNotFound();
    });

    dataset('invalid time log payloads', [
        'missing description' => [['hours' => 1, 'logged_at' => '2026-03-01'], 'description'],
        'missing hours' => [['description' => 'Work', 'logged_at' => '2026-03-01'], 'hours'],
        'missing logged_at' => [['description' => 'Work', 'hours' => 1], 'logged_at'],
        'hours below minimum' => [['description' => 'Work', 'hours' => 0, 'logged_at' => '2026-03-01'], 'hours'],
        'hours above maximum' => [['description' => 'Work', 'hours' => 25, 'logged_at' => '2026-03-01'], 'hours'],
        'future date' => [['description' => 'Work', 'hours' => 1, 'logged_at' => '2099-01-01'], 'logged_at'],
        'invalid date' => [['description' => 'Work', 'hours' => 1, 'logged_at' => 'not-a-date'], 'logged_at'],
    ]);

    it('rejects invalid payload with 422', function (array $payload, string $errorField) {
        $project = Project::factory()->create();

        $this->postJson("/api/projects/{$project->id}/time-logs", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($errorField);
    })->with('invalid time log payloads');
});
