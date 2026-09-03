import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { useState } from "react";
import { MoneyInput } from "../MoneyInput";

function Harness() {
  const [cents, setCents] = useState(0);
  return (
    <>
      <MoneyInput label="Valor" value={cents} onChange={setCents} />
      <output>{cents}</output>
    </>
  );
}

it("keeps internal state as integer cents while typing digits", async () => {
  const user = userEvent.setup();
  render(<Harness />);

  const input = screen.getByLabelText("Valor");
  await user.type(input, "15000");

  expect(input).toHaveValue("R$ 150,00");
  expect(screen.getByRole("status")).toHaveTextContent("15000");
});

it("ignores non-digit characters", async () => {
  const user = userEvent.setup();
  render(<Harness />);

  await user.type(screen.getByLabelText("Valor"), "1a2b3");
  expect(screen.getByRole("status")).toHaveTextContent("123");
});
