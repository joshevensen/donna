<?php

namespace App\Ai\Tools;

use App\Enums\AppActionType;
use App\Models\Message;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Offers Josh a button under the reply. Nothing is done here: signing out
 * and turning notifications on happen in the app, on his tap. That is
 * confirm-first, and a browser only lets a tap ask for notification
 * permission anyway.
 */
class AppAction implements Tool
{
    /**
     * @param  Message  $reply  The reply being written. The job that finishes it saves the button with it.
     */
    public function __construct(private Message $reply) {}

    public function name(): string
    {
        return 'app_action';
    }

    public function description(): string
    {
        return 'Put a button under your reply for something only the app on Josh\'s device can do: sign out, sign out everywhere, '
            .'or turn notifications on or off. You cannot do these yourself. Nothing happens until he taps it, so say what the button does, not that it is done.';
    }

    public function handle(Request $request): string
    {
        $input = $request->validate([
            'type' => ['required', Rule::enum(AppActionType::class)],
        ]);

        $type = AppActionType::from($input['type']);

        // Kept on the model in memory: a reply that fails and is retried starts clean.
        $this->reply->meta = [
            ...($this->reply->meta ?? []),
            'actions' => collect($this->reply->meta['actions'] ?? [])
                ->reject(fn (array $action): bool => $action['type'] === $type->value)
                ->push(['type' => $type->value, 'label' => $type->label()])
                ->values()
                ->all(),
        ];

        return "A \"{$type->label()}\" button will sit under your reply. Nothing has happened yet; it happens when Josh taps it.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(AppActionType::class)->description('What the button does when Josh taps it.')->required(),
        ];
    }
}
