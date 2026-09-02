"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { walletKeys } from "@/features/wallet/queryKeys";
import type { WalletResource } from "@/features/wallet/api/wallet";
import { centsToDecimalString, formatCentsBRL } from "@/shared/money/format";
import { transferFunds, type TransferInput } from "../api/transfer";

export function useTransfer() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: transferFunds,
    onMutate: async (input: TransferInput) => {
      await queryClient.cancelQueries({ queryKey: walletKeys.all });
      const previous = queryClient.getQueryData<WalletResource>(walletKeys.all);

      if (previous) {
        const nextCents = previous.balance_cents - input.amountCents;
        queryClient.setQueryData<WalletResource>(walletKeys.all, {
          ...previous,
          balance_cents: nextCents,
          balance: centsToDecimalString(nextCents),
          balance_formatted: formatCentsBRL(nextCents),
        });
      }

      return { previous };
    },
    onError: (_error, _input, context) => {
      if (context?.previous) {
        queryClient.setQueryData(walletKeys.all, context.previous);
      }
    },
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: walletKeys.all });
      queryClient.invalidateQueries({ queryKey: ["transactions"] });
    },
  });
}
