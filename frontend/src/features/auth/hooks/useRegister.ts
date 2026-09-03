"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { ensureCsrf } from "@/shared/api/csrf";
import { register } from "../api/auth";

export function useRegister() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: { name: string; email: string; password: string; password_confirmation: string }) => {
      await ensureCsrf();
      return register(input);
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["session"] }),
  });
}
