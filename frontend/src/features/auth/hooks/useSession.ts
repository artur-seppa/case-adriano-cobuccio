"use client";

import { useQuery } from "@tanstack/react-query";
import { fetchSession } from "../api/auth";
import { ApiError } from "@/shared/api/problem";

export function useSession() {
  return useQuery({
    queryKey: ["session"],
    queryFn: async () => {
      try {
        return await fetchSession();
      } catch (error) {
        if (error instanceof ApiError && error.status === 401) return null;
        throw error;
      }
    },
    staleTime: Infinity,
  });
}
