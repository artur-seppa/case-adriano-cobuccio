import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { server } from "@/shared/testing/server";
import { QueryClientProvider } from "@tanstack/react-query";
import { createQueryClient } from "@/shared/query/queryClient";
import { ToastProvider } from "@/shared/ui/Toast";
import AppLayout from "../layout";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
  usePathname: () => "/",
}));

// jsdom has no EventSource — the success branch mounts <RealtimeProvider>, which opens one.
class StubEventSource {
  onopen: (() => void) | null = null;
  onerror: (() => void) | null = null;
  constructor(public url: string) {}
  addEventListener() {}
  close() {}
}

beforeEach(() => {
  // @ts-expect-error test double
  global.EventSource = StubEventSource;
});

// The success branch mounts <RealtimeProvider>, which calls useToast() — mirror the real
// app tree (root layout.tsx wraps ToastProvider around everything) so that branch renders.
function renderWithClient(ui: React.ReactElement) {
  return render(
    <QueryClientProvider client={createQueryClient()}>
      <ToastProvider>{ui}</ToastProvider>
    </QueryClientProvider>,
  );
}

it("shows a retry state, not an infinite skeleton, when the session check errors (not a 401)", async () => {
  server.use(
    http.get("/api/user", () =>
      HttpResponse.json(
        { type: "about:blank", title: "Internal Server Error", status: 500 },
        { status: 500, headers: { "Content-Type": "application/problem+json" } },
      ),
    ),
  );

  renderWithClient(
    <AppLayout>
      <p>conteúdo protegido</p>
    </AppLayout>,
  );

  expect(await screen.findByText("Não foi possível verificar sua sessão.")).toBeInTheDocument();
  expect(screen.queryByText("conteúdo protegido")).not.toBeInTheDocument();

  server.use(
    http.get("/api/user", () =>
      HttpResponse.json({ id: "u1", name: "Ana Souza", email: "ana@example.test", email_verified_at: null }),
    ),
  );

  await userEvent.click(screen.getByRole("button", { name: "Tentar de novo" }));

  expect(await screen.findByText("conteúdo protegido")).toBeInTheDocument();
});
