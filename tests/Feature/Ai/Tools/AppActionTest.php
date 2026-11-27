<?php

use App\Enums\MessageAuthor;
use App\Models\Message;
use App\Models\PushSubscription;
use Illuminate\Support\Facades\DB;

// Graduated Autonomy: Donna can offer these, and only Josh's tap does them.
it('puts a button under the reply and does nothing itself', function () {
    ['user' => $user] = donnaExists();
    fakeEmbeddings();
    PushSubscription::factory()->for($user)->create();
    DB::table('sessions')->insert(['id' => 'this-device', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->getTimestamp()]);

    donnaCalls([['app_action', ['type' => 'sign_out_everywhere']]], said: 'Sign me out everywhere.');

    expect(Message::where('author', MessageAuthor::Donna)->sole()->meta['actions'])->toBe([['type' => 'sign_out_everywhere', 'label' => 'Sign out everywhere']])
        ->and(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(1)
        ->and(PushSubscription::count())->toBe(1);
});

it('offers a button once however many times she asks for it in one reply', function () {
    donnaExists();
    fakeEmbeddings();

    donnaCalls([
        ['app_action', ['type' => 'sign_out']],
        ['app_action', ['type' => 'enable_notifications']],
        ['app_action', ['type' => 'sign_out']],
    ]);

    expect(Message::where('author', MessageAuthor::Donna)->sole()->meta['actions'])->toBe([
        ['type' => 'enable_notifications', 'label' => 'Turn on notifications'],
        ['type' => 'sign_out', 'label' => 'Sign out'],
    ]);
});
