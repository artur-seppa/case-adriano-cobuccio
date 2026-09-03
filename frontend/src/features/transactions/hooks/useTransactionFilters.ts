// features/transactions/hooks/useTransactionFilters.ts
"use client";

import { useQueryStates } from "nuqs";
import { transactionFilterParsers } from "../filters/schema";
import type { TransactionFilters } from "../api/transactions";

export function useTransactionFilters() {
  const [state, setState] = useQueryStates(transactionFilterParsers, { history: "push" });

  const filters: TransactionFilters = {
    type: state.type ?? undefined,
    direction: state.direction ?? undefined,
    status: state.status ?? undefined,
    from: state.from ?? undefined,
    to: state.to ?? undefined,
  };

  return [filters, setState] as const;
}
