<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MessageAuthor;
use App\Enums\MessageStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexMessagesRequest;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Resources\MessageResource;
use App\Jobs\GenerateReply;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    /**
     * The conversation, newest first. Pass the returned `next_before` as
     * `before` to page further back.
     */
    public function index(IndexMessagesRequest $request): AnonymousResourceCollection
    {
        $limit = $request->integer('limit', 20);

        $messages = Message::query()
            ->when($request->has('before'), fn ($query) => $query->where('id', '<', $request->integer('before')))
            ->latest('id')
            ->limit($limit + 1)
            ->get();

        $page = $messages->take($limit);

        return MessageResource::collection($page)->additional([
            'meta' => ['next_before' => $messages->count() > $limit ? $page->last()->id : null],
        ]);
    }

    /**
     * Save Josh's message and start Donna's reply. Both exist before this
     * returns, so nothing is lost if the client goes away.
     */
    public function store(StoreMessageRequest $request): JsonResponse
    {
        [$message, $reply] = DB::transaction(function () use ($request): array {
            $message = Message::create([
                'author' => MessageAuthor::Josh,
                'body' => $request->validated('body'),
                'source' => $request->validated('source'),
                'status' => MessageStatus::Complete,
            ]);

            $reply = Message::create([
                'author' => MessageAuthor::Donna,
                'body' => '',
                'source' => $message->source,
                'status' => MessageStatus::Pending,
                'in_reply_to_id' => $message->id,
            ]);

            return [$message, $reply];
        });

        GenerateReply::dispatch($reply);

        return response()->json([
            'data' => [
                'message' => new MessageResource($message),
                'reply' => new MessageResource($reply),
            ],
        ], 202);
    }

    /**
     * One message. The client polls Donna's pending reply here.
     */
    public function show(Message $message): MessageResource
    {
        return new MessageResource($message);
    }
}
