import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { http, HttpResponse } from "msw";
import { server } from "@/shared/testing/server";
import { QueryClientProvider } from "@tanstack/react-query";
import { createQueryClient } from "@/shared/query/queryClient";
import { ToastProvider } from "@/shared/ui/Toast";
import { ReversalDialog } from "../ReversalDialog";

function renderDialog() {
  return render(
    <QueryClientProvider client={createQueryClient()}>
      <ToastProvider>
        <ReversalDialog transactionId="t1" open onOpenChange={() => {}} />
      </ToastProvider>
    </QueryClientProvider>,
  );
}

it("reuses the same Idempotency-Key on a retry after a failed attempt", async () => {
  const seenKeys: (string | null)[] = [];
  let attempts = 0;

  server.use(
    http.post("/api/v1/transactions/t1/reversal", ({ request }) => {
      attempts += 1;
      seenKeys.push(request.headers.get("Idempotency-Key"));
      if (attempts === 1) {
        return HttpResponse.json(
          { type: "https://wallet.test/problems/internal", title: "Internal server error.", status: 500 },
          { status: 500, headers: { "Content-Type": "application/problem+json" } },
        );
      }
      return HttpResponse.json({ id: "reversal-1" }, { status: 201 });
    }),
  );

  const user = userEvent.setup();
  renderDialog();

  await user.click(screen.getByRole("button", { name: "Confirmar estorno" }));
  await screen.findByRole("alert");
  await user.click(screen.getByRole("button", { name: "Confirmar estorno" }));

  await screen.findByRole("button", { name: "Confirmar estorno" }); // dialog still controlled open in this harness

  expect(attempts).toBe(2);
  expect(seenKeys[0]).toBe(seenKeys[1]);
});
