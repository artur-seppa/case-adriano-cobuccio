"use client";

import { Suspense } from "react";
import { BalanceCard } from "@/features/wallet/components/BalanceCard";
import { DepositModal } from "@/features/deposit/components/DepositModal";
import { TransferModal } from "@/features/transfer/components/TransferModal";
import { TransactionList } from "@/features/transactions/components/TransactionList";
import { Button } from "@/shared/ui/Button";

export default function DashboardPage() {
  return (
    <div className="divide-y divide-ink-500/10">
      <section className="pb-6">
        <BalanceCard
          actions={
            <>
              <DepositModal trigger={<Button>Depositar</Button>} />
              <TransferModal trigger={<Button variant="secondary">Transferir</Button>} />
            </>
          }
        />
      </section>

      <section className="pt-6">
        <Suspense>
          <TransactionList />
        </Suspense>
      </section>
    </div>
  );
}
