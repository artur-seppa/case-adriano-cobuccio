// shared/ui/Badge.tsx
type Variant = "success" | "danger" | "warning" | "neutral";

const CLASSES: Record<Variant, string> = {
  success: "bg-brand-50 text-brand-900",
  danger: "bg-danger-500/10 text-danger-500",
  warning: "bg-warning-500/10 text-warning-500",
  neutral: "bg-ink-500/10 text-ink-500",
};

export function Badge({ variant = "neutral", children }: { variant?: Variant; children: React.ReactNode }) {
  return (
    <span className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ${CLASSES[variant]}`}>
      {children}
    </span>
  );
}
