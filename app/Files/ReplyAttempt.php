<?php

namespace App\Files;

use App\Models\FileVersion;
use App\Models\Message;
use Illuminate\Support\Collection;

/**
 * What a reply had already done to files before the model call now being
 * made: in an earlier attempt that failed partway, or in an earlier call of
 * this attempt (the one that asked to look at a picture).
 *
 * A reply is written again from the start when it is retried, and the model
 * rarely words an edit the same way twice. So the changes already made are
 * told to her, and an identical one is refused outright. A repeat inside
 * one call is hers to make: deleting line 2 twice is two edits.
 */
class ReplyAttempt
{
    /**
     * @var Collection<int, FileVersion>
     */
    private Collection $earlier;

    public function __construct()
    {
        $this->earlier = new Collection;
    }

    /**
     * Note what the reply has done so far. Called before each model call.
     */
    public function begin(Message $reply): void
    {
        $this->earlier = FileVersion::query()
            ->where('message_id', $reply->id)
            ->with('file:id,name')
            ->orderBy('id')
            ->get(['id', 'file_id', 'number', 'operation_hash', 'summary']);
    }

    /**
     * The version an identical operation already wrote, if there is one.
     */
    public function alreadyDid(string $operationHash): ?FileVersion
    {
        return $this->earlier->firstWhere('operation_hash', $operationHash);
    }

    /**
     * The changes already made, in words, for the prompt of a reply that is being written again.
     *
     * @return list<string>
     */
    public function summaries(): array
    {
        return $this->earlier
            ->map(fn (FileVersion $version): string => "{$version->file->name} (file #{$version->file_id}): {$version->summary}, now version {$version->number}")
            ->all();
    }
}
