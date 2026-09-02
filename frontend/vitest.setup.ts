import "@testing-library/jest-dom/vitest";
import { server } from "@/shared/testing/server";

// next/font/google relies on a webpack/SWC loader that only exists inside Next's
// own bundler — under Vitest the real module resolves to an empty stub, so calling
// e.g. Inter(...) throws. Mock it the way Next's own Vitest guide recommends:
// https://nextjs.org/docs/app/building-your-application/testing/vitest
vi.mock("next/font/google", () => ({
  Inter: () => ({ className: "", style: {}, variable: "--font-sans" }),
}));

beforeAll(() => server.listen({ onUnhandledRequest: "error" }));
afterEach(() => server.resetHandlers());
afterAll(() => server.close());
