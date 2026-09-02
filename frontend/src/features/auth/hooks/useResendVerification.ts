"use client";

import { useMutation } from "@tanstack/react-query";
import { resendVerification } from "../api/auth";

export function useResendVerification() {
  return useMutation({ mutationFn: resendVerification });
}
