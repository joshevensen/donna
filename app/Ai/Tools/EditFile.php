<?php

namespace App\Ai\Tools;

use App\Files\FileEditor;
use App\Files\FileFinder;
use App\Models\Message;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * One tool for the four ways Donna changes a markdown file. Which one, and
 * the new words, are her call; applying it is code.
 */
class EditFile implements Tool
{
    private const array OPERATIONS = ['replace_section', 'replace', 'append', 'delete_lines'];

    /**
     * @param  Message  $reply  The reply being written. Each edit is recorded against it.
     */
    public function __construct(
        private Message $reply,
        private FileFinder $files,
        private FileEditor $editor,
    ) {}

    public function name(): string
    {
        return 'edit_file';
    }

    public function description(): string
    {
        return 'Change a markdown file (.md or .txt). Never retype a whole file for a small change. Pick one operation: '
            .'"replace" swaps exact text (find, replace; all=true for every match); '
            .'"replace_section" rewrites everything under a heading (heading, new_text; the heading line stays); '
            .'"append" adds text at the end (text); '
            .'"delete_lines" removes lines by number (from_line, to_line) or by their exact text (content). '
            .'It works on the file as it is now, so a change Josh just made by hand is kept. The result says what changed, or why nothing did.';
    }

    public function handle(Request $request): string
    {
        $input = $request->validate([
            'file' => ['required', 'string', 'max:255'],
            'operation' => ['required', Rule::in(self::OPERATIONS)],
            'find' => ['required_if:operation,replace', 'nullable', 'string'],
            'replace' => ['nullable', 'string'],
            'all' => ['nullable', 'boolean'],
            'heading' => ['required_if:operation,replace_section', 'nullable', 'string', 'max:500'],
            'new_text' => ['nullable', 'string'],
            'text' => ['required_if:operation,append', 'nullable', 'string'],
            'from_line' => ['nullable', 'integer', 'min:1'],
            'to_line' => ['nullable', 'integer', 'min:1'],
            'content' => ['nullable', 'string'],
        ]);

        $file = $this->files->find($input['file']);

        if (is_string($file)) {
            return $file;
        }

        $all = (bool) ($input['all'] ?? false);

        return match ($input['operation']) {
            'replace' => $this->editor->replace($file, $this->reply, $input['find'], $input['replace'] ?? '', $all),
            'replace_section' => $this->editor->replaceSection($file, $this->reply, $input['heading'], $input['new_text'] ?? ''),
            'append' => $this->editor->append($file, $this->reply, $input['text']),
            'delete_lines' => match (true) {
                isset($input['from_line']) => $this->editor->deleteLines($file, $this->reply, $input['from_line'], $input['to_line'] ?? $input['from_line']),
                filled($input['content'] ?? null) => $this->editor->deleteContent($file, $this->reply, $input['content'], $all),
                default => 'delete_lines needs from_line (and to_line), or content. Nothing changed.',
            },
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'file' => $schema->string()->description('The file\'s number (12) or name.')->required(),
            'operation' => $schema->string()->enum(self::OPERATIONS)->required(),
            'find' => $schema->string()->description('replace: the exact text to find.'),
            'replace' => $schema->string()->description('replace: what to put in its place. Empty removes it.'),
            'all' => $schema->boolean()->description('replace, delete_lines by content: act on every match instead of requiring exactly one.'),
            'heading' => $schema->string()->description('replace_section: the heading, or its full path ("Pricing › Wholesale") when the name repeats.'),
            'new_text' => $schema->string()->description('replace_section: the new text under the heading, without the heading line.'),
            'text' => $schema->string()->description('append: the text to add at the end.'),
            'from_line' => $schema->integer()->description('delete_lines: the first line to delete, counting from 1.'),
            'to_line' => $schema->integer()->description('delete_lines: the last line to delete. Defaults to from_line.'),
            'content' => $schema->string()->description('delete_lines: the exact text of the line or lines to delete, instead of numbers.'),
        ];
    }
}
