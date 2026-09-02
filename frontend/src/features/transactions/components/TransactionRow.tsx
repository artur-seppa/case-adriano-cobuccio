import Link from "next/link";
import { Badge } from "@/shared/ui/Badge";
import type { TransactionResource } from "../api/transactions";

const DIRECTION_ICON: Record<TransactionResource["direction"], string> = {
  in: "↓",
  out: "↑",
  self: "↔",
};

export function TransactionRow({ transaction }: { transaction: TransactionResource }) {
  const isReversed = transaction.status === "reversed";
  const isIncoming = transaction.direction === "in";

  return (
    <Link
      href={`/transactions/${transaction.id}`}
      className="flex items-center justify-between gap-4 px-4 py-3 hover:bg-canvas"
    >
      <div className="flex items-center gap-3">
        <span
          aria-hidden
          className={`flex h-8 w-8 items-center justify-center rounded-full text-sm ${
            isIncoming ? "bg-brand-50 text-brand-900" : "bg-ink-500/10 text-ink-900"
          }`}
        >
          {DIRECTION_ICON[transaction.direction]}
        </span>
        <div>
          <p className={`text-sm font-medium text-ink-900 ${isReversed ? "line-through" : ""}`}>
            {transaction.counterparty.label}
          </p>
          <p className="text-xs text-ink-500">{new Date(transaction.created_at).toLocaleString("pt-BR")}</p>
        </div>
      </div>
      <div className="flex items-center gap-2">
        {isReversed && <Badge variant="neutral">Estornada</Badge>}
        <span className={`text-sm font-semibold ${isIncoming ? "text-brand-600" : "text-danger-500"}`}>
          {isIncoming ? "+" : "-"} {transaction.amount_formatted}
        </span>
      </div>
    </Link>
  );
}
