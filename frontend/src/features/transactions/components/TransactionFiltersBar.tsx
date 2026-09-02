// features/transactions/components/TransactionFiltersBar.tsx
"use client";

import { useTransactionFilters } from "../hooks/useTransactionFilters";

const TYPE_OPTIONS = [
  { value: "", label: "Todos os tipos" },
  { value: "deposit", label: "Depósito" },
  { value: "transfer", label: "Transferência" },
  { value: "reversal", label: "Estorno" },
] as const;

const DIRECTION_OPTIONS = [
  { value: "", label: "Todas as direções" },
  { value: "in", label: "Entrada" },
  { value: "out", label: "Saída" },
] as const;

const STATUS_OPTIONS = [
  { value: "", label: "Todos os status" },
  { value: "completed", label: "Concluída" },
  { value: "reversed", label: "Estornada" },
] as const;

function selectClass() {
  return "rounded-lg border border-ink-500/20 bg-surface px-3 py-2 text-sm text-ink-900";
}

export function TransactionFiltersBar() {
  const [, setFilters] = useTransactionFilters();

  return (
    <div className="flex flex-wrap gap-3 p-4">
      <select
        aria-label="Tipo"
        className={selectClass()}
        onChange={(e) => setFilters({ type: (e.target.value || null) as never })}
      >
        {TYPE_OPTIONS.map((opt) => (
          <option key={opt.value} value={opt.value}>
            {opt.label}
          </option>
        ))}
      </select>
      <select
        aria-label="Direção"
        className={selectClass()}
        onChange={(e) => setFilters({ direction: (e.target.value || null) as never })}
      >
        {DIRECTION_OPTIONS.map((opt) => (
          <option key={opt.value} value={opt.value}>
            {opt.label}
          </option>
        ))}
      </select>
      <select
        aria-label="Status"
        className={selectClass()}
        onChange={(e) => setFilters({ status: (e.target.value || null) as never })}
      >
        {STATUS_OPTIONS.map((opt) => (
          <option key={opt.value} value={opt.value}>
            {opt.label}
          </option>
        ))}
      </select>
    </div>
  );
}
