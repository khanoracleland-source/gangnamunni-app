"use client";

import Icon from "../Icon";
import type { BoardCategory } from "@/lib/api";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/locale";

/** One category of the home menu: order buttons, name (opens the panel), counts and the active switch; the panel holds its treatments. */
export default function CategoryRow({
  category,
  rank,
  index,
  total,
  open,
  canWrite,
  busy,
  onToggle,
  onMove,
  onActive,
  onAdd,
  onRename,
  onDelete,
  onRemove,
}: {
  category: BoardCategory;
  /** The place in the app's home menu, or null for a hidden category. */
  rank: number | null;
  index: number;
  total: number;
  open: boolean;
  canWrite: boolean;
  busy: boolean;
  onToggle: () => void;
  onMove: (dir: -1 | 1) => void;
  onActive: (next: boolean) => void;
  onAdd: () => void;
  onRename: () => void;
  onDelete: () => void;
  onRemove: (treatmentId: number) => void;
}) {
  const t = useT();
  const count = category.treatments.length;
  const empty = count === 0;
  const lock = !canWrite || busy;
  return (
    <li className={open ? "cb-item is-open" : "cb-item"} aria-busy={busy || undefined}>
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
          <button type="button" className="cb-name" id={`cb-nm-${category.id}`} aria-expanded={open} aria-controls={`cb-pan-${category.id}`} title={category.name} onClick={onToggle}>
            <Icon name="chevron" size={16} className="cb-chev" />
            <span className="cb-name__t" translate="no">{category.name}</span>
          </button>
        </h3>
        <div className="cb-meta">
          <span className={category.isActive && empty ? "pill pill--warn" : "pill pill--info"}>{t("cb.count", { n: formatNumber(count) })}</span>
          {!category.isActive ? <span className="pill pill--off">{t("cb.hidden")}</span> : null}
          <span className="cb-meta__sp" />
          <span className="cb-sw">
            <span className="cb-sw__t" aria-hidden="true">{t("cb.active")}</span>
            <button type="button" role="switch" className="switch" aria-checked={category.isActive} aria-label={t("cb.switch", { name: category.name })} aria-disabled={lock || undefined} onClick={() => !lock && onActive(!category.isActive)}>
              <span className="switch__thumb" />
            </button>
          </span>
        </div>
      </div>
      <div className="cb-pan" id={`cb-pan-${category.id}`} role="region" aria-labelledby={`cb-nm-${category.id}`} hidden={!open}>
        <div className="cb-bar">
          <button type="button" className="btn btn--primary cb-bar__add" data-cb={`add-${category.id}`} disabled={lock} onClick={onAdd}>
            <Icon name="plus" size={16} /> {t("cb.btn.add")}
          </button>
          <button type="button" className="btn btn--ghost" disabled={lock} onClick={onRename}>
            <Icon name="edit" size={16} /> {t("cb.btn.rename")}
          </button>
          <span className="cb-bar__sp" />
          <button type="button" className="btn btn--ghost btn--reject" disabled={lock} onClick={onDelete}>
            <Icon name="trash" size={16} /> {t("cb.btn.delete")}
          </button>
        </div>
        {empty ? (
          <div className="cb-pempty">
            <p className="cb-pempty__t">{t("cb.pempty.title")}</p>
            <p className="cb-pempty__b">{t("cb.pempty.body")}</p>
          </div>
        ) : (
          <>
            <p className="cb-plabel" id={`cb-pl-${category.id}`}>
              {t("cb.plabel")} <strong>{formatNumber(count)}</strong>
            </p>
            <ul className="chips chips--edit" aria-labelledby={`cb-pl-${category.id}`}>
              {category.treatments.map((tr) => (
                <li key={tr.id} className="chip chip--edit">
                  <span translate="no">{tr.name}</span>
                  {!tr.isActive ? <span className="cb-chip__off">{t("cb.hidden")}</span> : null}
                  <button type="button" className="chip__x" data-cb={`chip-${tr.id}`} disabled={lock} aria-label={t("cb.chip.remove", { name: tr.name })} title={t("cb.chip.remove", { name: tr.name })} onClick={() => onRemove(tr.id)}>
                    <Icon name="close" size={14} />
                  </button>
                </li>
              ))}
            </ul>
          </>
        )}
      </div>
    </li>
  );
}
