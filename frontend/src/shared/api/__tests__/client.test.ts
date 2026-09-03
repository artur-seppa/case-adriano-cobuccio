import { http, HttpResponse } from "msw";
import { server } from "@/shared/testing/server";
import { apiFetch } from "../client";
import { ApiError } from "../problem";

it("prefixes the path with NEXT_PUBLIC_API_BASE and sends credentials", async () => {
  server.use(
    http.get("/api/v1/wallet", ({ request }) => {
      expect(request.credentials).toBe("include");
      return HttpResponse.json({ id: "w1", balance: "100.00" });
    }),
  );

  const wallet = await apiFetch<{ id: string }>("/wallet");
  expect(wallet.id).toBe("w1");
});

it("sends the XSRF header on non-GET requests", async () => {
  document.cookie = "XSRF-TOKEN=abc123";
  server.use(
    http.post("/api/v1/deposits", ({ request }) => {
      expect(request.headers.get("X-XSRF-TOKEN")).toBe("abc123");
      return HttpResponse.json({ id: "t1" }, { status: 201 });
    }),
  );

  await apiFetch("/deposits", { method: "POST", body: JSON.stringify({ amount: "10.00" }) });
});

it("sends the Idempotency-Key header when provided", async () => {
  server.use(
    http.post("/api/v1/deposits", ({ request }) => {
      expect(request.headers.get("Idempotency-Key")).toBe("key-123");
      return HttpResponse.json({ id: "t1" }, { status: 201 });
    }),
  );

  await apiFetch("/deposits", { method: "POST", body: "{}", idempotencyKey: "key-123" });
});

it("throws a typed ApiError on a non-2xx problem+json response", async () => {
  server.use(
    http.get("/api/v1/wallet", () =>
      HttpResponse.json(
        { type: "https://wallet.test/problems/unauthenticated", title: "Unauthenticated.", status: 401 },
        { status: 401, headers: { "Content-Type": "application/problem+json" } },
      ),
    ),
  );

  await expect(apiFetch("/wallet")).rejects.toBeInstanceOf(ApiError);
});

it("retries once after ensuring csrf on a 419", async () => {
  let attempts = 0;
  server.use(
    http.get("/sanctum/csrf-cookie", () => new HttpResponse(null, { status: 204 })),
    http.post("/api/v1/deposits", () => {
      attempts += 1;
      if (attempts === 1) {
        return HttpResponse.json(
          { type: "about:blank", title: "CSRF token mismatch.", status: 419 },
          { status: 419 },
        );
      }
      return HttpResponse.json({ id: "t1" }, { status: 201 });
    }),
  );

  const result = await apiFetch<{ id: string }>("/deposits", { method: "POST", body: "{}" });
  expect(result.id).toBe("t1");
  expect(attempts).toBe(2);
});
