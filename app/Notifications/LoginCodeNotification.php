<?php

namespace App\Notifications;

use App\Auth\IssuedLoginCode;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The sign-in email: the code first, because on an iPhone the link opens
 * Safari, not the installed app. Sent right away, never queued, so a long
 * reply on the queue can't hold up signing in.
 */
class LoginCodeNotification extends Notification
{
    public function __construct(public IssuedLoginCode $login) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = config('donna.login.code_minutes');

        return (new MailMessage)
            ->subject("{$this->login->code} is your Donna sign-in code")
            ->greeting('Your sign-in code')
            ->line("**{$this->login->code}**")
            ->line("Type it into Donna. It works once and expires in {$minutes} minutes.")
            ->action('Sign in on this device', $this->login->url)
            ->line('On your iPhone, use the code in the Donna app. The button signs in Safari, not the app on your Home Screen.')
            ->line("If you didn't ask for this, ignore it. Nothing happens without the code.")
            ->salutation('Donna');
    }
}
