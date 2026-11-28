<?php

namespace App\Files;

use App\Enums\FileRole;
use App\Enums\MessageAuthor;
use App\Models\File;
use App\Models\Message;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Donna's edits to a file. She decides what to change; applying it is code,
 * and a small edit never rewrites the whole file. Every edit works on the
 * file as it is at that moment, writes a new version with a summary of what
 * changed, and is applied at most once per reply.
 */
class FileEditor
{
    public function __construct(
        private FileWriter $writer,
        private FileLibrary $library,
        private ReplyAttempt $attempt,
    ) {}

    /**
     * Replace exact text. Refused when the text isn't there, or is there
     * more than once and `$all` isn't set.
     */
    public function replace(File $file, Message $reply, string $find, string $replace, bool $all = false): string
    {
        return $this->apply($file, $reply, ['replace', $find, $replace, $all], function (string $body) use ($find, $replace, $all): array {
            [$find, $replace] = [$this->withLineEndings($find, $body), $this->withLineEndings($replace, $body)];

            $count = $find === '' ? 0 : substr_count($body, $find);

            if ($count === 0) {
                throw new EditRefused('No match for "'.Str::limit($find, 80).'". Nothing changed. Read the file to see its exact text.');
            }

            if ($count > 1 && ! $all) {
                throw new EditRefused('"'.Str::limit($find, 80)."\" appears {$count} times. Nothing changed. Set all to true to replace every one, or include more of the surrounding text to pick one.");
            }

            return [
                str_replace($find, $replace, $body),
                "Replaced {$count} ".Str::plural('match', $count).' of "'.Str::limit($find, 40).'"',
            ];
        });
    }

    /**
     * Replace everything under a heading, up to the next heading of the same
     * or a higher level. The heading's own line stays.
     */
    public function replaceSection(File $file, Message $reply, string $heading, string $newText): string
    {
        return $this->apply($file, $reply, ['replace_section', $heading, $newText], function (string $body) use ($heading, $newText): array {
            $found = Markdown::find($body, $heading);

            if (count($found) !== 1) {
                throw new EditRefused($this->headingProblem($body, $heading, $found));
            }

            $lines = Markdown::lines($body);
            $section = Markdown::section($body, $found[0]);

            $new = Markdown::lines(trim($this->withoutHeading($newText, $found[0]['title']), "\r\n"));

            // A blank line either side: under the heading, and before the next one (or to end the file).
            array_splice($lines, $section['from'], $section['to'] - $section['from'], ['', ...$new, '']);

            return [implode(Markdown::lineEnding($body), $lines), "Rewrote the \"{$found[0]['title']}\" section"];
        });
    }

    /**
     * Add text at the end, on a line of its own.
     */
    public function append(File $file, Message $reply, string $text): string
    {
        return $this->apply($file, $reply, ['append', $text], function (string $body) use ($text): array {
            $ending = Markdown::lineEnding($body);
            $text = rtrim($this->withLineEndings($text, $body), "\r\n");
            $count = count(Markdown::lines($text));

            return [
                ($body === '' ? '' : rtrim($body, "\r\n").$ending).$text.$ending,
                "Appended {$count} ".Str::plural('line', $count),
            ];
        });
    }

    /**
     * Delete a range of lines, numbered from 1.
     */
    public function deleteLines(File $file, Message $reply, int $from, int $to): string
    {
        return $this->apply($file, $reply, ['delete_lines', $from, $to], function (string $body) use ($from, $to): array {
            $lines = Markdown::lines($body);

            if ($from < 1 || $to < $from || $to > count($lines)) {
                throw new EditRefused('The file has '.count($lines)." lines; {$from} to {$to} isn't a range in it. Nothing changed.");
            }

            array_splice($lines, $from - 1, $to - $from + 1);

            return [implode(Markdown::lineEnding($body), $lines), $from === $to ? "Deleted line {$from}" : "Deleted lines {$from}–{$to}"];
        });
    }

    /**
     * Delete the lines that read exactly as given. Refused when they aren't
     * there, or are there more than once and `$all` isn't set.
     */
    public function deleteContent(File $file, Message $reply, string $content, bool $all = false): string
    {
        return $this->apply($file, $reply, ['delete_content', $content, $all], function (string $body) use ($content, $all): array {
            $lines = Markdown::lines($body);
            $wanted = array_map(trim(...), Markdown::lines(trim($content)));
            $starts = $this->blocks(array_map(trim(...), $lines), $wanted);

            if ($starts === []) {
                throw new EditRefused('No lines read "'.Str::limit(trim($content), 80).'". Nothing changed. Read the file to see its exact text.');
            }

            if (count($starts) > 1 && ! $all) {
                throw new EditRefused('That text is in the file '.count($starts).' times, at lines '.implode(', ', array_map(fn (int $start): int => $start + 1, $starts))
                    .'. Nothing changed. Set all to true to delete every one, or delete by line number.');
            }

            foreach (array_reverse($starts) as $start) {
                array_splice($lines, $start, count($wanted));
            }

            $count = count($starts) * count($wanted);

            return [implode(Markdown::lineEnding($body), $lines), "Deleted {$count} ".Str::plural('line', $count)];
        });
    }

    /**
     * Put the file back as it was at an earlier version, by writing that
     * text as a new version. With no version given: as it was before the
     * last reply in which Donna edited it, which is what "undo that" means
     * whether she made one change then or three.
     *
     * The operation is identified by what was asked for, not by what it
     * resolves to: once an undo has happened, "her last edit" is a different
     * edit, and a retried reply must not undo the undo.
     */
    public function revert(File $file, Message $reply, ?int $number = null): string
    {
        return $this->apply($file, $reply, ['revert', $number], function () use ($file, $number): array {
            $number ??= $this->beforeDonnasLastEdits($file);

            $target = $file->versions()->where('number', $number)->first();

            if ($target === null) {
                throw new EditRefused("There is no version {$number} of {$file->name}. It has versions 1 to {$file->versions()->max('number')}. Nothing changed.");
            }

            return [$target->body, "Reverted to version {$number}"];
        });
    }

    /**
     * Run one edit against the file's current text, inside a transaction
     * with the file locked, and write the result as a new version.
     *
     * A reply that fails partway is written again from the start, and would
     * repeat its edits. So each one is recorded against the reply, and an
     * edit identical to one an earlier attempt (or an earlier call of this
     * one) already made is skipped and reported as already done.
     *
     * @param  list<mixed>  $operation  What identifies this edit: its kind and arguments.
     * @param  Closure(string): array{0: string, 1: string}  $change  Given the current text, returns the new text and a summary.
     */
    private function apply(File $file, Message $reply, array $operation, Closure $change): string
    {
        if (! $file->kind->isEditable()) {
            return "{$file->name} is a {$file->kind->label()} file. Only markdown files (.md, .txt) can be edited; the others are read.";
        }

        $hash = hash('sha256', json_encode([$file->id, ...$operation]));

        try {
            return DB::transaction(function () use ($file, $reply, $hash, $change): string {
                $current = File::query()->lockForUpdate()->findOrFail($file->id)->currentVersion;

                $done = $this->attempt->alreadyDid($hash);

                if ($done !== null) {
                    return "Already done earlier in this reply ({$done->summary}); not done again.";
                }

                [$body, $summary] = $change($current->body);

                if ($body === $current->body) {
                    return "That leaves {$file->name} exactly as it is. Nothing changed.";
                }

                $version = $this->writer->write($file, $body, MessageAuthor::Donna, $summary, $reply, $hash);

                $this->library->touch($file, $reply, FileRole::Edited);

                return "{$summary}. {$file->name} is now at version {$version->number}.";
            });
        } catch (EditRefused $refused) {
            return $refused->getMessage();
        }
    }

    /**
     * The version just before the edits Donna made in the last reply she
     * edited the file in.
     */
    private function beforeDonnasLastEdits(File $file): int
    {
        $last = $file->versions()->where('author', MessageAuthor::Donna)->orderByDesc('number')->first(['number', 'message_id']);

        if ($last === null) {
            return max(1, $file->versions()->max('number') - 1);
        }

        $first = $last->message_id === null
            ? $last->number
            : $file->versions()->where('message_id', $last->message_id)->min('number');

        return max(1, $first - 1);
    }

    /**
     * Where each run of lines equal to `$wanted` starts, counting from 0. Runs don't overlap.
     *
     * @param  list<string>  $lines
     * @param  list<string>  $wanted
     * @return list<int>
     */
    private function blocks(array $lines, array $wanted): array
    {
        $starts = [];
        $at = 0;

        while ($at <= count($lines) - count($wanted)) {
            if (array_slice($lines, $at, count($wanted)) === $wanted) {
                $starts[] = $at;
                $at += count($wanted);
            } else {
                $at++;
            }
        }

        return $starts;
    }

    /**
     * @param  list<array{line: int, level: int, title: string, path: string}>  $found
     */
    private function headingProblem(string $body, string $heading, array $found): string
    {
        if ($found !== []) {
            return 'More than one heading is called "'.$heading.'": '
                .collect($found)->map(fn (array $match): string => "\"{$match['path']}\" (line {$match['line']})")->implode(', ')
                .'. Nothing changed. Give the full path of the one you mean.';
        }

        $headings = collect(Markdown::headings($body))->take(40)->map(fn (array $match): string => "\"{$match['path']}\"");

        return 'There is no heading "'.$heading.'". Nothing changed. '
            .($headings->isEmpty() ? 'The file has no headings; use replace or append.' : 'The headings are: '.$headings->implode(', ').'.');
    }

    /**
     * She was asked for what goes under the heading, and sometimes repeats the heading line itself.
     */
    private function withoutHeading(string $text, string $title): string
    {
        $lines = Markdown::lines(ltrim($text, "\r\n"));

        if (preg_match('/^ {0,3}#{1,6}[ \t]+(.+?)[ \t]*#*[ \t]*$/u', $lines[0] ?? '', $match) === 1 && mb_strtolower(trim($match[1])) === mb_strtolower($title)) {
            array_shift($lines);
        }

        return implode("\n", $lines);
    }

    /**
     * Text she wrote uses plain newlines; a file may not.
     */
    private function withLineEndings(string $text, string $body): string
    {
        $text = str_replace("\r\n", "\n", $text);

        return Markdown::lineEnding($body) === "\r\n" ? str_replace("\n", "\r\n", $text) : $text;
    }
}
