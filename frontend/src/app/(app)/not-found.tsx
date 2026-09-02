// app/(app)/not-found.tsx
import Link from "next/link";

export default function AppNotFound() {
  return (
    <div className="flex flex-col items-center gap-2 py-20 text-center">
      <p className="text-lg font-semibold text-ink-900">Página não encontrada</p>
      <Link href="/" className="text-sm text-brand-600 hover:underline">
        Voltar ao dashboard
      </Link>
    </div>
  );
}
