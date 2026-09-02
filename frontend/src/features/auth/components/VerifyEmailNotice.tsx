"use client";

import { Button } from "@/shared/ui/Button";
import { useResendVerification } from "../hooks/useResendVerification";

export function VerifyEmailNotice() {
  const resend = useResendVerification();

  return (
    <div className="flex flex-col items-center gap-4 text-center">
      <h1 className="text-xl font-semibold text-ink-900">Verifique seu e-mail</h1>
      <p className="text-sm text-ink-500">
        Enviamos um link de confirmação. Abra-o para liberar depósitos e transferências. Em dev, veja a caixa em
        Mailpit.
      </p>
      <Button variant="secondary" onClick={() => resend.mutate()} loading={resend.isPending} disabled={resend.isSuccess}>
        {resend.isSuccess ? "Reenviado" : "Reenviar e-mail"}
      </Button>
    </div>
  );
}
