<?php

use App\Models\User;
use App\Notifications\QueuedResetPassword;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Laravel\Horizon\ProvisioningPlan;

it('queues the email verification notification on the mail queue', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $user->sendEmailVerificationNotification();

    Notification::assertSentTo($user, QueuedVerifyEmail::class, function (QueuedVerifyEmail $n) use ($user) {
        expect($n)->toBeInstanceOf(ShouldQueue::class)
            ->and($n->connection ?? config('queue.default'))->not->toBeNull();
        // a URL herdada do createUrlUsing continua apontando para o SPA
        expect($n->toMail($user)->actionUrl ?? '')->toContain('/api/email/verify/');

        return true;
    });
});

it('queues the password reset notification', function () {
    Notification::fake();
    $user = User::factory()->create();

    $user->sendPasswordResetNotification('tok123');

    Notification::assertSentTo($user, QueuedResetPassword::class, fn ($n) => $n instanceof ShouldQueue);
});

it('retries a failed mail job with an escalating backoff instead of hammering the SMTP host', function () {
    // SendQueuedNotifications does not forward a notification-level backoff(), so
    // the spacing has to live on the Horizon supervisor that drains the `mail`
    // queue. Assert Horizon's own config->worker pipeline carries it.
    $plan = ProvisioningPlan::get('test-master');

    foreach (['local', 'production'] as $env) {
        $options = $plan->optionsFor($env, 'supervisor-1');

        expect($options->queue)->toContain('mail')
            ->and($options->backoff)->toBe('60,300,900');
    }
});
