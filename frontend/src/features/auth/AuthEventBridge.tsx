"use client";

import { useEffect, useRef } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { usePathname, useRouter } from "next/navigation";

// Public pages that legitimately call an authenticated-only endpoint (this
// layout's own session check, e.g.) and get a 401 back — that 401 must not
// bounce the visitor away from the page they're already looking at.
const PUBLIC_PATHS = ["/login", "/register", "/forgot-password", "/reset-password", "/verify-email"];

export function AuthEventBridge() {
  const queryClient = useQueryClient();
  const router = useRouter();
  const pathname = usePathname();
  const pathnameRef = useRef(pathname);

  useEffect(() => {
    pathnameRef.current = pathname;
  }, [pathname]);

  useEffect(() => {
    function handleUnauthenticated() {
      queryClient.setQueryData(["session"], null);
      if (PUBLIC_PATHS.some((path) => pathnameRef.current.startsWith(path))) return;
      router.push("/login");
    }
    window.addEventListener("wallet:unauthenticated", handleUnauthenticated);
    return () => window.removeEventListener("wallet:unauthenticated", handleUnauthenticated);
  }, [queryClient, router]);

  return null;
}
