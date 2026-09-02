"use client";

import { FormEvent, useState } from "react";
import { Card } from "@/shared/ui/Card";
import { Input } from "@/shared/ui/Input";
import { Button } from "@/shared/ui/Button";
import { useToast } from "@/shared/ui/Toast";
import { fieldError, genericErrorMessage } from "@/shared/api/fieldError";
import { useUpdatePassword } from "../hooks/useUpdatePassword";

const EMPTY = { current_password: "", password: "", password_confirmation: "" };

export function PasswordForm() {
  const { toast } = useToast();
  const updatePassword = useUpdatePassword();
  const [form, setForm] = useState(EMPTY);

  function handleSubmit(event: FormEvent) {
    event.preventDefault();
    updatePassword.mutate(form, {
      onSuccess: () => {
        setForm(EMPTY);
        toast({ title: "Senha atualizada.", variant: "success" });
      },
    });
  }

  return (
    <Card>
      <form onSubmit={handleSubmit} className="flex flex-col gap-4" noValidate>
        <h2 className="text-sm font-semibold text-ink-900">Senha</h2>
        <Input
          label="Senha atual"
          type="password"
          value={form.current_password}
          onChange={(e) => setForm({ ...form, current_password: e.target.value })}
          error={fieldError(updatePassword.error, "current_password")}
        />
        <Input
          label="Nova senha"
          type="password"
          value={form.password}
          onChange={(e) => setForm({ ...form, password: e.target.value })}
          error={fieldError(updatePassword.error, "password")}
        />
        <Input
          label="Confirmar nova senha"
          type="password"
          value={form.password_confirmation}
          onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })}
        />
        {genericErrorMessage(updatePassword.error) && (
          <p role="alert" className="text-sm text-danger-500">
            {genericErrorMessage(updatePassword.error)}
          </p>
        )}
        <Button type="submit" loading={updatePassword.isPending} className="self-start">
          Atualizar senha
        </Button>
      </form>
    </Card>
  );
}
