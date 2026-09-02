import { renderHook, waitFor, act } from "@testing-library/react";
import { http, HttpResponse } from "msw";
import { server } from "@/shared/testing/server";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { createQueryClient } from "@/shared/query/queryClient";
import { useLogout } from "../useLogout";

let queryClient: QueryClient;

function wrapper({ children }: { children: React.ReactNode }) {
  return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
}

beforeEach(() => {
  queryClient = createQueryClient();
});

it("posts to /api/logout and clears all cached queries on success", async () => {
  queryClient.setQueryData(["session"], { id: "u1", name: "Ana", email: "ana@example.test", email_verified_at: null });

  server.use(http.post("/api/logout", () => new HttpResponse(null, { status: 204 })));

  const { result } = renderHook(() => useLogout(), { wrapper });
  act(() => result.current.mutate());
  await waitFor(() => expect(result.current.isSuccess).toBe(true));

  expect(queryClient.getQueryData(["session"])).toBeUndefined();
});
