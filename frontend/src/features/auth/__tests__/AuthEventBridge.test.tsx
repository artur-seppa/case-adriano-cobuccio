import { render, act } from "@testing-library/react";
import { QueryClientProvider } from "@tanstack/react-query";
import { createQueryClient } from "@/shared/query/queryClient";
import { AuthEventBridge } from "../AuthEventBridge";

const push = vi.fn();
let pathname = "/login";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push }),
  usePathname: () => pathname,
}));

beforeEach(() => {
  push.mockClear();
  pathname = "/login";
});

it("does not navigate away when a 401 fires while already on a public auth page", () => {
  render(
    <QueryClientProvider client={createQueryClient()}>
      <AuthEventBridge />
    </QueryClientProvider>,
  );

  act(() => {
    window.dispatchEvent(new CustomEvent("wallet:unauthenticated"));
  });

  expect(push).not.toHaveBeenCalled();
});

it("navigates to /login when a 401 fires on a protected page", () => {
  pathname = "/transactions";

  render(
    <QueryClientProvider client={createQueryClient()}>
      <AuthEventBridge />
    </QueryClientProvider>,
  );

  act(() => {
    window.dispatchEvent(new CustomEvent("wallet:unauthenticated"));
  });

  expect(push).toHaveBeenCalledWith("/login");
});
