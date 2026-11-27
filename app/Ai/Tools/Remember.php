<?php

namespace App\Ai\Tools;

use App\Enums\FactKind;
use App\Enums\FactProvenance;
use App\Memory\FactWriter;
use App\Memory\SubjectResolver;
use App\Models\Fact;
use App\Models\Message;
use App\Models\Person;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class Remember implements Tool
{
    public function __construct(
        private Message $message,
        private FactWriter $facts,
        private SubjectResolver $subjects,
    ) {}

    public function name(): string
    {
        return 'remember';
    }

    public function description(): string
    {
        return 'Save one fact to memory right now. Use when Josh asks you to remember something or clearly wants it to stick. '
            .'Write the statement so it stands on its own, with names instead of pronouns. '
            .'List who or what it is about; leave subjects empty when it is simply about Josh.';
    }

    public function handle(Request $request): string
    {
        $input = $request->validate([
            'statement' => ['required', 'string', 'max:2000'],
            'kind' => ['nullable', Rule::enum(FactKind::class)],
            'subjects' => ['nullable', 'array'],
            'subjects.*.type' => ['required', Rule::in(['person', 'project', 'topic'])],
            'subjects.*.name' => ['required', 'string', 'max:255'],
        ]);

        // A retried reply repeats its tool calls; the fact is only written once.
        $existing = Fact::query()
            ->active()
            ->where('source_message_id', $this->message->id)
            ->where('statement', $input['statement'])
            ->first();

        if ($existing !== null) {
            return "Already remembered as fact #{$existing->id}.";
        }

        [$subjects, $missing] = $this->subjects->resolve($input['subjects'] ?? [], $this->message);

        $fact = $this->facts->write(
            statement: $input['statement'],
            kind: FactKind::from($input['kind'] ?? FactKind::Fact->value),
            provenance: FactProvenance::Remember,
            subjects: $subjects === [] ? [Person::josh()] : $subjects,
            source: $this->message,
        );

        return "Remembered as fact #{$fact->id}."
            .($missing === [] ? '' : ' Not linked to '.implode(', ', $missing).': no such project is tracked.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'statement' => $schema->string()->description('The fact, as one self-contained sentence.')->required(),
            'kind' => $schema->string()->enum(FactKind::class)->description('Defaults to "fact". Use "moment" for shared history between you and Josh.'),
            'subjects' => $schema->array()->items($schema->object([
                'type' => $schema->string()->enum(['person', 'project', 'topic'])->required(),
                'name' => $schema->string()->description('Their name. Use "Josh" for Josh and "Donna" for yourself.')->required(),
            ]))->description('Who or what the fact is about.'),
        ];
    }
}
