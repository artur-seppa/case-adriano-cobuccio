"use client";
import { useQuery } from "@tanstack/react-query";
import { fetchSessions } from "../api/settings";

export function useSessions() {
  return useQuery({ queryKey: ["sessions"], queryFn: fetchSessions });
}
