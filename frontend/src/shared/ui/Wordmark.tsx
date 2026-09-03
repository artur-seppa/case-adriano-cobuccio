// shared/ui/Wordmark.tsx

/**
 * The product wordmark. Color and size come from the call site (it sits on a
 * white bar in one place and a dark gradient panel in another), so this only
 * fixes the weight and letter-spacing that make it read as a mark.
 */
export function Wordmark({ className = "" }: { className?: string }) {
  return <span className={`font-semibold tracking-tight ${className}`}>Expenses Adriano Cobuccio</span>;
}
