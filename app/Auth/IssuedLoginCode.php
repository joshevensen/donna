<?php

namespace App\Auth;

use Carbon\CarbonInterface;

/**
 * A sign-in code as it is the one time it exists in the clear: on its way
 * into the email, or printed on the server by `donna:login`.
 */
final readonly class IssuedLoginCode
{
    public function __construct(
        public string $code,
        public string $url,
        public CarbonInterface $expiresAt,
    ) {}
}
