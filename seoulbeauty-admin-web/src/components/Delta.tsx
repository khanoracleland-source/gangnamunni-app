"use client";

import { changeDirection, formatPercent, percentChange } from "@/lib/format";
import { useT } from "@/lib/locale";

const SPOKEN = { up: "delta.upSpoken", down: "delta.downSpoken" } as const;

/**
 * "▲ 12.3%  이전 7일 대비". Direction is told three ways (arrow, colour, hidden word), so it does not
 * depend on colour alone. Every piece is its own element holding a single text node.
 */
export default function Delta({ current, previous, days }: { current: number; previous: number; days: number }) {
  const t = useT();
  const change = percentChange(current, previous);
  const direction = changeDirection(change);
  const vs = <span className="delta__vs">{t("delta.vs", { days })}</span>;

  if (direction === "none") {
    return (
      <>
        <span className="delta delta--none" title={t("delta.none")}>—</span>
        <span className="sr-only">{t("delta.none")}</span>
        {vs}
      </>
    );
  }
  if (direction === "flat") {
    return (
      <>
        <span className="delta delta--flat">{t("delta.flat")}</span>
        {vs}
      </>
    );
  }
  return (
    <>
      <span className={`delta delta--${direction}`}>{t(direction === "up" ? "delta.up" : "delta.down", { pct: formatPercent(change) })}</span>
      <span className="sr-only">{t(SPOKEN[direction])}</span>
      {vs}
    </>
  );
}
