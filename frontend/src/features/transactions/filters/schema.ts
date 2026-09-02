// features/transactions/filters/schema.ts
import { parseAsStringEnum, parseAsString } from "nuqs";

export const transactionFilterParsers = {
  type: parseAsStringEnum(["deposit", "transfer", "reversal"] as const),
  direction: parseAsStringEnum(["in", "out"] as const),
  status: parseAsStringEnum(["completed", "reversed"] as const),
  from: parseAsString,
  to: parseAsString,
};
