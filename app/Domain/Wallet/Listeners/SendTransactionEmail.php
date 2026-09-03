<?php

namespace App\Domain\Wallet\Listeners;

use App\Domain\Wallet\Events\FundsTransferred;
use App\Domain\Wallet\Events\TransactionReversed;
use App\Domain\Wallet\Models\Transaction;
use App\Models\User;
use App\Notifications\TransactionReceipt;

/**
 * E-mail sobre transação concluída: o destinatário de uma transferência
 * recebida, e as duas pontas de um estorno (quem recebe o valor de volta e
 * quem tem a transação puxada). Síncrono e pós-commit, ao lado do
 * PublishUserEvent; só a Notification vai para a fila `mail`. Depósito não
 * dispara nada (o usuário iniciou), e quem envia uma transferência também não.
 * A carteira-sistema `external_world` não tem usuário, então nunca recebe.
 */
class SendTransactionEmail
{
    public function handleTransferred(FundsTransferred $event): void
    {
        $this->notify($event->transaction->destinationWallet->user, $event->transaction, 'in');
    }

    public function handleReversed(TransactionReversed $event): void
    {
        $this->notify($event->reversal->destinationWallet->user, $event->reversal, 'in');
        $this->notify($event->reversal->sourceWallet->user, $event->reversal, 'out');
    }

    /** @param  'in'|'out'  $direction */
    private function notify(?User $user, Transaction $transaction, string $direction): void
    {
        $user?->notify(new TransactionReceipt($transaction, $direction));
    }
}
