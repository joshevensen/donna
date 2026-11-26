<?php

namespace App\Memory;

use App\Models\Project;
use App\Models\Topic;
use Illuminate\Support\Facades\DB;

/**
 * Keeps track of which topics belong to which project. That link is what
 * lets a project boost its memories and know when it was last discussed.
 *
 * Code links a topic to the project it is named after; daily extraction
 * adds the links that take judgment.
 */
class ProjectLinker
{
    public function __construct(private SubjectResolver $subjects) {}

    public function link(Topic $topic, Project $project): void
    {
        $project->topics()->syncWithoutDetaching([$topic->id]);
    }

    /**
     * Link each topic to the project that goes by the same name, if any.
     *
     * @param  iterable<Topic>  $topics
     */
    public function linkTopics(iterable $topics): void
    {
        foreach ($topics as $topic) {
            $project = $this->subjects->project($topic->name);

            if ($project !== null) {
                $this->link($topic, $project);
            }
        }
    }

    /**
     * Link a new or renamed project to the topics that already go by its names.
     */
    public function linkProject(Project $project): void
    {
        $topics = Topic::query()
            ->whereIn(DB::raw('lower(name)'), array_map(mb_strtolower(...), $project->names()))
            ->pluck('id');

        $project->topics()->syncWithoutDetaching($topics);
    }
}
