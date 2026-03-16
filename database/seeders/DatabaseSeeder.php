<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Project;
use App\Models\TimeLog;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::factory()->create([
            'name' => 'Freelancer',
            'email' => 'freelancer@example.com',
            'password' => bcrypt('password'),
        ]);

        Client::factory(3)
            ->create()
            ->each(function (Client $client): void {
                $projectCount = fake()->numberBetween(2, 3);

                Project::factory($projectCount)
                    ->for($client)
                    ->active()
                    ->create()
                    ->each(function (Project $project): void {
                        $timeLogCount = fake()->numberBetween(10, 20);

                        TimeLog::factory($timeLogCount)
                            ->for($project)
                            ->create();
                    });
            });
    }
}
