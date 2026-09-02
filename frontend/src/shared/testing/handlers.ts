import { http, HttpResponse } from "msw";

export const handlers = [
  http.get("/api/user", () => HttpResponse.json(null, { status: 401 })),
];
