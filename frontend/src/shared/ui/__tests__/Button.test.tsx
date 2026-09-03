import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { Button } from "../Button";

it("fires onClick when enabled", async () => {
  const user = userEvent.setup();
  const onClick = vi.fn();
  render(<Button onClick={onClick}>Enviar</Button>);

  await user.click(screen.getByRole("button", { name: "Enviar" }));
  expect(onClick).toHaveBeenCalledOnce();
});

it("disables the button and marks it busy while loading", () => {
  render(<Button loading>Enviar</Button>);
  const button = screen.getByRole("button", { name: "Enviar" });
  expect(button).toBeDisabled();
  expect(button).toHaveAttribute("aria-busy", "true");
});
