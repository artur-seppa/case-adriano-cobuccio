import { render, screen } from "@testing-library/react";
import { TransactionRow } from "../TransactionRow";
import type { TransactionResource } from "../../api/transactions";

const base: TransactionResource = {
  id: "t1",
  type: "transfer",
  status: "completed",
  amount: "50.00",
  amount_cents: 5000,
  amount_formatted: "R$ 50,00",
  currency: "BRL",
  direction: "in",
  counterparty: { label: "Maria", name: "Maria", email: "maria@example.test" },
  description: null,
  reversal: { is_reversed: false, reversal_of_transaction_id: null, reason: null },
  created_at: "2026-01-01T00:00:00Z",
  metadata: [],
};

it("shows an incoming amount in green with a plus sign", () => {
  render(<TransactionRow transaction={base} />);
  expect(screen.getByText("+ R$ 50,00").className).toContain("text-brand-600");
});

it("strikes through the counterparty label and shows a badge when reversed", () => {
  render(<TransactionRow transaction={{ ...base, status: "reversed" }} />);
  expect(screen.getByText("Maria").className).toContain("line-through");
  expect(screen.getByText("Estornada")).toBeInTheDocument();
});
