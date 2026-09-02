"use client";

import { FormEvent, useState } from "react";
import { useRouter } from "next/navigation";
import { Input } from "@/shared/ui/Input";
import { Button } from "@/shared/ui/Button";
import { fieldError, genericErrorMessage } from "@/shared/api/fieldError";
import { useRegister } from "../hooks/useRegister";

export function RegisterForm() {
  const router = useRouter();
  const registerMutation = useRegister();
  const [form, setForm] = useState({ name: "", email: "", password: "", password_confirmation: "" });

  function handleSubmit(event: FormEvent) {
    event.preventDefault();
    registerMutation.mutate(form, {
      onSuccess: (data) => {
        router.push(data.requires_email_verification ? "/verify-email" : "/");
      },
    });
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-4" noValidate>
      <h1 className="text-xl font-semibold text-ink-900">Criar conta</h1>
      <Input
        label="Nome"
        required
        value={form.name}
        onChange={(e) => setForm({ ...form, name: e.target.value })}
        error={fieldError(registerMutation.error, "name")}
      />
      <Input
        label="E-mail"
        type="email"
        required
        value={form.email}
        onChange={(e) => setForm({ ...form, email: e.target.value })}
        error={fieldError(registerMutation.error, "email")}
      />
      <Input
        label="Senha"
        type="password"
        required
        value={form.password}
        onChange={(e) => setForm({ ...form, password: e.target.value })}
        error={fieldError(registerMutation.error, "password")}
      />
      <Input
        label="Confirmar senha"
        type="password"
        required
        value={form.password_confirmation}
        onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })}
      />
      {genericErrorMessage(registerMutation.error) && (
        <p role="alert" className="text-sm text-danger-500">
          {genericErrorMessage(registerMutation.error)}
        </p>
      )}
      <Button type="submit" loading={registerMutation.isPending}>
        Criar conta
      </Button>
      <a href="/login" className="text-center text-sm text-ink-500 hover:text-brand-600">
        Já tenho conta
      </a>
    </form>
  );
}
