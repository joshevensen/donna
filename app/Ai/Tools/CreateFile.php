<?php

namespace App\Ai\Tools;

use App\Enums\FileRole;
use App\Files\FileIntake;
use App\Files\FileLibrary;
use App\Models\File;
use App\Models\Message;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CreateFile implements Tool
{
    /**
     * @param  Message  $reply  The reply being written; the new file's card goes under it.
     */
    public function __construct(
        private Message $reply,
        private FileIntake $intake,
        private FileLibrary $library,
    ) {}

    public function name(): string
    {
        return 'create_file';
    }

    public function description(): string
    {
        return 'Write a new markdown file, e.g. when Josh says "write that up as a plan". He gets a card under your reply that opens it, '
            .'so don\'t repeat its contents in your reply. It starts as a working file; keep it only if he asks.';
    }

    public function handle(Request $request): string
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'content' => ['required', 'string', 'max:2000000'],
        ]);

        $hash = hash('sha256', json_encode(['create', $input['name'], $input['content']]));

        // A retried reply repeats its tool calls; the file is only written once.
        $existing = File::query()
            ->whereHas('versions', fn ($query) => $query->where('message_id', $this->reply->id)->where('operation_hash', $hash))
            ->first();

        if ($existing !== null) {
            return "Already created as file #{$existing->id} \"{$existing->name}\" earlier in this reply; not created again.";
        }

        $file = $this->intake->write($input['name'], $input['content'], $this->reply, $hash);

        $this->library->touch($file, $this->reply, FileRole::Created);

        return "Created file #{$file->id} \"{$file->name}\". A card for it sits under your reply.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('The file name, e.g. "launch-plan.md".')->required(),
            'content' => $schema->string()->description('The whole file, as markdown.')->required(),
        ];
    }
}
