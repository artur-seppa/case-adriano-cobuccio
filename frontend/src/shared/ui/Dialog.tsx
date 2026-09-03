// shared/ui/Dialog.tsx
import * as RadixDialog from "@radix-ui/react-dialog";
import { X } from "lucide-react";

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  title: string;
  description?: string;
  trigger?: React.ReactNode;
  children: React.ReactNode;
}

export function Dialog({ open, onOpenChange, title, description, trigger, children }: Props) {
  return (
    <RadixDialog.Root open={open} onOpenChange={onOpenChange}>
      {trigger && <RadixDialog.Trigger asChild>{trigger}</RadixDialog.Trigger>}
      <RadixDialog.Portal>
        <RadixDialog.Overlay className="fixed inset-0 bg-ink-900/40" />
        <RadixDialog.Content
          className="fixed left-1/2 top-1/2 max-h-[85vh] w-full max-w-md -translate-x-1/2 -translate-y-1/2
            overflow-y-auto rounded-xl bg-surface p-6 shadow-elevation focus:outline-none"
        >
          <RadixDialog.Close
            aria-label="Fechar"
            className="absolute right-3.5 top-3.5 rounded-md p-1 text-ink-500 transition-colors
              hover:bg-canvas hover:text-ink-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
          >
            <X size={18} aria-hidden="true" />
          </RadixDialog.Close>
          <RadixDialog.Title className="pr-8 text-lg font-semibold text-ink-900">{title}</RadixDialog.Title>
          {description && (
            <RadixDialog.Description className="mt-1 text-sm text-ink-500">{description}</RadixDialog.Description>
          )}
          <div className="mt-4">{children}</div>
        </RadixDialog.Content>
      </RadixDialog.Portal>
    </RadixDialog.Root>
  );
}
