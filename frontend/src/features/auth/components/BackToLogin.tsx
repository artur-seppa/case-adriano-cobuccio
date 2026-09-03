import Link from "next/link";
import { ArrowLeft } from "lucide-react";

export function BackToLogin() {
  return (
    <Link
      href="/login"
      className="mb-4 inline-flex items-center gap-1.5 text-sm text-ink-500 transition-colors hover:text-brand-600"
    >
      <ArrowLeft size={14} aria-hidden="true" />
      Voltar para o login
    </Link>
  );
}
