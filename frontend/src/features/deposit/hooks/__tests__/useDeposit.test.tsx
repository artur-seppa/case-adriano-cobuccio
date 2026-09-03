import { renderHook, waitFor, act } from "@testing-library/react";
import { http, HttpResponse } from "msw";
import { server } from "@/shared/testing/server";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { walletKeys } from "@/features/wallet/queryKeys";
import { useDeposit } from "../useDeposit";

function makeClient() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  client.setQueryData(walletKeys.all, {
    id: "w1",
    currency: "BRL",
    balance: "100.00",
    balance_cents: 10000,
    balance_formatted: "R$ 100,00",
    updated_at: "x",
  });
  return client;
}

function wrapperFor(client: QueryClient) {
  return function Wrapper({ children }: { children: React.ReactNode }) {
    return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
  };
}

it("bumps the cached balance immediately, before the server responds", async () => {
  let resolveRequest: (() => void) | null = null;
  server.use(
    http.post("/api/v1/deposits", async () => {
      await new Promise<void>((resolve) => (resolveRequest = resolve));
      return HttpResponse.json({ id: "t1" }, { status: 201 });
    }),
  );

  const client = makeClient();
  const { result } = renderHook(() => useDeposit(), { wrapper: wrapperFor(client) });

  act(() => {
    result.current.mutate({ amountCents: 5000, idempotencyKey: "key-1" });
  });

  await waitFor(() =>
    expect(client.getQueryData<{ balance_cents: number }>(walletKeys.all)?.balance_cents).toBe(15000),
  );

  resolveRequest!();
  await waitFor(() => expect(result.current.isSuccess).toBe(true));
});

it("rolls back the optimistic bump when the deposit fails", async () => {
  server.use(
    http.post("/api/v1/deposits", () =>
      HttpResponse.json(
        { type: "https://wallet.test/problems/validation-failed", title: "x", status: 422, errors: { amount: ["bad"] } },
        { status: 422, headers: { "Content-Type": "application/problem+json" } },
      ),
    ),
  );

  const client = makeClient();
  const { result } = renderHook(() => useDeposit(), { wrapper: wrapperFor(client) });

  act(() => {
    result.current.mutate({ amountCents: 5000, idempotencyKey: "key-1" });
  });

  await waitFor(() => expect(result.current.isError).toBe(true));
  expect(client.getQueryData<{ balance_cents: number }>(walletKeys.all)?.balance_cents).toBe(10000);
});
