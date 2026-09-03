"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { ensureCsrf } from "@/shared/api/csrf";
import { login } from "../api/auth";

export function useLogin() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: { email: string; password: string }) => {
      await ensureCsrf();
      await login(input);
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["session"] }),
  });
}
