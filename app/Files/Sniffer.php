<?php

namespace App\Files;

use App\Enums\FileKind;
use finfo;

/**
 * Decides what an uploaded file is from its content. The extension and the
 * type the browser claimed are never trusted: a name only settles which kind
 * of text a text file is, and a name that promises something the content
 * isn't gets the file refused.
 */
class Sniffer
{
    /**
     * The image types Donna can be shown.
     */
    public const array IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    private const string UNSUPPORTED = 'Unsupported. Text, PDF and images only';

    /**
     * @throws UnsupportedFile
     */
    public function sniff(string $bytes, string $name): Sniffed
    {
        if ($bytes === '') {
            throw new UnsupportedFile('Unsupported. The file is empty');
        }

        $sniffed = $this->fromContent($bytes, $name);
        $claimed = $this->claimedBy($name);

        if ($claimed !== null && $claimed !== $this->family($sniffed->kind)) {
            throw new UnsupportedFile("Unsupported. The file isn't what its name says");
        }

        return $sniffed;
    }

    /**
     * @throws UnsupportedFile
     */
    private function fromContent(string $bytes, string $name): Sniffed
    {
        $type = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'application/octet-stream';

        if ($type === 'application/pdf') {
            return new Sniffed(FileKind::Pdf, $type);
        }

        if (in_array($type, self::IMAGE_TYPES, true)) {
            return new Sniffed(FileKind::Image, $type);
        }

        $text = Encoding::toUtf8($bytes);

        if ($text === null) {
            throw new UnsupportedFile(self::UNSUPPORTED);
        }

        return match ($this->extension($name)) {
            'md', 'markdown', 'txt' => new Sniffed(FileKind::Markdown, 'text/markdown', $text),
            'csv' => new Sniffed(FileKind::Csv, 'text/csv', $text),
            default => new Sniffed(FileKind::Text, 'text/plain', $text),
        };
    }

    /**
     * What the name promises, for the extensions that promise something.
     */
    private function claimedBy(string $name): ?string
    {
        return match ($this->extension($name)) {
            'pdf' => 'pdf',
            'jpg', 'jpeg', 'png', 'webp', 'gif' => 'image',
            'md', 'markdown', 'txt', 'csv' => 'text',
            default => null,
        };
    }

    private function family(FileKind $kind): string
    {
        return match ($kind) {
            FileKind::Pdf => 'pdf',
            FileKind::Image => 'image',
            default => 'text',
        };
    }

    private function extension(string $name): string
    {
        return mb_strtolower(pathinfo($name, PATHINFO_EXTENSION));
    }
}
