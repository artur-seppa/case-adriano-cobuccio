import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { server } from "@/shared/testing/server";
import { QueryClientProvider } from "@tanstack/react-query";
import { createQueryClient } from "@/shared/query/queryClient";
import { RegisterForm } from "../RegisterForm";

vi.mock("next/navigation", () => ({ useRouter: () => ({ push: vi.fn() }) }));

function renderWithClient(ui: React.ReactElement) {
  return render(<QueryClientProvider client={createQueryClient()}>{ui}</QueryClientProvider>);
}

it("shows the field error returned by the API when the e-mail is already taken", async () => {
  server.use(
    http.post("/api/register", () =>
      HttpResponse.json(
        {
          type: "https://wallet.test/problems/validation-failed",
          title: "The given data was invalid.",
          status: 422,
          errors: { email: ["The email has already been taken."] },
        },
        { status: 422, headers: { "Content-Type": "application/problem+json" } },
      ),
    ),
  );

  const user = userEvent.setup();
  renderWithClient(<RegisterForm />);

  await user.type(screen.getByLabelText("Nome"), "Ana Souza");
  await user.type(screen.getByLabelText("E-mail"), "ana@example.test");
  await user.type(screen.getByLabelText("Senha"), "secret123");
  await user.type(screen.getByLabelText("Confirmar senha"), "secret123");
  await user.click(screen.getByRole("button", { name: "Criar conta" }));

  expect(await screen.findByText("The email has already been taken.")).toBeInTheDocument();
});
