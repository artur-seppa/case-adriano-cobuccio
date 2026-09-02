// features/transactions/hooks/useReverseTransaction.ts
"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { apiFetch } from "@/shared/api/client";
import type { TransactionResource } from "../api/transactions";

interface ReverseInput {
  transactionId: string;
  note?: string;
  idempotencyKey: string;
}

async function reverseTransaction({ transactionId, note, idempotencyKey }: ReverseInput): Promise<TransactionResource> {
  const { data } = await apiFetch<{ data: TransactionResource }>(`/transactions/${transactionId}/reversal`, {
    method: "POST",
    body: JSON.stringify({ note }),
    idempotencyKey,
  });
  return data;
}

export function useReverseTransaction() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: reverseTransaction,
    onSuccess: (_reversalTransaction, variables) => {
      queryClient.invalidateQueries({ queryKey: ["transactions", "detail", variables.transactionId] });
      queryClient.invalidateQueries({ queryKey: ["wallet"] });
      queryClient.invalidateQueries({ queryKey: ["transactions", "list"] });
    },
  });
}
