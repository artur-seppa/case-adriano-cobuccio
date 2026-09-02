// shared/ui/Toast.tsx
"use client";

import { createContext, useCallback, useContext, useState } from "react";
import * as RadixToast from "@radix-ui/react-toast";

type Variant = "info" | "success" | "danger";
interface ToastInput {
  title: string;
  description?: string;
  variant?: Variant;
}
interface ToastItem extends ToastInput {
  id: number;
}

const ToastContext = createContext<{ toast: (input: ToastInput) => void } | null>(null);

const VARIANT_CLASSES: Record<Variant, string> = {
  info: "border-ink-500/20",
  success: "border-brand-500",
  danger: "border-danger-500",
};

export function ToastProvider({ children }: { children: React.ReactNode }) {
  const [items, setItems] = useState<ToastItem[]>([]);

  const toast = useCallback((input: ToastInput) => {
    const id = Date.now() + Math.random();
    setItems((prev) => [...prev, { id, ...input }]);
  }, []);

  const dismiss = useCallback((id: number) => {
    setItems((prev) => prev.filter((item) => item.id !== id));
  }, []);

  return (
    <ToastContext.Provider value={{ toast }}>
      <RadixToast.Provider swipeDirection="right" duration={5000}>
        {children}
        {items.map((item) => (
          <RadixToast.Root
            key={item.id}
            onOpenChange={(open) => !open && dismiss(item.id)}
            className={`rounded-lg border-l-4 bg-surface p-4 shadow-elevation ${VARIANT_CLASSES[item.variant ?? "info"]}`}
          >
            <RadixToast.Title className="text-sm font-medium text-ink-900">{item.title}</RadixToast.Title>
            {item.description && (
              <RadixToast.Description className="mt-1 text-sm text-ink-500">
                {item.description}
              </RadixToast.Description>
            )}
          </RadixToast.Root>
        ))}
        <RadixToast.Viewport className="fixed bottom-4 right-4 z-50 flex w-80 flex-col gap-2" />
      </RadixToast.Provider>
    </ToastContext.Provider>
  );
}

export function useToast() {
  const ctx = useContext(ToastContext);
  if (!ctx) throw new Error("useToast must be used within <ToastProvider>");
  return ctx;
}
