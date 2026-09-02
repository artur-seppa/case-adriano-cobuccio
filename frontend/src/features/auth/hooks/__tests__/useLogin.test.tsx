import { renderHook, waitFor, act } from "@testing-library/react";
import { http, HttpResponse } from "msw";
import { server } from "@/shared/testing/server";
import { QueryClientProvider } from "@tanstack/react-query";
import { createQueryClient } from "@/shared/query/queryClient";
import { useLogin } from "../useLogin";

function wrapper({ children }: { children: React.ReactNode }) {
  return <QueryClientProvider client={createQueryClient()}>{children}</QueryClientProvider>;
}

it("bootstraps csrf before posting credentials", async () => {
  let csrfCalledBeforeLogin = false;
  server.use(
    http.get("/sanctum/csrf-cookie", () => {
      csrfCalledBeforeLogin = true;
      return new HttpResponse(null, { status: 204 });
    }),
    http.post("/api/login", () => {
      expect(csrfCalledBeforeLogin).toBe(true);
      return new HttpResponse(null, { status: 204 });
    }),
  );

  const { result } = renderHook(() => useLogin(), { wrapper });
  act(() => result.current.mutate({ email: "ana@example.test", password: "secret" }));
  await waitFor(() => expect(result.current.isSuccess).toBe(true));
});
