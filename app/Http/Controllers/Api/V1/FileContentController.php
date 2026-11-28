<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * A file's bytes, for showing an image, opening a PDF's original, or
 * downloading a text file. Always served by the app to a signed-in session,
 * never as a public storage link, and only ever as the type the content was
 * found to be when it arrived.
 */
class FileContentController extends Controller
{
    public function show(Request $request, File $file): Response
    {
        if (! $file->kind->isStored()) {
            return $this->text($file);
        }

        // Images have a smaller copy for the thread and the viewer; the original is for "Open original".
        $preview = $request->query('variant') === 'preview' ? $file->previewPath() : null;
        $path = $preview ?? $file->storage_path;

        abort_unless($path !== null && Storage::exists($path), 404);

        return Storage::response($path, $file->name, [
            'Content-Type' => $preview !== null ? 'image/jpeg' : $file->mime,
            'X-Content-Type-Options' => 'nosniff',
            // What was uploaded never changes, so the browser can keep it. Only this browser: it is Josh's.
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }

    /**
     * A text file is only ever a download: never a page the browser would run.
     */
    private function text(File $file): Response
    {
        $body = $file->body();

        abort_if($body === null, 404);

        return response()->streamDownload(
            function () use ($body): void {
                echo $body;
            },
            $file->name,
            [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
        );
    }
}
