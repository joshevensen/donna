<?php

namespace App\Files;

use App\Enums\FileExtraction;
use App\Enums\FileKind;
use App\Models\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\File as Attachment;
use Laravel\Ai\Files\Image;

/**
 * Turns an image or a PDF into something the model can be shown: the bytes
 * from storage, as an attachment on a prompt. Nothing is stored; it is read
 * each time she looks.
 */
class ModelFile
{
    /**
     * Why this file can't be shown to the model, or null when it can.
     */
    public function cannotShow(File $file): ?string
    {
        if ($file->kind === FileKind::Image) {
            // The smaller copy made on upload always fits. Without one, the original has to.
            $tooLarge = $file->previewPath() === null && (
                $file->size_bytes > config('donna.files.model_image_bytes')
                || max($file->meta['width'] ?? 0, $file->meta['height'] ?? 0) > config('donna.files.model_image_pixels')
            );

            return $tooLarge
                ? "{$file->name} is too large to look at (".Number::fileSize($file->size_bytes, maxPrecision: 1).', '.($file->meta['width'] ?? '?').'×'.($file->meta['height'] ?? '?').').'
                : null;
        }

        if ($file->kind !== FileKind::Pdf) {
            return "{$file->name} is a {$file->kind->label()} file: it has text, not pictures. Use read_file.";
        }

        $pages = $file->meta['pages'] ?? null;

        return match (true) {
            $file->extraction === FileExtraction::Failed => "{$file->name} couldn't be read when it arrived, so it can't be shown either.",
            $pages !== null && $pages > config('donna.files.model_pdf_pages') => "{$file->name} has {$pages} pages, too many to look at.",
            default => null,
        };
    }

    /**
     * As many of the attachments as one request can carry, dropping the
     * earliest when they are too much together: the last ones are what she
     * just asked to see.
     *
     * @param  list<Attachment>  $attachments
     * @return list<Attachment>
     */
    public function fitting(array $attachments): array
    {
        $room = config('donna.files.model_request_bytes');
        $kept = [];

        foreach (array_reverse($attachments) as $attachment) {
            $room -= $this->weight($attachment);

            if ($room < 0) {
                break;
            }

            array_unshift($kept, $attachment);
        }

        return $kept;
    }

    /**
     * How much of a request an attachment takes: it travels as base64.
     */
    public function weight(Attachment $attachment): int
    {
        return strlen($attachment->base64 ?? '');
    }

    /**
     * The file as an attachment. Images go as the smaller copy made when
     * they arrived, which is all the model reads of a photo anyway.
     */
    public function attachment(File $file): Attachment
    {
        if ($file->kind === FileKind::Image) {
            $preview = $file->previewPath();

            return Image::fromBase64(
                base64_encode((string) Storage::get($preview ?? $file->storage_path)),
                $preview !== null ? 'image/jpeg' : $file->mime,
            )->as($file->name);
        }

        return Document::fromBase64(base64_encode((string) Storage::get($file->storage_path)), 'application/pdf')->as($file->name);
    }
}
