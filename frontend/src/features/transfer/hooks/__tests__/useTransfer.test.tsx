import { renderHook, waitFor, act } from "@testing-library/react";
import { http, HttpResponse } from "msw";
import { server } from "@/shared/testing/server";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { walletKeys } from "@/features/wallet/queryKeys";
import { useTransfer } from "../useTransfer";

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

it("decrements the cached balance immediately and rolls back on insufficient funds", async () => {
  // Unlike the happy-path deposit test, this response must be held open with
  // a manual resolver (same as useDeposit's delayed-response test) — without
  // it, the whole onMutate -> mutationFn -> onError chain resolves within
  // microtasks before the first `waitFor` check ever runs, so the assertion
  // below would never observe the optimistic -5000 state.
  let resolveRequest: (() => void) | null = null;
  server.use(
    http.post("/api/v1/transfers", async () => {
      await new Promise<void>((resolve) => (resolveRequest = resolve));
      return HttpResponse.json(
        {
          type: "https://wallet.test/problems/insufficient-funds",
          title: "x",
          status: 422,
          available: "100.00",
          requested: "150.00",
          currency: "BRL",
        },
        { status: 422, headers: { "Content-Type": "application/problem+json" } },
      );
    }),
  );

  const client = makeClient();
  const { result } = renderHook(() => useTransfer(), {
    wrapper: ({ children }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>,
  });

  act(() => {
    result.current.mutate({ recipient: "bruno@example.test", amountCents: 15000, idempotencyKey: "key-1" });
  });

  await waitFor(() =>
    expect(client.getQueryData<{ balance_cents: number }>(walletKeys.all)?.balance_cents).toBe(-5000),
  );

  resolveRequest!();

  await waitFor(() => expect(result.current.isError).toBe(true));
  expect(client.getQueryData<{ balance_cents: number }>(walletKeys.all)?.balance_cents).toBe(10000);
});
