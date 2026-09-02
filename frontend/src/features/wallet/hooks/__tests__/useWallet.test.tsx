import { renderHook, waitFor } from "@testing-library/react";
import { http, HttpResponse } from "msw";
import { server } from "@/shared/testing/server";
import { QueryClientProvider } from "@tanstack/react-query";
import { createQueryClient } from "@/shared/query/queryClient";
import { useWallet } from "../useWallet";

function wrapper({ children }: { children: React.ReactNode }) {
  return <QueryClientProvider client={createQueryClient()}>{children}</QueryClientProvider>;
}

it("fetches the wallet balance", async () => {
  server.use(
    http.get("/api/v1/wallet", () =>
      HttpResponse.json({
        data: {
          id: "w1",
          currency: "BRL",
          balance: "150.00",
          balance_cents: 15000,
          balance_formatted: "R$ 150,00",
          updated_at: "2026-01-01T00:00:00Z",
        },
      }),
    ),
  );

  const { result } = renderHook(() => useWallet(), { wrapper });
  await waitFor(() => expect(result.current.data?.balance_formatted).toBe("R$ 150,00"));
});
