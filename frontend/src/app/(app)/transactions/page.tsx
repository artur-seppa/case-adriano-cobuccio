import { Suspense } from "react";
import { TransactionList } from "@/features/transactions/components/TransactionList";

export default function TransactionsPage() {
  return (
    <Suspense>
      <TransactionList />
    </Suspense>
  );
}
