"use client";

import { useState } from "react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { NuqsAdapter } from "nuqs/adapters/next/app";
import { createQueryClient } from "@/shared/query/queryClient";

export function Providers({ children }: { children: React.ReactNode }) {
  const [client] = useState<QueryClient>(() => createQueryClient());
  return (
    <QueryClientProvider client={client}>
      <NuqsAdapter>{children}</NuqsAdapter>
    </QueryClientProvider>
  );
}
