// shared/realtime/realtime-provider.tsx
"use client";

import { createContext, useContext, useEffect, useRef, useState } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { useToast } from "@/shared/ui/Toast";
import { walletKeys } from "@/features/wallet/queryKeys";
import { REALTIME_EVENT_TYPES, toastFor, type RealtimePayload } from "./events";

type Status = "connecting" | "open" | "reconnecting" | "down";

const RealtimeStatusContext = createContext<Status>("connecting");

export function useRealtimeStatus() {
  return useContext(RealtimeStatusContext);
}

// EventSource drops and re-establishes its connection routinely (server worker
// recycles, brief network blips) and heals itself within a few seconds. Only
// surface "reconnecting" once an outage outlasts this grace window, so the
// banner reflects real trouble instead of flickering on every reconnect.
const RECONNECT_GRACE_MS = 2_500;
const DOWN_AFTER_MS = 15_000;

export function RealtimeProvider({ children }: { children: React.ReactNode }) {
  const queryClient = useQueryClient();
  const { toast } = useToast();
  const [status, setStatus] = useState<Status>("connecting");
  const graceTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const downTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    const source = new EventSource("/api/v1/stream", { withCredentials: true });

    function clearTimers() {
      for (const timer of [graceTimer, downTimer]) {
        if (timer.current) {
          clearTimeout(timer.current);
          timer.current = null;
        }
      }
    }

    source.onopen = () => {
      setStatus("open");
      clearTimers();
    };

    source.onerror = () => {
      if (graceTimer.current || downTimer.current) return; // already mid-outage
      graceTimer.current = setTimeout(() => setStatus("reconnecting"), RECONNECT_GRACE_MS);
      downTimer.current = setTimeout(() => setStatus("down"), DOWN_AFTER_MS);
    };

    function handleBusinessEvent(event: MessageEvent) {
      const payload = JSON.parse(event.data) as RealtimePayload;
      queryClient.invalidateQueries({ queryKey: walletKeys.all });
      queryClient.invalidateQueries({ queryKey: ["transactions"] });
      toast(toastFor(payload));
    }

    for (const type of REALTIME_EVENT_TYPES) {
      source.addEventListener(type, handleBusinessEvent);
    }
    source.addEventListener("ping", () => {});

    return () => {
      clearTimers();
      source.close();
    };
  }, [queryClient, toast]);

  return <RealtimeStatusContext.Provider value={status}>{children}</RealtimeStatusContext.Provider>;
}
