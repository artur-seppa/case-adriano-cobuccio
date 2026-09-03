import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { server } from "@/shared/testing/server";
import { QueryClientProvider } from "@tanstack/react-query";
import { createQueryClient } from "@/shared/query/queryClient";
import { ToastProvider } from "@/shared/ui/Toast";
import { TransferModal } from "../TransferModal";

function renderModal() {
  server.use(
    http.get("/api/v1/wallet", () =>
      HttpResponse.json({
        data: {
          id: "w1",
          currency: "BRL",
          balance: "100.00",
          balance_cents: 10000,
          balance_formatted: "R$ 100,00",
          updated_at: "x",
        },
      }),
    ),
  );
  return render(
    <QueryClientProvider client={createQueryClient()}>
      <ToastProvider>
        <TransferModal trigger={<button type="button">Abrir</button>} />
      </ToastProvider>
    </QueryClientProvider>,
  );
}

it("opens on the trigger and closes itself after a successful transfer", async () => {
  server.use(
    http.post("/api/v1/transfers", () => HttpResponse.json({ data: { id: "t1" } }, { status: 201 })),
  );

  const user = userEvent.setup();
  renderModal();

  expect(screen.queryByRole("dialog")).not.toBeInTheDocument();

  await user.click(screen.getByRole("button", { name: "Abrir" }));
  expect(await screen.findByRole("dialog", { name: "Transferir" })).toBeInTheDocument();

  await user.type(screen.getByLabelText("Destinatário (e-mail)"), "bruno@example.test");
  await user.type(screen.getByLabelText("Valor"), "5000");
  await user.click(screen.getByRole("button", { name: "Transferir" }));

  expect(await screen.findByText("Transferência enviada.")).toBeInTheDocument();
  await waitFor(() => expect(screen.queryByRole("dialog")).not.toBeInTheDocument());
});
