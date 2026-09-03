import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { server } from "@/shared/testing/server";
import { QueryClientProvider } from "@tanstack/react-query";
import { createQueryClient } from "@/shared/query/queryClient";
import { ForgotPasswordForm } from "../ForgotPasswordForm";

function renderWithClient(ui: React.ReactElement) {
  return render(<QueryClientProvider client={createQueryClient()}>{ui}</QueryClientProvider>);
}

it("shows the field error returned by the API for a malformed e-mail", async () => {
  server.use(
    http.post("/api/forgot-password", () =>
      HttpResponse.json(
        {
          type: "https://wallet.test/problems/validation-failed",
          title: "The given data was invalid.",
          status: 422,
          errors: { email: ["The email field must be a valid email address."] },
        },
        { status: 422, headers: { "Content-Type": "application/problem+json" } },
      ),
    ),
  );

  const user = userEvent.setup();
  renderWithClient(<ForgotPasswordForm />);

  await user.type(screen.getByLabelText("E-mail"), "not-an-email");
  await user.click(screen.getByRole("button", { name: "Enviar link" }));

  expect(await screen.findByText("The email field must be a valid email address.")).toBeInTheDocument();
});
