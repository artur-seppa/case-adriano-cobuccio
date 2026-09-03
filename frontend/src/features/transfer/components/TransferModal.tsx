"use client";

import { useState } from "react";
import { Dialog } from "@/shared/ui/Dialog";
import { TransferForm } from "./TransferForm";

export function TransferModal({ trigger }: { trigger: React.ReactNode }) {
  const [open, setOpen] = useState(false);

  return (
    <Dialog
      open={open}
      onOpenChange={setOpen}
      title="Transferir"
      description="Envie para outra conta pelo e-mail do destinatário."
      trigger={trigger}
    >
      <TransferForm onSuccess={() => setOpen(false)} />
    </Dialog>
  );
}
