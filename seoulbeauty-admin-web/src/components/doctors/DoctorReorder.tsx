"use client";

import { useEffect, useRef, useState, type KeyboardEvent as ReactKeyboardEvent, type PointerEvent as ReactPointerEvent } from "react";
import Icon from "../Icon";
import { showUnitToast } from "../treatment-units/UnitToast";
import DoctorPhoto from "./DoctorPhoto";
import { fetchDoctorOrder, moveDoctor, type DoctorHospitalOption, type DoctorOrderItem } from "@/lib/api";
import { PHP_ADMIN_URL } from "@/lib/config";
import { actionFailureKey } from "@/lib/doctors";
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
function relocate<T>(list: T[], from: number, to: number): T[] {
  const copy = list.slice();
  const [item] = copy.splice(from, 1);
  copy.splice(to, 0, item);
  return copy;
}
const renumber = (list: DoctorOrderItem[]): DoctorOrderItem[] => list.map((d, i) => ({ ...d, position: i + 1 }));

function GripIcon() {
  return (
    <svg className="icon" width="18" height="18" viewBox="0 0 20 20" aria-hidden="true" fill="currentColor">
      {[6, 14].flatMap((cx) => [5, 10, 15].map((cy) => <circle key={`${cx}-${cy}`} cx={cx} cy={cy} r="1.4" />))}
    </svg>
  );
}
function ArrowIcon({ down = false }: { down?: boolean }) {
  return (
    <svg className="icon" width="18" height="18" viewBox="0 0 20 20" aria-hidden="true" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" style={down ? { transform: "rotate(180deg)" } : undefined}>
      <path d="M10 16V4M5 9l5-5 5 5" />
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
 * Display order of the active doctors of ONE hospital. Every move is saved at once (the server renumbers 1..N) and the list is loaded again,
 * so what is on screen is what is stored. A hospital account has its hospital fixed; a super admin picks one first.
 */
export default function DoctorReorder({
  hospitals,
  initialHospitalId,
  fixedHospital,
  onExit,
}: {
  hospitals: DoctorHospitalOption[];
  initialHospitalId: number;
  /** A hospital account: no picker, no "change hospital". */
  fixedHospital: boolean;
  onExit: () => void;
}) {
  const t = useT();
  const [hospitalId, setHospitalId] = useState(initialHospitalId);
  const [pick, setPick] = useState(initialHospitalId > 0 ? initialHospitalId : 0);
  if (hospitalId <= 0) {
    return (
      <div className="reo dc-pick">
        <p className="reo-help">{t("d.ro.pickDesc")}</p>
        <div className="dc-pick__box">
          <label className="field">
            <span className="field__label">{t("d.ro.pickLabel")}</span>
            <select className="sel sel--field" value={pick} onChange={(e) => setPick(Number(e.target.value) || 0)}>
              <option value={0}>{t("d.ro.pickNone")}</option>
              {hospitals.map((h) => (
                <option key={h.id} value={h.id}>{h.name}</option>
              ))}
            </select>
          </label>
          <div className="dc-pick__acts">
            <button type="button" className="btn btn--primary" disabled={pick <= 0} onClick={() => setHospitalId(pick)}>{t("d.ro.pickGo")}</button>
            <button type="button" className="btn btn--ghost" onClick={onExit}>{t("d.ro.back")}</button>
          </div>
        </div>
      </div>
    );
  }
  const hospitalName = hospitals.find((h) => h.id === hospitalId)?.name ?? "";
  return <Board key={hospitalId} hospitalId={hospitalId} hospitalName={hospitalName} fixedHospital={fixedHospital} onChangeHospital={() => { setPick(hospitalId); setHospitalId(0); }} onExit={onExit} />;
}

function Board({
  hospitalId,
  hospitalName,
  fixedHospital,
  onChangeHospital,
  onExit,
}: {
  hospitalId: number;
  hospitalName: string;
  fixedHospital: boolean;
  onChangeHospital: () => void;
  onExit: () => void;
}) {
  const t = useT();
  const [phase, setPhase] = useState<Phase>("loading");
  const [items, setItems] = useState<DoctorOrderItem[]>([]);
  const [orderable, setOrderable] = useState(true);
  const [busy, setBusy] = useState(false);
  const [status, setStatus] = useState<Status>({ kind: "idle" });
  const [banner, setBanner] = useState<Banner>(null);
  const [flashId, setFlashId] = useState(0);
  const [kb, setKb] = useState<{ id: number; index: number } | null>(null);
  const [drag, setDrag] = useState<Drag | null>(null);
  const [editing, setEditing] = useState(0);
  const [editValue, setEditValue] = useState("");
  const [editErr, setEditErr] = useState(0);
  const [live, setLive] = useState("");
  const [attempt, setAttempt] = useState(0);

  const busyRef = useRef(false);
  const itemsRef = useRef<DoctorOrderItem[]>([]);
  const commitRef = useRef<(id: number, to: number, undo?: boolean) => Promise<void>>(async () => undefined);
  const wrapRef = useRef<HTMLDivElement>(null);
  const stickRef = useRef<HTMLDivElement>(null);
  const listRef = useRef<HTMLOListElement>(null);
  const grips = useRef(new Map<number, HTMLButtonElement>());
  const rows = useRef(new Map<number, HTMLLIElement>());
  const dragRef = useRef<{ id: number; index: number; offset: number; pointerY: number } | null>(null);
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

  useEffect(() => {
    const controller = new AbortController();
    const run = async () => {
      const res = await fetchDoctorOrder(hospitalId, controller.signal);
      if (controller.signal.aborted) return;
      if (res.ok) {
        setItems(res.data.items);
        setOrderable(res.data.orderable);
        setPhase("ready");
        return;
      }
      if (res.status === 401) {
        endSession();
        return;
      }
      setPhase("error");
    };
    void run();
    return () => controller.abort();
  }, [hospitalId, attempt]);

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

  useEffect(() => {
    if (!kb) return;
    grips.current.get(kb.id)?.focus({ preventScroll: true });
    rows.current.get(kb.id)?.scrollIntoView({ block: "nearest" });
  }, [kb]);

  const shown = (() => {
    if (!kb) return items;
    const from = items.findIndex((d) => d.id === kb.id);
    return from < 0 ? items : relocate(items, from, kb.index);
  })();
  const total = items.length;
  const canMove = orderable && !busy;

  const reload = async () => {
    setBanner(null);
    const fresh = await fetchDoctorOrder(hospitalId);
    if (fresh.ok) {
      setItems(fresh.data.items);
      setOrderable(fresh.data.orderable);
    } else {
      setBanner("reload");
    }
  };

  const commit = async (id: number, to: number, undo = false) => {
    if (busyRef.current || !orderable) return;
    const prev = itemsRef.current;
    const fromIndex = prev.findIndex((d) => d.id === id);
    if (fromIndex < 0) return;
    const from = fromIndex + 1;
    const item = prev[fromIndex];
    if (to === from) {
      announce(t("d.ro.live_same"));
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
    const res = await moveDoctor(id, { position: to });
    if (!res.ok) {
      setItems(prev);
      setBanner("err");
      setStatus({ kind: "error" });
      announce(t("d.ro.live_failed"));
      busyRef.current = false;
      setBusy(false);
      if (res.status === 401) endSession();
      else if (res.status === 404 || res.status === 400 || res.status === 409) {
        showUnitToast({ tone: "error", message: t(actionFailureKey(res)), closeLabel: t("m.close") });
        void reload();
      }
      return;
    }
    announce(t("d.ro.live_saved", { name: item.name, n: String(to) }));
    showUnitToast({
      tone: "ok",
      message: t("d.ro.moved", { name: item.name, from: String(from), to: String(to) }),
      closeLabel: t("m.close"),
      action: undo ? undefined : { label: t("d.ro.undo"), onClick: () => void commitRef.current(id, from, true) },
    });
    const fresh = await fetchDoctorOrder(hospitalId);
    if (fresh.ok) {
      setItems(fresh.data.items);
      setOrderable(fresh.data.orderable);
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
    const stickBottom = stickRef.current?.getBoundingClientRect().bottom ?? 56;
    const zone = window.matchMedia("(pointer: coarse)").matches ? 96 : 72;
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
  const onGripDown = (e: ReactPointerEvent<HTMLButtonElement>, d: DoctorOrderItem, index: number) => {
    if (busyRef.current || !orderable || kb || (e.pointerType === "mouse" && e.button !== 0)) return;
    const li = rows.current.get(d.id);
    if (!li) return;
    e.preventDefault();
    e.currentTarget.setPointerCapture(e.pointerId);
    const rect = li.getBoundingClientRect();
    dragRef.current = { id: d.id, index, offset: e.clientY - rect.top, pointerY: e.clientY };
    const { slot, lineTop } = slotAt(e.clientY);
    document.body.classList.add("reo-dragging");
    setDrag({ id: d.id, name: d.name, position: index + 1, left: rect.left, width: rect.width, y: rect.top, slot, lineTop });
    announce(t("d.ro.live_dragStart", { name: d.name }));
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
      announce(t("d.ro.live_same"));
      return;
    }
    void commit(d.id, newIndex + 1);
  };
  const cancelDrag = () => {
    if (!dragRef.current) return;
    stopDrag();
    announce(t("d.ro.live_dragCancel"));
  };
  const dragging = drag !== null;
  useEffect(() => {
    if (!dragging) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") cancelDrag();
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [dragging]); // eslint-disable-line react-hooks/exhaustive-deps

  // ───────── Keyboard ─────────
  const onGripKey = (e: ReactKeyboardEvent<HTMLButtonElement>, d: DoctorOrderItem, index: number) => {
    if (busyRef.current || !orderable) return;
    if (!kb) {
      if (e.key === " " || e.key === "Enter") {
        e.preventDefault();
        setKb({ id: d.id, index });
        announce(t("d.ro.live_grab", { name: d.name, n: String(index + 1) }));
      }
      return;
    }
    if (kb.id !== d.id) return;
    const jump: Record<string, number> = { ArrowUp: kb.index - 1, ArrowDown: kb.index + 1, PageUp: kb.index - 10, PageDown: kb.index + 10, Home: 0, End: total - 1 };
    if (e.key in jump) {
      e.preventDefault();
      const next = clamp(jump[e.key], 0, total - 1);
      setKb({ id: d.id, index: next });
      announce(t("d.ro.live_step", { n: String(next + 1) }));
      return;
    }
    if (e.key === " " || e.key === "Enter") {
      e.preventDefault();
      const from = items.findIndex((x) => x.id === d.id);
      const to = kb.index;
      setKb(null);
      if (to === from) announce(t("d.ro.live_same"));
      else void commit(d.id, to + 1);
      return;
    }
    if (e.key === "Escape") {
      e.preventDefault();
      setKb(null);
      announce(t("d.ro.live_cancel", { n: String(index + 1) }));
    }
  };
  const onGripBlur = (id: number) => {
    if (!kb || kb.id !== id) return;
    window.setTimeout(() => {
      if (document.activeElement !== grips.current.get(id)) setKb((k) => (k && k.id === id ? null : k));
    }, 60);
  };

  // ───────── Typed position ─────────
  const startEdit = (d: DoctorOrderItem, index: number) => {
    if (!canMove) return;
    setEditErr(0);
    setEditValue(String(index + 1));
    setEditing(d.id);
  };
  const applyEdit = (d: DoctorOrderItem, index: number) => {
    const text = editValue.trim();
    const n = Number(text);
    if (!/^\d{1,7}$/.test(text) || n < 1 || n > total) {
      setEditErr(d.id);
      return;
    }
    setEditing(0);
    setEditErr(0);
    if (n === index + 1) {
      announce(t("d.ro.live_same"));
      return;
    }
    void commit(d.id, n);
  };

  const statusText =
    status.kind === "saving" ? t("d.ro.st_saving") : status.kind === "saved" ? t("d.ro.st_saved", { time: status.time ?? "" }) : status.kind === "error" ? t("d.ro.st_error") : t("d.ro.st_idle");

  let body;
  if (phase === "loading") {
    body = (
      <div className="reo-skels" aria-busy="true" aria-label={t("d.ro.loading")} role="status">
        {Array.from({ length: 5 }, (_, i) => (
          <div key={i} className="reo-skel" />
        ))}
      </div>
    );
  } else if (phase === "error") {
    body = (
      <div className="empty reo-state" role="alert">
        <Icon name="alert" size={22} />
        <p className="reo-state__t">{t("d.ro.loadErr_t")}</p>
        <p>{t("d.ro.loadErr_d")}</p>
        <div className="reo-state__acts">
          <button type="button" className="btn btn--primary" onClick={() => { setPhase("loading"); setAttempt((n) => n + 1); }}>{t("d.ro.retry")}</button>
          <button type="button" className="btn btn--ghost" onClick={onExit}>{t("d.ro.back")}</button>
        </div>
      </div>
    );
  } else if (items.length < 2) {
    body = (
      <div className="empty reo-state">
        <Icon name="people" size={22} />
        <p className="reo-state__t">{t("d.ro.empty_t")}</p>
        <p>{t("d.ro.empty_d")}</p>
        <div className="reo-state__acts">
          {fixedHospital ? null : <button type="button" className="btn btn--ghost" onClick={onChangeHospital}>{t("d.ro.change")}</button>}
          <button type="button" className="btn btn--ghost" onClick={onExit}>{t("d.ro.back")}</button>
        </div>
      </div>
    );
  } else {
    body = (
      <ol className="reo-list" ref={listRef} aria-label={t("d.ro.list")}>
        {shown.map((d, i) => {
          const classes = ["reo-row", "dc-row"];
          if (d.id === flashId) classes.push("is-flash");
          if (drag?.id === d.id) classes.push("is-lifted");
          if (kb?.id === d.id) classes.push("is-grabbed");
          const name = d.name || t("d.card.noName");
          return (
            <li
              key={d.id}
              className={classes.join(" ")}
              data-id={d.id}
              ref={(el) => {
                if (el) rows.current.set(d.id, el);
                else rows.current.delete(d.id);
              }}
            >
              <button
                type="button"
                className="reo-grip"
                ref={(el) => {
                  if (el) grips.current.set(d.id, el);
                  else grips.current.delete(d.id);
                }}
                aria-roledescription={t("d.ro.gripRole")}
                aria-label={t("d.ro.grip", { name, n: String(i + 1) })}
                aria-pressed={kb?.id === d.id}
                aria-disabled={!canMove || undefined}
                onPointerDown={(e) => onGripDown(e, d, i)}
                onPointerMove={onGripMove}
                onPointerUp={onGripUp}
                onPointerCancel={cancelDrag}
                onKeyDown={(e) => onGripKey(e, d, i)}
                onBlur={() => onGripBlur(d.id)}
              >
                <GripIcon />
              </button>
              {editing === d.id ? (
                <input
                  className="reo-numin"
                  inputMode="numeric"
                  enterKeyHint="go"
                  maxLength={7}
                  value={editValue}
                  aria-label={t("d.ro.numIn", { name, max: String(total) })}
                  aria-invalid={editErr === d.id || undefined}
                  autoFocus
                  onFocus={(e) => e.currentTarget.select()}
                  onChange={(e) => setEditValue(e.target.value.replace(/\D/g, ""))}
                  onBlur={() => setEditing(0)}
                  onKeyDown={(e) => {
                    if (e.key === "Enter") {
                      e.preventDefault();
                      applyEdit(d, i);
                    } else if (e.key === "Escape") {
                      e.preventDefault();
                      setEditing(0);
                      setEditErr(0);
                    }
                  }}
                />
              ) : (
                <button type="button" className="reo-num" aria-disabled={!canMove || undefined} aria-label={t("d.ro.num", { name, n: String(i + 1) })} onClick={() => startEdit(d, i)}>
                  {i + 1}
                </button>
              )}
              <DoctorPhoto name={name} url={d.photoUrl} />
              <div className="reo-main">
                <div className="reo-name"><span className="reo-name__t" title={name}>{name}</span></div>
                {editErr === d.id ? (
                  <div className="reo-sub is-err">{t("d.ro.numErr", { max: String(total) })}</div>
                ) : (
                  <div className="reo-sub">{d.specialty || t("d.card.noSpecialty")}</div>
                )}
              </div>
              <div className="dc-ctl">
                <button type="button" className="ibtn dc-ctl__btn" aria-disabled={!canMove || i === 0 || undefined} aria-label={t("d.ro.upLabel", { name })} title={t("d.ro.upLabel", { name })} onClick={() => { if (canMove && i > 0) void commit(d.id, i); }}>
                  <ArrowIcon />
                </button>
                <button type="button" className="ibtn dc-ctl__btn" aria-disabled={!canMove || i === total - 1 || undefined} aria-label={t("d.ro.downLabel", { name })} title={t("d.ro.downLabel", { name })} onClick={() => { if (canMove && i < total - 1) void commit(d.id, i + 2); }}>
                  <ArrowIcon down />
                </button>
                <button type="button" className="ibtn reo-top" aria-disabled={!canMove || i === 0 || undefined} aria-label={t("d.ro.topLabel", { name })} title={t("d.ro.topLabel", { name })} onClick={() => { if (canMove && i > 0) void commit(d.id, 1); }}>
                  <TopIcon />
                  <span>{t("d.ro.top")}</span>
                </button>
              </div>
            </li>
          );
        })}
        {drag ? <li className="reo-drop" aria-hidden="true" style={{ top: drag.lineTop }} /> : null}
      </ol>
    );
  }

  return (
    <div className="reo" ref={wrapRef}>
      <p className="reo-help">{t("d.ro.help")}</p>
      <p className="reo-help reo-help__kbd">{t("d.ro.helpKbd")}</p>
      <div className="reo-stick" ref={stickRef}>
        {phase === "ready" && !orderable ? (
          <div className="reo-banner" role="alert"><span>{t("d.ro.unavailable")}</span></div>
        ) : null}
        {banner ? (
          <div className="reo-banner" role="alert">
            <span>{banner === "err" ? t("d.ro.err") : t("d.ro.reloadErr")}</span>
            {banner === "reload" ? <button type="button" className="txtbtn" onClick={() => void reload()}>{t("d.ro.reload")}</button> : null}
          </div>
        ) : null}
        <div className="reo-bar">
          <div className="reo-bar__top">
            <div className="reo-bar__title">
              <span>
                <strong>{hospitalName || t("d.ro.title")}</strong> <span className="reo-bar__count">{phase === "ready" ? t("d.ro.count", { n: formatNumber(total) }) : ""}</span>
              </span>
              <span className="reo-status" data-kind={status.kind}>
                {status.kind === "saving" ? <Icon name="refresh" size={16} className="reo-spin" /> : status.kind === "saved" ? <Icon name="check" size={16} /> : status.kind === "error" ? <Icon name="close" size={16} /> : null}
                {statusText}
              </span>
            </div>
            {fixedHospital ? null : <button type="button" className="btn btn--ghost" onClick={onChangeHospital}>{t("d.ro.change")}</button>}
            <button type="button" className="btn btn--primary" onClick={onExit}>
              <Icon name="check" size={16} />
              {t("d.ro.done")}
            </button>
          </div>
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
