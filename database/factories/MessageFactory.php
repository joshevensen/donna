<?php

namespace Database\Factories;

use App\Enums\MessageAuthor;
use App\Enums\MessageSource;
use App\Enums\MessageStatus;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    /**
     * Define the model's default state: something Josh said.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'author' => MessageAuthor::Josh,
            'body' => fake()->sentence(),
            'source' => MessageSource::Terminal,
            'status' => MessageStatus::Complete,
        ];
    }

    /**
     * A finished reply from Donna.
     */
    public function fromDonna(): static
    {
        return $this->state(['author' => MessageAuthor::Donna]);
    }

    /**
     * Donna's reply to the given message.
     */
    public function replyTo(Message $message): static
    {
        return $this->fromDonna()->state(['in_reply_to_id' => $message->id]);
    }

    /**
     * A reply Donna has not written yet.
     */
    public function pending(): static
    {
        return $this->fromDonna()->state([
            'body' => '',
            'status' => MessageStatus::Pending,
        ]);
    }

    /**
     * A reply that could not be generated.
     */
    public function failed(): static
    {
        return $this->fromDonna()->state([
            'body' => '',
            'status' => MessageStatus::Failed,
            'error' => 'Donna could not reply.',
        ]);
    }
}
