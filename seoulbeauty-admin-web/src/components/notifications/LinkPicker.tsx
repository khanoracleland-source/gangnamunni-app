"use client";

import { useEffect, useRef, useState } from "react";
import Icon from "../Icon";
import { useT } from "@/lib/locale";
import { fetchLinkTargets, type LinkTarget } from "@/lib/notificationsApi";

export type PickedLink = { path: string; label: string } | null;
const GROUPS = ["page", "hospital", "event"] as const;

/** The page a notification opens: a button that opens a searchable list (under the button, or a sheet from the bottom on a phone). */
export default function LinkPicker({ value, onChange, disabled, onUnauthorized }: { value: PickedLink; onChange: (v: PickedLink) => void; disabled: boolean; onUnauthorized: () => void }) {
  const t = useT();
  const [open, setOpen] = useState(false);
  const [q, setQ] = useState("");
  const [got, setGot] = useState<{ q: string; items: LinkTarget[] | null } | null>(null);
  const button = useRef<HTMLButtonElement>(null);
  const search = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (!open) return;
    const c = new AbortController();
    const id = window.setTimeout(() => {
      void fetchLinkTargets(q.trim(), "", c.signal).then((res) => {
        if (c.signal.aborted) return;
        if (!res.ok && res.status === 401) onUnauthorized();
        setGot({ q, items: res.ok ? res.data : null });
      });
    }, q === "" ? 0 : 250);
    return () => { window.clearTimeout(id); c.abort(); };
  }, [open, q, onUnauthorized]);

  // On a computer the search takes the focus; on a touch screen it does not (the keyboard would squeeze the sheet).
  useEffect(() => {
    if (open && window.matchMedia("(pointer: fine)").matches) search.current?.focus({ preventScroll: true });
  }, [open]);

  const close = () => { setOpen(false); button.current?.focus(); };
  const pick = (v: PickedLink) => { onChange(v); close(); };
  const loading = got?.q !== q;
  const items = got?.items ?? null;

  return (
    <div className="nt-link">
      <button ref={button} type="button" className="nt-lk" aria-expanded={open} aria-haspopup="dialog" disabled={disabled} onClick={() => setOpen((o) => !o)}>
        <span className="nt-lk__l">{value ? value.label : t("nt.link.none")}</span>
        {value ? <span className="nt-lk__p">{value.path}</span> : null}
        <Icon name="chevron" size={16} className="nt-lk__c" />
      </button>
      {open ? (
        <>
          <div className="nt-sheet-bd" onClick={close} aria-hidden="true" />
          <div className="nt-linkp" role="dialog" aria-label={t("nt.link.title")} onKeyDown={(e) => { if (e.key === "Escape") { e.stopPropagation(); close(); } }}>
            <div className="nt-linkp__head">
              <h3>{t("nt.link.title")}</h3>
              <button type="button" className="ibtn" onClick={close} aria-label={t("nt.close")}><Icon name="close" size={18} /></button>
            </div>
            <div className="nt-linkp__search">
              <input ref={search} className="input" type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder={t("nt.link.search")} aria-label={t("nt.link.search")} maxLength={60} />
            </div>
            <div className="nt-linkp__list" aria-busy={loading}>
              {q.trim() === "" ? (
                <button type="button" className="nt-lrow" onClick={() => pick(null)} aria-current={value === null}>
                  <span className="nt-lrow__l">{t("nt.link.none")}</span>
                </button>
              ) : null}
              {items === null && !loading ? <p className="nt-linkp__msg">{t("nt.link.failed")}</p> : null}
              {items !== null && items.length === 0 ? <p className="nt-linkp__msg">{t("nt.link.empty")}</p> : null}
              {items !== null
                ? GROUPS.map((g) => {
                    const rows = items.filter((x) => x.kind === g);
                    if (rows.length === 0) return null;
                    return (
                      <div key={g} role="group" aria-label={t(`nt.link.g.${g}`)}>
                        <p className="nt-linkp__gh">{t(`nt.link.g.${g}`)}</p>
                        {rows.map((x) => (
                          <button key={x.path} type="button" className="nt-lrow" onClick={() => pick({ path: x.path, label: x.label })} aria-current={value?.path === x.path}>
                            <span className="nt-lrow__l">{x.label}</span>
                            <span className="nt-lrow__p">{x.path}</span>
                          </button>
                        ))}
                      </div>
                    );
                  })
                : null}
            </div>
          </div>
        </>
      ) : null}
    </div>
  );
}
