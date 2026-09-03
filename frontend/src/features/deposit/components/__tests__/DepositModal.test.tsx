import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { server } from "@/shared/testing/server";
import { QueryClientProvider } from "@tanstack/react-query";
import { createQueryClient } from "@/shared/query/queryClient";
import { ToastProvider } from "@/shared/ui/Toast";
import { DepositModal } from "../DepositModal";

function renderModal() {
  return render(
    <QueryClientProvider client={createQueryClient()}>
      <ToastProvider>
        <DepositModal trigger={<button type="button">Abrir</button>} />
      </ToastProvider>
    </QueryClientProvider>,
  );
}

it("opens on the trigger and closes itself after a successful deposit", async () => {
  server.use(
    http.post("/api/v1/deposits", () => HttpResponse.json({ data: { id: "t1" } }, { status: 201 })),
  );

  const user = userEvent.setup();
  renderModal();

  expect(screen.queryByRole("dialog")).not.toBeInTheDocument();

  await user.click(screen.getByRole("button", { name: "Abrir" }));
  expect(await screen.findByRole("dialog", { name: "Depositar" })).toBeInTheDocument();

  await user.type(screen.getByLabelText("Valor"), "5000");
  await user.click(screen.getByRole("button", { name: "Depositar" }));

  expect(await screen.findByText("Depósito realizado.")).toBeInTheDocument();
  await waitFor(() => expect(screen.queryByRole("dialog")).not.toBeInTheDocument());
});
