import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { ToastProvider, useToast } from "../Toast";

function Harness() {
  const { toast } = useToast();
  return <button onClick={() => toast({ title: "Você recebeu R$ 50,00" })}>disparar</button>;
}

it("renders a toast in a live region when toast() is called", async () => {
  const user = userEvent.setup();
  render(
    <ToastProvider>
      <Harness />
    </ToastProvider>,
  );

  await user.click(screen.getByRole("button", { name: "disparar" }));
  expect(await screen.findByText("Você recebeu R$ 50,00")).toBeInTheDocument();
});
