import { apiFetch } from "@/shared/api/client";
import type { components } from "@/shared/api/generated/api.d.ts";

export type TransactionResource = components["schemas"]["TransactionResource"];

export interface CursorPage<T> {
  data: T[];
  links: { first?: string | null; last?: string | null; prev?: string | null; next?: string | null };
  meta: { path: string; per_page: number; next_cursor?: string | null; prev_cursor?: string | null };
}

export interface TransactionFilters {
  type?: "deposit" | "transfer" | "reversal";
  direction?: "in" | "out";
  status?: "completed" | "reversed";
  from?: string;
  to?: string;
  per_page?: number;
  cursor?: string;
}

export function fetchTransactions(filters: TransactionFilters = {}): Promise<CursorPage<TransactionResource>> {
  const query = new URLSearchParams(
    Object.entries(filters).filter(([, v]) => v !== undefined) as [string, string][],
  ).toString();
  return apiFetch<CursorPage<TransactionResource>>(`/transactions${query ? `?${query}` : ""}`);
}

// Single-resource endpoints are wrapped in a `{ data: ... }` envelope
// (Laravel's default JsonResource wrapping), unlike the paginated index
// above whose `data`/`links`/`meta` already match `CursorPage` directly.
export function fetchTransaction(id: string): Promise<TransactionResource> {
  return apiFetch<{ data: TransactionResource }>(`/transactions/${id}`).then((res) => res.data);
}
