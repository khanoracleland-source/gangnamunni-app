"use client";
import { useEffect, useRef, useState, useSyncExternalStore } from "react";
import Link from "next/link";
import type { SearchSuggest } from "@/lib/api";
import { phpUrl, usePhpBase } from "@/lib/php";
import styles from "./SearchOverlay.module.css";

/** includes/search_overlay.php: 최근 검색어 (localStorage) + 인기 검색어 + search_suggest.php 자동완성. */

const RECENT_KEY = "sb_recent_searches"; // same key as PHP, so both sides share the list
const RECENT_MAX = 10;
const listeners = new Set<() => void>();
function readRecentRaw(): string { try { return localStorage.getItem(RECENT_KEY) || "[]"; } catch { return "[]"; } }
function subscribeRecent(cb: () => void) { listeners.add(cb); return () => { listeners.delete(cb); }; }
function parseRecent(raw: string): string[] { try { const v = JSON.parse(raw); return Array.isArray(v) ? v.map(String) : []; } catch { return []; } }
function saveRecent(list: string[]) {
  try { localStorage.setItem(RECENT_KEY, JSON.stringify(list.slice(0, RECENT_MAX))); } catch { /* ignore */ }
  listeners.forEach((cb) => cb());
}
export function addRecentSearch(w: string) {
  w = (w || "").trim();
  if (!w) return;
  saveRecent([w, ...parseRecent(readRecentRaw()).filter((x) => x !== w)]);
}

const PIN = <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M12 21.5s7-6.1 7-11.8A7 7 0 0 0 5 9.7c0 5.7 7 11.8 7 11.8z" /><circle cx="12" cy="9.7" r="2.6" /></svg>;
const X = <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" /></svg>;
const ICO_T = <svg className={styles.ico} viewBox="0 0 24 24" fill="none" stroke="#AC1830" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5" /><path d="M16 16l4.5 4.5" /></svg>;
const ICO_H = <span className={styles.logoBox}><svg className={styles.ico} viewBox="0 0 24 24" fill="none" stroke="#6B6466" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M4 21V7l8-4 8 4v14M9 21v-5h6v5M9 10h.01M15 10h.01" /></svg></span>;

/** Next routes go through <Link> (basePath + client navigation); PHP pages through a plain anchor. */
function SmartLink({ to, className, onClick, children }: { to: { href: string; next: boolean }; className?: string; onClick?: () => void; children: React.ReactNode }) {
  if (to.next) return <Link href={to.href} className={className} onClick={onClick}>{children}</Link>;
  return <a href={to.href} className={className} onClick={onClick}>{children}</a>;
}

function Highlight({ text, q }: { text: string; q: string }) {
  const i = text.toLowerCase().indexOf(q.toLowerCase());
  if (i < 0 || !q) return <>{text}</>;
  return <>{text.slice(0, i)}<b>{text.slice(i, i + q.length)}</b>{text.slice(i + q.length)}</>;
}

/** PHP hrefs: plain `hospitals.php?q=X` works in Next; dosage/hid variants stay in PHP (Flutter leaves those too). */
function mapHref(php: string, href: string): { href: string; next: boolean } {
  const m = /^hospitals\.php\?(.*)$/.exec(href);
  if (m) {
    const p = new URLSearchParams(m[1]);
    const keys = [...p.keys()];
    if (keys.length === 1 && keys[0] === "q") return { href: `/hospitals?q=${encodeURIComponent(p.get("q") || "")}`, next: true };
  }
  return { href: phpUrl(php, href), next: false };
}

export default function SearchOverlay(props: { open: boolean; initial: string; regionLabel: string; popular: string[]; onClose: () => void; onSubmit: (q: string) => void; onRegion: (q: string) => void }) {
  if (!props.open) return null;
  return <Body key={props.initial} {...props} />;
}

function Body({ initial, regionLabel, popular, onClose, onSubmit, onRegion }: { initial: string; regionLabel: string; popular: string[]; onClose: () => void; onSubmit: (q: string) => void; onRegion: (q: string) => void }) {
  const php = usePhpBase();
  const [q, setQ] = useState(initial);
  const [sugg, setSugg] = useState<{ q: string; data: SearchSuggest } | null>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const recent = parseRecent(useSyncExternalStore(subscribeRecent, readRecentRaw, () => "[]"));
  const typing = q.trim().length > 0;

  useEffect(() => {
    inputRef.current?.focus();
    const prev = document.documentElement.style.overflow;
    document.documentElement.style.overflow = "hidden";
    return () => { document.documentElement.style.overflow = prev; };
  }, []);

  // Autocomplete (debounced 160 ms like PHP); stale responses are dropped.
  useEffect(() => {
    const term = q.trim();
    if (!term) return;
    const ctrl = new AbortController();
    const t = setTimeout(() => {
      fetch(phpUrl(php, `search_suggest.php?q=${encodeURIComponent(term)}`), { credentials: "same-origin", signal: ctrl.signal })
        .then((r) => (r.ok ? r.json() : null))
        .then((d: SearchSuggest | null) => { if (d) setSugg({ q: term, data: d }); })
        .catch(() => { /* ignore */ });
    }, 160);
    return () => { clearTimeout(t); ctrl.abort(); };
  }, [q, php]);

  const submit = (w: string) => { w = w.trim(); if (!w) { onClose(); return; } addRecentSearch(w); onSubmit(w); };
  const today = new Date();
  const dateLabel = `${String(today.getMonth() + 1).padStart(2, "0")}.${String(today.getDate()).padStart(2, "0")} 기준`;
  const d = sugg && sugg.q === q.trim() ? sugg.data : null;
  const none = d ? !d.treatments.length && !d.hospitals.length && !d.offered?.items?.length : false;

  return (
    <div className={styles.overlay} role="dialog" aria-label="검색">
      <div className={styles.inner}>
        <div className={styles.bar}>
          <button type="button" className={styles.back} aria-label="뒤로 가기" onClick={onClose}><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><path d="M15 5l-7 7 7 7" /></svg></button>
          <div className={styles.field}>
            <input ref={inputRef} type="search" className={styles.input} enterKeyHint="search" autoComplete="off" placeholder="시술, 병원 이름을 검색해보세요" value={q} onChange={(e) => setQ(e.target.value)} onKeyDown={(e) => { if (e.key === "Enter") { e.preventDefault(); submit(q); } }} aria-label="검색어" />
            {typing && <button type="button" className={styles.clr} aria-label="지우기" onClick={() => { setQ(""); inputRef.current?.focus(); }}>{X}</button>}
            <button type="button" className={styles.region} onClick={() => onRegion(q)}>{PIN}<span>{regionLabel}</span></button>
          </div>
        </div>

        {!typing && (
          <div>
            <div className={styles.head}><span className={styles.t}>최근 검색어</span>{recent.length > 0 && <button type="button" className={styles.clear} onClick={() => saveRecent([])}>전체 삭제</button>}</div>
            {recent.length ? (
              <div className={styles.chips}>
                {recent.map((w) => (
                  <span key={w} className={styles.chip} role="button" tabIndex={0} onClick={() => submit(w)} onKeyDown={(e) => { if (e.key === "Enter") submit(w); }}>
                    <span>{w}</span>
                    <span className={styles.chipX} aria-label="삭제" onClick={(e) => { e.stopPropagation(); saveRecent(recent.filter((v) => v !== w)); }}>{X}</span>
                  </span>
                ))}
              </div>
            ) : <div className={styles.empty}>최근 검색어가 없습니다</div>}
            <div className={styles.gap} />
            <div className={styles.head2}><span className={styles.t}>인기 검색어</span><span className={styles.d}>{dateLabel}</span></div>
            <div className={styles.rank}>
              {popular.map((w, i) => <button key={w} type="button" className={i < 3 ? styles.top : ""} onClick={() => submit(w)}><span className={styles.n}>{i + 1}</span><span>{w}</span></button>)}
            </div>
          </div>
        )}

        {typing && d && (
          <div className={styles.suggest}>
            {none ? (
              <>
                <div className={styles.noneBox}>
                  <div className={styles.noneIco}><svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#AC1830" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"><circle cx="11" cy="11" r="6.5" /><path d="M16 16l4.5 4.5" /></svg></div>
                  <div className={styles.noneT}>&lsquo;{q.trim()}&rsquo; 검색 결과가 없어요</div>
                  <div className={styles.noneS}>철자를 확인하거나 다른 단어로 검색해보세요</div>
                </div>
                <div className={styles.sugg}>
                  <div className={styles.suggT}>이런 시술은 어떠세요?</div>
                  <div className={styles.suggC}>{popular.slice(0, 4).map((w) => <button key={w} type="button" onClick={() => submit(w)}>{w}</button>)}</div>
                </div>
              </>
            ) : (
              <>
                {d.treatments.length > 0 && <div className={styles.sgT}>시술</div>}
                {d.treatments.map((t, i) => (
                  <SmartLink key={`${t.name}-${i}`} className={`${styles.row} ${styles.tr}`} to={mapHref(php, t.href)} onClick={() => addRecentSearch(q.trim())}>{ICO_T}<span><Highlight text={t.name} q={q.trim()} /></span>{t.hint && <span className={styles.hint}>{t.hint}</span>}</SmartLink>
                ))}
                {d.hospitals.length > 0 && <div className={styles.sgT}>병원</div>}
                {d.hospitals.map((h) => (
                  <SmartLink key={h.id} className={`${styles.row} ${styles.hp}`} to={{ href: `/hospitals/${h.id}`, next: true }} onClick={() => addRecentSearch(h.name)}>{ICO_H}<span className={styles.grow}><span className={styles.block}><Highlight text={h.name} q={q.trim()} /></span><span className={styles.sub}>{(h.district ? `${h.district} · ` : "") + h.address}</span></span><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9A9395" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" style={{ flex: "0 0 16px" }}><path d="M9 5l7 7-7 7" /></svg></SmartLink>
                ))}
                {d.offered?.items?.length > 0 && <div className={styles.sgT}>{d.offered.treatment} 시술 병원</div>}
                {d.offered?.items?.map((x) => (
                  <SmartLink key={x.id} className={`${styles.row} ${styles.hp}`} to={mapHref(php, x.href)} onClick={() => addRecentSearch(d.offered.treatment)}>{ICO_H}<span className={styles.grow}><span className={styles.block}>{x.name}</span><span className={styles.sub}>{(x.district ? `${x.district} · ` : "") + x.address}</span></span><span className={styles.price}>{Number(x.price).toLocaleString()}<small>원~</small></span></SmartLink>
                ))}
                <div className={styles.note}>시술을 고르면 병원별 가격이, 병원을 고르면 그 병원의 시술이 보여요</div>
              </>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
