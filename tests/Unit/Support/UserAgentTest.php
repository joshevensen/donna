<?php

use App\Support\UserAgent;

// The new-sign-in email is only useful if it names the right device. Browsers
// borrow each other's names, so the order the patterns are tried in matters.
it('names the browser and device behind a User-Agent', function (?string $userAgent, string $described) {
    expect(UserAgent::describe($userAgent))->toBe($described);
})->with([
    'Safari on an iPhone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 26_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.1 Mobile/15E148 Safari/604.1', 'Safari on iPhone'],
    'Chrome on an iPhone, which also says Safari' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 26_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/142.0.0.0 Mobile/15E148 Safari/604.1', 'Chrome on iPhone'],
    'Chrome on a Mac' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', 'Chrome on Mac'],
    'Edge on Windows, which also says Chrome' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36 Edg/142.0.0.0', 'Edge on Windows'],
    'Firefox on Linux' => ['Mozilla/5.0 (X11; Linux x86_64; rv:145.0) Gecko/20100101 Firefox/145.0', 'Firefox on Linux'],
    'something that is not a browser' => ['curl/8.5.0', 'An unknown device'],
    'no header at all' => [null, 'An unknown device'],
]);
