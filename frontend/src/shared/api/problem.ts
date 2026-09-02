export class ApiError extends Error {
  status: number;
  type: string;
  title: string;
  detail?: string;
  errors?: Record<string, string[]>;
  request_id?: string;
  extra: Record<string, unknown>;

  constructor(init: {
    status: number;
    type: string;
    title: string;
    detail?: string;
    errors?: Record<string, string[]>;
    request_id?: string;
    extra?: Record<string, unknown>;
  }) {
    super(init.detail ?? init.title);
    this.name = "ApiError";
    this.status = init.status;
    this.type = init.type;
    this.title = init.title;
    this.detail = init.detail;
    this.errors = init.errors;
    this.request_id = init.request_id;
    this.extra = init.extra ?? {};
  }
}

const KNOWN_KEYS = new Set(["type", "title", "status", "detail", "errors", "request_id"]);

export async function parseProblem(response: Response): Promise<ApiError> {
  const contentType = response.headers.get("Content-Type") ?? "";

  if (!contentType.includes("application/problem+json")) {
    return new ApiError({
      status: response.status,
      type: "about:blank",
      title: response.statusText || "Request failed",
    });
  }

  let body: Record<string, unknown>;
  try {
    body = (await response.json()) as Record<string, unknown>;
  } catch {
    return new ApiError({
      status: response.status,
      type: "about:blank",
      title: response.statusText || "Request failed",
    });
  }

  const extra: Record<string, unknown> = {};
  for (const [key, value] of Object.entries(body)) {
    if (!KNOWN_KEYS.has(key)) extra[key] = value;
  }

  return new ApiError({
    status: (body.status as number) ?? response.status,
    type: (body.type as string) ?? "about:blank",
    title: (body.title as string) ?? "Request failed",
    detail: body.detail as string | undefined,
    errors: body.errors as Record<string, string[]> | undefined,
    request_id: body.request_id as string | undefined,
    extra,
  });
}
