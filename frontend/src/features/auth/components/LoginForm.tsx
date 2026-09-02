"use client";

import { FormEvent, useState } from "react";
import { useRouter } from "next/navigation";
import { Input } from "@/shared/ui/Input";
import { Button } from "@/shared/ui/Button";
import { fieldError, genericErrorMessage } from "@/shared/api/fieldError";
import { useLogin } from "../hooks/useLogin";

export function LoginForm() {
  const router = useRouter();
  const login = useLogin();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");

  function handleSubmit(event: FormEvent) {
    event.preventDefault();
    login.mutate(
      { email, password },
      { onSuccess: () => router.push("/") },
    );
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-4" noValidate>
      <h1 className="text-xl font-semibold text-ink-900">Entrar</h1>
      <Input
        label="E-mail"
        type="email"
        required
        value={email}
        onChange={(e) => setEmail(e.target.value)}
        error={fieldError(login.error, "email")}
      />
      <Input
        label="Senha"
        type="password"
        required
        value={password}
        onChange={(e) => setPassword(e.target.value)}
        error={fieldError(login.error, "password")}
      />
      {genericErrorMessage(login.error) && (
        <p role="alert" className="text-sm text-danger-500">
          {genericErrorMessage(login.error)}
        </p>
      )}
      <Button type="submit" loading={login.isPending}>
        Entrar
      </Button>
      <a href="/forgot-password" className="text-center text-sm text-ink-500 hover:text-brand-600">
        Esqueci minha senha
      </a>
      <a href="/register" className="text-center text-sm text-ink-500 hover:text-brand-600">
        Criar conta
      </a>
    </form>
  );
}
