"use client";

import { FormEvent, useState } from "react";
import { Input } from "@/shared/ui/Input";
import { Button } from "@/shared/ui/Button";
import { useForgotPassword } from "../hooks/useForgotPassword";

export function ForgotPasswordForm() {
  const forgotPassword = useForgotPassword();
  const [email, setEmail] = useState("");

  if (forgotPassword.isSuccess) {
    return (
      <p className="text-center text-sm text-ink-900">
        Se existir uma conta com esse e-mail, enviamos um link de redefinição.
      </p>
    );
  }

  return (
    <form
      onSubmit={(event: FormEvent) => {
        event.preventDefault();
        forgotPassword.mutate({ email });
      }}
      className="flex flex-col gap-4"
      noValidate
    >
      <h1 className="text-xl font-semibold text-ink-900">Esqueci minha senha</h1>
      <Input label="E-mail" type="email" required value={email} onChange={(e) => setEmail(e.target.value)} />
      <Button type="submit" loading={forgotPassword.isPending}>
        Enviar link
      </Button>
    </form>
  );
}
