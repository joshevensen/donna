<?php

namespace App\Ai\Tools;

use App\Enums\CharacterProfileStatus;
use App\Models\CharacterProfile;
use App\Models\Message;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ProposeCharacterChange implements Tool
{
    public function __construct(private Message $message) {}

    public function name(): string
    {
        return 'propose_character_change';
    }

    public function description(): string
    {
        return 'Propose a change to your own character profile. Pass the complete revised profile, not a diff, '
            .'and a one-line summary of what changes and why. Nothing about you changes until Josh approves it.';
    }

    public function handle(Request $request): string
    {
        $input = $request->validate([
            'body' => ['required', 'string', 'max:50000'],
            'change_summary' => ['required', 'string', 'max:1000'],
        ]);

        $pending = CharacterProfile::query()->where('status', CharacterProfileStatus::Proposed)->first();

        if ($pending !== null) {
            return "Version {$pending->version} is still waiting on Josh: \"{$pending->change_summary}\". Settle that one first.";
        }

        $profile = CharacterProfile::create([
            'version' => CharacterProfile::query()->max('version') + 1,
            'body' => $input['body'],
            'status' => CharacterProfileStatus::Proposed,
            'change_summary' => $input['change_summary'],
            'proposed_in_message_id' => $this->message->id,
        ]);

        return "Proposed as version {$profile->version}. Tell Josh what would change and ask him. It takes effect only if he says yes.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'body' => $schema->string()->description('The full text of the revised profile.')->required(),
            'change_summary' => $schema->string()->description('One line: what changes and why.')->required(),
        ];
    }
}
