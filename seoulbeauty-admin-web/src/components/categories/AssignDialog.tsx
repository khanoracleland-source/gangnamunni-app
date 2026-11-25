"use client";

import { useEffect, useId, useMemo, useRef, useState } from "react";
import { createPortal } from "react-dom";
import Icon from "../Icon";
import { failureKey } from "../treatments/writeErrors";
import { assignBoardTreatments, type BoardCategory, type BoardData, type WriteFailure } from "@/lib/api";
import { filterPick, pickItems, type PickFilter } from "@/lib/categoryBoard";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/locale";

/** Picks existing treatments for one category. A treatment of another category is moved here; nothing is created. */
export default function AssignDialog({ category, data, onClose, onSaved, onFailed }: { category: BoardCategory; data: BoardData; onClose: () => void; onSaved: (board: BoardData, count: number) => void; onFailed: (res: WriteFailure) => void }) {
  const t = useT();
  const uid = useId();
  const ref = useRef<HTMLDialogElement>(null);
  const [query, setQuery] = useState("");
  const [filter, setFilter] = useState<PickFilter>("all");
  const [picked, setPicked] = useState<Set<number>>(new Set());
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const dialog = ref.current;
    if (!dialog) return;
    const returnTo = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    if (!dialog.open) dialog.showModal();
    dialog.querySelector<HTMLElement>("[data-autofocus]")?.focus({ preventScroll: true });
    return () => {
      if (dialog.open) dialog.close();
      if (returnTo?.isConnected) returnTo.focus();
    };
  }, []);

  const all = useMemo(() => pickItems(data, category.id), [data, category.id]);
  const counts = useMemo(
    () => ({
      all: all.length,
      none: all.filter((i) => i.fromId === 0).length,
      other: all.filter((i) => i.fromId !== 0 && !i.here).length,
    }),
    [all],
  );
  const shown = useMemo(() => filterPick(all, filter, query), [all, filter, query]);
  const moving = all.filter((i) => picked.has(i.id) && i.fromId !== 0 && !i.here).length;

  const toggle = (id: number) =>
    setPicked((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });

  const submit = async () => {
    if (busy || picked.size === 0) return;
    setBusy(true);
    setError(null);
    const res = await assignBoardTreatments(category.id, [...picked]);
    setBusy(false);
    if (res.ok) {
      onSaved(res.data, picked.size);
      return;
    }
    if (res.status === 404 || res.status === 409) {
      onFailed(res);
      return;
    }
    setError(t(failureKey(res)));
  };

  const filters: { key: PickFilter; label: string }[] = [
    { key: "all", label: t("cb.f.all", { n: formatNumber(counts.all) }) },
    { key: "none", label: t("cb.f.none", { n: formatNumber(counts.none) }) },
    { key: "other", label: t("cb.f.other", { n: formatNumber(counts.other) }) },
  ];

  return createPortal(
    <dialog
      ref={ref}
      className="dialog cb-dlg cb-dlg--add"
      aria-labelledby={`${uid}-t`}
      onCancel={(e) => {
        if (busy) e.preventDefault();
      }}
      onClose={onClose}
      onClick={(e) => {
        if (e.target === e.currentTarget && !busy) onClose();
      }}
    >
      <div className="cb-dlg__form">
        <div className="cb-dlg__head">
          <div>
            <h2 id={`${uid}-t`} className="dialog__title">{t("cb.dlg.add.title")}</h2>
            <p className="cb-dlg__sub" translate="no">{t("cb.dlg.add.to", { name: category.name })}</p>
          </div>
          <button type="button" className="ibtn dialog__close" onClick={onClose} disabled={busy} aria-label={t("m.close")} title={t("m.close")}>
            <Icon name="close" />
          </button>
        </div>
        <div className="cb-dlg__body">
          <div className="cb-search">
            <Icon name="search" size={16} />
            <input
              type="search"
              className="input"
              value={query}
              autoComplete="off"
              aria-label={t("cb.dlg.search")}
              placeholder={t("cb.dlg.search")}
              data-autofocus=""
              onChange={(e) => setQuery(e.target.value)}
            />
          </div>
          <div className="seg cb-seg" role="group" aria-label={t("cb.dlg.filter")}>
            {filters.map((f) => (
              <button key={f.key} type="button" aria-pressed={filter === f.key} onClick={() => setFilter(f.key)}>{f.label}</button>
            ))}
          </div>
          <p className="cb-pick__hint" aria-live="polite">{t("cb.pick.hint", { n: formatNumber(shown.length) })}</p>
          {shown.length === 0 ? (
            <p className="cb-pick__empty">{t("cb.pick.empty")}</p>
          ) : (
            <ul className="cb-pick">
              {shown.map((i) => (
                <li key={i.id}>
                  <label className={i.here ? "cb-pick__r is-here" : "cb-pick__r"}>
                    <input type="checkbox" checked={i.here || picked.has(i.id)} disabled={i.here || busy} onChange={() => toggle(i.id)} />
                    <span className="cb-pick__t">
                      <span className="cb-pick__n" translate="no">{i.name}</span>
                      {i.here ? <span className="cb-pick__s">{t("cb.pick.already")}</span> : i.fromId !== 0 ? <span className="cb-pick__s">{t("cb.pick.from", { name: i.fromName })}</span> : null}
                    </span>
                    {!i.isActive ? <span className="pill pill--off">{t("cb.hidden")}</span> : null}
                    {i.fromId === 0 ? <span className="pill pill--warn">{t("cb.stat.none")}</span> : null}
                  </label>
                </li>
              ))}
            </ul>
          )}
          {error ? (
            <p className="field__error cb-dlg__err" role="alert">
              <Icon name="alert" size={14} /> {error}
            </p>
          ) : null}
        </div>
        <div className="cb-dlg__foot">
          <p className="cb-dlg__count" aria-live="polite">
            <strong>{t("cb.foot.sel", { n: formatNumber(picked.size) })}</strong>
            {moving > 0 ? <span> · {t("cb.foot.move", { n: formatNumber(moving) })}</span> : null}
          </p>
          <div className="cb-dlg__acts">
            <button type="button" className="btn btn--ghost" onClick={onClose} disabled={busy}>{t("tmw.cancel")}</button>
            <button type="button" className="btn btn--primary" onClick={() => void submit()} disabled={busy || picked.size === 0} aria-busy={busy || undefined}>
              {busy ? t("tmw.saving") : t("cb.assign", { n: formatNumber(picked.size) })}
            </button>
          </div>
        </div>
      </div>
    </dialog>,
    document.body,
  );
}
