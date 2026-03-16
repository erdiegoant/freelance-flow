<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProjectRequest;
use App\Models\Client;
use Illuminate\Http\JsonResponse;

class ProjectController extends Controller
{
    public function index(Client $client): JsonResponse
    {
        $projects = $client->projects()->with('client')->get();

        return response()->json($projects);
    }

    public function store(StoreProjectRequest $request, Client $client): JsonResponse
    {
        $project = $client->projects()->create([
            ...$request->validated(),
            'status' => $request->enum('status', ProjectStatus::class) ?? ProjectStatus::Active,
        ]);

        return response()->json($project->load('client'), 201);
    }
}
