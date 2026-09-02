"use client";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { revokeSession } from "../api/settings";

export function useRevokeSession() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: revokeSession,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["sessions"] }),
  });
}
