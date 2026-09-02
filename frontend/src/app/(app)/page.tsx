"use client";

import Link from "next/link";
import { BalanceCard } from "@/features/wallet/components/BalanceCard";
import { useRecentTransactions } from "@/features/transactions/hooks/useRecentTransactions";
import { TransactionRow } from "@/features/transactions/components/TransactionRow";
import { Card } from "@/shared/ui/Card";
import { Button } from "@/shared/ui/Button";
import { Skeleton } from "@/shared/ui/Skeleton";

export default function DashboardPage() {
  const recent = useRecentTransactions();

  return (
    <div className="flex flex-col gap-6">
      <BalanceCard />

      <div className="flex gap-3">
        <Link href="/deposit">
          <Button>Depositar</Button>
        </Link>
        <Link href="/transfer">
          <Button variant="secondary">Transferir</Button>
        </Link>
      </div>

      <Card className="p-0">
        <div className="border-b border-ink-500/10 px-4 py-3">
          <h2 className="text-sm font-semibold text-ink-900">Últimas transações</h2>
        </div>
        {recent.isLoading && (
          <div className="flex flex-col gap-3 p-4">
            <Skeleton className="h-10" />
            <Skeleton className="h-10" />
            <Skeleton className="h-10" />
          </div>
        )}
        {recent.isError && <p className="p-4 text-sm text-danger-500">Não foi possível carregar as transações.</p>}
        {recent.data?.data.length === 0 && <p className="p-4 text-sm text-ink-500">Nenhuma transação ainda.</p>}
        {recent.data?.data.map((transaction) => (
          <TransactionRow key={transaction.id} transaction={transaction} />
        ))}
      </Card>
    </div>
  );
}
