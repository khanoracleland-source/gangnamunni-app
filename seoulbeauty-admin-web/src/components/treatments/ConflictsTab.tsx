"use client";

import { useId, useMemo, useState, type FormEvent } from "react";
import { toast } from "sonner";
import Icon from "../Icon";
import { Notice } from "../hospitals/parts";
import { failureKey, reasonKey } from "./writeErrors";
import { addConflict, removeConflict, type TreatmentsData } from "@/lib/api";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/locale";
import { treatmentName } from "@/lib/treatments";

/** The pairs of treatments that cannot be booked together on the same day. With `canWrite` pairs can be added and deleted; `refresh` reads the list again. */
export default function ConflictsTab({ data, canWrite, refresh }: { data: TreatmentsData; canWrite: boolean; refresh: () => Promise<void> }) {
  const t = useT();
  const uid = useId();
  const [a, setA] = useState("");
  const [b, setB] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  // Only shown (active) treatments can be paired, like on the old page.
  const choices = useMemo(() => data.items.filter((i) => i.isActive), [data.items]);

  const add = async (e: FormEvent) => {
    e.preventDefault();
    if (busy) return;
    if (a === "" || b === "" || a === b) {
      setError(t("tmc.cf.same"));
      return;
    }
    setBusy(true);
    setError(null);
    const res = await addConflict(Number(a), Number(b));
    if (res.ok) {
      if (res.data.existed) toast.info(t("tmc.cf.existed"));
      else toast.success(t("tmc.cf.added"));
      setA("");
      setB("");
      await refresh();
    } else if (res.status === 400 && res.error === "invalid_field") setError(t(reasonKey(res.reason)));
    else setError(t(failureKey(res)));
    setBusy(false);
  };
  const remove = async (id: number) => {
    if (busy) return;
    setBusy(true);
    const res = await removeConflict(id);
    if (res.ok) {
      toast.success(t("tmc.cf.removed"));
      await refresh();
    } else toast.error(t(failureKey(res)));
    setBusy(false);
  };
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
      {canWrite ? (
        <form className="tm__pair-add" onSubmit={add} noValidate>
          {(["a", "b"] as const).map((side) => {
            const value = side === "a" ? a : b;
            const set = side === "a" ? setA : setB;
            return (
              <label key={side} className="tm__pair-field">
                <span className="field__label">{t(side === "a" ? "tmc.cf.a" : "tmc.cf.b")}</span>
                <select id={`${uid}-${side}`} className="sel sel--field" aria-invalid={error ? true : undefined} aria-describedby={error ? `${uid}-err` : undefined} value={value} onChange={(e) => { set(e.target.value); setError(null); }}>
                  <option value="">{t("tmc.cf.pick")}</option>
                  {choices.map((i) => (
                    <option key={i.id} value={i.id}>{i.name}</option>
                  ))}
                </select>
              </label>
            );
          })}
          <button type="submit" className="btn btn--primary" disabled={busy}>{t("tmc.cf.add")}</button>
          {error ? <p className="field__error tm__pair-error" id={`${uid}-err`} role="alert">{error}</p> : null}
        </form>
      ) : null}
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
                {canWrite ? (
                  <button type="button" className="ibtn tm__pair-del" disabled={busy} onClick={() => void remove(c.id)} aria-label={t("tmc.cf.remove", { a, b })} title={t("tmw.delete")}>
                    <Icon name="trash" size={16} />
                  </button>
                ) : null}
              </li>
            );
          })}
        </ul>
      )}
    </>
  );
}
