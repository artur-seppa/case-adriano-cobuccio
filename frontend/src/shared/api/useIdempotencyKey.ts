// shared/api/useIdempotencyKey.ts
"use client";

import { useRef } from "react";

export function useIdempotencyKey() {
  const ref = useRef<string | null>(null);
  if (ref.current == null) {
    ref.current = crypto.randomUUID();
  }

  return {
    get: () => ref.current as string,
    reset: () => {
      ref.current = crypto.randomUUID();
    },
  };
}
