<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTimeLogRequest;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

class TimeLogController extends Controller
{
    public function store(StoreTimeLogRequest $request, Project $project): JsonResponse
    {
        $timeLog = $project->timeLogs()->create($request->validated());

        return response()->json($timeLog, 201);
    }
}
