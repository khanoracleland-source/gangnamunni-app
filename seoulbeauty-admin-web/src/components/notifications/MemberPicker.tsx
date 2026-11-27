"use client";

import { useEffect, useState } from "react";
import Icon from "../Icon";
import { useT } from "@/lib/locale";
import { fetchMemberSearch, type MemberHit } from "@/lib/notificationsApi";

const MIN = 2;

/** Finds members by id, name, nickname or phone and picks them. `multi` keeps the list open so that several can be chosen in a row. */
export default function MemberPicker({ multi, ids, onAdd, onRemove, onUnauthorized }: { multi: boolean; ids: string[]; onAdd: (id: string) => void; onRemove: (id: string) => void; onUnauthorized: () => void }) {
  const t = useT();
  const [q, setQ] = useState("");
  const [got, setGot] = useState<{ q: string; items: MemberHit[] | null } | null>(null);
  const [names, setNames] = useState<Record<string, string>>({});
  const text = q.trim();
  const ready = [...text].length >= MIN;

  useEffect(() => {
    if (!ready) return;
    const c = new AbortController();
    const timer = window.setTimeout(() => {
      void fetchMemberSearch(text, c.signal).then((res) => {
        if (c.signal.aborted) return;
        if (!res.ok && res.status === 401) onUnauthorized();
        if (res.ok) setNames((x) => { const y = { ...x }; for (const m of res.data) y[m.id] = m.name || m.nick; return y; });
        setGot({ q: text, items: res.ok ? res.data : null });
      });
    }, 250);
    return () => { window.clearTimeout(timer); c.abort(); };
  }, [text, ready, onUnauthorized]);

  const loading = ready && got?.q !== text;
  const items = ready && got?.q === text ? got.items : null;
  const failed = ready && got?.q === text && got.items === null;
  const chosen = new Set(ids);
  const label = (id: string) => { const n = names[id]; return n ? `${n} (${id})` : id; };
  const pick = (m: MemberHit) => {
    if (chosen.has(m.id)) { if (multi) onRemove(m.id); return; }
    setNames((x) => ({ ...x, [m.id]: m.name || m.nick }));
    onAdd(m.id);
    if (!multi) setQ("");
  };

  return (
    <div className="nt-mp">
      {ids.length > 0 ? (
        <ul className="nt-mp__chips" aria-label={t("nt.m.chosen")}>
          {ids.slice(0, multi ? 50 : 1).map((id) => (
            <li key={id} className="nt-mp__chip">
              <span className="nt-mp__chiptx">{label(id)}</span>
              <button type="button" className="nt-mp__x" onClick={() => onRemove(id)} aria-label={t("nt.m.remove", { id })}><Icon name="close" size={14} /></button>
            </li>
          ))}
          {multi && ids.length > 50 ? <li className="nt-mp__more">{t("nt.m.more", { n: ids.length - 50 })}</li> : null}
        </ul>
      ) : null}
      <input type="search" className="input nt-ctl" value={q} onChange={(e) => setQ(e.target.value)} onKeyDown={(e) => { if (e.key === "Enter") e.preventDefault(); }} placeholder={t("nt.m.ph")} aria-label={t("nt.m.ph")} maxLength={50} autoComplete="off" enterKeyHint="search" />
      <p className="nt-fld__h" aria-live="polite">
        {!ready ? t("nt.m.hint") : loading ? t("nt.m.loading") : failed ? t("nt.m.failed") : items && items.length === 0 ? t("nt.m.none") : items ? t("nt.m.found", { n: items.length }) : ""}
      </p>
      {items && items.length > 0 ? (
        <ul className="nt-mp__list">
          {items.map((m) => (
            <li key={m.id}>
              <button type="button" className="nt-mp__row" onClick={() => pick(m)} aria-pressed={chosen.has(m.id)}>
                <span className="nt-mp__who">
                  <strong>{m.name || m.nick || m.id}</strong>
                  <small>{[m.nick && m.nick !== m.name ? m.nick : "", m.id, m.phoneTail ? `···${m.phoneTail}` : ""].filter((x) => x !== "").join(" · ")}</small>
                </span>
                <span className={m.hasPush ? "nt-st nt-st--sent" : "nt-st"}>{t(m.hasPush ? "nt.m.push" : "nt.m.noPush")}</span>
                {chosen.has(m.id) ? <Icon name="check" size={16} /> : null}
              </button>
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}
