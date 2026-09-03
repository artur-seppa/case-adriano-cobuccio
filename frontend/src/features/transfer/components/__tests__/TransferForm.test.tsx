import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { server } from "@/shared/testing/server";
import { QueryClientProvider } from "@tanstack/react-query";
import { createQueryClient } from "@/shared/query/queryClient";
import { ToastProvider } from "@/shared/ui/Toast";
import { TransferForm } from "../TransferForm";

function renderForm() {
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
        <TransferForm />
      </ToastProvider>
    </QueryClientProvider>,
  );
}

it("disables the submit button while the amount is zero or the recipient is empty", async () => {
  const user = userEvent.setup();
  renderForm();

  const submit = screen.getByRole("button", { name: "Transferir" });
  expect(submit).toBeDisabled();

  await user.type(screen.getByLabelText("Destinatário (e-mail)"), "bruno@example.test");
  expect(submit).toBeDisabled();

  await user.type(screen.getByLabelText("Valor"), "5000");
  expect(submit).toBeEnabled();
});

it("shows the insufficient-funds message inline on the amount field, without a duplicate generic banner", async () => {
  server.use(
    http.post("/api/v1/transfers", () =>
      HttpResponse.json(
        {
          type: "https://wallet.test/problems/insufficient-funds",
          title: "Insufficient funds.",
          status: 422,
          available: "100.00",
          requested: "150.00",
          currency: "BRL",
        },
        { status: 422, headers: { "Content-Type": "application/problem+json" } },
      ),
    ),
  );

  const user = userEvent.setup();
  renderForm();

  await user.type(screen.getByLabelText("Destinatário (e-mail)"), "bruno@example.test");
  await user.type(screen.getByLabelText("Valor"), "15000");
  await user.click(screen.getByRole("button", { name: "Transferir" }));

  expect(await screen.findByText("Saldo insuficiente (disponível R$ 100,00).")).toBeInTheDocument();
  // The generic banner (the problem's raw title/detail) must not also render —
  // otherwise the same failure would be shown twice, once inline and once as a banner.
  expect(screen.queryByText("Insufficient funds.")).not.toBeInTheDocument();
});

it("shows a recipient-not-found error on the recipient field", async () => {
  server.use(
    http.post("/api/v1/transfers", () =>
      HttpResponse.json(
        {
          type: "https://wallet.test/problems/validation-failed",
          title: "The given data was invalid.",
          status: 422,
          errors: { recipient: ["No wallet was found for this recipient."] },
        },
        { status: 422, headers: { "Content-Type": "application/problem+json" } },
      ),
    ),
  );

  const user = userEvent.setup();
  renderForm();

  await user.type(screen.getByLabelText("Destinatário (e-mail)"), "unknown@example.test");
  await user.type(screen.getByLabelText("Valor"), "5000");
  await user.click(screen.getByRole("button", { name: "Transferir" }));

  expect(await screen.findByText("No wallet was found for this recipient.")).toBeInTheDocument();
});
