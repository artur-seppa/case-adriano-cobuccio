// features/transactions/components/TransactionList.tsx
"use client";

import { useState } from "react";
import { Button } from "@/shared/ui/Button";
import { Skeleton } from "@/shared/ui/Skeleton";
import { TransactionFiltersBar } from "./TransactionFiltersBar";
import { TransactionRow } from "./TransactionRow";
import { TransactionDetailModal } from "./TransactionDetailModal";
import { useTransactionFilters } from "../hooks/useTransactionFilters";
import { useTransactions } from "../hooks/useTransactions";

export function TransactionList() {
  const [filters] = useTransactionFilters();
  const query = useTransactions(filters);
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const transactions = query.data?.pages.flatMap((page) => page.data) ?? [];

  return (
    <div>
      <div className="mb-4">
        <h2 className="text-sm font-semibold text-ink-900">Extrato</h2>
        <TransactionFiltersBar />
      </div>

      <div className="overflow-hidden rounded-xl border border-ink-500/10">
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
          <TransactionRow key={transaction.id} transaction={transaction} onSelect={setSelectedId} />
        ))}
      </div>

      {query.hasNextPage && (
        <div className="flex justify-center pt-4">
          <Button
            variant="secondary"
            loading={query.isFetchingNextPage}
            onClick={() => query.fetchNextPage()}
          >
            Carregar mais
          </Button>
        </div>
      )}

      <TransactionDetailModal id={selectedId} onClose={() => setSelectedId(null)} />
    </div>
  );
}
