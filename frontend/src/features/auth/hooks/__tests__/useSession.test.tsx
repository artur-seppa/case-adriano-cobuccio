import { renderHook, waitFor } from "@testing-library/react";
import { http, HttpResponse } from "msw";
import { server } from "@/shared/testing/server";
import { QueryClientProvider } from "@tanstack/react-query";
import { createQueryClient } from "@/shared/query/queryClient";
import { useSession } from "../useSession";

function wrapper({ children }: { children: React.ReactNode }) {
  return <QueryClientProvider client={createQueryClient()}>{children}</QueryClientProvider>;
}

it("resolves to null (not an error) on a 401", async () => {
  server.use(
    http.get("/api/user", () =>
      HttpResponse.json(
        { type: "https://wallet.test/problems/unauthenticated", title: "Unauthenticated.", status: 401 },
        { status: 401, headers: { "Content-Type": "application/problem+json" } },
      ),
    ),
  );

  const { result } = renderHook(() => useSession(), { wrapper });
  await waitFor(() => expect(result.current.isSuccess).toBe(true));
  expect(result.current.data).toBeNull();
});

it("resolves to the user on 200", async () => {
  server.use(
    http.get("/api/user", () =>
      HttpResponse.json({ id: "u1", name: "Ana", email: "ana@example.test", email_verified_at: null }),
    ),
  );

  const { result } = renderHook(() => useSession(), { wrapper });
  await waitFor(() => expect(result.current.data?.id).toBe("u1"));
});
