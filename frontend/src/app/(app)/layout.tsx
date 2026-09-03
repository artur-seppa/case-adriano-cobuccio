// app/(app)/layout.tsx
"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { useSession } from "@/features/auth/hooks/useSession";
import { TopBar } from "@/shared/ui/TopBar";
import { Skeleton } from "@/shared/ui/Skeleton";
import { Button } from "@/shared/ui/Button";
import { RealtimeProvider } from "@/shared/realtime/realtime-provider";
import { ConnectionBanner } from "@/shared/realtime/ConnectionBanner";

const APP_BACKGROUND = "linear-gradient(135deg, #0E3D12 0%, #2E7D32 55%, #4CAF4F 100%)";

export default function AppLayout({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const session = useSession();

  useEffect(() => {
    if (session.isSuccess && session.data === null) {
      router.replace("/login");
    }
  }, [session.isSuccess, session.data, router]);

  if (session.isError) {
    return (
      <div className="flex min-h-screen flex-col items-center justify-center gap-3" style={{ background: APP_BACKGROUND }}>
        <p className="text-sm text-white">Não foi possível verificar sua sessão.</p>
        <Button onClick={() => session.refetch()}>Tentar de novo</Button>
      </div>
    );
  }

  if (session.isLoading || !session.data) {
    return (
      <div className="flex min-h-screen items-center justify-center" style={{ background: APP_BACKGROUND }}>
        <Skeleton className="h-8 w-40" />
      </div>
    );
  }

  return (
    <RealtimeProvider>
      <ConnectionBanner />
      <div className="min-h-screen" style={{ background: APP_BACKGROUND }}>
        <div className="mx-auto max-w-5xl px-4 py-6 sm:py-10">
          <div className="overflow-hidden rounded-2xl bg-surface shadow-elevation">
            <TopBar user={session.data} />
            <div className="p-5 sm:p-6">{children}</div>
          </div>
        </div>
      </div>
    </RealtimeProvider>
  );
}
