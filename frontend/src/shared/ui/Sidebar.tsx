// shared/ui/Sidebar.tsx
"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";

const ITEMS = [
  { href: "/", label: "Dashboard" },
  { href: "/transactions", label: "Extrato" },
  { href: "/deposit", label: "Depositar" },
  { href: "/transfer", label: "Transferir" },
  { href: "/settings", label: "Configurações" },
];

export function Sidebar() {
  const pathname = usePathname();

  return (
    <aside className="fixed inset-y-0 left-0 w-60 bg-surface p-4 shadow-elevation">
      <div className="mb-6 px-2 text-lg font-semibold text-ink-900">Carteira</div>
      <nav className="flex flex-col gap-1">
        {ITEMS.map((item) => {
          const active = item.href === "/" ? pathname === "/" : pathname.startsWith(item.href);
          return (
            <Link
              key={item.href}
              href={item.href}
              className={`rounded-lg px-3 py-2.5 text-sm font-medium transition-colors ${
                active ? "bg-brand-500 text-white" : "text-ink-900 hover:bg-canvas"
              }`}
            >
              {item.label}
            </Link>
          );
        })}
      </nav>
    </aside>
  );
}
