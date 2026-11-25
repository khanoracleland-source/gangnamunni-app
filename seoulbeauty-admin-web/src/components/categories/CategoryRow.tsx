"use client";

import type { ReactNode } from "react";
import Icon from "../Icon";
import type { BoardCategory } from "@/lib/api";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/locale";

/**
 * One line of the list: order buttons, name (the whole line is the click target), number of treatments and the active switch.
 * On a wide screen `selected` marks the category shown in the panel beside the list; on a narrow one it is the open accordion item and `children` is its panel.
 */
export default function CategoryRow({
  category,
  rank,
  index,
  total,
  selected,
  wide,
  canWrite,
  busy,
  onSelect,
  onMove,
  onActive,
  children,
}: {
  category: BoardCategory;
  /** The place in the app's home menu, or null for a hidden category. */
  rank: number | null;
  index: number;
  total: number;
  selected: boolean;
  wide: boolean;
  canWrite: boolean;
  busy: boolean;
  onSelect: () => void;
  onMove: (dir: -1 | 1) => void;
  onActive: (next: boolean) => void;
  children?: ReactNode;
}) {
  const t = useT();
  const count = category.treatments.length;
  const lock = !canWrite || busy;
  const expand = wide ? { "aria-current": selected ? ("true" as const) : undefined } : { "aria-expanded": selected, "aria-controls": `cb-pan-${category.id}` };
  return (
    <li className={`cb-item${selected ? " is-open" : ""}${category.isActive ? "" : " is-off"}`}>
      <div className="cb-row">
        <div className="cb-ord" role="group" aria-label={category.name}>
          <button type="button" className="ibtn" data-cb={`up-${category.id}`} aria-label={t("cb.up", { name: category.name })} title={t("cb.up", { name: category.name })} disabled={lock || index === 0} onClick={() => onMove(-1)}>
            <Icon name="chevron" size={18} className="cb-rot-up" />
          </button>
          <span className="cb-rank" aria-hidden="true">{rank ?? "–"}</span>
          <button type="button" className="ibtn" data-cb={`down-${category.id}`} aria-label={t("cb.down", { name: category.name })} title={t("cb.down", { name: category.name })} disabled={lock || index === total - 1} onClick={() => onMove(1)}>
            <Icon name="chevron" size={18} className="cb-rot-dn" />
          </button>
        </div>
        <h3 className="cb-h">
          <button type="button" className="cb-name" id={`cb-nm-${category.id}`} title={category.name} onClick={onSelect} {...expand}>
            <Icon name="chevron" size={16} className="cb-chev" />
            <span className="cb-name__t" translate="no">{category.name}</span>
            {!category.isActive ? <span className="cb-off">{t("cb.hidden")}</span> : null}
          </button>
        </h3>
        <span className="cb-n">
          {category.isActive && count === 0 ? (
            <span className="pill pill--warn"><span className="sr-only">{t("cb.stat.treatments")} </span>0</span>
          ) : (
            <>
              <span className="sr-only">{t("cb.stat.treatments")} </span>
              {formatNumber(count)}
            </>
          )}
        </span>
        <span className="cb-sw">
          <button type="button" role="switch" className="switch" aria-checked={category.isActive} aria-label={t("cb.switch", { name: category.name })} aria-disabled={lock || undefined} onClick={() => !lock && onActive(!category.isActive)}>
            <span className="switch__thumb" />
          </button>
        </span>
      </div>
      {children}
    </li>
  );
}
