import { render, screen, waitFor, act } from "@testing-library/react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { ToastProvider } from "@/shared/ui/Toast";
import { RealtimeProvider } from "../realtime-provider";

class FakeEventSource {
  static instances: FakeEventSource[] = [];
  listeners: Record<string, ((event: MessageEvent) => void)[]> = {};
  onopen: (() => void) | null = null;
  onerror: (() => void) | null = null;
  constructor(public url: string) {
    FakeEventSource.instances.push(this);
  }
  addEventListener(type: string, cb: (event: MessageEvent) => void) {
    (this.listeners[type] ??= []).push(cb);
  }
  emit(type: string, data: unknown) {
    this.listeners[type]?.forEach((cb) => cb({ data: JSON.stringify(data) } as MessageEvent));
  }
  close() {}
}

beforeEach(() => {
  FakeEventSource.instances = [];
  // @ts-expect-error test double
  global.EventSource = FakeEventSource;
});

it("invalidates wallet and transactions and shows a toast on a business event", async () => {
  const queryClient = new QueryClient();
  const invalidateSpy = vi.spyOn(queryClient, "invalidateQueries");

  render(
    <QueryClientProvider client={queryClient}>
      <ToastProvider>
        <RealtimeProvider>
          <p>app</p>
        </RealtimeProvider>
      </ToastProvider>
    </QueryClientProvider>,
  );

  const es = FakeEventSource.instances[0];
  act(() => {
    es.emit("transaction.received", {
      type: "transaction.received",
      transaction_id: "t1",
      direction: "in",
      amount_formatted: "R$ 50,00",
      at: "2026-01-01T00:00:00Z",
    });
  });

  expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: ["wallet"] });
  expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: ["transactions"] });
  expect(await screen.findByText("Você recebeu R$ 50,00.")).toBeInTheDocument();
});

it("does not treat the ping heartbeat as a business event", async () => {
  const queryClient = new QueryClient();
  const invalidateSpy = vi.spyOn(queryClient, "invalidateQueries");

  render(
    <QueryClientProvider client={queryClient}>
      <ToastProvider>
        <RealtimeProvider>
          <p>app</p>
        </RealtimeProvider>
      </ToastProvider>
    </QueryClientProvider>,
  );

  act(() => FakeEventSource.instances[0].emit("ping", {}));
  await waitFor(() => expect(invalidateSpy).not.toHaveBeenCalled());
});
