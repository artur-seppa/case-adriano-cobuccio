"use client";

import { useMutation } from "@tanstack/react-query";
import { ensureCsrf } from "@/shared/api/csrf";
import { resetPassword } from "../api/auth";

export function useResetPassword() {
  return useMutation({
    mutationFn: async (input: { token: string; email: string; password: string; password_confirmation: string }) => {
      await ensureCsrf();
      await resetPassword(input);
    },
  });
}
