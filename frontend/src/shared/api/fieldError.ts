import { ApiError } from "./problem";

export function fieldError(error: unknown, field: string): string | undefined {
  if (error instanceof ApiError) return error.errors?.[field]?.[0];
  return undefined;
}

export function genericErrorMessage(error: unknown): string | undefined {
  if (error instanceof ApiError) {
    return error.errors ? undefined : (error.detail ?? error.title);
  }
  if (error) return "Erro de rede. Verifique sua conexão e tente novamente.";
  return undefined;
}
