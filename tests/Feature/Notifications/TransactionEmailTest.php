<?php

use App\Domain\Wallet\Actions\DepositFunds;
use App\Domain\Wallet\Actions\ReverseTransaction;
use App\Domain\Wallet\Actions\TransferFunds;
use App\Domain\Wallet\DTOs\DepositData;
use App\Domain\Wallet\DTOs\ReversalData;
use App\Domain\Wallet\DTOs\TransferData;
use App\Domain\Wallet\Enums\ReversalReason;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\ValueObjects\Money;
use App\Models\User;
use App\Notifications\TransactionReceipt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

function emailUser(int $cents = 0): User
{
    $user = User::factory()->create();
    Wallet::factory()->forUser($user)->create();

    if ($cents > 0) {
        app(DepositFunds::class)(new DepositData(userId: $user->id, amount: Money::fromCents($cents)));
    }

    return $user;
}

it('does not email anyone on a deposit', function () {
    Notification::fake();
    $user = User::factory()->create();
    Wallet::factory()->forUser($user)->create();

    app(DepositFunds::class)(new DepositData(userId: $user->id, amount: Money::fromCents(5000)));

    Notification::assertNothingSent();
});

it('emails only the recipient of a transfer, queued on the mail queue', function () {
    $alice = emailUser(10000);
    $bob = emailUser();
    Notification::fake();

    app(TransferFunds::class)(new TransferData(
        senderId: $alice->id, recipientId: $bob->id, amount: Money::fromCents(4000),
    ));

    Notification::assertSentTo($bob, TransactionReceipt::class, function (TransactionReceipt $n) {
        expect($n)->toBeInstanceOf(ShouldQueue::class);

        return true;
    });
    Notification::assertNotSentTo($alice, TransactionReceipt::class);
});

it('emails both parties when a transfer is reversed, each with their own side', function () {
    $alice = emailUser(10000);
    $bob = emailUser();
    $transfer = app(TransferFunds::class)(new TransferData(
        senderId: $alice->id, recipientId: $bob->id, amount: Money::fromCents(4000),
    ));
    Notification::fake();

    app(ReverseTransaction::class)(new ReversalData(
        transactionId: $transfer->id,
        reason: ReversalReason::UserRequest,
        initiatedByUserId: $alice->id,
    ));

    // Alice gets the money back (in); Bob has the received transfer pulled (out).
    Notification::assertSentTo($alice, TransactionReceipt::class, fn (TransactionReceipt $n) => $n->direction === 'in');
    Notification::assertSentTo($bob, TransactionReceipt::class, fn (TransactionReceipt $n) => $n->direction === 'out');
});

it('names the formatted amount in the receipt subject', function () {
    $user = emailUser();
    $tx = app(DepositFunds::class)(new DepositData(userId: $user->id, amount: Money::fromCents(4000)));

    $mail = (new TransactionReceipt($tx, 'in'))->toMail($user);

    expect($mail->subject)->toContain($tx->amount->formatBRL());
});
