<?php

namespace App\Ai\Agents;

use App\Ai\Contracts\TracksCost;
use App\Enums\CostPurpose;
use App\Models\Message;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

/**
 * Decides which topics one exchange is about. A judgment call, so a model
 * makes it; everything done with the answer is code.
 */
class TopicTagger implements Agent, HasProviderOptions, HasStructuredOutput, TracksCost
{
    use Promptable;

    /**
     * @param  Message  $message  The message of Josh's that started the exchange.
     */
    public function __construct(public Message $message) {}

    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
            You label one exchange between Josh and his assistant Donna with the topics it is about.

            A topic is something they talk about over time: a project, a product, a person, a hobby, a trip, a recurring problem. Name it the way Josh would, in one to three words ("Fibermade", "Mechanical keyboards", "Texas trip"). Small talk, greetings, and questions about Donna herself have no topic.

            You are given the exchange and the existing topics closest to it. Reuse an existing topic whenever it fits, by its id. Only add a new topic when none of the existing ones covers it, and never add one that is just a rewording of an existing topic. Most exchanges have one topic; many have none; few have more than two.
            INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'existing_topic_ids' => $schema->array()->items($schema->integer())
                ->description('Ids of the existing topics this exchange is about.')
                ->required(),
            'new_topics' => $schema->array()->items($schema->string())
                ->description('Names of topics this exchange is about that do not exist yet.')
                ->required(),
        ];
    }

    public function providerOptions(Lab|string $provider): array
    {
        return $provider === Lab::Anthropic
            ? ['output_config' => ['effort' => config('donna.models.tagging.effort')]]
            : [];
    }

    public function provider(): string
    {
        return config('donna.models.tagging.provider');
    }

    public function model(): string
    {
        return config('donna.models.tagging.model');
    }

    public function maxTokens(): int
    {
        return config('donna.models.tagging.max_tokens');
    }

    public function timeout(): int
    {
        return config('donna.models.tagging.timeout');
    }

    public function costPurpose(): CostPurpose
    {
        return CostPurpose::Tagging;
    }

    public function costTrigger(): Message
    {
        return $this->message;
    }
}
