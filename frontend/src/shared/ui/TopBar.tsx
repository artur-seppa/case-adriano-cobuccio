// shared/ui/TopBar.tsx
"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import * as DropdownMenu from "@radix-ui/react-dropdown-menu";
import { useLogout } from "@/features/auth/hooks/useLogout";
import type { AuthUser } from "@/features/auth/api/auth";
import { Wordmark } from "./Wordmark";

function initials(name: string): string {
  return name
    .split(" ")
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join("");
}

export function TopBar({ user }: { user: AuthUser }) {
  const router = useRouter();
  const logout = useLogout();

  return (
    <header className="flex items-center justify-between border-b border-ink-500/10 px-5 py-4 sm:px-6">
      <Link
        href="/"
        aria-label="Ir para o início"
        className="rounded focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
      >
        <Wordmark className="text-lg text-ink-900" />
      </Link>
      <DropdownMenu.Root>
        <DropdownMenu.Trigger
          aria-label="Menu da conta"
          className="flex items-center gap-2 rounded-full py-1 pl-1 pr-3 transition-colors hover:bg-canvas"
        >
          <span className="flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-900">
            {initials(user.name)}
          </span>
          <span className="text-sm font-medium text-ink-900">{user.name}</span>
        </DropdownMenu.Trigger>
        <DropdownMenu.Portal>
          <DropdownMenu.Content
            align="end"
            sideOffset={4}
            className="min-w-[10rem] rounded-lg bg-surface p-1 shadow-elevation"
          >
            <DropdownMenu.Item asChild>
              <Link href="/settings" className="block rounded-md px-3 py-2 text-sm text-ink-900 hover:bg-canvas">
                Configurações
              </Link>
            </DropdownMenu.Item>
            <DropdownMenu.Item
              onSelect={() => logout.mutate(undefined, { onSuccess: () => router.push("/login") })}
              className="block cursor-pointer rounded-md px-3 py-2 text-sm text-danger-500 hover:bg-canvas"
            >
              Sair
            </DropdownMenu.Item>
          </DropdownMenu.Content>
        </DropdownMenu.Portal>
      </DropdownMenu.Root>
    </header>
  );
}
