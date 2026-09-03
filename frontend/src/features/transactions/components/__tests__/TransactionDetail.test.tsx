import { render, screen } from "@testing-library/react";
import { http, HttpResponse } from "msw";
import { server } from "@/shared/testing/server";
import { QueryClientProvider } from "@tanstack/react-query";
import { createQueryClient } from "@/shared/query/queryClient";
import { ToastProvider } from "@/shared/ui/Toast";
import { TransactionDetail } from "../TransactionDetail";

function renderDetail(id: string) {
  return render(
    <QueryClientProvider client={createQueryClient()}>
      <ToastProvider>
        <TransactionDetail id={id} />
      </ToastProvider>
    </QueryClientProvider>,
  );
}

it("hides the Estornar button once the transaction is already reversed", async () => {
  server.use(
    http.get("/api/v1/transactions/t1", () =>
      HttpResponse.json({
        data: {
          id: "t1",
          type: "transfer",
          status: "reversed",
          amount: "50.00",
          amount_cents: 5000,
          amount_formatted: "R$ 50,00",
          currency: "BRL",
          direction: "out",
          counterparty: { label: "Bruno" },
          description: null,
          reversal: { is_reversed: true, reversal_of_transaction_id: null, reversed_by_transaction_id: "t2", reason: "user_request" },
          created_at: "2026-01-01T00:00:00Z",
          metadata: {},
        },
      }),
    ),
  );

  renderDetail("t1");
  expect(await screen.findByText("Bruno")).toBeInTheDocument();
  expect(screen.queryByRole("button", { name: "Estornar" })).not.toBeInTheDocument();
});
