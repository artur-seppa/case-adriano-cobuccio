"use client";

import { useEffect, useRef } from "react";
import { usePathname, useRouter } from "next/navigation";
import { Card } from "@/shared/ui/Card";
import { Wordmark } from "@/shared/ui/Wordmark";
import { useSession } from "@/features/auth/hooks/useSession";

// Only these redirect an already-authenticated visitor back to "/" — visiting
// /reset-password or /verify-email while logged in (e.g. from a stale email
// link, or re-verifying after a session already exists) is a legitimate flow.
const REDIRECT_IF_AUTHENTICATED_PATHS = ["/login", "/register", "/forgot-password"];

export default function AuthLayout({ children }: { children: React.ReactNode }) {
  const router = useRouter();
  const pathname = usePathname();
  const session = useSession();

  const shouldRedirectIfAuthenticated = REDIRECT_IF_AUTHENTICATED_PATHS.some((path) =>
    pathname.startsWith(path),
  );

  // Only the *first* session resolution counts — this guard is for a visitor
  // who lands here already logged in (typed the URL, hit back). A session
  // that flips from null -> authenticated *after* that (a successful
  // login/register submitted from this very page) must not compete with
  // that flow's own onSuccess navigation (e.g. RegisterForm routing to
  // /verify-email) — this stays silent once it's made its one decision.
  const initialCheckDone = useRef(false);

  useEffect(() => {
    if (initialCheckDone.current || !session.isSuccess) return;
    initialCheckDone.current = true;

    if (shouldRedirectIfAuthenticated && session.data !== null) {
      // An unverified session has no app access (see (app)/layout.tsx) — send it
      // to the verification notice instead of bouncing through the app gate.
      router.replace(session.data.email_verified_at === null ? "/verify-email" : "/");
    }
  }, [shouldRedirectIfAuthenticated, session.isSuccess, session.data, router]);

  return (
    <div
      className="flex min-h-screen flex-col items-center justify-center gap-6 px-4 py-12"
      style={{ background: "linear-gradient(135deg, #0E3D12 0%, #2E7D32 55%, #4CAF4F 100%)" }}
    >
      <div className="flex flex-col items-center gap-1 text-center">
        <Wordmark className="text-3xl text-white" />
        <p className="text-sm text-white/80">Deposite, transfira, acompanhe.</p>
      </div>
      <Card className="w-full max-w-sm p-6">{children}</Card>
    </div>
  );
}
