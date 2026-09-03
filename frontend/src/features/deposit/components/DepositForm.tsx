"use client";

import { FormEvent, useState } from "react";
import { Button } from "@/shared/ui/Button";
import { Input } from "@/shared/ui/Input";
import { MoneyInput } from "@/shared/money/MoneyInput";
import { useToast } from "@/shared/ui/Toast";
import { useIdempotencyKey } from "@/shared/api/useIdempotencyKey";
import { fieldError, genericErrorMessage } from "@/shared/api/fieldError";
import { useDeposit } from "../hooks/useDeposit";

export function DepositForm({ onSuccess }: { onSuccess?: () => void }) {
  const { toast } = useToast();
  const deposit = useDeposit();
  const idempotencyKey = useIdempotencyKey();
  const [amountCents, setAmountCents] = useState(0);
  const [description, setDescription] = useState("");

  function handleSubmit(event: FormEvent) {
    event.preventDefault();
    deposit.mutate(
      { amountCents, description: description || undefined, idempotencyKey: idempotencyKey.get() },
      {
        onSuccess: () => {
          idempotencyKey.reset();
          toast({ title: "Depósito realizado.", variant: "success" });
          onSuccess?.();
        },
      },
    );
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-4" noValidate>
      <MoneyInput
        label="Valor"
        value={amountCents}
        onChange={setAmountCents}
        error={fieldError(deposit.error, "amount")}
      />
      <Input
        label="Descrição (opcional)"
        value={description}
        onChange={(e) => setDescription(e.target.value)}
      />
      {genericErrorMessage(deposit.error) && (
        <p role="alert" className="text-sm text-danger-500">
          {genericErrorMessage(deposit.error)}
        </p>
      )}
      <Button type="submit" loading={deposit.isPending} disabled={amountCents === 0}>
        Depositar
      </Button>
    </form>
  );
}
