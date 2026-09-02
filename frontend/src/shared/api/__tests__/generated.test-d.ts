import type { components } from "../generated/api.d.ts";

// Este arquivo não roda em runtime — falha de compilação (`npm run typecheck`)
// é o teste. Confirma que os schemas que as próximas tasks vão usar existem
// com o nome esperado no OpenAPI exportado pelo Scramble.
type _WalletCheck = components["schemas"]["WalletResource"];
type _TransactionCheck = components["schemas"]["TransactionResource"];
type _LedgerEntryCheck = components["schemas"]["LedgerEntryResource"];

export type { _WalletCheck, _TransactionCheck, _LedgerEntryCheck };
