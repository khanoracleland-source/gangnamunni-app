"use client";

import Icon from "../Icon";
import type { BoardCategory } from "@/lib/api";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/locale";

/** The open category: its place in the home menu, rename / add treatments, the treatments it holds, and delete. A wide screen shows it once beside the list, a narrow one under its row (`inline`). */
export default function CategoryPanel({
  category,
  rank,
  inline,
  canWrite,
  busy,
  onAdd,
  onRename,
  onDelete,
  onRemove,
}: {
  category: BoardCategory;
  rank: number | null;
  inline: boolean;
  canWrite: boolean;
  busy: boolean;
  onAdd: () => void;
  onRename: () => void;
  onDelete: () => void;
  onRemove: (treatmentId: number) => void;
}) {
  const t = useT();
  const count = category.treatments.length;
  const lock = !canWrite || busy;
  return (
    <div className={inline ? "cb-pan cb-pan--in" : "cb-pan"} id={`cb-pan-${category.id}`} role="region" aria-labelledby={`cb-nm-${category.id}`}>
      <div className="cb-pan__head">
        <div className="cb-pan__id">
          <h3 className="cb-pan__t" translate="no">{category.name}</h3>
          <p className="cb-pan__s">{rank ? t("cb.pos", { n: rank }) : t("cb.pos.off")}</p>
        </div>
        <div className="cb-pan__acts">
          <button type="button" className="btn btn--ghost" disabled={lock} onClick={onRename}>
            <Icon name="edit" size={16} /> {t("cb.btn.rename")}
          </button>
          <button type="button" className="btn btn--primary" data-cb={`add-${category.id}`} disabled={lock} onClick={onAdd}>
            <Icon name="plus" size={16} /> {t("cb.btn.add")}
          </button>
        </div>
      </div>
      {count === 0 ? (
        category.isActive ? (
          <div className="notice notice--warn" role="note">
            <Icon name="alert" size={18} />
            <div className="notice__text">
              <span className="notice__title">{t("cb.pempty.title")}</span>
              <span className="notice__body">{t("cb.pempty.body")}</span>
            </div>
          </div>
        ) : (
          <div className="cb-pempty">
            <strong>{t("cb.pempty.title")}</strong>
            {t("cb.pempty.body")}
          </div>
        )
      ) : (
        <div>
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
        </div>
      )}
      <div className="cb-pan__foot">
        <button type="button" className="btn btn--ghost btn--reject" disabled={lock} onClick={onDelete}>
          <Icon name="trash" size={16} /> {t("cb.btn.delete")}
        </button>
      </div>
    </div>
  );
}
