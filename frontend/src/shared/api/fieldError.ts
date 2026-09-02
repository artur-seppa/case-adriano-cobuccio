import { ApiError } from "./problem";
import { formatDecimalBRL } from "@/shared/money/format";

export function fieldError(error: unknown, field: string): string | undefined {
  if (error instanceof ApiError) return error.errors?.[field]?.[0];
  return undefined;
}

export function insufficientFundsMessage(error: unknown): string | undefined {
  if (error instanceof ApiError && error.type.endsWith("/insufficient-funds")) {
    const available = error.extra.available;
    if (typeof available !== "string") return "Saldo insuficiente.";
    return `Saldo insuficiente (disponível ${formatDecimalBRL(available)}).`;
  }
  return undefined;
}

export function genericErrorMessage(error: unknown): string | undefined {
  if (error instanceof ApiError) {
    if (error.errors) return undefined;
    // insufficient-funds is shown inline on the amount field via
    // insufficientFundsMessage — showing it again here would duplicate it.
    if (error.type.endsWith("/insufficient-funds")) return undefined;
    return error.detail ?? error.title;
  }
  if (error) return "Erro de rede. Verifique sua conexão e tente novamente.";
  return undefined;
}
