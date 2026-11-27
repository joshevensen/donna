<?php

namespace App\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent on every sign-in, so a sign-in that wasn't Josh is known within
 * minutes and can be undone from the email itself.
 */
class NewSignInNotification extends Notification
{
    /**
     * @param  string  $device  The browser and device, e.g. "Safari on iPhone".
     * @param  string  $revokeUrl  The page whose one button signs every device out.
     */
    public function __construct(
        public string $device,
        public ?string $ip,
        public CarbonInterface $at,
        public string $revokeUrl,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $when = $this->at->copy()->timezone(config('donna.timezone'))->format('l, F j \a\t g:i A');
        $days = config('donna.login.revoke_link_days');

        return (new MailMessage)
            ->subject('New sign-in to Donna')
            ->greeting('New sign-in')
            ->line("**{$this->device}** signed in to Donna on {$when}".($this->ip !== null ? ", from {$this->ip}." : '.'))
            ->line("If that was you, there's nothing to do.")
            ->action('Sign out everywhere', $this->revokeUrl)
            ->line("If it wasn't, press the button. It signs every device out, and works for {$days} days.")
            ->salutation('Donna');
    }
}
