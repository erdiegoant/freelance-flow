<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    private const array PROJECT_NAMES = [
        'Website Redesign',
        'Mobile App Development',
        'API Integration',
        'Brand Identity Package',
        'E-commerce Platform',
        'Dashboard Analytics',
        'CRM Implementation',
        'DevOps Automation',
        'Content Strategy',
        'SEO Optimization',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'name' => fake()->unique()->randomElement(self::PROJECT_NAMES),
            'description' => fake()->sentence(12),
            'hourly_rate' => fake()->randomElement([50, 65, 75, 85, 100, 120, 150]),
            'status' => ProjectStatus::Active,
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => ProjectStatus::Active]);
    }

    public function completed(): static
    {
        return $this->state(['status' => ProjectStatus::Completed]);
    }

    public function paused(): static
    {
        return $this->state(['status' => ProjectStatus::Paused]);
    }
}
