<?php

namespace App\Enums;

/**
 * Things only the web app can do, on this device, when Josh taps a button
 * Donna offers: she can suggest them, never do them.
 */
enum AppActionType: string
{
    case SignOut = 'sign_out';
    case SignOutEverywhere = 'sign_out_everywhere';
    case EnableNotifications = 'enable_notifications';
    case DisableNotifications = 'disable_notifications';

    /**
     * The words on the button.
     */
    public function label(): string
    {
        return match ($this) {
            self::SignOut => 'Sign out',
            self::SignOutEverywhere => 'Sign out everywhere',
            self::EnableNotifications => 'Turn on notifications',
            self::DisableNotifications => 'Turn off notifications',
        };
    }
}
