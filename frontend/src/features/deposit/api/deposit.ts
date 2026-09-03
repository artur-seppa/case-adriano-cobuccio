import { apiFetch } from "@/shared/api/client";
import { centsToDecimalString } from "@/shared/money/format";
import type { TransactionResource } from "@/features/transactions/api/transactions";

export interface DepositInput {
  amountCents: number;
  fundingMethod?: "pix" | "boleto";
  description?: string;
  idempotencyKey: string;
}

// Single-resource endpoints are wrapped in a `{ data: ... }` envelope
// (Laravel's default JsonResource wrapping), same as `fetchWallet`/`fetchTransaction`.
export async function depositFunds({
  amountCents,
  fundingMethod,
  description,
  idempotencyKey,
}: DepositInput): Promise<TransactionResource> {
  const { data } = await apiFetch<{ data: TransactionResource }>("/deposits", {
    method: "POST",
    body: JSON.stringify({
      amount: centsToDecimalString(amountCents),
      currency: "BRL",
      funding_method: fundingMethod,
      description,
    }),
    idempotencyKey,
  });
  return data;
}
