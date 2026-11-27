<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMessageEvaluationRequest;
use App\Http\Resources\EvalExampleResource;
use App\Models\EvalExample;
use App\Models\Message;
use Illuminate\Validation\ValidationException;

class MessageEvaluationController extends Controller
{
    /**
     * Flag one of Donna's replies as a good or bad example. Flagging the
     * same reply again changes the verdict rather than adding a second one.
     */
    public function store(StoreMessageEvaluationRequest $request, Message $message): EvalExampleResource
    {
        if (! $message->isFromDonna() || $message->isPending()) {
            throw ValidationException::withMessages([
                'message' => 'Only a reply Donna has finished can be flagged.',
            ]);
        }

        $example = EvalExample::updateOrCreate(
            ['message_id' => $message->id],
            $request->safe()->only(['rating', 'note']) + ['note' => null],
        );

        return new EvalExampleResource($example);
    }
}
