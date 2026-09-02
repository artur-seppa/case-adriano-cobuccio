// app/(app)/error.tsx
"use client";

import { Button } from "@/shared/ui/Button";

export default function AppError({ error, reset }: { error: Error; reset: () => void }) {
  return (
    <div className="flex flex-col items-center gap-3 py-20 text-center">
      <p className="text-lg font-semibold text-ink-900">Algo deu errado</p>
      <p className="text-sm text-ink-500">{error.message}</p>
      <Button onClick={reset}>Tentar de novo</Button>
    </div>
  );
}
