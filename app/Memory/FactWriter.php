<?php

namespace App\Memory;

use App\Enums\FactKind;
use App\Enums\FactProvenance;
use App\Models\Fact;
use App\Models\Message;
use App\Models\Person;
use App\Models\Project;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The only way facts change. Facts are never edited in place: a correction
 * writes a new fact and marks the old one superseded. The single hard delete
 * is forgetting, and that only happens when Josh asks.
 */
class FactWriter
{
    public function __construct(private Embedder $embedder) {}

    /**
     * Write a new fact about the given subjects.
     *
     * @param  list<Person|Project|Topic>  $subjects
     * @param  list<float>|null  $embedding  Pass one that is already computed to skip the embedding call.
     * @param  Model|null  $trigger  What the embedding call is charged to.
     */
    public function write(
        string $statement,
        FactKind $kind,
        FactProvenance $provenance,
        array $subjects,
        ?Message $source = null,
        float $confidence = 1.0,
        bool $notableChange = false,
        ?array $embedding = null,
        ?Model $trigger = null,
    ): Fact {
        $embedding ??= $this->embedder->attempt($statement, $trigger ?? $source);

        return DB::transaction(function () use ($statement, $kind, $provenance, $subjects, $source, $confidence, $notableChange, $embedding): Fact {
            $fact = Fact::create([
                'statement' => $statement,
                'kind' => $kind,
                'provenance' => $provenance,
                'confidence' => $confidence,
                'notable_change' => $notableChange,
                'source_message_id' => $source?->id,
                'embedding' => $embedding,
            ]);

            foreach ($subjects as $subject) {
                DB::table('fact_subjects')->insert([
                    'fact_id' => $fact->id,
                    'subject_type' => $subject->getMorphClass(),
                    'subject_id' => $subject->getKey(),
                ]);
            }

            return $fact;
        });
    }

    /**
     * Replace a fact with a corrected statement about the same subjects,
     * keeping the old one as history.
     *
     * @param  list<float>|null  $embedding
     */
    public function supersede(
        Fact $old,
        string $statement,
        FactProvenance $provenance,
        bool $notableChange = false,
        ?Message $source = null,
        ?array $embedding = null,
        ?Model $trigger = null,
    ): Fact {
        $embedding ??= $this->embedder->attempt($statement, $trigger ?? $source);

        $old->loadMissing(['people', 'projects', 'topics']);

        return DB::transaction(function () use ($old, $statement, $provenance, $notableChange, $source, $embedding): Fact {
            $new = $this->write(
                statement: $statement,
                kind: $old->kind,
                provenance: $provenance,
                subjects: [...$old->people, ...$old->projects, ...$old->topics],
                source: $source,
                notableChange: $notableChange,
                embedding: $embedding,
            );

            $old->update([
                'superseded_by_id' => $new->id,
                'superseded_at' => now(),
            ]);

            return $new;
        });
    }

    /**
     * Forget a fact for good, along with every earlier version of it.
     *
     * @return int How many facts were deleted.
     */
    public function forget(Fact $fact): int
    {
        return DB::transaction(function () use ($fact): int {
            $ids = [$fact->id];
            $frontier = [$fact->id];

            while ($frontier !== []) {
                $frontier = Fact::query()->whereIn('superseded_by_id', $frontier)->pluck('id')->all();
                $ids = [...$ids, ...$frontier];
            }

            return Fact::query()->whereKey($ids)->delete();
        });
    }
}
