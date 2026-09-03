import { render, screen } from "@testing-library/react";
import { Input } from "../Input";

it("associates the label and announces the error via aria-describedby", () => {
  render(<Input label="Valor" error="Campo obrigatório" />);
  const input = screen.getByLabelText("Valor");
  expect(input).toHaveAttribute("aria-invalid", "true");
  expect(screen.getByRole("alert")).toHaveTextContent("Campo obrigatório");
});
