<?php

namespace App\Memory;

use App\Enums\FileExtraction;
use App\Enums\FileKind;
use App\Enums\FileRole;
use App\Enums\MessageAuthor;
use App\Files\FileText;
use App\Files\LineDiff;
use App\Files\Markdown;
use App\Files\ModelFile;
use App\Memory\Context\AttachedFile;
use App\Memory\Context\FileInPlay;
use App\Models\File;
use App\Models\FileVersion;
use App\Models\Message;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Works out what Donna gets of Josh's files for one message: the ones he
 * just attached, with exactly how much of each she was given, and the ones
 * in play from the last stretch of conversation. All of it is code.
 */
class FileContext
{
    public function __construct(private ModelFile $shown) {}

    /**
     * The files attached to this message. A small text file goes in whole; a
     * large one as an outline; an image or a scan is shown to the model
     * itself. Each says which, so she never answers as if she had read more.
     *
     * @return Collection<int, AttachedFile>
     */
    public function attached(Message $message): Collection
    {
        return $message->files()
            ->wherePivot('role', FileRole::Attached->value)
            ->with('currentVersion')
            ->orderBy('files.id')
            ->get()
            ->map(fn (File $file): AttachedFile => match (true) {
                $file->kind === FileKind::Image => $this->picture($file, 'shown to you as an image'),
                $file->extraction === FileExtraction::Failed => new AttachedFile($file, 'extraction failed: '.$file->extraction_error),
                $file->kind === FileKind::Pdf && ! ($file->meta['has_text'] ?? true) => $this->picture(
                    $file,
                    'no text could be extracted (scanned, '.$this->pages($file).'), shown to you as images',
                    'no text could be extracted (scanned, '.$this->pages($file).')',
                ),
                default => $this->text($file),
            })
            ->values();
    }

    /**
     * Files attached or touched in the last stretch of conversation, plus
     * any Josh has edited by hand since Donna last replied.
     *
     * @return Collection<int, FileInPlay>
     */
    public function inPlay(Message $message): Collection
    {
        $messageIds = Message::query()
            ->where('id', '<=', $message->id)
            ->latest('id')
            ->limit(config('donna.context.files_in_play_messages') + 1)
            ->pluck('id');

        $lastSawAt = $this->lastSawAt($message);

        $fileIds = DB::table('file_message')->whereIn('message_id', $messageIds)->pluck('file_id')
            ->merge($lastSawAt === null ? [] : FileVersion::query()
                ->where('author', MessageAuthor::Josh)
                ->where('number', '>', 1)
                ->where('created_at', '>', $lastSawAt)
                ->pluck('file_id'))
            ->unique();

        $files = File::withTrashed()
            ->with('currentVersion:id,file_id,number,author,summary,created_at')
            ->findMany($fileIds)
            ->sortBy('id');

        $arrivals = DB::table('file_message')
            ->whereIn('file_id', $files->modelKeys())
            ->whereIn('role', [FileRole::Attached->value, FileRole::Created->value])
            ->orderBy('message_id')
            ->get()
            ->unique('file_id')
            ->keyBy('file_id');

        $messages = Message::query()->with('inReplyTo')->findMany($arrivals->pluck('message_id'))->keyBy('id');

        return $files->map(function (File $file) use ($arrivals, $messages, $lastSawAt): FileInPlay {
            $arrival = $arrivals->get($file->id);
            $with = $arrival === null ? null : $messages->get($arrival->message_id);

            return new FileInPlay(
                file: $file,
                // A file she wrote arrived with the message of Josh's she was answering.
                arrivedWith: $with?->isFromDonna() ? $with->inReplyTo : $with,
                arrivedAs: $arrival === null ? null : FileRole::from($arrival->role),
                joshChanges: $file->trashed() ? null : $this->joshChanges($file, $lastSawAt),
            );
        })->values();
    }

    /**
     * When Donna last had the conversation in front of her: when her most
     * recent reply before this message was started.
     */
    private function lastSawAt(Message $message): ?CarbonInterface
    {
        return Message::query()
            ->where('author', MessageAuthor::Donna)
            ->where('id', '<', $message->id)
            ->latest('id')
            ->value('created_at');
    }

    /**
     * What Josh changed by hand since Donna last saw the file: a diff when
     * it is small enough to show, otherwise the sections that changed.
     *
     * The version she last saw is the newest one she wrote herself or that
     * existed when she last replied. Everything after it is Josh's.
     */
    private function joshChanges(File $file, ?CarbonInterface $lastSawAt): ?string
    {
        if (! $file->kind->isEditable() || $file->currentVersion === null) {
            return null;
        }

        $seen = $file->versions()
            ->where(fn ($query) => $query
                ->where('author', MessageAuthor::Donna)
                ->when($lastSawAt !== null, fn ($query) => $query->orWhere('created_at', '<=', $lastSawAt)))
            ->orderByDesc('number')
            ->first();

        if ($seen === null || $seen->number >= $file->currentVersion->number) {
            return null;
        }

        $now = $file->currentVersion()->value('body');
        $heading = "Josh has edited it by hand since you last saw it (version {$seen->number} → {$file->currentVersion->number}).";
        $diff = LineDiff::between($seen->body, $now, config('donna.files.diff_cells'));

        if ($diff !== null && Tokens::estimate($diff) <= config('donna.files.diff_tokens')) {
            return "{$heading} What he changed:\n{$diff}";
        }

        return "{$heading} Too much changed to show line by line. ".$this->changedSections($seen->body, $now).' Read the file before you edit it.';
    }

    private function changedSections(string $before, string $after): string
    {
        $sections = fn (string $text): Collection => collect(Markdown::pieces($text))->mapWithKeys(fn (array $piece): array => [
            $piece['path'] ?? '(the opening, before any heading)' => hash('sha256', implode("\n", array_slice(Markdown::lines($text), $piece['from'] - 1, $piece['to'] - $piece['from'] + 1))),
        ]);

        [$old, $new] = [$sections($before), $sections($after)];

        $parts = array_filter([
            'Sections changed' => $new->filter(fn (string $hash, string $path): bool => $old->has($path) && $old[$path] !== $hash)->keys(),
            'added' => $new->keys()->diff($old->keys()),
            'removed' => $old->keys()->diff($new->keys()),
        ], fn (Collection $paths): bool => $paths->isNotEmpty());

        return collect($parts)->map(fn (Collection $paths, string $label): string => "{$label}: ".$paths->take(30)->implode('; '))->implode('. ').'.';
    }

    private function text(File $file): AttachedFile
    {
        $body = $file->body() ?? '';

        if (Tokens::estimate($body) <= config('donna.files.inline_tokens')) {
            return new AttachedFile($file, 'read in full', implode("\n", FileText::lines($file, $body)));
        }

        $outline = FileText::outline($file, $body);

        return new AttachedFile($file, $outline['given'], $outline['text']);
    }

    /**
     * @param  string  $given  What to say when it is shown.
     * @param  string|null  $otherwise  What to say when it can't be, before the reason.
     */
    private function picture(File $file, string $given, ?string $otherwise = null): AttachedFile
    {
        $cannot = $this->shown->cannotShow($file);

        return $cannot === null
            ? new AttachedFile($file, $given, attachment: $this->shown->attachment($file))
            : new AttachedFile($file, trim(($otherwise ?? 'not shown to you').'; '.rtrim($cannot, '.'), '; '));
    }

    private function pages(File $file): string
    {
        $pages = $file->meta['pages'] ?? 0;

        return number_format($pages).' '.($pages === 1 ? 'page' : 'pages');
    }
}
