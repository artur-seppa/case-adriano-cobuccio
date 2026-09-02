import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { server } from "@/shared/testing/server";
import { QueryClientProvider } from "@tanstack/react-query";
import { createQueryClient } from "@/shared/query/queryClient";
import { LoginForm } from "../LoginForm";

vi.mock("next/navigation", () => ({ useRouter: () => ({ push: vi.fn() }) }));

function renderWithClient(ui: React.ReactElement) {
  return render(<QueryClientProvider client={createQueryClient()}>{ui}</QueryClientProvider>);
}

it("shows the field error returned by the API on invalid credentials", async () => {
  server.use(
    http.post("/api/login", () =>
      HttpResponse.json(
        {
          type: "https://wallet.test/problems/validation-failed",
          title: "The given data was invalid.",
          status: 422,
          errors: { email: ["These credentials do not match our records."] },
        },
        { status: 422, headers: { "Content-Type": "application/problem+json" } },
      ),
    ),
  );

  const user = userEvent.setup();
  renderWithClient(<LoginForm />);

  await user.type(screen.getByLabelText("E-mail"), "ana@example.test");
  await user.type(screen.getByLabelText("Senha"), "wrong");
  await user.click(screen.getByRole("button", { name: "Entrar" }));

  expect(await screen.findByText("These credentials do not match our records.")).toBeInTheDocument();
});
