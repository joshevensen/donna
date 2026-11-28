<?php

namespace App\Ai\Tools;

use App\Enums\EvalRating;
use App\Enums\MessageAuthor;
use App\Enums\MessageStatus;
use App\Models\EvalExample;
use App\Models\Message;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class FlagReply implements Tool
{
    public function __construct(private Message $message) {}

    public function name(): string
    {
        return 'flag_reply';
    }

    public function description(): string
    {
        return 'Flag your previous reply, the one before this message, as a good or a bad example for the eval set. '
            .'Only when Josh says to flag it, or clearly says a reply was good or bad as an example. '
            .'Put what he said about why in the note.';
    }

    public function handle(Request $request): string
    {
        $input = $request->validate([
            'rating' => ['required', Rule::enum(EvalRating::class)],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $reply = Message::query()
            ->where('author', MessageAuthor::Donna)
            ->where('status', MessageStatus::Complete)
            ->where('id', '<', $this->message->id)
            ->latest('id')
            ->first();

        if ($reply === null) {
            return 'There is no earlier reply of yours to flag.';
        }

        EvalExample::updateOrCreate(
            ['message_id' => $reply->id],
            ['rating' => $input['rating'], 'note' => $input['note'] ?? null],
        );

        return "Flagged your reply #{$reply->id} as a {$input['rating']} example.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'rating' => $schema->string()->enum(['good', 'bad'])->description('Whether the reply was a good or a bad example.')->required(),
            'note' => $schema->string()->description("Why, in Josh's words. Leave out when he gave no reason."),
        ];
    }
}
