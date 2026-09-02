// shared/realtime/events.ts
export const REALTIME_EVENT_TYPES = [
  "deposit.completed",
  "transaction.sent",
  "transaction.received",
  "transaction.reversed",
] as const;

export type RealtimeEventType = (typeof REALTIME_EVENT_TYPES)[number];

export interface RealtimePayload {
  type: RealtimeEventType;
  transaction_id: string;
  direction: "in" | "out";
  amount_formatted: string;
  at: string;
}

export function toastFor(payload: RealtimePayload): { title: string; variant: "success" | "danger" } {
  switch (payload.type) {
    case "deposit.completed":
      return { title: `Depósito de ${payload.amount_formatted} recebido.`, variant: "success" };
    case "transaction.sent":
      return { title: `Você enviou ${payload.amount_formatted}.`, variant: "success" };
    case "transaction.received":
      return { title: `Você recebeu ${payload.amount_formatted}.`, variant: "success" };
    case "transaction.reversed":
      return { title: `Uma transação de ${payload.amount_formatted} foi estornada.`, variant: "danger" };
  }
}
