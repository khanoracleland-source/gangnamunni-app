"use client";

import { useCallback, useEffect, useId, useLayoutEffect, useRef, useState, type KeyboardEvent } from "react";
import { createPortal } from "react-dom";
import Icon from "../Icon";
import ConfirmDialog, { type ConfirmSpec } from "./ConfirmDialog";
import { saveTreatmentPrices, type HospitalTreatment } from "@/lib/api";
import {
  BODY_PART_GROUPS,
  MAX_OPTIONS,
  bodyPartSummary,
  checkPrices,
  draftSignature,
  draftsFromOptions,
  legacyParts,
  newDraft,
  orderBodyParts,
  priceErrorKey,
  priceSaveFailure,
  unitChoices,
  type DraftField,
  type PriceDraft,
} from "@/lib/hospitalTreatmentPrices";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";

type RowError = { index: number | null; field: DraftField | null; key: MessageKey };

type Props = {
  hospitalId: number;
  /** The treatment whose prices are edited. */
  item: HospitalTreatment;
  units: readonly string[];
  /** The prices were saved; `saved` is the treatment with its new options (null when the answer did not carry it). */
  onSaved: (saved: HospitalTreatment | null) => void;
  /** The window is closed without saving. */
  onClose: () => void;
  /** The server says the treatment is gone or changed: the list should be read again. */
  onStale: () => void;
  onUnauthorized: () => void;
};

/** The element id suffix of the control that belongs to a refused field. */
const FIELD_ID: Record<DraftField, string> = { optionName: "name", bodyParts: "parts", dosageValue: "dose", dosageUnit: "unit", regularPrice: "regular", price: "price" };

const glyph = { viewBox: "0 0 24 24", "aria-hidden": true, focusable: "false" } as const;

function StarGlyph({ on }: { on: boolean }) {
  return (
    <svg className="icon" width={16} height={16} {...glyph} fill={on ? "currentColor" : "none"} stroke={on ? "none" : "currentColor"} strokeWidth={2} strokeLinejoin="round">
      <path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z" />
    </svg>
  );
}
function TrashGlyph() {
  return (
    <svg className="icon" width={18} height={18} {...glyph} fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
      <path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 002 2h6a2 2 0 002-2l1-12M9 7V4h6v3" />
    </svg>
  );
}
function InfoGlyph() {
  return (
    <svg className="icon" width={18} height={18} {...glyph} fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
      <circle cx="12" cy="12" r="9" />
      <path d="M12 8v5M12 16.5v.01" />
    </svg>
  );
}
function PlusGlyph() {
  return (
    <svg className="icon" width={16} height={16} {...glyph} fill="none" stroke="currentColor" strokeWidth={2.2} strokeLinecap="round">
      <path d="M12 5v14M5 12h14" />
    </svg>
  );
}
function ChevGlyph({ className }: { className: string }) {
  return (
    <svg className={`icon ${className}`} width={16} height={16} {...glyph} fill="none" stroke="currentColor" strokeWidth={2} strokeLinecap="round" strokeLinejoin="round">
      <path d="M6 9l6 6 6-6" />
    </svg>
  );
}

const PHONE_QUERY = "(max-width: 639px)";
const REDUCED_QUERY = "(prefers-reduced-motion: reduce)";
/** Closing animation lengths (ms) of the part list, plus the margin of the fallback timer. */
/** Closing animation lengths (ms) of the window, plus the margin of the fallback timer. */
const DIALOG_CLOSE_MS = { desktop: 140, phone: 220, reduced: 80, margin: 60 } as const;

const PANEL_CLOSE_MS = { desktop: 100, phone: 200, reduced: 80, margin: 60 } as const;

const supportsPopover = (): boolean => typeof HTMLElement !== "undefined" && typeof HTMLElement.prototype.showPopover === "function";

/** "{n}개 선택" with the number picked out: the text is split around a marker that stands in for the number. */
function countText(text: string, n: number) {
  const [before, after = ""] = text.split("\u0001");
  return (
    <>
      {before}
      <strong>{n}</strong>
      {after}
    </>
  );
}

/**
 * Multi select of body parts: a button that opens grouped check boxes in a popover (top layer, so no parent clips it).
 * Wide screens: a 480px panel under the button (above it when there is no room). Phones: a sheet from the bottom.
 * Esc and Tab close it; the arrow keys move between boxes.
 */
function PartPicker({
  id,
  value,
  invalid,
  describedBy,
  disabled,
  onChange,
}: {
  id: string;
  value: string[];
  invalid: boolean;
  describedBy?: string;
  disabled: boolean;
  onChange: (next: string[]) => void;
}) {
  const t = useT();
  // `mounted`: the panel is in the DOM; `open`: it is wanted open; `closing`: its closing animation runs.
  const [mounted, setMounted] = useState(false);
  const [open, setOpen] = useState(false);
  const [closing, setClosing] = useState(false);
  const root = useRef<HTMLDivElement>(null);
  const panel = useRef<HTMLDivElement>(null);
  const doneButton = useRef<HTMLButtonElement>(null);
  const pressedWhileOpen = useRef(false);
  const panelId = `${id}-list`;
  const native = mounted && supportsPopover();
  const shown = open && !closing;

  const finish = useCallback(() => {
    const el = panel.current;
    if (el) {
      try {
        if (el.matches(":popover-open")) el.hidePopover();
      } catch {
        // Not a popover (older browser): nothing to hide.
      }
    }
    setMounted(false);
    setOpen(false);
    setClosing(false);
  }, []);
  /** Starts the closing animation; without the Popover API the list simply goes. */
  const dismiss = useCallback(() => {
    if (native) setClosing(true);
    else finish();
  }, [native, finish]);

  /** Wide screens: coordinates from the button; phones: the sheet is placed by CSS. */
  const place = useCallback(() => {
    const el = panel.current;
    const btn = document.getElementById(id);
    if (!el || !btn) return;
    const s = el.style;
    if (window.matchMedia(PHONE_QUERY).matches) {
      s.top = "";
      s.bottom = "";
      s.left = "";
      s.maxHeight = "";
      delete el.dataset.side;
      delete el.dataset.align;
      return;
    }
    const r = btn.getBoundingClientRect();
    const scope = btn.closest(".htp__body")?.getBoundingClientRect();
    const seeTop = Math.max(0, scope?.top ?? 0);
    const seeBottom = Math.min(window.innerHeight, scope?.bottom ?? window.innerHeight);
    if (r.bottom < seeTop || r.top > seeBottom) {
      // The button scrolled out of sight: the list goes with it.
      dismiss();
      return;
    }
    const width = 480;
    const edge = 12;
    const gap = 4;
    const flushRight = r.left + width > window.innerWidth - edge;
    const left = flushRight ? Math.max(edge, r.right - width) : r.left;
    const below = window.innerHeight - r.bottom - edge;
    const above = r.top - edge;
    const down = below >= 280 || below >= above;
    s.left = `${left}px`;
    if (down) {
      s.top = `${r.bottom + gap}px`;
      s.bottom = "auto";
      s.maxHeight = `${Math.min(440, below - gap)}px`;
    } else {
      s.top = "auto";
      s.bottom = `${window.innerHeight - r.top + gap}px`;
      s.maxHeight = `${Math.min(440, above - gap)}px`;
    }
    el.dataset.side = down ? "below" : "above";
    el.dataset.align = flushRight ? "right" : "left";
  }, [id, dismiss]);

  // Shows the popover once it is in the DOM; keeps it placed and in step with the browser's own closing (outside click, Esc).
  useLayoutEffect(() => {
    if (!native) return;
    const el = panel.current;
    if (!el) return;
    place();
    try {
      if (!el.matches(":popover-open")) el.showPopover();
    } catch {
      // Not connected or already shown: nothing to do.
    }
    const onToggle = (e: Event) => {
      if ((e as Event & { newState?: string }).newState === "closed") finish();
    };
    const onScroll = (e: Event) => {
      if (e.target instanceof Node && el.contains(e.target)) return;
      place();
    };
    el.addEventListener("toggle", onToggle);
    window.addEventListener("resize", place);
    document.addEventListener("scroll", onScroll, true);
    return () => {
      el.removeEventListener("toggle", onToggle);
      window.removeEventListener("resize", place);
      document.removeEventListener("scroll", onScroll, true);
    };
  }, [native, place, finish]);

  // Closing animation: the panel stays in the DOM until it ends (or until the fallback timer, whichever is first).
  useEffect(() => {
    if (!closing) return;
    const el = panel.current;
    if (!el) {
      finish();
      return;
    }
    let done = false;
    let timer = 0;
    const end = () => {
      if (done) return;
      done = true;
      window.clearTimeout(timer);
      el.removeEventListener("animationend", onEnd);
      finish();
    };
    const onEnd = (e: AnimationEvent) => {
      if (e.target === el && /^htp-(pop-out|sheet-out|fade-out)$/.test(e.animationName)) end();
    };
    const ms = window.matchMedia(REDUCED_QUERY).matches ? PANEL_CLOSE_MS.reduced : window.matchMedia(PHONE_QUERY).matches ? PANEL_CLOSE_MS.phone : PANEL_CLOSE_MS.desktop;
    timer = window.setTimeout(end, ms + PANEL_CLOSE_MS.margin);
    el.addEventListener("animationend", onEnd);
    return () => {
      done = true;
      window.clearTimeout(timer);
      el.removeEventListener("animationend", onEnd);
    };
  }, [closing, finish]);

  // Without the Popover API there is no outside-click handling from the browser.
  useEffect(() => {
    if (!mounted || native) return;
    const away = (e: PointerEvent) => {
      if (root.current && e.target instanceof Node && !root.current.contains(e.target)) finish();
    };
    document.addEventListener("pointerdown", away);
    return () => document.removeEventListener("pointerdown", away);
  }, [mounted, native, finish]);

  const boxes = (): HTMLInputElement[] => Array.from(root.current?.querySelectorAll<HTMLInputElement>(`#${CSS.escape(panelId)} input[type="checkbox"]`) ?? []);
  const focusBox = (at: "first" | "next" | "prev") => {
    const all = boxes();
    if (all.length === 0) return;
    const now = all.indexOf(document.activeElement as HTMLInputElement);
    const to = at === "first" ? 0 : at === "next" ? Math.min(all.length - 1, now + 1) : Math.max(0, now - 1);
    all[to].focus();
  };

  const openPanel = () => {
    if (closing) return;
    setMounted(true);
    setOpen(true);
  };
  /** Closes the list; the focus goes back to the button at once (the animation is only decoration). */
  const startClose = (refocus: boolean) => {
    if (!open || closing) return;
    if (refocus) document.getElementById(id)?.focus();
    dismiss();
  };

  const onKeyDown = (e: KeyboardEvent<HTMLDivElement>) => {
    if (e.key === "Escape" && open) {
      // Closes the list only: the window stays.
      e.preventDefault();
      e.stopPropagation();
      startClose(true);
    } else if (e.key === "ArrowDown" || e.key === "ArrowUp") {
      const target = e.target;
      const onButton = target === document.getElementById(id);
      const onBox = target instanceof HTMLInputElement && target.type === "checkbox";
      if (!onButton && !onBox) return;
      e.preventDefault();
      if (closing) return;
      if (!open) {
        openPanel();
        requestAnimationFrame(() => focusBox("first"));
      } else if (!onButton) focusBox(e.key === "ArrowDown" ? "next" : "prev");
      else focusBox("first");
    }
  };

  const toggle = (part: string, on: boolean) => onChange(orderBodyParts(on ? [...value, part] : value.filter((p) => p !== part)));
  const clear = () => {
    onChange([]);
    // The button is disabled now: keep the focus inside the list.
    doneButton.current?.focus();
  };
  const summary = bodyPartSummary(value);
  const legacy = legacyParts(value);
  const groups = legacy.length > 0 ? [...BODY_PART_GROUPS, { id: "legacy", label: t("h.e.tp.parts.legacy"), parts: legacy }] : BODY_PART_GROUPS;
  const n = value.length;

  return (
    <div
      ref={root}
      className="htp__pick"
      onKeyDown={onKeyDown}
      onBlur={(e) => {
        const next = e.relatedTarget;
        if (next instanceof Node && root.current && !root.current.contains(next)) startClose(false);
      }}
    >
      <button
        type="button"
        id={id}
        className="input htp__pickbtn"
        aria-expanded={shown}
        aria-controls={mounted ? panelId : undefined}
        data-invalid={invalid ? "true" : undefined}
        aria-describedby={describedBy}
        disabled={disabled}
        title={value.length > 0 ? value.join(", ") : undefined}
        onPointerDown={() => {
          pressedWhileOpen.current = open;
        }}
        onClick={(e) => {
          // A mouse press on the button while the list is open is an outside click: the browser has closed the list already.
          const wasOpen = pressedWhileOpen.current && e.detail > 0;
          pressedWhileOpen.current = false;
          if (wasOpen) finish();
          else if (!open) openPanel();
          else startClose(false);
        }}
      >
        <span className="htp__pickline">
          {summary.kind === "none" ? (
            <span className="htp__pickval htp__pickval--ph">{t("h.e.tp.parts.pick")}</span>
          ) : (
            <>
              <span className="htp__pickval" translate="no">{summary.first}</span>
              {summary.kind === "more" ? <span className="htp__pickmore">{t("h.e.tp.parts.more", { first: "", n: summary.rest }).trim()}</span> : null}
            </>
          )}
        </span>
        <ChevGlyph className={shown ? "htp__chev htp__chev--up" : "htp__chev"} />
      </button>
      {mounted ? (
        <div
          ref={panel}
          id={panelId}
          className={native ? "htp__panel" : "htp__panel htp__panel--inline"}
          popover={native ? "auto" : undefined}
          role="group"
          aria-label={t("h.e.tp.parts.list")}
          tabIndex={-1}
          data-state={closing ? "closing" : undefined}
        >
          <span className="htp__phandle" aria-hidden="true" />
          <div className="htp__phead">
            <div className="htp__ptitle">
              <span className="htp__ptt">{t("h.e.tp.parts.pick")}</span>
              <span className="htp__psub" aria-live="polite">{countText(t("h.e.tp.parts.chosen", { n: "\u0001" }), n)}</span>
            </div>
            <button type="button" className="ibtn htp__pclose" aria-label={t("h.e.tp.parts.close")} title={t("m.close")} onClick={() => startClose(true)}>
              <Icon name="close" size={18} />
            </button>
          </div>
          <div className="htp__plist">
            {groups.map((group) => (
              <fieldset key={group.id} className="htp__grp">
                <legend className="htp__grplabel" translate="no">{group.label}</legend>
                <div className="htp__parts">
                  {group.parts.map((part) => (
                    <label key={part} className="htp__chk">
                      <input type="checkbox" checked={value.includes(part)} onChange={(e) => toggle(part, e.target.checked)} />
                      <span translate="no">{part}</span>
                    </label>
                  ))}
                </div>
              </fieldset>
            ))}
          </div>
          <div className="htp__pfoot">
            <span className="htp__pcount" aria-live="polite">{countText(t("h.e.tp.parts.count", { n: "\u0001" }), n)}</span>
            <button type="button" className="htp__pclear" disabled={n === 0} onClick={clear}>{t("h.e.tp.parts.clear")}</button>
            <button ref={doneButton} type="button" className="btn btn--primary htp__pdone" onClick={() => startClose(true)}>
              {t("h.e.tp.parts.done")}
              <span className="htp__pdonen">{` (${n})`}</span>
            </button>
          </div>
        </div>
      ) : null}
    </div>
  );
}

/**
 * The "edit prices" window of one treatment: native <dialog> (a bottom sheet on narrow screens), one card per price
 * option, the same checks the server makes. It is drawn outside the hospital form, so nothing in it can submit that form
 * or change its dirty state. Saving replaces every price option of the treatment in one request.
 */
export default function HospitalTreatmentPrices({ hospitalId, item, units, onSaved, onClose, onStale, onUnauthorized }: Props) {
  const t = useT();
  const uid = useId();
  const dialogRef = useRef<HTMLDialogElement>(null);
  const [drafts, setDrafts] = useState<PriceDraft[]>(() => draftsFromOptions(item.priceOptions));
  const [initial] = useState(() => draftSignature(drafts));
  const [busy, setBusy] = useState(false);
  const [rowError, setRowError] = useState<RowError | null>(null);
  const [generalError, setGeneralError] = useState<MessageKey | null>(null);
  const [discardAsk, setDiscardAsk] = useState(false);
  const [closing, setClosing] = useState(false);
  const closingRef = useRef(false);
  const returnTo = useRef<HTMLElement | null>(null);
  const onCloseRef = useRef(onClose);
  useEffect(() => {
    onCloseRef.current = onClose;
  });
  const dirty = draftSignature(drafts) !== initial;
  const choices = unitChoices(units, drafts);

  // Opens as a modal; the focus goes to the save button while the window animates in.
  useEffect(() => {
    const dialog = dialogRef.current;
    if (!dialog) return;
    returnTo.current = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    if (!dialog.open) dialog.showModal();
    dialog.querySelector<HTMLElement>("[data-autofocus]")?.focus({ preventScroll: true });
    return () => {
      // Unmounted without the closing animation (the page left): only tidy up.
      if (dialog.open) dialog.close();
      if (!closingRef.current && returnTo.current?.isConnected) returnTo.current.focus();
    };
  }, []);

  /** Every way out (X, Esc, backdrop, cancel, discard, saved) ends here: the closing animation runs, then the window goes. */
  const startClose = () => {
    if (closingRef.current) return;
    closingRef.current = true;
    setClosing(true);
  };

  // The window stays in the DOM until its closing animation ends (or the fallback timer fires, whichever is first).
  useEffect(() => {
    if (!closing) return;
    const dialog = dialogRef.current;
    let done = false;
    let timer = 0;
    const end = () => {
      if (done) return;
      done = true;
      window.clearTimeout(timer);
      dialog?.removeEventListener("animationend", onEnd);
      if (dialog?.open) dialog.close();
      if (returnTo.current?.isConnected) returnTo.current.focus();
      onCloseRef.current();
    };
    const onEnd = (e: AnimationEvent) => {
      if (e.target === dialog && /^htp-(out|sheet-out|fade-out)$/.test(e.animationName)) end();
    };
    const ms = window.matchMedia(REDUCED_QUERY).matches ? DIALOG_CLOSE_MS.reduced : window.matchMedia(PHONE_QUERY).matches ? DIALOG_CLOSE_MS.phone : DIALOG_CLOSE_MS.desktop;
    timer = window.setTimeout(end, ms + DIALOG_CLOSE_MS.margin);
    dialog?.addEventListener("animationend", onEnd);
    return () => {
      done = true;
      window.clearTimeout(timer);
      dialog?.removeEventListener("animationend", onEnd);
    };
  }, [closing]);

  const requestClose = () => {
    if (busy || closingRef.current) return;
    if (dirty) setDiscardAsk(true);
    else startClose();
  };

  const edit = (key: number, change: Partial<PriceDraft>) => {
    setDrafts((prev) => prev.map((d) => (d.key === key ? { ...d, ...change } : d)));
    setRowError(null);
    setGeneralError(null);
  };
  const setRepresentative = (key: number, on: boolean) => {
    setDrafts((prev) => prev.map((d) => (d.key === key ? { ...d, representative: on } : on ? { ...d, representative: false } : d)));
    setRowError(null);
    setGeneralError(null);
  };
  const addRow = () => {
    if (drafts.length >= MAX_OPTIONS) {
      setGeneralError("h.e.tp.err.tooMany");
      return;
    }
    const row = newDraft();
    setDrafts((prev) => [...prev, row]);
    setRowError(null);
    setGeneralError(null);
    requestAnimationFrame(() => document.getElementById(`${uid}-${row.key}-name`)?.focus());
  };
  const removeRow = (key: number) => {
    if (drafts.length <= 1) return;
    setDrafts((prev) => prev.filter((d) => d.key !== key));
    setRowError(null);
    setGeneralError(null);
  };

  const show = (error: RowError) => {
    if (error.index === null) {
      setGeneralError(error.key);
      return;
    }
    setRowError(error);
    const row = drafts[error.index];
    if (!row) return;
    const suffix = error.field ? FIELD_ID[error.field] : error.key === "h.e.tp.err.rep" ? "rep" : "name";
    requestAnimationFrame(() => document.getElementById(`${uid}-${row.key}-${suffix}`)?.focus());
  };

  const save = async () => {
    if (busy || closingRef.current) return;
    setRowError(null);
    setGeneralError(null);
    const check = checkPrices(drafts);
    if (!check.ok) {
      show({ index: check.error.index, field: check.error.field, key: priceErrorKey(check.error.code, check.error.field) });
      return;
    }
    setBusy(true);
    const res = await saveTreatmentPrices(hospitalId, item.id, check.options);
    setBusy(false);
    if (res.ok) {
      onSaved(res.data.item);
      startClose();
      return;
    }
    if (res.status === 401) {
      onUnauthorized();
      return;
    }
    const failure = priceSaveFailure(res, check.rows);
    if (failure.refresh) onStale();
    if (failure.row) show({ index: failure.row.index, field: failure.row.field, key: failure.key });
    else setGeneralError(failure.key);
  };

  const discardSpec: ConfirmSpec | null = discardAsk
    ? { title: t("h.e.tp.discard.title"), body: t("h.e.tp.discard.body"), confirmLabel: t("h.e.tp.discard.confirm"), cancelLabel: t("h.e.tp.discard.keep"), focus: "cancel" }
    : null;

  const titleId = `${uid}-title`;
  const lastRow = drafts.length <= 1;

  return createPortal(
    <>
      <dialog
        ref={dialogRef}
        className="dialog dialog--prices"
        aria-labelledby={titleId}
        aria-busy={busy || closing}
        data-state={closing ? "closing" : undefined}
        onCancel={(e) => {
          // Esc: the window decides (unsaved changes ask first; a running save cannot be cut off).
          e.preventDefault();
          requestClose();
        }}
        onClick={(e) => {
          if (e.target === e.currentTarget) requestClose();
        }}
      >
        <div className="htp">
          <span className="htp__handle" aria-hidden="true" />
          <div className="htp__head">
            <div className="htp__headt">
              <h2 id={titleId} className="dialog__title htp__title">{t("h.e.tp.title")}</h2>
              <span className="htp__chip" translate="no" title={item.name}>{item.name}</span>
            </div>
            <button type="button" className="ibtn dialog__close" onClick={requestClose} disabled={busy} aria-label={t("h.e.tp.close")} title={t("m.close")}>
              <Icon name="close" size={18} />
            </button>
          </div>

          <div className="htp__body">
            <div className="htp__hint">
              <InfoGlyph />
              <span>{t("h.e.tp.hint")}</span>
            </div>

            <div className="htp__tbl">
              <div className="htp__thead" aria-hidden="true">
                <span className="htp__th--c">{t("h.e.tp.rep")}</span>
                <span>{t("h.e.tp.name")}</span>
                <span>{t("h.e.tp.parts")}</span>
                <span>{t("h.e.tp.dose")} <span className="field__req">*</span></span>
                <span>{t("h.e.tp.unit")} <span className="field__req">*</span></span>
                <span className="htp__th--r">{t("h.e.tp.regular")}</span>
                <span className="htp__th--r">{t("h.e.tp.price")}</span>
                <span />
              </div>
              <ol className="htp__rows">
                {drafts.map((d, i) => {
                  const mine = rowError && rowError.index === i ? rowError : null;
                  const errId = `${uid}-${d.key}-err`;
                  const bad = (field: DraftField, fallback = false): boolean => mine !== null && (mine.field === field || (mine.field === null && fallback));
                  const describe = (field: DraftField, fallback = false): string | undefined => (bad(field, fallback) ? errId : undefined);
                  const n = i + 1;
                  const repLabel = d.representative ? t("h.e.tp.repOn") : t("h.e.tp.repSet");
                  const repError = mine !== null && mine.field === null && mine.key === "h.e.tp.err.rep";
                  return (
                    <li key={d.key} className={["htp__row", d.representative ? "htp__row--rep" : "", mine ? "htp__row--err" : ""].filter(Boolean).join(" ")}>
                      <div className="htp__rowhead">
                        <span className="htp__rowtitle">
                          <span className="htp__badge" aria-hidden="true">{n}</span>
                          <span className="htp__rowno">{t("h.e.tp.row", { n })}</span>
                        </span>
                        <div className="htp__rowact">
                          <button
                            type="button"
                            id={`${uid}-${d.key}-rep`}
                            className="htp__rep"
                            aria-pressed={d.representative}
                            aria-label={repLabel}
                            title={t("h.e.tp.repHint")}
                            disabled={busy}
                            aria-describedby={repError ? errId : undefined}
                            onClick={() => setRepresentative(d.key, !d.representative)}
                          >
                            <StarGlyph on={d.representative} />
                            <span className="htp__rept htp__rept--full" aria-hidden="true">{repLabel}</span>
                            <span className="htp__rept htp__rept--short" aria-hidden="true">{t("h.e.tp.rep")}</span>
                          </button>
                          <button type="button" className="ibtn htp__del" disabled={busy || lastRow} aria-label={t("h.e.tp.remove", { n })} title={t("h.e.tp.remove", { n })} onClick={() => removeRow(d.key)}>
                            <TrashGlyph />
                          </button>
                        </div>
                      </div>

                      <div className="htp__grid">
                        <div className="htp__f htp__f--name">
                          <label className="field__label" htmlFor={`${uid}-${d.key}-name`}>{t("h.e.tp.name")}</label>
                          <input
                            id={`${uid}-${d.key}-name`}
                            className="input"
                            type="text"
                            autoComplete="off"
                            maxLength={200}
                            placeholder={t("h.e.tp.name.ph")}
                            title={d.optionName || undefined}
                            value={d.optionName}
                            disabled={busy}
                            aria-invalid={bad("optionName", mine?.key === "h.e.tp.err.dup") ? true : undefined}
                            aria-describedby={describe("optionName", mine?.key === "h.e.tp.err.dup")}
                            onChange={(e) => edit(d.key, { optionName: e.target.value })}
                          />
                        </div>
                        <div className="htp__f htp__f--parts">
                          <label className="field__label" htmlFor={`${uid}-${d.key}-parts`}>{t("h.e.tp.parts")}</label>
                          <PartPicker
                            id={`${uid}-${d.key}-parts`}
                            value={d.bodyParts}
                            invalid={bad("bodyParts")}
                            describedBy={describe("bodyParts")}
                            disabled={busy}
                            onChange={(next) => edit(d.key, { bodyParts: next })}
                          />
                        </div>
                        <div className="htp__f htp__f--dose">
                          <label className="field__label" htmlFor={`${uid}-${d.key}-dose`}>{t("h.e.tp.dose")} <span className="field__req" aria-hidden="true">*</span></label>
                          <input
                            id={`${uid}-${d.key}-dose`}
                            className="input"
                            type="number"
                            inputMode="decimal"
                            min={0.01}
                            step={0.01}
                            value={d.dosage}
                            disabled={busy}
                            aria-invalid={bad("dosageValue") ? true : undefined}
                            aria-describedby={describe("dosageValue")}
                            onChange={(e) => edit(d.key, { dosage: e.target.value })}
                          />
                        </div>
                        <div className="htp__f htp__f--unit">
                          <label className="field__label" htmlFor={`${uid}-${d.key}-unit`}>{t("h.e.tp.unit")} <span className="field__req" aria-hidden="true">*</span></label>
                          <div className="htp__sel">
                            <select
                              id={`${uid}-${d.key}-unit`}
                              className="input"
                              title={d.unit || undefined}
                              value={d.unit}
                              disabled={busy}
                              aria-invalid={bad("dosageUnit") ? true : undefined}
                              aria-describedby={describe("dosageUnit")}
                              onChange={(e) => edit(d.key, { unit: e.target.value })}
                            >
                              <option value="">{t("h.e.tp.unit.pick")}</option>
                              {choices.map((unit) => (
                                <option key={unit} value={unit}>{unit}</option>
                              ))}
                            </select>
                            <ChevGlyph className="htp__selchev" />
                          </div>
                        </div>
                        <div className="htp__f htp__f--regular">
                          <label className="field__label" htmlFor={`${uid}-${d.key}-regular`}>{t("h.e.tp.regular.short")}</label>
                          <div className="htp__moneybox" data-invalid={bad("regularPrice") ? "true" : undefined}>
                            <input
                              id={`${uid}-${d.key}-regular`}
                              className="htp__moneyin"
                              type="number"
                              inputMode="numeric"
                              min={0}
                              step={1}
                              placeholder={t("h.e.tp.optional")}
                              value={d.regular}
                              disabled={busy}
                              aria-invalid={bad("regularPrice") ? true : undefined}
                              aria-describedby={describe("regularPrice")}
                              onChange={(e) => edit(d.key, { regular: e.target.value })}
                            />
                            <span className="htp__won">{t("h.e.tp.won")}</span>
                          </div>
                        </div>
                        <div className="htp__f htp__f--price">
                          <label className="field__label" htmlFor={`${uid}-${d.key}-price`}>{t("h.e.tp.price.short")}</label>
                          <div className="htp__moneybox" data-invalid={bad("price") ? "true" : undefined}>
                            <input
                              id={`${uid}-${d.key}-price`}
                              className="htp__moneyin"
                              type="number"
                              inputMode="numeric"
                              min={0}
                              step={1}
                              placeholder={t("h.e.tp.optional")}
                              value={d.price}
                              disabled={busy}
                              aria-invalid={bad("price") ? true : undefined}
                              aria-describedby={describe("price")}
                              onChange={(e) => edit(d.key, { price: e.target.value })}
                            />
                            <span className="htp__won">{t("h.e.tp.won")}</span>
                          </div>
                        </div>
                      </div>
                      {mine ? <p id={errId} className="field__error htp__rowerr" role="alert">{t(mine.key, { max: MAX_OPTIONS })}</p> : null}
                    </li>
                  );
                })}
              </ol>
            </div>

            <div className="htp__addrow">
              <button type="button" className="htp__add" disabled={busy} onClick={addRow}>
                <PlusGlyph />
                <span>{t("h.e.tp.add")}</span>
              </button>
              <div className="htp__notes">
                <p>{t("h.e.tp.noteMoney")}</p>
                <p>{t("h.e.tp.noteParts")}</p>
              </div>
            </div>
          </div>

          <div className="htp__foot">
            <p className="htp__msg" role="alert">{generalError ? t(generalError, { max: MAX_OPTIONS }) : rowError ? t("h.e.tp.err.fix") : ""}</p>
            {generalError || rowError ? null : (
              <p className="htp__sum">
                <span>{t("h.e.tp.sum.label")} <strong>{t("h.e.tp.sum.count", { n: drafts.length })}</strong></span> · <span>{t("h.e.tp.sum.rep", { n: drafts.filter((d) => d.representative).length })}</span>
              </p>
            )}
            <div className="htp__btns">
              <button type="button" className="btn btn--ghost" disabled={busy} onClick={requestClose}>{t("h.e.tp.cancel")}</button>
              <button type="button" className="btn btn--primary" disabled={busy} data-autofocus="" onClick={() => void save()}>
                {busy ? t("h.e.tp.saving") : t("h.e.tp.save")}
              </button>
            </div>
          </div>
        </div>
      </dialog>
      <ConfirmDialog
        spec={discardSpec}
        onConfirm={() => {
          setDiscardAsk(false);
          startClose();
        }}
        onClose={() => setDiscardAsk(false)}
      />
    </>,
    document.body,
  );
}
