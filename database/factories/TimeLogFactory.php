<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\TimeLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeLog>
 */
class TimeLogFactory extends Factory
{
    private const array DESCRIPTIONS = [
        'Initial discovery and requirements gathering',
        'UI/UX wireframing',
        'Frontend component development',
        'Backend API implementation',
        'Database schema design',
        'Code review and refactoring',
        'Testing and QA',
        'Client feedback revisions',
        'Deployment and configuration',
        'Documentation',
        'Performance optimisation',
        'Security audit',
        'Integration testing',
        'Bug fixes',
        'Meeting and project coordination',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'description' => fake()->randomElement(self::DESCRIPTIONS),
            'hours' => fake()->randomElement([0.5, 1, 1.5, 2, 2.5, 3, 4, 6, 8]),
            'logged_at' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
        ];
    }
}
