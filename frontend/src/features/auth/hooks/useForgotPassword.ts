"use client";

import { useMutation } from "@tanstack/react-query";
import { ensureCsrf } from "@/shared/api/csrf";
import { forgotPassword } from "../api/auth";

export function useForgotPassword() {
  return useMutation({
    mutationFn: async (input: { email: string }) => {
      await ensureCsrf();
      await forgotPassword(input);
    },
  });
}
