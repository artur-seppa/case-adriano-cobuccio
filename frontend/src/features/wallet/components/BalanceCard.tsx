"use client";

import { Skeleton } from "@/shared/ui/Skeleton";
import { Button } from "@/shared/ui/Button";
import { useWallet } from "../hooks/useWallet";

export function BalanceCard({ actions }: { actions?: React.ReactNode }) {
  const wallet = useWallet();

  if (wallet.isError) {
    return (
      <div className="flex flex-col gap-3">
        <p className="text-sm text-danger-500">Não foi possível carregar o saldo.</p>
        <Button variant="secondary" onClick={() => wallet.refetch()} className="self-start">
          Tentar de novo
        </Button>
      </div>
    );
  }

  if (wallet.isLoading || !wallet.data) {
    return (
      <div>
        <Skeleton className="h-4 w-24" />
        <Skeleton className="mt-3 h-11 w-48" />
      </div>
    );
  }

  return (
    <div>
      <p className="text-sm text-ink-500">Saldo disponível</p>
      <p aria-live="polite" className="mt-2 text-4xl font-semibold tabular-nums tracking-tight text-ink-900">
        {wallet.data.balance_formatted}
      </p>
      {actions && <div className="mt-5 flex flex-wrap gap-3">{actions}</div>}
    </div>
  );
}
