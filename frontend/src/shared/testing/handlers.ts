import { http, HttpResponse } from "msw";

export const handlers = [
  http.get("/api/user", () => HttpResponse.json(null, { status: 401 })),
  http.get("/sanctum/csrf-cookie", () => new HttpResponse(null, { status: 204 })),
];
