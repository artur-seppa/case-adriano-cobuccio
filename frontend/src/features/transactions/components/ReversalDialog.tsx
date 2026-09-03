// features/transactions/components/ReversalDialog.tsx
"use client";

import { useState } from "react";
import { Dialog } from "@/shared/ui/Dialog";
import { Button } from "@/shared/ui/Button";
import { useToast } from "@/shared/ui/Toast";
import { useIdempotencyKey } from "@/shared/api/useIdempotencyKey";
import { genericErrorMessage } from "@/shared/api/fieldError";
import { useReverseTransaction } from "../hooks/useReverseTransaction";

interface Props {
  transactionId: string;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

export function ReversalDialog({ transactionId, open, onOpenChange }: Props) {
  const [note, setNote] = useState("");
  const idempotencyKey = useIdempotencyKey();
  const { toast } = useToast();
  const reverse = useReverseTransaction();

  function handleConfirm() {
    reverse.mutate(
      { transactionId, note: note || undefined, idempotencyKey: idempotencyKey.get() },
      {
        onSuccess: () => {
          idempotencyKey.reset();
          toast({ title: "Transação estornada.", variant: "success" });
          onOpenChange(false);
        },
      },
    );
  }

  return (
    <Dialog
      open={open}
      onOpenChange={onOpenChange}
      title="Estornar transação"
      description="Isso reverte o valor integralmente. Essa ação não pode ser desfeita."
    >
      <label className="flex flex-col gap-1.5 text-sm text-ink-900">
        Motivo (opcional)
        <textarea
          className="rounded-lg border border-ink-500/20 p-2 text-sm"
          value={note}
          onChange={(e) => setNote(e.target.value)}
          rows={3}
        />
      </label>
      {genericErrorMessage(reverse.error) && (
        <p role="alert" className="mt-2 text-sm text-danger-500">
          {genericErrorMessage(reverse.error)}
        </p>
      )}
      <div className="mt-4 flex justify-end gap-2">
        <Button variant="secondary" onClick={() => onOpenChange(false)}>
          Cancelar
        </Button>
        <Button variant="danger" loading={reverse.isPending} onClick={handleConfirm}>
          Confirmar estorno
        </Button>
      </div>
    </Dialog>
  );
}
