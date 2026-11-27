<?php

namespace App\Notifications;

use App\Conversation\Bubbles;
use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Donna answered while the app wasn't open: a push, like a text arriving.
 * Queued on its own so a push service being slow or down never touches the
 * reply, which is already saved.
 */
class ReplyReady extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60];

    public function __construct(public Message $reply) {}

    /**
     * @return list<class-string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        // The same tag (on the device) and topic (at the push service) for a reply, so a
        // second push for it replaces the first instead of stacking up.
        $tag = "reply-{$this->reply->id}";

        return (new WebPushMessage)
            ->title('Donna')
            ->body($this->preview())
            ->icon('/icon-192.png')
            ->tag($tag)
            ->data(['url' => '/'])
            ->options(['TTL' => 86400, 'topic' => $tag]);
    }

    /**
     * The reply's first bubble as a line of plain text, cut to fit a lock screen.
     */
    private function preview(): string
    {
        $first = Bubbles::split($this->reply->body)[0] ?? '';

        return Str::limit(
            Str::squish(str_replace(['```', '**', '`'], '', $first)),
            config('donna.push.body_characters'),
            '…',
        );
    }
}
