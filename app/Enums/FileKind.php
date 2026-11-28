<?php

namespace App\Enums;

/**
 * What a file is, decided from its content when it arrives. Markdown, CSV
 * and text live in Postgres as versions; a PDF keeps its original in storage
 * and its extracted text as one version; an image is only its original.
 */
enum FileKind: string
{
    case Markdown = 'markdown';
    case Csv = 'csv';
    case Text = 'text';
    case Pdf = 'pdf';
    case Image = 'image';

    /**
     * Whether the file has text Donna and the viewer read.
     */
    public function hasBody(): bool
    {
        return $this !== self::Image;
    }

    /**
     * Whether the original bytes are kept in storage instead of the database.
     */
    public function isStored(): bool
    {
        return $this === self::Pdf || $this === self::Image;
    }

    /**
     * Only markdown is written together; everything else is read.
     */
    public function isEditable(): bool
    {
        return $this === self::Markdown;
    }

    public function label(): string
    {
        return match ($this) {
            self::Markdown => 'markdown',
            self::Csv => 'CSV',
            self::Text => 'text',
            self::Pdf => 'PDF',
            self::Image => 'image',
        };
    }
}
