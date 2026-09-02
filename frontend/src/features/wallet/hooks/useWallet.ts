"use client";

import { useQuery } from "@tanstack/react-query";
import { fetchWallet } from "../api/wallet";
import { walletKeys } from "../queryKeys";

export function useWallet() {
  return useQuery({
    queryKey: walletKeys.all,
    queryFn: fetchWallet,
    staleTime: 60_000,
    gcTime: 5 * 60_000,
    refetchOnWindowFocus: true,
  });
}
