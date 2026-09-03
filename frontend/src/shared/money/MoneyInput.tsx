// shared/money/MoneyInput.tsx
"use client";

import { ChangeEvent } from "react";
import { Input } from "@/shared/ui/Input";
import { formatCentsBRL } from "./format";

interface Props {
  label: string;
  value: number; // cents
  onChange: (cents: number) => void;
  error?: string;
  id?: string;
}

export function MoneyInput({ label, value, onChange, error, id }: Props) {
  function handleChange(event: ChangeEvent<HTMLInputElement>) {
    const digitsOnly = event.target.value.replace(/\D/g, "");
    const cents = digitsOnly === "" ? 0 : parseInt(digitsOnly, 10);
    onChange(cents);
  }

  return (
    <Input
      id={id}
      label={label}
      error={error}
      inputMode="numeric"
      value={formatCentsBRL(value)}
      onChange={handleChange}
    />
  );
}
