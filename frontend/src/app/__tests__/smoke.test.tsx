// RootLayout returns a full <html>/<body> shell. Rendering that through
// @testing-library/react's `render` nests it inside jsdom's existing <html><body>,
// which React flags as invalid HTML nesting (a stderr warning, not a failure) —
// per the task brief, we render to a static string instead so the smoke test
// stays pristine while still exercising the real layout output.
import { renderToStaticMarkup } from "react-dom/server";
import RootLayout from "../layout";

// RootLayout mounts AuthEventBridge (Task 9), which calls useRouter() — outside
// Next's app router context that requires mocking, same as other component tests.
vi.mock("next/navigation", () => ({ useRouter: () => ({ push: vi.fn(), replace: vi.fn() }) }));

it("renders children inside the html/body shell", () => {
  const html = renderToStaticMarkup(
    <RootLayout>
      <p>hello</p>
    </RootLayout>,
  );
  expect(html).toContain("hello");
});
