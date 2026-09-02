// shared/money/format.ts
export function centsToDecimalString(cents: number): string {
  const negative = cents < 0;
  const abs = Math.abs(Math.trunc(cents));
  const reais = Math.floor(abs / 100);
  const centavos = String(abs % 100).padStart(2, "0");
  return `${negative ? "-" : ""}${reais}.${centavos}`;
}

const BRL_FORMATTER = new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" });

export function formatCentsBRL(cents: number): string {
  return BRL_FORMATTER.format(cents / 100);
}

/**
 * Formats a decimal string that already came from the server (e.g. the
 * `available` field of an `insufficient-funds` error) for display. Never
 * uses `Number()`/`parseFloat` — pure string manipulation, same spirit as
 * `centsToDecimalString`: the value never becomes a float anywhere along
 * the way.
 */
export function formatDecimalBRL(decimal: string): string {
  const negative = decimal.startsWith("-");
  const unsigned = negative ? decimal.slice(1) : decimal;
  const [reais, centavos = "00"] = unsigned.split(".");
  const withThousands = reais.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
  return `${negative ? "-" : ""}R$ ${withThousands},${centavos}`;
}
