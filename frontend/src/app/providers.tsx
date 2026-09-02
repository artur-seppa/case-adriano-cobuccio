"use client";

import { useState } from "react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { createQueryClient } from "@/shared/query/queryClient";

export function Providers({ children }: { children: React.ReactNode }) {
  const [client] = useState<QueryClient>(() => createQueryClient());
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}
