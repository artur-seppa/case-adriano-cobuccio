// features/transactions/hooks/useTransaction.ts
"use client";

import { useQuery } from "@tanstack/react-query";
import { fetchTransaction } from "../api/transactions";

export function useTransaction(id: string) {
  return useQuery({
    queryKey: ["transactions", "detail", id],
    queryFn: () => fetchTransaction(id),
  });
}
