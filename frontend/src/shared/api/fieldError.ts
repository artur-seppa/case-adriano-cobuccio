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

/**
 * Login/auth failures are shown as one general message rather than a per-field
 * error: the API can't (and shouldn't) say whether it was the e-mail or the
 * password, and confirming which is a mild account-enumeration leak.
 */
export function loginFailureMessage(error: unknown): string | undefined {
  if (error instanceof ApiError && (error.status === 401 || error.status === 422)) {
    return "E-mail ou senha incorretos.";
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
