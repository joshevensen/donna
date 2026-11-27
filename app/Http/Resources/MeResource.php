<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What the web app needs to know about the one user and this server.
 *
 * @mixin User
 */
class MeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $publicKey = config('donna.push.vapid.public_key');
        $configured = filled($publicKey) && filled(config('donna.push.vapid.private_key'));

        return [
            'name' => $this->name,
            'timezone' => config('donna.timezone'),
            'push' => [
                // Whether this device is subscribed is the browser's to say, not the server's.
                'configured' => $configured,
                'public_key' => $configured ? $publicKey : null,
            ],
        ];
    }
}
