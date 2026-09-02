// features/transactions/components/TransactionList.tsx
"use client";

import { Card } from "@/shared/ui/Card";
import { Button } from "@/shared/ui/Button";
import { Skeleton } from "@/shared/ui/Skeleton";
import { TransactionFiltersBar } from "./TransactionFiltersBar";
import { TransactionRow } from "./TransactionRow";
import { useTransactionFilters } from "../hooks/useTransactionFilters";
import { useTransactions } from "../hooks/useTransactions";

export function TransactionList() {
  const [filters] = useTransactionFilters();
  const query = useTransactions(filters);
  const transactions = query.data?.pages.flatMap((page) => page.data) ?? [];

  return (
    <Card className="p-0">
      <TransactionFiltersBar />
      <div className="border-t border-ink-500/10">
        {query.isLoading && (
          <div className="flex flex-col gap-3 p-4">
            <Skeleton className="h-12" />
            <Skeleton className="h-12" />
            <Skeleton className="h-12" />
          </div>
        )}
        {query.isError && <p className="p-4 text-sm text-danger-500">Não foi possível carregar o extrato.</p>}
        {query.isSuccess && transactions.length === 0 && (
          <p className="p-4 text-sm text-ink-500">Nenhuma transação encontrada para esses filtros.</p>
        )}
        {transactions.map((transaction) => (
          <TransactionRow key={transaction.id} transaction={transaction} />
        ))}
      </div>
      {query.hasNextPage && (
        <div className="flex justify-center border-t border-ink-500/10 p-4">
          <Button
            variant="secondary"
            loading={query.isFetchingNextPage}
            onClick={() => query.fetchNextPage()}
          >
            Carregar mais
          </Button>
        </div>
      )}
    </Card>
  );
}
