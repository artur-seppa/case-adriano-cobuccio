// app/(app)/layout.tsx
"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { useSession } from "@/features/auth/hooks/useSession";
import { Sidebar } from "@/shared/ui/Sidebar";
import { Navbar } from "@/shared/ui/Navbar";
import { Skeleton } from "@/shared/ui/Skeleton";
import { Button } from "@/shared/ui/Button";

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
      <div className="flex min-h-screen flex-col items-center justify-center gap-3 bg-canvas">
        <p className="text-sm text-danger-500">Não foi possível verificar sua sessão.</p>
        <Button onClick={() => session.refetch()}>Tentar de novo</Button>
      </div>
    );
  }

  if (session.isLoading || !session.data) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-canvas">
        <Skeleton className="h-8 w-40" />
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-canvas">
      <Sidebar />
      <Navbar user={session.data} />
      <main className="ml-60 pt-16">
        <div className="mx-auto max-w-6xl p-6">{children}</div>
      </main>
    </div>
  );
}
