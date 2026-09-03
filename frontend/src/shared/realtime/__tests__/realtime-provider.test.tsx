import { render, screen, waitFor, act } from "@testing-library/react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { ToastProvider } from "@/shared/ui/Toast";
import { RealtimeProvider } from "../realtime-provider";
import { ConnectionBanner } from "../ConnectionBanner";

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

function renderWithBanner() {
  return render(
    <QueryClientProvider client={new QueryClient()}>
      <ToastProvider>
        <RealtimeProvider>
          <ConnectionBanner />
        </RealtimeProvider>
      </ToastProvider>
    </QueryClientProvider>,
  );
}

it("does not flash the reconnecting banner when the stream recovers within the grace period", () => {
  vi.useFakeTimers();
  try {
    renderWithBanner();
    const es = FakeEventSource.instances[0];

    act(() => es.onopen?.());
    act(() => es.onerror?.());
    act(() => vi.advanceTimersByTime(1000)); // still inside the grace window

    expect(screen.queryByText("Reconectando…")).not.toBeInTheDocument();

    act(() => es.onopen?.()); // reconnected
    act(() => vi.advanceTimersByTime(10_000));

    expect(screen.queryByText("Reconectando…")).not.toBeInTheDocument();
  } finally {
    vi.useRealTimers();
  }
});

it("shows the reconnecting banner once the stream stays down past the grace period", () => {
  vi.useFakeTimers();
  try {
    renderWithBanner();
    const es = FakeEventSource.instances[0];

    act(() => es.onopen?.());
    act(() => es.onerror?.());
    act(() => vi.advanceTimersByTime(3000)); // past the grace window

    expect(screen.getByText("Reconectando…")).toBeInTheDocument();
  } finally {
    vi.useRealTimers();
  }
});
