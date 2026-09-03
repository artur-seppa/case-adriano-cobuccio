<?php

use App\Models\User;
use App\Notifications\QueuedResetPassword;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

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
