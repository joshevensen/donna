<?php

namespace App\Ai\Tools;

use App\Enums\CharacterProfileStatus;
use App\Models\CharacterProfile;
use App\Models\Message;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class DecideCharacterChange implements Tool
{
    public function __construct(private Message $message) {}

    public function name(): string
    {
        return 'decide_character_change';
    }

    public function description(): string
    {
        return 'Record Josh\'s decision on the character change you proposed: approve it, making it who you are, or reject it. '
            .'Only after Josh has answered the proposal in his own message.';
    }

    public function handle(Request $request): string
    {
        $input = $request->validate(['approve' => ['required', 'boolean']]);

        $proposal = CharacterProfile::query()->where('status', CharacterProfileStatus::Proposed)->first();

        if ($proposal === null) {
            return 'There is no proposed character change waiting on a decision.';
        }

        // Donna's character never changes without Josh's approval, and he cannot
        // have approved something first proposed in reply to this very message.
        if ($proposal->proposed_in_message_id === $this->message->id) {
            return 'Josh has not seen this proposal yet. Ask him, and record his decision once he answers.';
        }

        if (! $input['approve']) {
            $proposal->update(['status' => CharacterProfileStatus::Rejected, 'decided_at' => now()]);

            return "Version {$proposal->version} rejected. You stay as you are.";
        }

        DB::transaction(function () use ($proposal): void {
            CharacterProfile::query()
                ->where('status', CharacterProfileStatus::Active)
                ->update(['status' => CharacterProfileStatus::Superseded]);

            $proposal->update(['status' => CharacterProfileStatus::Active, 'decided_at' => now()]);
        });

        return "Version {$proposal->version} is now your character profile, starting with your next reply.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'approve' => $schema->boolean()->description('True if Josh said yes, false if he said no.')->required(),
        ];
    }
}
