// shared/realtime/ConnectionBanner.tsx
"use client";

import { useEffect, useState } from "react";
import { useRealtimeStatus } from "./realtime-provider";

export function ConnectionBanner() {
  const status = useRealtimeStatus();
  const [online, setOnline] = useState(() => typeof navigator === "undefined" || navigator.onLine);

  useEffect(() => {
    const goOnline = () => setOnline(true);
    const goOffline = () => setOnline(false);
    window.addEventListener("online", goOnline);
    window.addEventListener("offline", goOffline);
    return () => {
      window.removeEventListener("online", goOnline);
      window.removeEventListener("offline", goOffline);
    };
  }, []);

  if (!online) {
    return (
      <div role="status" className="bg-danger-500 py-1.5 text-center text-sm text-white">
        Sem conexão com a internet.
      </div>
    );
  }

  if (status === "reconnecting" || status === "down") {
    return (
      <div role="status" className="bg-warning-500 py-1.5 text-center text-sm text-white">
        Reconectando…
      </div>
    );
  }

  return null;
}
