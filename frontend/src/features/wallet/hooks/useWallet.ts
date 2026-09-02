"use client";

import { useQuery } from "@tanstack/react-query";
import { fetchWallet } from "../api/wallet";
import { walletKeys } from "../queryKeys";
import { useRealtimeStatus } from "@/shared/realtime/realtime-provider";

export function useWallet() {
  const status = useRealtimeStatus();
  return useQuery({
    queryKey: walletKeys.all,
    queryFn: fetchWallet,
    staleTime: 60_000,
    gcTime: 5 * 60_000,
    refetchOnWindowFocus: true,
    refetchInterval: status === "down" ? 30_000 : false,
  });
}
