// shared/ui/Navbar.tsx
"use client";

import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import * as DropdownMenu from "@radix-ui/react-dropdown-menu";
import { useLogout } from "@/features/auth/hooks/useLogout";
import type { AuthUser } from "@/features/auth/api/auth";

const TITLES: Record<string, string> = {
  "/": "Dashboard",
  "/transactions": "Extrato",
  "/deposit": "Depositar",
  "/transfer": "Transferir",
  "/settings": "Configurações",
};

function initials(name: string): string {
  return name
    .split(" ")
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join("");
}

export function Navbar({ user }: { user: AuthUser }) {
  const pathname = usePathname();
  const router = useRouter();
  const logout = useLogout();

  const title = Object.entries(TITLES).find(([path]) => pathname === path || (path !== "/" && pathname.startsWith(path)))?.[1] ?? "";

  return (
    <header className="fixed inset-x-0 top-0 z-40 ml-60 flex h-16 items-center justify-between bg-surface px-6 shadow-elevation">
      <h1 className="text-lg font-semibold text-ink-900">{title}</h1>
      <DropdownMenu.Root>
        <DropdownMenu.Trigger className="flex items-center gap-2 rounded-full py-1 pl-1 pr-3 hover:bg-canvas">
          <span className="flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-sm font-semibold text-brand-900">
            {initials(user.name)}
          </span>
          <span className="text-sm font-medium text-ink-900">{user.name}</span>
        </DropdownMenu.Trigger>
        <DropdownMenu.Portal>
          <DropdownMenu.Content
            align="end"
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
