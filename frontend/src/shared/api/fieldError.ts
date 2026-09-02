import { ApiError } from "./problem";

export function fieldError(error: unknown, field: string): string | undefined {
  if (error instanceof ApiError) return error.errors?.[field]?.[0];
  return undefined;
}

export function genericErrorMessage(error: unknown): string | undefined {
  if (error instanceof ApiError && !error.errors) return error.detail ?? error.title;
  return undefined;
}
