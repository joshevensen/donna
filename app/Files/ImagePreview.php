<?php

namespace App\Files;

use Throwable;

/**
 * Makes a smaller copy of a photo when it arrives: turned the right way up
 * and no longer than the model would read it at anyway. The copy is what the
 * thread, the viewer and the model get; the original is kept untouched for
 * "Open original". A phone photo can be bigger than a model accepts, so
 * without this a large one could never be shown to Donna.
 */
class ImagePreview
{
    /**
     * A JPEG copy of the image, or null when none is needed or one can't be made.
     */
    public function make(string $bytes, string $mime, int $width, int $height): ?string
    {
        // An animated GIF would lose its frames, and GIFs are small anyway.
        if (! function_exists('imagecreatefromstring') || $mime === 'image/gif') {
            return null;
        }

        $orientation = $this->orientation($bytes, $mime);
        $longEdge = config('donna.files.preview.long_edge');

        if (max($width, $height) <= $longEdge && strlen($bytes) <= config('donna.files.preview.skip_under_bytes') && $orientation <= 1) {
            return null;
        }

        // Decoding takes four bytes a pixel; a huge image isn't worth running out of memory for.
        if ($width * $height > config('donna.files.preview.max_pixels')) {
            return null;
        }

        try {
            $image = @imagecreatefromstring($bytes);

            if ($image === false) {
                return null;
            }

            $scale = min(1, $longEdge / max(imagesx($image), imagesy($image)));
            $target = imagecreatetruecolor((int) max(1, round(imagesx($image) * $scale)), (int) max(1, round(imagesy($image) * $scale)));

            // Transparent pixels would turn black in a JPEG; put them on white.
            imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
            imagecopyresampled($target, $image, 0, 0, 0, 0, imagesx($target), imagesy($target), imagesx($image), imagesy($image));

            // Turned after it is made small: turning the full-size photo would take as much memory again.
            unset($image);
            $target = $this->upright($target, $orientation);

            ob_start();
            imagejpeg($target, null, config('donna.files.preview.quality'));

            return ob_get_clean() ?: null;
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * The EXIF orientation of a JPEG (1 is upright), which phones use instead of turning the pixels.
     */
    private function orientation(string $bytes, string $mime): int
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return 1;
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $bytes);
        rewind($stream);

        $exif = @exif_read_data($stream);

        fclose($stream);

        return (int) ($exif['Orientation'] ?? 1);
    }

    private function upright(\GdImage $image, int $orientation): \GdImage
    {
        $degrees = match ($orientation) {
            3, 4 => 180,
            5, 6 => 270,
            7, 8 => 90,
            default => 0,
        };

        if ($degrees !== 0) {
            $image = imagerotate($image, $degrees, 0) ?: $image;
        }

        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        return $image;
    }
}
