"use client";

import { useEffect } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { useRouter } from "next/navigation";

export function AuthEventBridge() {
  const queryClient = useQueryClient();
  const router = useRouter();

  useEffect(() => {
    function handleUnauthenticated() {
      queryClient.setQueryData(["session"], null);
      router.push("/login");
    }
    window.addEventListener("wallet:unauthenticated", handleUnauthenticated);
    return () => window.removeEventListener("wallet:unauthenticated", handleUnauthenticated);
  }, [queryClient, router]);

  return null;
}
