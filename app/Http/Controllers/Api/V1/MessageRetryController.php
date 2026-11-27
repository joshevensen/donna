<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MessageAuthor;
use App\Enums\MessageStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use App\Jobs\GenerateReply;
use App\Models\Message;
use Illuminate\Http\JsonResponse;

class MessageRetryController extends Controller
{
    /**
     * Have Donna try again at a reply she couldn't write. Only a failed reply
     * can be retried, and only once at a time.
     */
    public function store(Message $message): JsonResponse
    {
        $retrying = Message::query()
            ->whereKey($message->id)
            ->where('author', MessageAuthor::Donna)
            ->where('status', MessageStatus::Failed)
            ->update(['status' => MessageStatus::Pending, 'error' => null]);

        abort_if($retrying === 0, 409, 'Only a reply that failed can be retried.');

        $message->refresh();

        GenerateReply::dispatch($message);

        return (new MessageResource($message))->response()->setStatusCode(202);
    }
}
