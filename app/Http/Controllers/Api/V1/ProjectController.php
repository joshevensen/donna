<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectController extends Controller
{
    /**
     * Every project that hasn't been dropped, by name, for `/projects`.
     */
    public function index(): AnonymousResourceCollection
    {
        $projects = Project::query()
            ->with('owner')
            ->whereNot('status', ProjectStatus::Dropped)
            ->orderByRaw('lower(name)')
            ->get();

        return ProjectResource::collection($projects);
    }
}
