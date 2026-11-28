<?php

namespace App\Ai\Agents;

use App\Ai\Contracts\TracksCost;
use App\Ai\Middleware\AnswerOnFinalStep;
use App\Ai\Tools\AppAction;
use App\Ai\Tools\CorrectFact;
use App\Ai\Tools\CostReport;
use App\Ai\Tools\CreateFile;
use App\Ai\Tools\DecideCharacterChange;
use App\Ai\Tools\DeleteFile;
use App\Ai\Tools\EditFile;
use App\Ai\Tools\FlagReply;
use App\Ai\Tools\ForgetFact;
use App\Ai\Tools\KeepFile;
use App\Ai\Tools\LinkFile;
use App\Ai\Tools\ListFiles;
use App\Ai\Tools\ProposeCharacterChange;
use App\Ai\Tools\ProposeProject;
use App\Ai\Tools\QueryCsv;
use App\Ai\Tools\ReadFile;
use App\Ai\Tools\Recall;
use App\Ai\Tools\Remember;
use App\Ai\Tools\RenameFile;
use App\Ai\Tools\RestoreFile;
use App\Ai\Tools\RevertFile;
use App\Ai\Tools\SavePerson;
use App\Ai\Tools\UpdateInterview;
use App\Ai\Tools\UpdateProject;
use App\Ai\Tools\ViewFile;
use App\Ai\Tools\WhatIKnow;
use App\Enums\CostPurpose;
use App\Files\Looks;
use App\Memory\Context\ReplyContext;
use App\Models\Message;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Tools\WebSearch;

/**
 * Donna, replying to one message of Josh's with the context rebuilt for it.
 */
class Donna implements Agent, Conversational, HasMiddleware, HasProviderOptions, HasTools, TracksCost
{
    use Promptable;

    /**
     * @param  Message  $message  The message of Josh's being answered.
     * @param  Message  $reply  The reply being written.
     * @param  Looks  $looks  The files she asks to be shown while writing it.
     */
    public function __construct(
        public ReplyContext $context,
        public Message $message,
        public Message $reply,
        public Looks $looks = new Looks,
    ) {}

    public function instructions(): string
    {
        return $this->context->character."\n\n".$this->context->profile;
    }

    /**
     * The recent conversation, verbatim.
     */
    public function messages(): iterable
    {
        return $this->context->turns;
    }

    /**
     * Everything Donna can do mid-reply. Each tool's work is plain code.
     */
    public function tools(): iterable
    {
        $about = ['message' => $this->message];
        $writing = ['reply' => $this->reply];

        return [
            resolve(Remember::class, $about),
            resolve(Recall::class, $about),
            resolve(WhatIKnow::class),
            resolve(CorrectFact::class, $about),
            resolve(ForgetFact::class),
            resolve(FlagReply::class, $about),
            resolve(SavePerson::class),
            resolve(ProposeProject::class),
            resolve(UpdateProject::class),
            resolve(ProposeCharacterChange::class, $about),
            resolve(DecideCharacterChange::class, $about),
            resolve(UpdateInterview::class),
            resolve(CostReport::class),
            resolve(AppAction::class, $writing),
            resolve(CreateFile::class, $writing),
            resolve(ReadFile::class),
            resolve(EditFile::class, $writing),
            resolve(QueryCsv::class),
            resolve(RenameFile::class, $writing),
            resolve(KeepFile::class, $writing),
            resolve(LinkFile::class, $about),
            resolve(DeleteFile::class, $writing),
            resolve(RestoreFile::class, $writing),
            resolve(RevertFile::class, $writing),
            resolve(ListFiles::class, $writing),
            resolve(ViewFile::class, ['looks' => $this->looks]),
            (new WebSearch)->max(config('donna.web_search.max_uses')),
        ];
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new AnswerOnFinalStep];
    }

    /**
     * On Anthropic the system prompt goes out as two cached blocks, stable
     * first: who Donna is, then who Josh is. Everything that changes per
     * message comes after both breakpoints.
     */
    public function providerOptions(Lab|string $provider): array
    {
        if ($provider !== Lab::Anthropic) {
            return [];
        }

        $cache = array_filter(['type' => 'ephemeral', 'ttl' => config('donna.models.reply.cache_ttl') === '1h' ? '1h' : null]);

        return [
            'system' => [
                ['type' => 'text', 'text' => $this->context->character, 'cache_control' => $cache],
                ['type' => 'text', 'text' => $this->context->profile, 'cache_control' => $cache],
            ],
            'output_config' => ['effort' => config('donna.models.reply.effort')],
            // Lets Anthropic retry a declined request on another model before it reaches Josh.
            ...(config('donna.models.reply.refusal_fallback') ? ['fallbacks' => 'default'] : []),
        ];
    }

    public function provider(): string
    {
        return config('donna.models.reply.provider');
    }

    public function model(): string
    {
        return config('donna.models.reply.model');
    }

    public function maxSteps(): int
    {
        return config('donna.models.reply.max_steps');
    }

    public function maxTokens(): int
    {
        return config('donna.models.reply.max_tokens');
    }

    public function timeout(): int
    {
        return config('donna.models.reply.timeout');
    }

    public function costPurpose(): CostPurpose
    {
        return CostPurpose::Reply;
    }

    public function costTrigger(): Message
    {
        return $this->message;
    }
}
