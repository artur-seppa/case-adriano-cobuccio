// shared/ui/Card.tsx
import { HTMLAttributes } from "react";

export function Card({ className = "", ...rest }: HTMLAttributes<HTMLDivElement>) {
  return <div className={`rounded-xl bg-surface p-5 shadow-elevation ${className}`} {...rest} />;
}
