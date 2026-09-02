import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { server } from "@/shared/testing/server";
import { QueryClientProvider } from "@tanstack/react-query";
import { createQueryClient } from "@/shared/query/queryClient";
import { ToastProvider } from "@/shared/ui/Toast";
import { DepositForm } from "../DepositForm";

const { pushMock } = vi.hoisted(() => ({ pushMock: vi.fn() }));
vi.mock("next/navigation", () => ({ useRouter: () => ({ push: pushMock }) }));

function renderForm() {
  return render(
    <QueryClientProvider client={createQueryClient()}>
      <ToastProvider>
        <DepositForm />
      </ToastProvider>
    </QueryClientProvider>,
  );
}

it("disables the submit button while the amount is zero", () => {
  renderForm();
  expect(screen.getByRole("button", { name: "Depositar" })).toBeDisabled();
});

it("shows a success toast and redirects home after a successful deposit", async () => {
  server.use(
    http.post("/api/v1/deposits", () => HttpResponse.json({ data: { id: "t1" } }, { status: 201 })),
  );

  const user = userEvent.setup();
  renderForm();

  await user.type(screen.getByLabelText("Valor"), "5000");
  expect(screen.getByRole("button", { name: "Depositar" })).toBeEnabled();

  await user.click(screen.getByRole("button", { name: "Depositar" }));

  expect(await screen.findByText("Depósito realizado.")).toBeInTheDocument();
  expect(pushMock).toHaveBeenCalledWith("/");
});
