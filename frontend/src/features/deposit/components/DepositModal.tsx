"use client";

import { useState } from "react";
import { Dialog } from "@/shared/ui/Dialog";
import { DepositForm } from "./DepositForm";

export function DepositModal({ trigger }: { trigger: React.ReactNode }) {
  const [open, setOpen] = useState(false);

  return (
    <Dialog
      open={open}
      onOpenChange={setOpen}
      title="Depositar"
      description="O valor entra na sua carteira na hora."
      trigger={trigger}
    >
      <DepositForm onSuccess={() => setOpen(false)} />
    </Dialog>
  );
}
