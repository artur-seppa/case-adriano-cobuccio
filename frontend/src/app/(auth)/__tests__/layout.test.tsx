import { render, screen } from "@testing-library/react";
import { http, HttpResponse } from "msw";
import { server } from "@/shared/testing/server";
import { QueryClientProvider } from "@tanstack/react-query";
import { createQueryClient } from "@/shared/query/queryClient";
import AuthLayout from "../layout";

const replace = vi.fn();
let pathname = "/login";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push: vi.fn(), replace }),
  usePathname: () => pathname,
}));

function renderWithClient(ui: React.ReactElement) {
  return render(<QueryClientProvider client={createQueryClient()}>{ui}</QueryClientProvider>);
}

beforeEach(() => {
  replace.mockClear();
  pathname = "/login";
});

it("does not redirect away from /login while unauthenticated (default: /api/user -> 401)", async () => {
  renderWithClient(
    <AuthLayout>
      <p>formulário de login</p>
    </AuthLayout>,
  );

  expect(await screen.findByText("formulário de login")).toBeInTheDocument();
  expect(replace).not.toHaveBeenCalled();
});

it("redirects an already-authenticated visitor away from /login, back to /", async () => {
  server.use(
    http.get("/api/user", () =>
      HttpResponse.json({ id: "u1", name: "Ana Souza", email: "ana@example.test", email_verified_at: null }),
    ),
  );

  renderWithClient(
    <AuthLayout>
      <p>formulário de login</p>
    </AuthLayout>,
  );

  await vi.waitFor(() => expect(replace).toHaveBeenCalledWith("/"));
});

it("does not redirect away from /reset-password even when already authenticated", async () => {
  pathname = "/reset-password";
  server.use(
    http.get("/api/user", () =>
      HttpResponse.json({ id: "u1", name: "Ana Souza", email: "ana@example.test", email_verified_at: null }),
    ),
  );

  renderWithClient(
    <AuthLayout>
      <p>formulário de reset</p>
    </AuthLayout>,
  );

  expect(await screen.findByText("formulário de reset")).toBeInTheDocument();
  expect(replace).not.toHaveBeenCalled();
});
