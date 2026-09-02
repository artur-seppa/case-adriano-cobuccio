import { apiFetch } from "@/shared/api/client";
import type { components } from "@/shared/api/generated/api.d.ts";

export type WalletResource = components["schemas"]["WalletResource"];

// The API wraps single resources in a `{ data: ... }` envelope (Laravel's
// default JsonResource wrapping — see WalletController::show and the
// generated `wallet.show` operation), so this unwraps it for callers.
export function fetchWallet(): Promise<WalletResource> {
  return apiFetch<{ data: WalletResource }>("/wallet").then((res) => res.data);
}
