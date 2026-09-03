<?php

namespace App\Notifications;

use App\Domain\Wallet\Enums\TransactionType;
use App\Domain\Wallet\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso sobre uma transação concluída. Vai para o destinatário de uma
 * transferência recebida e para as duas pontas de um estorno. Enfileirado na
 * fila `mail` (mesmo supervisor do Horizon dos e-mails de auth, com `tries: 3`
 * + backoff). Disparado pelo SendTransactionEmail. Depósito não notifica (o
 * próprio usuário iniciou), e quem envia uma transferência também não.
 */
class TransactionReceipt extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  'in'|'out'  $direction  o lado desta carteira na transação */
    public function __construct(
        public Transaction $transaction,
        public string $direction,
    ) {
        $this->onQueue('mail');
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = $this->transaction->amount->formatBRL();
        $isReversal = $this->transaction->type === TransactionType::Reversal;

        [$subject, $line] = match (true) {
            $isReversal && $this->direction === 'in' => [
                "Estorno recebido: {$amount}",
                "Um estorno de {$amount} entrou na sua carteira.",
            ],
            $isReversal => [
                "Transação estornada: {$amount}",
                "Uma transação de {$amount} que você havia recebido foi estornada; o valor saiu da sua carteira.",
            ],
            default => [
                "Você recebeu {$amount}",
                "Uma transferência de {$amount} entrou na sua carteira.",
            ],
        };

        return (new MailMessage)
            ->subject($subject)
            ->greeting("Olá, {$notifiable->name}.")
            ->line($line)
            ->line("Referência da transação: {$this->transaction->id}.");
    }
}
