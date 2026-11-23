"use client";

import { useEffect, useMemo, useRef, useState, type KeyboardEvent as ReactKeyboardEvent, type PointerEvent as ReactPointerEvent } from "react";
import Icon from "../Icon";
import { showUnitToast } from "../treatment-units/UnitToast";
import { Pill } from "./parts";
import { fetchOrderBoard, moveHospital, type OrderBoardItem } from "@/lib/api";
import { PHP_ADMIN_URL } from "@/lib/config";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/locale";

type Phase = "loading" | "ready" | "error";
type Status = { kind: "idle" | "saving" | "saved" | "error"; time?: string };
type Banner = "err" | "reload" | null;
type Drag = { id: number; name: string; position: number; left: number; width: number; y: number; slot: number; lineTop: number };

const clamp = (n: number, lo: number, hi: number) => Math.min(hi, Math.max(lo, n));
const clock = () => {
  const d = new Date();
  return `${String(d.getHours()).padStart(2, "0")}:${String(d.getMinutes()).padStart(2, "0")}`;
};

/** A copy of `list` with the item at `from` taken out and put at `to` (0-based). */
function relocate<T>(list: T[], from: number, to: number): T[] {
  const copy = list.slice();
  const [item] = copy.splice(from, 1);
  copy.splice(to, 0, item);
  return copy;
}

const renumber = (list: OrderBoardItem[]): OrderBoardItem[] => list.map((h, i) => ({ ...h, position: i + 1 }));

function GripIcon() {
  return (
    <svg className="icon" width="18" height="18" viewBox="0 0 20 20" aria-hidden="true" fill="currentColor">
      {[6, 14].flatMap((cx) => [5, 10, 15].map((cy) => <circle key={`${cx}-${cy}`} cx={cx} cy={cy} r="1.4" />))}
    </svg>
  );
}

function TopIcon() {
  return (
    <svg className="icon" width="18" height="18" viewBox="0 0 20 20" aria-hidden="true" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
      <path d="M4 4h12M6 12l4-4 4 4M10 8v8" />
    </svg>
  );
}

/**
 * Super admin's reorder screen: every active hospital in the app's order, one long list. Each move is saved at once
 * (the server renumbers 1..N) and the list is loaded again, so what is on screen is what is stored.
 */
export default function HospitalReorder({ onExit, onShowInactive }: { onExit: () => void; onShowInactive: () => void }) {
  const t = useT();
  const [phase, setPhase] = useState<Phase>("loading");
  const [items, setItems] = useState<OrderBoardItem[]>([]);
  const [inactive, setInactive] = useState(0);
  const [busy, setBusy] = useState(false);
  const [status, setStatus] = useState<Status>({ kind: "idle" });
  const [banner, setBanner] = useState<Banner>(null);
  const [flashId, setFlashId] = useState(0);
  const [kb, setKb] = useState<{ id: number; index: number } | null>(null);
  const [drag, setDrag] = useState<Drag | null>(null);
  const [editing, setEditing] = useState(0);
  const [editValue, setEditValue] = useState("");
  const [editErr, setEditErr] = useState(0);
  const [q, setQ] = useState("");
  const [cur, setCur] = useState(0);
  const [live, setLive] = useState("");
  const [attempt, setAttempt] = useState(0);

  const busyRef = useRef(false);
  const itemsRef = useRef<OrderBoardItem[]>([]);
  const commitRef = useRef<(id: number, to: number, undo?: boolean) => Promise<void>>(async () => undefined);
  const wrapRef = useRef<HTMLDivElement>(null);
  const stickRef = useRef<HTMLDivElement>(null);
  const listRef = useRef<HTMLOListElement>(null);
  const grips = useRef(new Map<number, HTMLButtonElement>());
  const rows = useRef(new Map<number, HTMLLIElement>());
  const dragRef = useRef<{ id: number; index: number; offset: number; pointerY: number; left: number; width: number; name: string } | null>(null);
  const frame = useRef(0);
  const liveTimer = useRef(0);
  const flashTimer = useRef(0);

  useEffect(() => {
    itemsRef.current = items;
  }, [items]);

  const announce = (text: string) => {
    window.clearTimeout(liveTimer.current);
    setLive("");
    liveTimer.current = window.setTimeout(() => setLive(text), 30);
  };

  const endSession = () => window.location.assign(PHP_ADMIN_URL);

  // The first load, and "try again" after a failed one.
  useEffect(() => {
    const controller = new AbortController();
    const run = async () => {
      const res = await fetchOrderBoard(controller.signal);
      if (controller.signal.aborted) return;
      if (res.ok) {
        setItems(res.data.items);
        setInactive(res.data.inactive);
        setPhase("ready");
        return;
      }
      if (res.status === 401) {
        window.location.assign(PHP_ADMIN_URL);
        return;
      }
      setPhase("error");
    };
    void run();
    return () => controller.abort();
  }, [attempt]);

  // The sticky panel's height, so a row scrolled into view is not hidden behind it.
  useEffect(() => {
    const stick = stickRef.current;
    const wrap = wrapRef.current;
    if (!stick || !wrap || typeof ResizeObserver === "undefined") return;
    const set = () => wrap.style.setProperty("--reo-stick", `${stick.offsetHeight}px`);
    set();
    const observer = new ResizeObserver(set);
    observer.observe(stick);
    return () => observer.disconnect();
  }, [phase]);

  useEffect(
    () => () => {
      window.clearTimeout(liveTimer.current);
      window.clearTimeout(flashTimer.current);
      window.cancelAnimationFrame(frame.current);
      document.body.classList.remove("reo-dragging");
    },
    [],
  );

  // While a row is held with the keyboard, the focus stays on its handle even though the row has moved.
  useEffect(() => {
    if (!kb) return;
    const grip = grips.current.get(kb.id);
    grip?.focus({ preventScroll: true });
    rows.current.get(kb.id)?.scrollIntoView({ block: "nearest" });
  }, [kb]);

  const shown = useMemo(() => {
    if (!kb) return items;
    const from = items.findIndex((h) => h.id === kb.id);
    return from < 0 ? items : relocate(items, from, kb.index);
  }, [items, kb]);

  const total = items.length;

  const retry = async () => {
    setBanner(null);
    const fresh = await fetchOrderBoard();
    if (fresh.ok) {
      setItems(fresh.data.items);
      setInactive(fresh.data.inactive);
    } else {
      setBanner("reload");
    }
  };

  // ───────── Saving a move ─────────
  const commit = async (id: number, to: number, undo = false) => {
    if (busyRef.current) return;
    const prev = itemsRef.current;
    const fromIndex = prev.findIndex((h) => h.id === id);
    if (fromIndex < 0) return;
    const from = fromIndex + 1;
    const item = prev[fromIndex];
    if (to === from) {
      announce(t("h.reo.live_same"));
      return;
    }
    if (to < 1 || to > prev.length) {
      setEditErr(id);
      return;
    }
    busyRef.current = true;
    setBusy(true);
    setBanner(null);
    setStatus({ kind: "saving" });
    setItems(renumber(relocate(prev, fromIndex, to - 1)));
    const res = await moveHospital(id, { position: to });
    if (!res.ok) {
      setItems(prev);
      setBanner("err");
      setStatus({ kind: "error" });
      announce(t("h.reo.live_failed"));
      busyRef.current = false;
      setBusy(false);
      if (res.status === 401) endSession();
      // The hospital is gone or the place is out of range: the screen is out of date, so load it again.
      else if (res.status === 404 || res.status === 400) void retry();
      return;
    }
    announce(t("h.reo.live_saved", { name: item.name, n: String(to) }));
    showUnitToast({
      tone: "ok",
      message: t("h.reo.moved", { name: item.name, from: String(from), to: String(to) }),
      closeLabel: t("m.close"),
      action: undo ? undefined : { label: t("h.reo.undo"), onClick: () => void commitRef.current(id, from, true) },
    });
    const fresh = await fetchOrderBoard();
    if (fresh.ok) {
      setItems(fresh.data.items);
      setInactive(fresh.data.inactive);
    } else {
      setBanner("reload");
    }
    setStatus({ kind: "saved", time: clock() });
    setFlashId(id);
    window.clearTimeout(flashTimer.current);
    flashTimer.current = window.setTimeout(() => setFlashId(0), 1400);
    busyRef.current = false;
    setBusy(false);
  };
  useEffect(() => {
    commitRef.current = commit;
  });

  // ───────── Dragging ─────────
  const slotAt = (y: number): { slot: number; lineTop: number } => {
    const list = listRef.current;
    const lis = list ? (Array.from(list.querySelectorAll(":scope > .reo-row")) as HTMLElement[]) : [];
    let slot = lis.length;
    for (let i = 0; i < lis.length; i += 1) {
      const r = lis[i].getBoundingClientRect();
      if (y < r.top + r.height / 2) {
        slot = i;
        break;
      }
    }
    const lineTop = slot < lis.length ? lis[slot].offsetTop : lis.length ? lis[lis.length - 1].offsetTop + lis[lis.length - 1].offsetHeight : 0;
    return { slot, lineTop };
  };

  const stopDrag = () => {
    window.cancelAnimationFrame(frame.current);
    dragRef.current = null;
    setDrag(null);
    document.body.classList.remove("reo-dragging");
  };

  const dragTick = () => {
    const d = dragRef.current;
    if (!d) return;
    const stickBottom = (stickRef.current?.getBoundingClientRect().bottom ?? 56);
    const coarse = window.matchMedia("(pointer: coarse)").matches;
    const zone = coarse ? 96 : 72;
    const maxSpeed = window.matchMedia("(prefers-reduced-motion: reduce)").matches ? 12 : 22;
    const top = stickBottom + zone;
    const bottom = window.innerHeight - zone;
    let speed = 0;
    if (d.pointerY < top) speed = -Math.min(maxSpeed, (top - d.pointerY) / 3);
    else if (d.pointerY > bottom) speed = Math.min(maxSpeed, (d.pointerY - bottom) / 3);
    if (speed !== 0) {
      window.scrollBy(0, speed);
      const { slot, lineTop } = slotAt(d.pointerY);
      setDrag((cur) => (cur ? { ...cur, slot, lineTop } : cur));
    }
    frame.current = window.requestAnimationFrame(dragTick);
  };

  const onGripDown = (e: ReactPointerEvent<HTMLButtonElement>, h: OrderBoardItem, index: number) => {
    if (busyRef.current || kb || (e.pointerType === "mouse" && e.button !== 0)) return;
    const li = rows.current.get(h.id);
    if (!li) return;
    e.preventDefault();
    e.currentTarget.setPointerCapture(e.pointerId);
    const rect = li.getBoundingClientRect();
    dragRef.current = { id: h.id, index, offset: e.clientY - rect.top, pointerY: e.clientY, left: rect.left, width: rect.width, name: h.name };
    const { slot, lineTop } = slotAt(e.clientY);
    document.body.classList.add("reo-dragging");
    setDrag({ id: h.id, name: h.name, position: index + 1, left: rect.left, width: rect.width, y: rect.top, slot, lineTop });
    announce(t("h.reo.live_dragStart", { name: h.name }));
    frame.current = window.requestAnimationFrame(dragTick);
  };

  const onGripMove = (e: ReactPointerEvent<HTMLButtonElement>) => {
    const d = dragRef.current;
    if (!d) return;
    d.pointerY = e.clientY;
    const { slot, lineTop } = slotAt(e.clientY);
    setDrag((cur) => (cur ? { ...cur, y: e.clientY - d.offset, slot, lineTop } : cur));
  };

  const onGripUp = (e: ReactPointerEvent<HTMLButtonElement>) => {
    const d = dragRef.current;
    if (!d) return;
    const { slot } = slotAt(e.clientY);
    stopDrag();
    const newIndex = slot > d.index ? slot - 1 : slot;
    if (newIndex === d.index) {
      announce(t("h.reo.live_same"));
      return;
    }
    void commit(d.id, newIndex + 1);
  };

  const cancelDrag = () => {
    if (!dragRef.current) return;
    stopDrag();
    announce(t("h.reo.live_dragCancel"));
  };

  useEffect(() => {
    if (!drag) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") cancelDrag();
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [drag === null]); // eslint-disable-line react-hooks/exhaustive-deps

  // ───────── Keyboard ─────────
  const onGripKey = (e: ReactKeyboardEvent<HTMLButtonElement>, h: OrderBoardItem, index: number) => {
    if (busyRef.current) return;
    if (!kb) {
      if (e.key === " " || e.key === "Enter") {
        e.preventDefault();
        setKb({ id: h.id, index });
        announce(t("h.reo.live_grab", { name: h.name, n: String(index + 1) }));
      }
      return;
    }
    if (kb.id !== h.id) return;
    const jump: Record<string, number> = { ArrowUp: kb.index - 1, ArrowDown: kb.index + 1, PageUp: kb.index - 10, PageDown: kb.index + 10, Home: 0, End: total - 1 };
    if (e.key in jump) {
      e.preventDefault();
      const next = clamp(jump[e.key], 0, total - 1);
      setKb({ id: h.id, index: next });
      announce(t("h.reo.live_step", { n: String(next + 1) }));
      return;
    }
    if (e.key === " " || e.key === "Enter") {
      e.preventDefault();
      const from = items.findIndex((x) => x.id === h.id);
      const to = kb.index;
      setKb(null);
      if (to === from) announce(t("h.reo.live_same"));
      else void commit(h.id, to + 1);
      return;
    }
    if (e.key === "Escape") {
      e.preventDefault();
      setKb(null);
      announce(t("h.reo.live_cancel", { n: String(index + 1) }));
    }
  };

  const onGripBlur = (id: number) => {
    if (!kb || kb.id !== id) return;
    window.setTimeout(() => {
      if (document.activeElement !== grips.current.get(id)) setKb((k) => (k && k.id === id ? null : k));
    }, 60);
  };

  // ───────── Typed position ─────────
  const startEdit = (h: OrderBoardItem, index: number) => {
    if (busyRef.current) return;
    setEditErr(0);
    setEditValue(String(index + 1));
    setEditing(h.id);
  };
  const applyEdit = (h: OrderBoardItem, index: number) => {
    const text = editValue.trim();
    const n = Number(text);
    if (!/^\d{1,7}$/.test(text) || n < 1 || n > total) {
      setEditErr(h.id);
      return;
    }
    setEditing(0);
    setEditErr(0);
    if (n === index + 1) {
      announce(t("h.reo.live_same"));
      return;
    }
    void commit(h.id, n);
  };

  // ───────── Search ─────────
  const needle = q.trim().toLowerCase();
  const hits = useMemo(() => (needle ? items.reduce<number[]>((acc, h, i) => (h.name.toLowerCase().includes(needle) ? [...acc, i] : acc), []) : []), [items, needle]);
  const hitIndex = hits.length ? clamp(cur, 0, hits.length - 1) : 0;
  const currentHit = hits.length ? items[hits[hitIndex]]?.id ?? 0 : 0;
  const hitSet = useMemo(() => new Set(hits.map((i) => items[i]?.id)), [hits, items]);
  const goHit = (to: number) => {
    if (!hits.length) return;
    const next = (to + hits.length) % hits.length;
    setCur(next);
    rows.current.get(items[hits[next]].id)?.scrollIntoView({ block: "center" });
  };

  const statusText =
    status.kind === "saving" ? t("h.reo.st_saving") : status.kind === "saved" ? t("h.reo.st_saved", { time: status.time ?? "" }) : status.kind === "error" ? t("h.reo.st_error") : t("h.reo.st_idle");

  // ───────── Screens ─────────
  let body;
  if (phase === "loading") {
    body = (
      <div className="reo-skels" aria-busy="true" aria-label={t("h.reo.loading")} role="status">
        {Array.from({ length: 9 }, (_, i) => (
          <div key={i} className="reo-skel" />
        ))}
      </div>
    );
  } else if (phase === "error") {
    body = (
      <div className="empty reo-state" role="alert">
        <Icon name="alert" size={22} />
        <p className="reo-state__t">{t("h.reo.loadErr_t")}</p>
        <p>{t("h.reo.loadErr_d")}</p>
        <div className="reo-state__acts">
          <button type="button" className="btn btn--primary" onClick={() => { setPhase("loading"); setAttempt((n) => n + 1); }}>{t("h.reo.retry")}</button>
          <button type="button" className="btn btn--ghost" onClick={onExit}>{t("h.reo.back")}</button>
        </div>
      </div>
    );
  } else if (items.length === 0) {
    body = (
      <div className="empty reo-state">
        <Icon name="hospital" size={22} />
        <p className="reo-state__t">{t("h.reo.empty_t")}</p>
        <p>{t("h.reo.empty_d")}</p>
        <div className="reo-state__acts">
          <button type="button" className="btn btn--ghost" onClick={onExit}>{t("h.reo.back")}</button>
        </div>
      </div>
    );
  } else {
    body = (
      <ol className="reo-list" ref={listRef} aria-label={t("h.reo.list")}>
        {shown.map((h, i) => {
          const classes = ["reo-row"];
          if (hitSet.has(h.id)) classes.push("is-hit");
          if (h.id === currentHit && needle) classes.push("is-cur");
          if (h.id === flashId) classes.push("is-flash");
          if (drag?.id === h.id) classes.push("is-lifted");
          if (kb?.id === h.id) classes.push("is-grabbed");
          const name = h.name || t("h.noName");
          const ad = h.adGrade !== "none";
          return (
            <li
              key={h.id}
              className={classes.join(" ")}
              data-id={h.id}
              ref={(el) => {
                if (el) rows.current.set(h.id, el);
                else rows.current.delete(h.id);
              }}
            >
              <button
                type="button"
                className="reo-grip"
                ref={(el) => {
                  if (el) grips.current.set(h.id, el);
                  else grips.current.delete(h.id);
                }}
                aria-roledescription={t("h.reo.gripRole")}
                aria-label={t("h.reo.grip", { name, n: String(i + 1) })}
                aria-pressed={kb?.id === h.id}
                aria-disabled={busy || undefined}
                onPointerDown={(e) => onGripDown(e, h, i)}
                onPointerMove={onGripMove}
                onPointerUp={onGripUp}
                onPointerCancel={cancelDrag}
                onKeyDown={(e) => onGripKey(e, h, i)}
                onBlur={() => onGripBlur(h.id)}
              >
                <GripIcon />
              </button>
              {editing === h.id ? (
                <input
                  className="reo-numin"
                  inputMode="numeric"
                  enterKeyHint="go"
                  maxLength={7}
                  value={editValue}
                  aria-label={t("h.reo.numIn", { name, max: String(total) })}
                  aria-invalid={editErr === h.id || undefined}
                  autoFocus
                  onFocus={(e) => e.currentTarget.select()}
                  onChange={(e) => setEditValue(e.target.value.replace(/\D/g, ""))}
                  onBlur={() => setEditing(0)}
                  onKeyDown={(e) => {
                    if (e.key === "Enter") {
                      e.preventDefault();
                      applyEdit(h, i);
                    } else if (e.key === "Escape") {
                      e.preventDefault();
                      setEditing(0);
                      setEditErr(0);
                    }
                  }}
                />
              ) : (
                <button type="button" className="reo-num" aria-disabled={busy || undefined} aria-label={t("h.reo.num", { name, n: String(i + 1) })} onClick={() => startEdit(h, i)}>
                  {i + 1}
                </button>
              )}
              <div className="reo-main">
                <div className="reo-name">
                  <span className="reo-name__t" title={name}>{name}</span>
                  {ad ? <span className="reo-name__ad"><Pill tone="info">{t("h.reo.ad")}</Pill></span> : null}
                </div>
                {editErr === h.id ? (
                  <div className="reo-sub is-err">{t("h.reo.numErr", { max: String(total) })}</div>
                ) : (
                  <div className="reo-sub">{h.address || "—"}</div>
                )}
              </div>
              {ad ? <span className="reo-ad"><Pill tone="info">{t("h.reo.ad")}</Pill></span> : <span className="reo-ad" />}
              <button
                type="button"
                className="ibtn reo-top"
                aria-disabled={busy || i === 0 || undefined}
                aria-label={t("h.reo.topLabel", { name })}
                title={t("h.reo.topLabel", { name })}
                onClick={() => {
                  if (!busy && i > 0) void commit(h.id, 1);
                }}
              >
                <TopIcon />
                <span>{t("h.reo.top")}</span>
              </button>
            </li>
          );
        })}
        {drag ? <li className="reo-drop" aria-hidden="true" style={{ top: drag.lineTop }} /> : null}
      </ol>
    );
  }

  return (
    <div className="reo" ref={wrapRef}>
      <p className="reo-help">{t("h.reo.help")}</p>
      <p className="reo-help reo-help__kbd">{t("h.reo.helpKbd")}</p>
      {phase === "ready" && inactive > 0 ? (
        <p className="reo-note">
          <span>{t("h.reo.inactive", { n: formatNumber(inactive) })}</span>
          <button type="button" className="txtbtn reo-note__lnk" onClick={onShowInactive}>{t("h.reo.inactiveLink")}</button>
        </p>
      ) : null}
      <div className="reo-stick" ref={stickRef}>
        {banner ? (
          <div className="reo-banner" role="alert">
            <span>{banner === "err" ? t("h.reo.err") : t("h.reo.reloadErr")}</span>
            {banner === "reload" ? <button type="button" className="txtbtn" onClick={() => void retry()}>{t("h.reo.reload")}</button> : null}
          </div>
        ) : null}
        <div className="reo-bar">
          <div className="reo-bar__top">
            <div className="reo-bar__title">
              <span>
                <strong>{t("h.reo.title")}</strong> <span className="reo-bar__count">{phase === "ready" ? t("h.reo.count", { n: formatNumber(total) }) : ""}</span>
              </span>
              <span className="reo-status" data-kind={status.kind}>
                {status.kind === "saving" ? <Icon name="refresh" size={16} className="reo-spin" /> : status.kind === "saved" ? <Icon name="check" size={16} /> : status.kind === "error" ? <Icon name="close" size={16} /> : null}
                {statusText}
              </span>
            </div>
            <button type="button" className="btn btn--primary" onClick={onExit}>
              <Icon name="check" size={16} />
              {t("h.reo.done")}
            </button>
          </div>
          {phase === "ready" && items.length > 0 ? (
            <div className="reo-find">
              <label className="reo-find__in">
                <span className="sr-only">{t("h.reo.find")}</span>
                <Icon name="search" size={16} />
                <input
                  className="input"
                  type="search"
                  value={q}
                  placeholder={t("h.reo.find")}
                  autoComplete="off"
                  enterKeyHint="search"
                  onChange={(e) => {
                    setQ(e.target.value);
                    setCur(0);
                  }}
                  onKeyDown={(e) => {
                    if (e.key === "Enter") {
                      e.preventDefault();
                      goHit(hitIndex + (e.shiftKey ? -1 : 1));
                    }
                  }}
                />
              </label>
              {needle ? (
                <>
                  <span className="reo-hits" aria-live="polite">{hits.length ? t("h.reo.hits", { i: String(hitIndex + 1), n: String(hits.length) }) : t("h.reo.noHits")}</span>
                  <button type="button" className="ibtn" aria-label={t("h.reo.prev")} title={t("h.reo.prev")} disabled={!hits.length} onClick={() => goHit(hitIndex - 1)}>
                    <Icon name="chevronUp" size={18} />
                  </button>
                  <button type="button" className="ibtn" aria-label={t("h.reo.next")} title={t("h.reo.next")} disabled={!hits.length} onClick={() => goHit(hitIndex + 1)}>
                    <Icon name="chevron" size={18} className="reo-down" />
                  </button>
                </>
              ) : null}
            </div>
          ) : null}
        </div>
      </div>
      {body}
      {drag ? (
        <div className="reo-ghost" aria-hidden="true" style={{ left: drag.left, width: drag.width, transform: `translate3d(0, ${drag.y}px, 0)` }}>
          <span className="reo-ghost__n">{drag.position}</span>
          <span className="reo-ghost__t">{drag.name}</span>
        </div>
      ) : null}
      <div className="sr-only" aria-live="assertive" aria-atomic="true">{live}</div>
    </div>
  );
}
