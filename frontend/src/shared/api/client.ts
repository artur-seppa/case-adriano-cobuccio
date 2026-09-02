import { ensureCsrf, readXsrfToken } from "./csrf";
import { parseProblem, ApiError } from "./problem";

const BASE = process.env.NEXT_PUBLIC_API_BASE ?? "/api/v1";

type ApiInit = RequestInit & { idempotencyKey?: string };

async function request<T>(path: string, init: ApiInit, isRetry = false): Promise<T> {
  const headers = new Headers(init.headers);
  headers.set("Accept", "application/json");

  const method = (init.method ?? "GET").toUpperCase();
  if (method !== "GET" && method !== "HEAD") {
    if (!headers.has("Content-Type") && init.body) {
      headers.set("Content-Type", "application/json");
    }
    const xsrf = readXsrfToken();
    if (xsrf) headers.set("X-XSRF-TOKEN", xsrf);
  }
  if (init.idempotencyKey) {
    headers.set("Idempotency-Key", init.idempotencyKey);
  }

  const response = await fetch(`${BASE}${path}`, {
    ...init,
    headers,
    credentials: "include",
  });

  if (response.status === 419 && !isRetry) {
    await ensureCsrf();
    return request<T>(path, init, true);
  }

  if (!response.ok) {
    const problem = await parseProblem(response);
    if (problem.status === 401 && typeof window !== "undefined") {
      window.dispatchEvent(new CustomEvent("wallet:unauthenticated"));
    }
    throw problem;
  }

  if (response.status === 204) {
    return undefined as T;
  }

  return (await response.json()) as T;
}

export function apiFetch<T>(path: string, init: ApiInit = {}): Promise<T> {
  return request<T>(path, init);
}

export { ApiError };
