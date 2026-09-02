"use client";

import { Card } from "@/shared/ui/Card";
import { Skeleton } from "@/shared/ui/Skeleton";
import { Button } from "@/shared/ui/Button";
import { useWallet } from "../hooks/useWallet";

export function BalanceCard() {
  const wallet = useWallet();

  if (wallet.isError) {
    return (
      <Card className="flex flex-col gap-3">
        <p className="text-sm text-danger-500">Não foi possível carregar o saldo.</p>
        <Button variant="secondary" onClick={() => wallet.refetch()}>
          Tentar de novo
        </Button>
      </Card>
    );
  }

  if (wallet.isLoading || !wallet.data) {
    return (
      <Card>
        <Skeleton className="h-4 w-24" />
        <Skeleton className="mt-3 h-10 w-48" />
      </Card>
    );
  }

  return (
    <Card>
      <p className="text-sm text-ink-500">Saldo disponível</p>
      <p aria-live="polite" className="mt-2 text-4xl font-semibold text-ink-900">
        {wallet.data.balance_formatted}
      </p>
    </Card>
  );
}
