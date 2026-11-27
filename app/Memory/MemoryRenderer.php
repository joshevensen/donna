<?php

namespace App\Memory;

use App\Memory\Context\Memory;
use App\Memory\Context\Mentions;
use App\Memory\Context\RelatedExchange;
use App\Memory\Context\RelatedFact;
use App\Memory\Context\RelatedPerson;
use App\Memory\Context\RelatedTopic;
use App\Models\CharacterProfile;
use App\Models\Fact;
use App\Models\InterviewArea;
use App\Models\Person;
use App\Models\Project;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Formats retrieved memory as the text Donna reads. Every date is shown in
 * Josh's timezone; every fact carries its number so tools can refer to it.
 */
class MemoryRenderer
{
    public function render(Memory $memory): string
    {
        $sections = array_filter([
            $this->clock($memory),
            $this->section('Related past exchanges', $memory->exchanges->map($this->exchange(...))),
            $this->section('What you know', $memory->facts->map(fn (RelatedFact $related): string => $this->fact($related->fact))),
            $this->section('People', collect([
                ...$memory->people->map($this->person(...)),
                ...$this->mentionNotes($memory->mentions),
            ])),
            $this->section('Topics', $memory->topics->map($this->topic(...))),
            $this->section('Projects', $memory->projects->map(fn (Project $project): string => $this->projectActivity($project, $memory))),
            $this->section('Interview: areas still open', $memory->interviewAreas->map($this->interviewArea(...))),
            $this->section('Waiting on Josh', collect([
                ...$memory->proposedProfiles->map($this->proposedProfile(...)),
                ...$memory->proposedProjects->map($this->proposedProject(...)),
            ])),
        ]);

        return "<memory>\n".implode("\n\n", $sections)."\n</memory>";
    }

    /**
     * Who Josh is and what is being worked on: the second, rarely-changing
     * part of the system prompt.
     *
     * @param  Collection<int, Project>  $projects
     */
    public function profile(Person $josh, Collection $projects): string
    {
        $lines = ['# Josh', $josh->summary ?? "You don't have a summary of Josh yet. The interview will fix that."];

        $lines[] = "\n# Projects";

        $lines[] = $projects->isEmpty()
            ? 'None tracked yet.'
            : $projects->map(fn (Project $project): string => '- '.$this->projectLine($project))->implode("\n");

        return implode("\n", $lines);
    }

    /**
     * A moment in Josh's timezone, e.g. "Mon Nov 23, 2026 9:14 AM".
     */
    public function timestamp(CarbonInterface $moment): string
    {
        return $moment->copy()->timezone(config('donna.timezone'))->format('D M j, Y g:i A');
    }

    private function date(CarbonInterface $moment): string
    {
        return $moment->copy()->timezone(config('donna.timezone'))->format('Y-m-d');
    }

    private function clock(Memory $memory): string
    {
        $line = 'Now: '.$memory->now->format('l, F j, Y g:i A').' ('.config('donna.timezone').').';

        if ($memory->previousMessageAt !== null) {
            $line .= " Josh's previous message was ".$memory->previousMessageAt->diffForHumans($memory->now, CarbonInterface::DIFF_ABSOLUTE)
                .' ago ('.$this->timestamp($memory->previousMessageAt).').';
        }

        return $line;
    }

    /**
     * @param  Collection<int, string>  $entries
     */
    private function section(string $title, Collection $entries): ?string
    {
        return $entries->isEmpty() ? null : "## {$title}\n".$entries->implode("\n");
    }

    public function exchange(RelatedExchange $exchange): string
    {
        $text = '['.$this->date($exchange->message->created_at).'] Josh: '.$exchange->message->body;

        if ($exchange->reply !== null) {
            $text .= "\n  Donna: ".$exchange->reply->body;
        }

        return $text;
    }

    public function fact(Fact $fact): string
    {
        $subjects = collect([...$fact->people, ...$fact->projects, ...$fact->topics])->pluck('name');

        $tags = array_filter([
            "#{$fact->id}",
            $fact->kind->value,
            $this->date($fact->created_at),
            $subjects->isEmpty() ? null : 'about '.$subjects->implode(', '),
            $fact->confidence < config('donna.extraction.min_confidence') ? "unconfirmed ({$fact->confidence})" : null,
            $fact->notable_change ? 'a change worth raising' : null,
        ]);

        $text = '- ['.implode(' | ', $tags).'] '.$fact->statement;

        foreach ($fact->predecessors->sortByDesc('superseded_at') as $predecessor) {
            $text .= "\n    before ".$this->date($predecessor->superseded_at ?? $fact->created_at)
                .' (since '.$this->date($predecessor->created_at)."): {$predecessor->statement}";
        }

        return $text;
    }

    private function person(RelatedPerson $related): string
    {
        $person = $related->person;

        $heading = "### {$person->name}"
            .(count($person->aliases) > 0 ? ' (also: '.implode(', ', $person->aliases).')' : '')
            .($person->relationship !== null ? " — {$person->relationship}" : '');

        return implode("\n", array_filter([
            $heading,
            $person->summary,
            ...$related->facts->map(fn (Fact $fact): string => $this->fact($fact)),
        ]));
    }

    /**
     * What `@` matching couldn't settle, for Donna to deal with in her reply.
     *
     * @return list<string>
     */
    private function mentionNotes(Mentions $mentions): array
    {
        return [
            ...array_map(
                fn (string $name): string => "- Josh mentioned @{$name} with @, but you don't know who that is.",
                $mentions->unknown,
            ),
            ...collect($mentions->ambiguous)->map(
                fn (Collection $people, string $name): string => "- Josh mentioned @{$name} with @, and more than one person fits: "
                    .$people->pluck('name')->implode(', ').'. Ask which one he means.',
            )->values(),
        ];
    }

    private function topic(RelatedTopic $related): string
    {
        $topic = $related->topic;

        $lastDiscussed = $topic->last_discussed_at === null
            ? 'never discussed before'
            : 'last discussed '.$this->date($topic->last_discussed_at)." ({$related->daysIdle} days ago)";

        $counts = collect($related->mentions)
            ->map(fn (array $count, int $days): string => "last {$days} days: {$count['messages']} messages on {$count['days']} days")
            ->implode('; ');

        $lines = [
            "- {$topic->name} — {$lastDiscussed}".($related->stale ? ' — STALE' : ''),
            "    Josh brought it up — {$counts}",
        ];

        if ($topic->summary !== null) {
            $lines[] = "    Where it stands: {$topic->summary}";
        }

        if ($topic->projects->isNotEmpty()) {
            $lines[] = '    Part of: '.$topic->projects->pluck('name')->implode(', ');
        }

        return implode("\n", $lines);
    }

    private function projectLine(Project $project): string
    {
        return implode(' — ', array_filter([
            $project->name.(count($project->aliases) > 0 ? ' (also: '.implode(', ', $project->aliases).')' : ''),
            $project->owner !== null ? "{$project->owner->name}'s" : null,
            $project->tier?->value,
            $project->status->value,
            $project->summary,
        ]));
    }

    private function projectActivity(Project $project, Memory $memory): string
    {
        $activity = $project->last_discussed_at === null
            ? 'not discussed yet'
            : 'last discussed '.$this->date($project->last_discussed_at)
                .' ('.(int) $project->last_discussed_at->diffInDays($memory->now).' days ago)';

        return "- {$project->name} ({$project->status->value}) — {$activity}"
            .($memory->relatedProjectIds->contains($project->id) ? ' — this message relates to it' : '');
    }

    private function interviewArea(InterviewArea $area): string
    {
        return "- {$area->area} ({$area->status->value})"
            .($area->notes !== null ? ": {$area->notes}" : '');
    }

    private function proposedProfile(CharacterProfile $profile): string
    {
        return "- Character change you proposed (profile #{$profile->id}, version {$profile->version}): {$profile->change_summary}";
    }

    private function proposedProject(Project $project): string
    {
        return "- Project you suggested tracking (#{$project->id}): {$project->name}"
            .($project->summary !== null ? " — {$project->summary}" : '');
    }
}
