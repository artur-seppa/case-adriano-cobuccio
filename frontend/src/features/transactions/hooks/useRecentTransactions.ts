"use client";

import { useQuery } from "@tanstack/react-query";
import { fetchTransactions } from "../api/transactions";

export function useRecentTransactions() {
  return useQuery({
    queryKey: ["transactions", "recent"],
    queryFn: () => fetchTransactions({ per_page: 5 }),
    staleTime: 30_000,
  });
}
