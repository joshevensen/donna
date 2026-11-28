<?php

namespace App\Memory\Context;

use App\Models\CharacterProfile;
use App\Models\InterviewArea;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Everything retrieved for one message, before it is rendered into the prompt.
 */
final class Memory
{
    /**
     * @param  Collection<int, RelatedExchange>  $exchanges
     * @param  Collection<int, RelatedFact>  $facts
     * @param  Collection<int, RelatedPerson>  $people
     * @param  Collection<int, RelatedTopic>  $topics
     * @param  Collection<int, Project>  $projects  Tracked projects, each with a `last_discussed_at` attribute.
     * @param  Collection<int, int>  $relatedProjectIds  The projects this message relates to; their memories were boosted.
     * @param  Collection<int, InterviewArea>  $interviewAreas
     * @param  Collection<int, CharacterProfile>  $proposedProfiles
     * @param  Collection<int, Project>  $proposedProjects
     * @param  Mentions  $mentions  Who Josh pointed at with `@`; anyone matched is also in `$people`.
     * @param  Collection<int, RelatedChunk>  $chunks  The best-matching parts of kept files (Archive Memory).
     * @param  Collection<int, FileInPlay>  $files  Files attached or touched lately.
     * @param  array{kept: int, working: int}  $fileCounts
     */
    public function __construct(
        public CarbonImmutable $now,
        public ?CarbonImmutable $previousMessageAt,
        public Collection $exchanges,
        public Collection $facts,
        public Collection $people,
        public Collection $topics,
        public Collection $projects,
        public Collection $relatedProjectIds,
        public Collection $interviewAreas,
        public Collection $proposedProfiles,
        public Collection $proposedProjects,
        public Mentions $mentions,
        public Collection $chunks = new Collection,
        public Collection $files = new Collection,
        public array $fileCounts = ['kept' => 0, 'working' => 0],
    ) {}
}
