<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MessageAuthor;
use App\Files\FileWriter;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveFileRequest;
use App\Http\Resources\FileResource;
use App\Models\File;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class FileBodyController extends Controller
{
    /**
     * Josh saves his own edit as a new version. If the file has changed
     * since the version he started from, nothing is saved and he gets the
     * current file back with a 409, to choose between the two.
     */
    public function update(SaveFileRequest $request, File $file, FileWriter $writer): JsonResponse
    {
        abort_unless($file->kind->isEditable(), 422, 'Only markdown and plain text files can be edited here.');

        $body = $request->validated('body');

        $saved = DB::transaction(function () use ($request, $file, $writer, $body): bool {
            // Locked, so an edit of Donna's can't land between the check and the save.
            $current = File::query()->lockForUpdate()->findOrFail($file->id)->currentVersion;

            if ($current?->number !== $request->integer('base_version')) {
                return false;
            }

            if ($current->body !== $body) {
                $writer->write($file, $body, MessageAuthor::Josh, 'Edited by Josh');
            }

            return true;
        });

        $file->refresh();

        return (new FileResource($file))->withContent()
            ->additional($saved ? [] : ['message' => 'The file changed while you were editing, so it wasn\'t saved.'])
            ->response()
            ->setStatusCode($saved ? 200 : 409);
    }
}
