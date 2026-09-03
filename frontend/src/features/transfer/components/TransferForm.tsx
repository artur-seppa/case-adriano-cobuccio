"use client";

import { FormEvent, useState } from "react";
import { Button } from "@/shared/ui/Button";
import { Input } from "@/shared/ui/Input";
import { MoneyInput } from "@/shared/money/MoneyInput";
import { useToast } from "@/shared/ui/Toast";
import { useIdempotencyKey } from "@/shared/api/useIdempotencyKey";
import { ApiError } from "@/shared/api/problem";
import { fieldError, genericErrorMessage, insufficientFundsMessage } from "@/shared/api/fieldError";
import { useWallet } from "@/features/wallet/hooks/useWallet";
import { useTransfer } from "../hooks/useTransfer";

export function TransferForm({ onSuccess }: { onSuccess?: () => void }) {
  const { toast } = useToast();
  const wallet = useWallet();
  const transfer = useTransfer();
  const idempotencyKey = useIdempotencyKey();
  const [recipient, setRecipient] = useState("");
  const [amountCents, setAmountCents] = useState(0);
  const [description, setDescription] = useState("");

  function handleSubmit(event: FormEvent) {
    event.preventDefault();
    transfer.mutate(
      { recipient, amountCents, description: description || undefined, idempotencyKey: idempotencyKey.get() },
      {
        onSuccess: () => {
          idempotencyKey.reset();
          toast({ title: "Transferência enviada.", variant: "success" });
          onSuccess?.();
        },
        onError: (error) => {
          // The server returned a verdict (any HTTP status) — the next click is
          // a new logical request, so it needs its own key. Only a transport
          // failure (no response) keeps the key, so re-submitting still de-dupes
          // a write that may have landed.
          if (error instanceof ApiError) idempotencyKey.reset();
        },
      },
    );
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-4" noValidate>
      {wallet.data && (
        <p className="text-sm text-ink-500">Saldo disponível: {wallet.data.balance_formatted}</p>
      )}
      <Input
        label="Destinatário (e-mail)"
        type="email"
        required
        value={recipient}
        onChange={(e) => setRecipient(e.target.value)}
        error={fieldError(transfer.error, "recipient")}
      />
      <MoneyInput
        label="Valor"
        value={amountCents}
        onChange={setAmountCents}
        error={fieldError(transfer.error, "amount") ?? insufficientFundsMessage(transfer.error)}
      />
      <Input
        label="Descrição (opcional)"
        value={description}
        onChange={(e) => setDescription(e.target.value)}
      />
      {genericErrorMessage(transfer.error) && (
        <p role="alert" className="text-sm text-danger-500">
          {genericErrorMessage(transfer.error)}
        </p>
      )}
      <Button type="submit" loading={transfer.isPending} disabled={amountCents === 0 || !recipient}>
        Transferir
      </Button>
    </form>
  );
}
