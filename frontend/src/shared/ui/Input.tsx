// shared/ui/Input.tsx
import { InputHTMLAttributes, forwardRef, useId } from "react";
import * as Label from "@radix-ui/react-label";

interface Props extends InputHTMLAttributes<HTMLInputElement> {
  label: string;
  error?: string;
}

export const Input = forwardRef<HTMLInputElement, Props>(function Input(
  { label, error, id, className = "", ...rest },
  ref,
) {
  const autoId = useId();
  const inputId = id ?? autoId;
  const errorId = `${inputId}-error`;

  return (
    <div className="flex flex-col gap-1.5">
      <Label.Root htmlFor={inputId} className="text-sm font-medium text-ink-900">
        {label}
      </Label.Root>
      <input
        ref={ref}
        id={inputId}
        aria-invalid={!!error}
        aria-describedby={error ? errorId : undefined}
        className={`rounded-lg border px-3 py-2.5 text-sm text-ink-900 outline-none transition-colors
          focus:border-brand-500 focus:ring-2 focus:ring-brand-100
          ${error ? "border-danger-500" : "border-ink-500/20"} ${className}`}
        {...rest}
      />
      {error && (
        <p id={errorId} role="alert" className="text-sm text-danger-500">
          {error}
        </p>
      )}
    </div>
  );
});
