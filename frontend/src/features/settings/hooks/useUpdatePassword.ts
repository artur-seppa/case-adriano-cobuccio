"use client";
import { useMutation } from "@tanstack/react-query";
import { updatePassword } from "../api/settings";

export function useUpdatePassword() {
  return useMutation({ mutationFn: updatePassword });
}
