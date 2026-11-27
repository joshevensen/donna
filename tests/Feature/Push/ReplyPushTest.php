<?php

use App\Ai\Agents\Donna;
use App\Jobs\EmbedMessage;
use App\Jobs\GenerateReply;
use App\Jobs\TagTopics;
use App\Models\Message;
use App\Models\PushSubscription;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\travel;

/**
 * Have Donna finish a reply to Josh, as the queue worker would.
 */
function donnaReplies(string $body): Message
{
    Queue::fake([EmbedMessage::class, TagTopics::class]);
    fakeEmbeddings();
    Donna::fake([$body]);

    $reply = pendingReplyTo('Did the order ship?');

    GenerateReply::dispatchSync($reply);

    return $reply->refresh();
}

it('pushes a finished reply to every subscribed device when the app is not open', function () {
    ['user' => $user] = donnaExists();
    [$phone, $mac] = PushSubscription::factory()->count(2)->for($user)->create()->all();
    $sent = fakePushServices();

    $reply = donnaReplies("It shipped **Tuesday**, tracking `1Z999`.\n---\nWant me to watch for the delivery?");

    expect($sent->pluck('endpoint')->all())->toBe([$phone->endpoint, $mac->endpoint])
        ->and($sent->first()['payload'])->toMatchArray([
            'title' => 'Donna',
            'body' => 'It shipped Tuesday, tracking 1Z999.',
            'tag' => "reply-{$reply->id}",
        ])
        ->and($sent->first()['options']['topic'])->toBe("reply-{$reply->id}");
});

it('cuts a long reply down to fit a notification', function () {
    ['user' => $user] = donnaExists();
    PushSubscription::factory()->for($user)->create();
    $sent = fakePushServices();

    donnaReplies(str_repeat('A long answer. ', 40));

    expect(mb_strlen($sent->sole()['payload']['body']))->toBe(141)
        ->and($sent->sole()['payload']['body'])->toEndWith('…');
});

it('sends no push while the app is open and polling, and sends again once it stops', function (Closure $poll) {
    ['user' => $user] = donnaExists();
    PushSubscription::factory()->for($user)->create();
    $sent = fakePushServices();
    actingAs($user);

    $poll(Message::factory()->create());
    donnaReplies('It shipped Tuesday.');

    expect($sent)->toBeEmpty();

    travel(16)->seconds();
    donnaReplies('And it has arrived.');

    expect($sent)->toHaveCount(1);
})->with([
    'a pending reply' => [fn (Message $message) => getJson("/api/v1/messages/{$message->id}")->assertOk()],
    'for anything new' => [fn (Message $message) => getJson("/api/v1/messages?after={$message->id}")->assertOk()],
]);

it('pushes right away once the app says it went to the background', function () {
    ['user' => $user] = donnaExists();
    PushSubscription::factory()->for($user)->create();
    $sent = fakePushServices();
    actingAs($user);
    getJson('/api/v1/messages?after=0')->assertOk();

    deleteJson('/api/v1/presence')->assertNoContent();
    donnaReplies('It shipped Tuesday.');

    expect($sent)->toHaveCount(1);
});

it('does not treat loading history as watching', function () {
    ['user' => $user] = donnaExists();
    PushSubscription::factory()->for($user)->create();
    $sent = fakePushServices();
    actingAs($user);

    getJson('/api/v1/messages')->assertOk();
    donnaReplies('It shipped Tuesday.');

    expect($sent)->toHaveCount(1);
});

it('forgets a device whose push service says it is gone, and keeps the rest', function (int $status) {
    ['user' => $user] = donnaExists();
    [$gone, $alive] = PushSubscription::factory()->count(2)->for($user)->create()->all();
    fakePushServices([$gone->endpoint => $status]);

    donnaReplies('It shipped Tuesday.');

    expect(PushSubscription::sole()->is($alive))->toBeTrue();
})->with([404, 410]);

it('keeps a device when its push service only had a bad moment', function () {
    ['user' => $user] = donnaExists();
    $subscription = PushSubscription::factory()->for($user)->create();
    fakePushServices([$subscription->endpoint => 503]);

    donnaReplies('It shipped Tuesday.');

    expect(PushSubscription::sole()->is($subscription))->toBeTrue();
});

it('still writes the reply, and pushes nothing, when push is not set up', function () {
    ['user' => $user] = donnaExists();
    PushSubscription::factory()->for($user)->create();
    $sent = fakePushServices();
    config(['donna.push.vapid' => ['subject' => null, 'public_key' => null, 'private_key' => null]]);

    $reply = donnaReplies('It shipped Tuesday.');

    expect($sent)->toBeEmpty()
        ->and($reply->body)->toBe('It shipped Tuesday.');
});
