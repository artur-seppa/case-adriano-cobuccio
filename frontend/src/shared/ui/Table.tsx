// shared/ui/Table.tsx
import { HTMLAttributes, TdHTMLAttributes, ThHTMLAttributes } from "react";

function Root({ className = "", ...rest }: HTMLAttributes<HTMLTableElement>) {
  return (
    <div className="overflow-x-auto rounded-xl bg-surface shadow-elevation">
      <table className={`w-full text-left text-sm ${className}`} {...rest} />
    </div>
  );
}
const Head = (props: HTMLAttributes<HTMLTableSectionElement>) => (
  <thead className="border-b border-ink-500/10 text-xs uppercase text-ink-500" {...props} />
);
const Body = (props: HTMLAttributes<HTMLTableSectionElement>) => <tbody {...props} />;
const Row = (props: HTMLAttributes<HTMLTableRowElement>) => (
  <tr className="border-b border-ink-500/5 last:border-0" {...props} />
);
const HeadCell = (props: ThHTMLAttributes<HTMLTableCellElement>) => <th className="px-4 py-3 font-medium" {...props} />;
const Cell = (props: TdHTMLAttributes<HTMLTableCellElement>) => <td className="px-4 py-3" {...props} />;

export const Table = Object.assign(Root, { Head, Body, Row, HeadCell, Cell });
