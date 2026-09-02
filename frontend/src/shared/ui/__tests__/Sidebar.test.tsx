import { render, screen } from "@testing-library/react";
import { Sidebar } from "../Sidebar";

vi.mock("next/navigation", () => ({ usePathname: () => "/transactions" }));

it("marks the current route's item as active", () => {
  render(<Sidebar />);
  const active = screen.getByRole("link", { name: "Extrato" });
  expect(active.className).toContain("bg-brand-500");
  expect(screen.getByRole("link", { name: "Dashboard" }).className).not.toContain("bg-brand-500");
});
