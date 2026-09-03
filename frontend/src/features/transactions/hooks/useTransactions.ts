// features/transactions/hooks/useTransactions.ts
"use client";

import { useInfiniteQuery } from "@tanstack/react-query";
import { fetchTransactions, type TransactionFilters } from "../api/transactions";

export function useTransactions(filters: TransactionFilters) {
  return useInfiniteQuery({
    queryKey: ["transactions", "list", filters],
    queryFn: ({ pageParam }) => fetchTransactions({ ...filters, cursor: pageParam }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (lastPage) => lastPage.meta.next_cursor ?? undefined,
  });
}
