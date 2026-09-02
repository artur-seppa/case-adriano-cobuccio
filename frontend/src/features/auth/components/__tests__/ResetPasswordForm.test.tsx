import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { server } from "@/shared/testing/server";
import { QueryClientProvider } from "@tanstack/react-query";
import { createQueryClient } from "@/shared/query/queryClient";
import { ResetPasswordForm } from "../ResetPasswordForm";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push: vi.fn() }),
  useSearchParams: () => new URLSearchParams("token=abc123&email=ana@example.test"),
}));

function renderWithClient(ui: React.ReactElement) {
  return render(<QueryClientProvider client={createQueryClient()}>{ui}</QueryClientProvider>);
}

it("shows the invalid/expired-token error as a top-level banner, since there is no email input on this form", async () => {
  server.use(
    http.post("/api/reset-password", () =>
      HttpResponse.json(
        {
          type: "https://wallet.test/problems/validation-failed",
          title: "The given data was invalid.",
          status: 422,
          errors: { email: ["This password reset token is invalid."] },
        },
        { status: 422, headers: { "Content-Type": "application/problem+json" } },
      ),
    ),
  );

  const user = userEvent.setup();
  renderWithClient(<ResetPasswordForm />);

  await user.type(screen.getByLabelText("Nova senha"), "newSecret123");
  await user.type(screen.getByLabelText("Confirmar nova senha"), "newSecret123");
  await user.click(screen.getByRole("button", { name: "Redefinir" }));

  expect(await screen.findByText("This password reset token is invalid.")).toBeInTheDocument();
});
