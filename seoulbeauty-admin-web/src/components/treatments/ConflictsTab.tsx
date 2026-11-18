"use client";

import Icon from "../Icon";
import { Notice } from "../hospitals/parts";
import type { TreatmentsData } from "@/lib/api";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/locale";
import { treatmentName } from "@/lib/treatments";

/** The pairs of treatments that cannot be booked together on the same day. */
export default function ConflictsTab({ data }: { data: TreatmentsData }) {
  const t = useT();
  const nameOf = (id: number) => treatmentName(data.items, id, (missing) => t("tm.cf.missing", { id: missing }));

  if (!data.schema.conflicts) {
    return (
      <div className="tm__notice">
        <Notice tone="warn" icon="alert">{t("tm.cf.noTable")}</Notice>
      </div>
    );
  }

  return (
    <>
      <div className="tm__notice">
        <Notice>{t("tm.cf.hint")}</Notice>
      </div>
      <div className="list-head">
        <div className="list-head__main">
          <p className="list-head__count" role="status">{t("tm.cf.count", { n: formatNumber(data.conflicts.length) })}</p>
        </div>
      </div>
      {data.conflicts.length === 0 ? (
        <div className="empty tm__empty">
          <span className="state__ring"><Icon name="ban" size={22} /></span>
          <p className="tm__empty-title">{t("tm.cf.empty")}</p>
          <p>{t("tm.cf.emptyBody")}</p>
        </div>
      ) : (
        <ul className="tm__pairs" aria-label={t("tm.cf.list")}>
          {data.conflicts.map((c) => {
            const a = nameOf(c.a);
            const b = nameOf(c.b);
            return (
              <li key={c.id} className="tm__pair" aria-label={t("tm.cf.pair", { a, b })}>
                <span className="tm__pair-a" translate="no">{a}</span>
                <span className="tm__pair-x" aria-hidden="true"><Icon name="ban" size={16} /></span>
                <span className="tm__pair-b" translate="no"><Icon name="ban" size={14} className="tm__pair-bicon" />{b}</span>
              </li>
            );
          })}
        </ul>
      )}
    </>
  );
}
