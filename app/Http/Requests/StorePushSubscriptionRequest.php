<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * A browser's PushSubscription, as its `toJSON()` gives it.
 */
class StorePushSubscriptionRequest extends FormRequest
{
    /**
     * @return array<string, list<ValidationRule|Closure|string>>
     */
    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'string', 'url:https', 'max:1024', $this->isAPushService(...)],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'content_encoding' => ['nullable', Rule::in(['aesgcm', 'aes128gcm'])],
        ];
    }

    /**
     * The server will post to this address, so it has to belong to a push
     * service, not to wherever a stolen session would like requests sent.
     */
    private function isAPushService(string $attribute, mixed $value, Closure $fail): void
    {
        $host = Str::lower((string) parse_url((string) $value, PHP_URL_HOST));

        $known = collect(config('donna.push.services'))
            ->contains(fn (string $service): bool => $host === $service || str_ends_with($host, ".{$service}"));

        if (! $known) {
            $fail("This browser's push service isn't one Donna knows.");
        }
    }
}
