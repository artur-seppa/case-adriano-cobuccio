"use client";

import { Dialog } from "@/shared/ui/Dialog";
import { TransactionDetail } from "./TransactionDetail";

/**
 * Opened from a row in the extrato. `id === null` keeps it closed; setting an id
 * opens it. The reversal flow is a second dialog stacked on top (see
 * TransactionDetail → ReversalDialog).
 */
export function TransactionDetailModal({ id, onClose }: { id: string | null; onClose: () => void }) {
  return (
    <Dialog
      open={id !== null}
      onOpenChange={(open) => {
        if (!open) onClose();
      }}
      title="Detalhe da transação"
    >
      {id !== null && <TransactionDetail id={id} embedded />}
    </Dialog>
  );
}
