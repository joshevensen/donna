<?php

namespace App\Http\Controllers\Api\V1;

use App\Files\FileIntake;
use App\Files\FileLibrary;
use App\Files\UnsupportedFile;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexFilesRequest;
use App\Http\Requests\StoreFileRequest;
use App\Http\Requests\UpdateFileRequest;
use App\Http\Resources\FileResource;
use App\Models\File;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * Josh's files. There is no delete here: deleting is something he asks Donna to do.
 */
class FileController extends Controller
{
    /**
     * Kept files first, then the working files used lately. With `q`, any
     * file whose name contains it, however old.
     */
    public function index(IndexFilesRequest $request, FileLibrary $library): AnonymousResourceCollection
    {
        return FileResource::collection($library->listing($request->validated('q')));
    }

    /**
     * Take in one upload. It belongs to no message until one names it.
     */
    public function store(StoreFileRequest $request, FileIntake $intake): JsonResponse
    {
        $upload = $request->file('file');

        try {
            $file = $intake->upload($upload->getContent(), $upload->getClientOriginalName());
        } catch (UnsupportedFile $exception) {
            throw ValidationException::withMessages(['file' => [$exception->getMessage()]]);
        }

        return (new FileResource($file))->response()->setStatusCode(201);
    }

    public function show(File $file): FileResource
    {
        return (new FileResource($file))->withContent();
    }

    /**
     * The keep switch, and renaming.
     */
    public function update(UpdateFileRequest $request, File $file, FileLibrary $library): FileResource
    {
        if ($request->has('name')) {
            $library->rename($file, $request->validated('name'));
        }

        if ($request->has('kept')) {
            $library->keep($file, $request->boolean('kept'));
        }

        return (new FileResource($file))->withContent();
    }
}
