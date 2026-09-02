import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { server } from "@/shared/testing/server";
import { QueryClientProvider } from "@tanstack/react-query";
import { createQueryClient } from "@/shared/query/queryClient";
import { SessionsList } from "../SessionsList";

it("hides the revoke button for the current session and shows it for others", async () => {
  server.use(
    http.get("/api/v1/wallet/sessions", () =>
      HttpResponse.json({
        data: [
          { id: "s1", ip_address: "127.0.0.1", user_agent: "Chrome", last_active: "2026-01-01T00:00:00Z", is_current: true },
          { id: "s2", ip_address: "10.0.0.1", user_agent: "Firefox", last_active: "2026-01-01T00:00:00Z", is_current: false },
        ],
      }),
    ),
  );

  render(
    <QueryClientProvider client={createQueryClient()}>
      <SessionsList />
    </QueryClientProvider>,
  );

  expect(await screen.findByText("Esta sessão")).toBeInTheDocument();
  expect(screen.getByRole("button", { name: "Revogar" })).toBeInTheDocument();
});

it("revokes a non-current session on click", async () => {
  let revoked = false;
  server.use(
    http.get("/api/v1/wallet/sessions", () =>
      HttpResponse.json({
        data: [{ id: "s2", ip_address: "10.0.0.1", user_agent: "Firefox", last_active: "2026-01-01T00:00:00Z", is_current: false }],
      }),
    ),
    http.delete("/api/v1/wallet/sessions/s2", () => {
      revoked = true;
      return new HttpResponse(null, { status: 204 });
    }),
  );

  const user = userEvent.setup();
  render(
    <QueryClientProvider client={createQueryClient()}>
      <SessionsList />
    </QueryClientProvider>,
  );

  await user.click(await screen.findByRole("button", { name: "Revogar" }));
  expect(revoked).toBe(true);
});
