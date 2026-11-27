<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DestroyPushSubscriptionRequest;
use App\Http\Requests\StorePushSubscriptionRequest;
use App\Models\PushSubscription;
use Illuminate\Http\Response;

class PushSubscriptionController extends Controller
{
    /**
     * Turn notifications on for the device making the request. A device
     * that is already subscribed just has its keys and last-seen time updated.
     */
    public function store(StorePushSubscriptionRequest $request): Response
    {
        PushSubscription::updateOrCreate(
            ['endpoint' => $request->validated('endpoint')],
            [
                'user_id' => $request->user()->id,
                'public_key' => $request->validated('keys.p256dh'),
                'auth_token' => $request->validated('keys.auth'),
                'content_encoding' => $request->validated('content_encoding') ?? 'aes128gcm',
                'user_agent' => $request->userAgent(),
                'last_used_at' => now(),
            ],
        );

        return response()->noContent();
    }

    /**
     * Turn notifications off for the device making the request.
     */
    public function destroy(DestroyPushSubscriptionRequest $request): Response
    {
        $request->user()->pushSubscriptions()->where('endpoint', $request->validated('endpoint'))->delete();

        return response()->noContent();
    }
}
