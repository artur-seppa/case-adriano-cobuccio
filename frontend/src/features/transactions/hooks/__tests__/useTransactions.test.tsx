import { renderHook, waitFor } from "@testing-library/react";
import { http, HttpResponse } from "msw";
import { server } from "@/shared/testing/server";
import { QueryClientProvider } from "@tanstack/react-query";
import { createQueryClient } from "@/shared/query/queryClient";
import { useTransactions } from "../useTransactions";

function wrapper({ children }: { children: React.ReactNode }) {
  return <QueryClientProvider client={createQueryClient()}>{children}</QueryClientProvider>;
}

it("fetches the next page using the cursor from meta.next_cursor", async () => {
  server.use(
    http.get("/api/v1/transactions", ({ request }) => {
      const cursor = new URL(request.url).searchParams.get("cursor");
      if (!cursor) {
        return HttpResponse.json({ data: [{ id: "t1" }], links: {}, meta: { path: "x", per_page: 20, next_cursor: "abc" } });
      }
      expect(cursor).toBe("abc");
      return HttpResponse.json({ data: [{ id: "t2" }], links: {}, meta: { path: "x", per_page: 20, next_cursor: null } });
    }),
  );

  const { result } = renderHook(() => useTransactions({}), { wrapper });
  await waitFor(() => expect(result.current.data?.pages).toHaveLength(1));

  await result.current.fetchNextPage();
  await waitFor(() => expect(result.current.data?.pages).toHaveLength(2));
  expect(result.current.hasNextPage).toBe(false);
});
