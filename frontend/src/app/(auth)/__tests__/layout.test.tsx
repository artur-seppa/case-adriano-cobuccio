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

function renderWithClient(ui: React.ReactElement, client = createQueryClient()) {
  return { queryClient: client, ...render(<QueryClientProvider client={client}>{ui}</QueryClientProvider>) };
}

const VERIFIED_USER = {
  id: "u1",
  name: "Ana Souza",
  email: "ana@example.test",
  email_verified_at: "2026-01-01T00:00:00+00:00",
};

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

it("redirects an already-authenticated, verified visitor away from /login, back to /", async () => {
  server.use(http.get("/api/user", () => HttpResponse.json(VERIFIED_USER)));

  renderWithClient(
    <AuthLayout>
      <p>formulário de login</p>
    </AuthLayout>,
  );

  await vi.waitFor(() => expect(replace).toHaveBeenCalledWith("/"));
});

it("sends an authenticated but unverified visitor on /login to /verify-email, not /", async () => {
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

  await vi.waitFor(() => expect(replace).toHaveBeenCalledWith("/verify-email"));
  expect(replace).not.toHaveBeenCalledWith("/");
});

it("does not compete with a same-page register/login navigation when the session flips to authenticated afterwards", async () => {
  pathname = "/register";

  const { queryClient } = renderWithClient(
    <AuthLayout>
      <p>formulário de registro</p>
    </AuthLayout>,
  );

  // Initial check resolves unauthenticated first (default MSW handler: 401).
  // AuthLayout renders `children` unconditionally, so wait on the actual
  // query state settling to null — not just on the form text appearing.
  await vi.waitFor(() => expect(queryClient.getQueryState(["session"])?.status).toBe("success"));
  expect(queryClient.getQueryData(["session"])).toBeNull();
  expect(replace).not.toHaveBeenCalled();

  // Registration just succeeded on this same page — useRegister's onSuccess
  // invalidates ["session"], which resolves to the new user. RegisterForm's
  // own onSuccess is what should navigate from here (to /verify-email), not
  // this layout's guard.
  queryClient.setQueryData(["session"], { id: "u1", name: "Ana Souza", email: "ana@example.test", email_verified_at: null });

  await new Promise((resolve) => setTimeout(resolve, 0));
  expect(replace).not.toHaveBeenCalled();
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
