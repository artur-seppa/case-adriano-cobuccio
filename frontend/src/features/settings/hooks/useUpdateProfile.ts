"use client";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { updateProfile } from "../api/settings";

export function useUpdateProfile() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: updateProfile,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["session"] }),
  });
}
