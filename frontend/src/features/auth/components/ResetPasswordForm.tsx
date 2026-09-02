"use client";

import { FormEvent, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { Input } from "@/shared/ui/Input";
import { Button } from "@/shared/ui/Button";
import { fieldError, genericErrorMessage } from "@/shared/api/fieldError";
import { useResetPassword } from "../hooks/useResetPassword";

export function ResetPasswordForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const resetPassword = useResetPassword();
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");

  const token = searchParams.get("token") ?? "";
  const email = searchParams.get("email") ?? "";

  function handleSubmit(event: FormEvent) {
    event.preventDefault();
    resetPassword.mutate(
      { token, email, password, password_confirmation: passwordConfirmation },
      { onSuccess: () => router.push("/login") },
    );
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-4" noValidate>
      <h1 className="text-xl font-semibold text-ink-900">Redefinir senha</h1>
      <p className="text-sm text-ink-500">{email}</p>
      {fieldError(resetPassword.error, "email") && (
        <p role="alert" className="text-sm text-danger-500">
          {fieldError(resetPassword.error, "email")}
        </p>
      )}
      <Input
        label="Nova senha"
        type="password"
        required
        value={password}
        onChange={(e) => setPassword(e.target.value)}
        error={fieldError(resetPassword.error, "password")}
      />
      <Input
        label="Confirmar nova senha"
        type="password"
        required
        value={passwordConfirmation}
        onChange={(e) => setPasswordConfirmation(e.target.value)}
      />
      {genericErrorMessage(resetPassword.error) && (
        <p role="alert" className="text-sm text-danger-500">
          {genericErrorMessage(resetPassword.error)}
        </p>
      )}
      <Button type="submit" loading={resetPassword.isPending}>
        Redefinir
      </Button>
    </form>
  );
}
