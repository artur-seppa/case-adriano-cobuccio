"use client";

import { FormEvent, useState } from "react";
import { Card } from "@/shared/ui/Card";
import { Input } from "@/shared/ui/Input";
import { Button } from "@/shared/ui/Button";
import { useToast } from "@/shared/ui/Toast";
import { fieldError, genericErrorMessage } from "@/shared/api/fieldError";
import { useSession } from "@/features/auth/hooks/useSession";
import { useUpdateProfile } from "../hooks/useUpdateProfile";

export function ProfileForm() {
  const session = useSession();
  const { toast } = useToast();
  const updateProfile = useUpdateProfile();
  const [form, setForm] = useState({ name: session.data?.name ?? "", email: session.data?.email ?? "" });

  function handleSubmit(event: FormEvent) {
    event.preventDefault();
    updateProfile.mutate(form, { onSuccess: () => toast({ title: "Perfil atualizado.", variant: "success" }) });
  }

  return (
    <Card>
      <form onSubmit={handleSubmit} className="flex flex-col gap-4" noValidate>
        <h2 className="text-sm font-semibold text-ink-900">Perfil</h2>
        <Input
          label="Nome"
          value={form.name}
          onChange={(e) => setForm({ ...form, name: e.target.value })}
          error={fieldError(updateProfile.error, "name")}
        />
        <Input
          label="E-mail"
          type="email"
          value={form.email}
          onChange={(e) => setForm({ ...form, email: e.target.value })}
          error={fieldError(updateProfile.error, "email")}
        />
        {genericErrorMessage(updateProfile.error) && (
          <p role="alert" className="text-sm text-danger-500">
            {genericErrorMessage(updateProfile.error)}
          </p>
        )}
        <Button type="submit" loading={updateProfile.isPending} className="self-start">
          Salvar
        </Button>
      </form>
    </Card>
  );
}
