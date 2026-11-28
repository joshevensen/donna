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
use App\Push\Presence;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    /**
     * The conversation. Newest first by default: pass the returned
     * `next_before` as `before` to page further back. With `after`, only
     * what is newer than that message, oldest first: how a client that is
     * already showing the conversation picks up what it hasn't seen. Either way
     * `meta.failed_jobs` says how many background jobs have failed.
     */
    public function index(IndexMessagesRequest $request, Presence $presence): AnonymousResourceCollection
    {
        $limit = $request->integer('limit', 20);
        $newer = $request->has('after');

        // Asking for what's new is what an open app does: Josh is watching.
        if ($newer) {
            $presence->seen($request->user());
        }

        $messages = Message::query()
            ->when($newer, fn ($query) => $query->where('id', '>', $request->integer('after'))->oldest('id'))
            ->unless($newer, fn ($query) => $query
                ->when($request->has('before'), fn ($query) => $query->where('id', '<', $request->integer('before')))
                ->latest('id'))
            ->limit($limit + 1)
            ->get();

        $page = $messages->take($limit);
        $next = $messages->count() > $limit ? $page->last()->id : null;

        return MessageResource::collection($page)->additional([
            'meta' => [
                ...($newer ? ['next_after' => $next] : ['next_before' => $next]),
                // Jobs that gave up after their retries: the app shows a strip until they are cleared.
                'failed_jobs' => DB::table('failed_jobs')->count(),
            ],
        ]);
    }

    /**
     * Save Josh's message and start Donna's reply. Both exist before this
     * returns, so nothing is lost if the client goes away.
     *
     * A client that isn't sure its send arrived sends it again with the same
     * `Idempotency-Key` and gets the original pair back, never a second one.
     */
    public function store(StoreMessageRequest $request): JsonResponse
    {
        $key = $request->validated('idempotency_key');

        if ($key !== null && ($sent = $this->alreadySent($key)) !== null) {
            return $this->accepted($sent, $sent->reply);
        }

        try {
            [$message, $reply] = DB::transaction(function () use ($request, $key): array {
                $message = Message::create([
                    'author' => MessageAuthor::Josh,
                    'body' => $request->validated('body'),
                    'source' => $request->validated('source'),
                    'status' => MessageStatus::Complete,
                    'client_key' => $key,
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
        } catch (UniqueConstraintViolationException $exception) {
            // The same send arrived twice at once, and the other one won.
            $sent = $key !== null ? $this->alreadySent($key) : null;

            return $sent !== null ? $this->accepted($sent, $sent->reply) : throw $exception;
        }

        GenerateReply::dispatch($reply);

        return $this->accepted($message, $reply);
    }

    /**
     * One message. The client polls Donna's pending reply here.
     */
    public function show(Request $request, Message $message, Presence $presence): MessageResource
    {
        $presence->seen($request->user());

        return new MessageResource($message);
    }

    private function alreadySent(string $key): ?Message
    {
        return Message::query()->with('reply')->where('client_key', $key)->first();
    }

    private function accepted(Message $message, ?Message $reply): JsonResponse
    {
        return response()->json([
            'data' => [
                'message' => new MessageResource($message),
                'reply' => $reply === null ? null : new MessageResource($reply),
            ],
        ], 202);
    }
}
