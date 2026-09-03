// features/transactions/components/TransactionDetail.tsx
"use client";

import { useState, type ElementType } from "react";
import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import { Card } from "@/shared/ui/Card";
import { Badge } from "@/shared/ui/Badge";
import { Button } from "@/shared/ui/Button";
import { Skeleton } from "@/shared/ui/Skeleton";
import { useTransaction } from "../hooks/useTransaction";
import { ReversalDialog } from "./ReversalDialog";

function BackToStatement() {
  return (
    <Link
      href="/"
      className="inline-flex items-center gap-1.5 text-sm text-ink-500 transition-colors hover:text-brand-600"
    >
      <ArrowLeft size={14} aria-hidden="true" />
      Voltar ao extrato
    </Link>
  );
}

/**
 * `embedded` = rendered inside the detail modal: the modal is the panel and has
 * its own close affordance, so drop the standalone card chrome and the
 * back-to-extrato link (those are for the `/transactions/[id]` route view).
 */
export function TransactionDetail({ id, embedded = false }: { id: string; embedded?: boolean }) {
  const transaction = useTransaction(id);
  const [reversalOpen, setReversalOpen] = useState(false);

  const Panel: ElementType = embedded ? "div" : Card;

  if (transaction.isLoading) {
    return (
      <Panel>
        <Skeleton className="h-32" />
      </Panel>
    );
  }

  if (transaction.isError || !transaction.data) {
    return (
      <div className="flex flex-col gap-4">
        {!embedded && <BackToStatement />}
        <Panel>
          <p className="text-sm text-danger-500">Não foi possível carregar esta transação.</p>
        </Panel>
      </div>
    );
  }

  const t = transaction.data;
  const isIncoming = t.direction === "in";
  const canOfferReversal = t.type !== "reversal" && !t.reversal.is_reversed;

  return (
    <div className="flex flex-col gap-4">
      {!embedded && <BackToStatement />}
      <Panel className="flex flex-col gap-2">
        <div className="flex items-center justify-between">
          <p className={`text-3xl font-semibold ${isIncoming ? "text-brand-600" : "text-danger-500"}`}>
            {isIncoming ? "+" : "-"} {t.amount_formatted}
          </p>
          {t.status === "reversed" && <Badge variant="neutral">Estornada</Badge>}
        </div>
        <p className="text-sm text-ink-500">{t.counterparty.label}</p>
        {t.description && <p className="text-sm text-ink-900">{t.description}</p>}
        <p className="text-xs text-ink-500">{new Date(t.created_at).toLocaleString("pt-BR")}</p>
      </Panel>

      {canOfferReversal && (
        <div>
          <Button variant="danger" onClick={() => setReversalOpen(true)}>
            Estornar
          </Button>
        </div>
      )}

      <ReversalDialog transactionId={t.id} open={reversalOpen} onOpenChange={setReversalOpen} />
    </div>
  );
}
