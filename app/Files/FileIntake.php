<?php

namespace App\Files;

use App\Enums\FileExtraction;
use App\Enums\FileKind;
use App\Enums\MessageAuthor;
use App\Files\Pdf\PdfTextExtractor;
use App\Models\File;
use App\Models\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * How a file comes to exist: Josh uploads one, or Donna writes one. Either
 * way it starts as a working file, and what arrived is its first version.
 */
class FileIntake
{
    public function __construct(
        private Sniffer $sniffer,
        private PdfTextExtractor $pdfs,
        private ImagePreview $previews,
        private FileWriter $writer,
    ) {}

    /**
     * Take in an upload: work out what it is from its content, store it, and
     * read its text. The name is only ever a label; where the bytes go is
     * generated here.
     *
     * @throws UnsupportedFile
     */
    public function upload(string $bytes, string $name): File
    {
        $name = $this->cleanName($name);
        $sniffed = $this->sniffer->sniff($bytes, $name);

        return match ($sniffed->kind) {
            FileKind::Pdf => $this->pdf($bytes, $name, $sniffed),
            FileKind::Image => $this->image($bytes, $name, $sniffed),
            default => $this->text($name, $sniffed),
        };
    }

    /**
     * A markdown file Donna wrote.
     *
     * @param  Message|null  $reply  The reply she wrote it in.
     */
    public function write(string $name, string $content, ?Message $reply = null, ?string $operationHash = null): File
    {
        $name = $this->cleanName($name);

        if (! in_array(mb_strtolower(pathinfo($name, PATHINFO_EXTENSION)), ['md', 'markdown', 'txt'], true)) {
            $name .= '.md';
        }

        return DB::transaction(function () use ($name, $content, $reply, $operationHash): File {
            $file = File::create([
                'name' => $name,
                'kind' => FileKind::Markdown,
                'mime' => 'text/markdown',
                'size_bytes' => strlen($content),
            ]);

            $this->writer->write($file, $content, MessageAuthor::Donna, 'Created', $reply, $operationHash);

            return $file;
        });
    }

    private function text(string $name, Sniffed $sniffed): File
    {
        return DB::transaction(function () use ($name, $sniffed): File {
            $file = File::create([
                'name' => $name,
                'kind' => $sniffed->kind,
                'mime' => $sniffed->mime,
                'size_bytes' => strlen($sniffed->text),
                'meta' => $sniffed->kind === FileKind::Csv ? $this->csvMeta($sniffed->text) : null,
            ]);

            $this->writer->write($file, $sniffed->text, MessageAuthor::Josh, 'Uploaded');

            return $file;
        });
    }

    /**
     * The original goes to storage untouched. Its text, when it has any,
     * becomes the file's one version, pages separated by form feeds.
     */
    private function pdf(string $bytes, string $name, Sniffed $sniffed): File
    {
        $path = $this->store($bytes, 'pdf');

        try {
            $pages = $this->pdfs->pages($bytes);
        } catch (Throwable $exception) {
            report($exception);

            return File::create([
                'name' => $name,
                'kind' => FileKind::Pdf,
                'mime' => $sniffed->mime,
                'size_bytes' => strlen($bytes),
                'storage_path' => $path,
                'extraction' => FileExtraction::Failed,
                'extraction_error' => "The PDF's text could not be read. It may be damaged or password-protected.",
            ]);
        }

        $pages = array_map(fn (string $page): string => Encoding::toUtf8($page) ?? '', $pages);
        $text = implode("\f", $pages);
        $letters = mb_strlen(preg_replace('/\s+/u', '', $text));
        $hasText = count($pages) > 0 && $letters >= count($pages) * config('donna.files.pdf_text_per_page');

        // In a PDF that does have text, the pages that have none: scanned inserts, or pages that are one picture.
        $blank = $hasText ? array_values(array_map(
            fn (int $index): int => $index + 1,
            array_keys(array_filter($pages, fn (string $page): bool => trim($page) === '')),
        )) : [];

        return DB::transaction(function () use ($bytes, $name, $sniffed, $path, $pages, $text, $hasText, $blank): File {
            $file = File::create([
                'name' => $name,
                'kind' => FileKind::Pdf,
                'mime' => $sniffed->mime,
                'size_bytes' => strlen($bytes),
                'storage_path' => $path,
                'meta' => [
                    'pages' => count($pages),
                    // A scan has no words, or only a stamp the scanner added.
                    'has_text' => $hasText,
                    ...($blank === [] ? [] : ['blank_pages' => array_slice($blank, 0, 200)]),
                ],
            ]);

            $this->writer->write($file, $text, MessageAuthor::Josh, 'Uploaded');

            return $file;
        });
    }

    /**
     * @throws UnsupportedFile
     */
    private function image(string $bytes, string $name, Sniffed $sniffed): File
    {
        $size = @getimagesizefromstring($bytes);

        if ($size === false) {
            throw new UnsupportedFile("Unsupported. The image couldn't be read");
        }

        [$width, $height] = $size;

        $path = $this->store($bytes, Str::after($sniffed->mime, 'image/'));
        $preview = $this->previews->make($bytes, $sniffed->mime, $width, $height);

        return File::create([
            'name' => $name,
            'kind' => FileKind::Image,
            'mime' => $sniffed->mime,
            'size_bytes' => strlen($bytes),
            'storage_path' => $path,
            'meta' => array_filter([
                'width' => $width,
                'height' => $height,
                'preview_path' => $preview === null ? null : $this->store($preview, 'preview.jpg'),
            ]),
        ]);
    }

    /**
     * @return array{rows: int}
     */
    private function csvMeta(string $text): array
    {
        return ['rows' => Csv::parse($text, 0)->totalRows];
    }

    private function store(string $bytes, string $extension): string
    {
        $path = 'files/'.Str::lower((string) Str::ulid()).'.'.$extension;

        if (! Storage::put($path, $bytes)) {
            throw new RuntimeException('The file could not be stored. Check the file storage settings.');
        }

        return $path;
    }

    /**
     * The name as a label: no folders, no control characters, not empty, not endless.
     */
    private function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '');

        return $name === '' ? 'untitled' : Str::limit($name, 200, '');
    }
}
