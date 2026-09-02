import { apiFetch } from "@/shared/api/client";
import { centsToDecimalString } from "@/shared/money/format";
import type { TransactionResource } from "@/features/transactions/api/transactions";

export interface TransferInput {
  recipient: string;
  amountCents: number;
  description?: string;
  idempotencyKey: string;
}

// Single-resource endpoints are wrapped in a `{ data: ... }` envelope
// (Laravel's default JsonResource wrapping), same as `fetchWallet`/`fetchTransaction`.
export async function transferFunds({
  recipient,
  amountCents,
  description,
  idempotencyKey,
}: TransferInput): Promise<TransactionResource> {
  const { data } = await apiFetch<{ data: TransactionResource }>("/transfers", {
    method: "POST",
    body: JSON.stringify({
      recipient,
      amount: centsToDecimalString(amountCents),
      currency: "BRL",
      description,
    }),
    idempotencyKey,
  });
  return data;
}
