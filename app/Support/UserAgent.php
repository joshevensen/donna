<?php

namespace App\Support;

/**
 * Names the browser and device behind a User-Agent header, for the "new
 * sign-in" email. A few patterns cover what Josh owns; anything else is
 * reported as unknown rather than guessed at.
 */
class UserAgent
{
    /**
     * Checked in order: every Chromium browser also says "Chrome" and
     * "Safari", and Chrome on iOS says "Safari".
     */
    private const array BROWSERS = [
        'Edge' => '/Edg(e|A|iOS)?\//',
        'Firefox' => '/Firefox\/|FxiOS\//',
        'Chrome' => '/Chrome\/|CriOS\//',
        'Safari' => '/Safari\//',
    ];

    /**
     * Checked in order: iPads and Android phones also mention other systems.
     */
    private const array DEVICES = [
        'iPhone' => '/iPhone/',
        'iPad' => '/iPad/',
        'Android' => '/Android/',
        'Mac' => '/Macintosh|Mac OS X/',
        'Windows' => '/Windows/',
        'Linux' => '/Linux|X11/',
    ];

    /**
     * Describe a User-Agent, e.g. "Safari on iPhone".
     */
    public static function describe(?string $userAgent): string
    {
        $userAgent ??= '';

        $browser = self::firstMatch(self::BROWSERS, $userAgent);
        $device = self::firstMatch(self::DEVICES, $userAgent);

        return match (true) {
            $browser !== null && $device !== null => "{$browser} on {$device}",
            $browser !== null => $browser,
            $device !== null => "A browser on {$device}",
            default => 'An unknown device',
        };
    }

    /**
     * @param  array<string, string>  $patterns
     */
    private static function firstMatch(array $patterns, string $userAgent): ?string
    {
        foreach ($patterns as $name => $pattern) {
            if (preg_match($pattern, $userAgent) === 1) {
                return $name;
            }
        }

        return null;
    }
}
