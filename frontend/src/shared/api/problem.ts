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

function genericError(response: Response): ApiError {
  return new ApiError({
    status: response.status,
    type: "about:blank",
    title: response.statusText || "Request failed",
  });
}

/** An RFC 9457 body is still an RFC 9457 body when a proxy or replay layer
 *  serves it as plain `application/json` — accept it as long as it has the
 *  shape, so a field-level `errors` map isn't silently dropped. */
function looksLikeProblem(body: Record<string, unknown>): boolean {
  return (
    typeof body.type === "string" ||
    typeof body.title === "string" ||
    (typeof body.errors === "object" && body.errors !== null)
  );
}

export async function parseProblem(response: Response): Promise<ApiError> {
  const contentType = response.headers.get("Content-Type") ?? "";
  const isProblemJson = contentType.includes("application/problem+json");

  if (!isProblemJson && !contentType.includes("application/json")) {
    return genericError(response);
  }

  let parsed: unknown;
  try {
    parsed = await response.json();
  } catch {
    return genericError(response);
  }

  // A JSON body that isn't an object (`null`, an array, a bare string) carries
  // no problem fields — e.g. `/api/user` answering a guest with `null`, 401.
  if (typeof parsed !== "object" || parsed === null || Array.isArray(parsed)) {
    return genericError(response);
  }
  const body = parsed as Record<string, unknown>;

  if (!isProblemJson && !looksLikeProblem(body)) {
    return genericError(response);
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
