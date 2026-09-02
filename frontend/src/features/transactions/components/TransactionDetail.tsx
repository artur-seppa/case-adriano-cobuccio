// features/transactions/components/TransactionDetail.tsx
"use client";

import { useState } from "react";
import { Card } from "@/shared/ui/Card";
import { Badge } from "@/shared/ui/Badge";
import { Button } from "@/shared/ui/Button";
import { Skeleton } from "@/shared/ui/Skeleton";
import { useTransaction } from "../hooks/useTransaction";
import { ReversalDialog } from "./ReversalDialog";

export function TransactionDetail({ id }: { id: string }) {
  const transaction = useTransaction(id);
  const [reversalOpen, setReversalOpen] = useState(false);

  if (transaction.isLoading) {
    return (
      <Card>
        <Skeleton className="h-32" />
      </Card>
    );
  }

  if (transaction.isError || !transaction.data) {
    return (
      <Card>
        <p className="text-sm text-danger-500">Não foi possível carregar esta transação.</p>
      </Card>
    );
  }

  const t = transaction.data;
  const isIncoming = t.direction === "in";
  const canOfferReversal = t.type !== "reversal" && !t.reversal.is_reversed;

  return (
    <div className="flex flex-col gap-4">
      <Card className="flex flex-col gap-2">
        <div className="flex items-center justify-between">
          <p className={`text-3xl font-semibold ${isIncoming ? "text-brand-600" : "text-danger-500"}`}>
            {isIncoming ? "+" : "-"} {t.amount_formatted}
          </p>
          {t.status === "reversed" && <Badge variant="neutral">Estornada</Badge>}
        </div>
        <p className="text-sm text-ink-500">{t.counterparty.label}</p>
        {t.description && <p className="text-sm text-ink-900">{t.description}</p>}
        <p className="text-xs text-ink-500">{new Date(t.created_at).toLocaleString("pt-BR")}</p>
      </Card>

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
