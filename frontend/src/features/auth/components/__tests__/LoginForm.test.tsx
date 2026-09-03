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

it("does not submit while e-mail or password is empty (client-side required)", async () => {
  let hit = false;
  server.use(http.post("/api/login", () => { hit = true; return new HttpResponse(null, { status: 204 }); }));

  const user = userEvent.setup();
  renderWithClient(<LoginForm />);

  await user.click(screen.getByRole("button", { name: "Entrar" }));
  expect(hit).toBe(false);

  await user.type(screen.getByLabelText("E-mail"), "ana@example.test");
  await user.click(screen.getByRole("button", { name: "Entrar" }));
  expect(hit).toBe(false); // password still empty

  await user.type(screen.getByLabelText("Senha"), "secret");
  await user.click(screen.getByRole("button", { name: "Entrar" }));
  expect(hit).toBe(true);
});

it("shows one general message on invalid credentials, not a per-field error", async () => {
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

  expect(await screen.findByText("E-mail ou senha incorretos.")).toBeInTheDocument();
  // the raw API field message is not surfaced
  expect(screen.queryByText("These credentials do not match our records.")).not.toBeInTheDocument();
});
