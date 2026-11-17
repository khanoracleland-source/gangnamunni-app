"use client";

import { useEffect, useId, useLayoutEffect, useRef, useState, type KeyboardEvent } from "react";
import { createPortal } from "react-dom";
import Icon from "../Icon";
import ConfirmDialog, { type ConfirmSpec } from "./ConfirmDialog";
import { saveTreatmentQuestions, type HospitalTreatment } from "@/lib/api";
import {
  COUNTER_FROM,
  COUNTER_WARN_FROM,
  MAX_QUESTIONS,
  MAX_QUESTION_LENGTH,
  checkQuestions,
  draftSignature,
  draftsFromQuestions,
  moveDraft,
  newQuestionDraft,
  normalizeQuestion,
  questionLength,
  questionSaveFailure,
  rowInfos,
  type QuestionDraft,
  type QuestionError,
} from "@/lib/hospitalTreatmentQuestions";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";

type Props = {
  hospitalId: number;
  /** The treatment whose questions are edited. */
  item: HospitalTreatment;
  /** The questions were saved; `saved` is the treatment with its new questions (null when the answer did not carry it), `count` how many are stored now. */
  onSaved: (saved: HospitalTreatment | null, count: number) => void;
  /** The window is closed without saving. */
  onClose: () => void;
  /** The server says the treatment is gone or changed: the list should be read again. */
  onStale: () => void;
  onUnauthorized: () => void;
};

/** What the refused save (or the check) says: the line above the list and, when a row is named, that row. */
type Failure = { key: MessageKey; vars: Record<string, number>; error: QuestionError | null };

const glyph = { viewBox: "0 0 24 24", "aria-hidden": true, focusable: "false" } as const;
const stroke = { fill: "none", stroke: "currentColor", strokeWidth: 2, strokeLinecap: "round", strokeLinejoin: "round" } as const;

function InfoGlyph({ size = 18 }: { size?: number }) {
  return (
    <svg className="icon" width={size} height={size} {...glyph} {...stroke}>
      <circle cx="12" cy="12" r="9" />
      <path d="M12 8v.01M11 12h1v5h1" />
    </svg>
  );
}
function AlertGlyph({ size = 18 }: { size?: number }) {
  return (
    <svg className="icon" width={size} height={size} {...glyph} {...stroke}>
      <circle cx="12" cy="12" r="9" />
      <path d="M12 8v5M12 16.5v.01" />
    </svg>
  );
}
function HelpGlyph() {
  return (
    <svg className="icon" width={22} height={22} {...glyph} {...stroke}>
      <circle cx="12" cy="12" r="9" />
      <path d="M9.5 9.5a2.5 2.5 0 114 2c-.9.6-1.5 1.1-1.5 2.3M12 17v.01" />
    </svg>
  );
}
function PlusGlyph() {
  return (
    <svg className="icon" width={18} height={18} {...glyph} {...stroke} strokeWidth={2.2}>
      <path d="M12 5v14M5 12h14" />
    </svg>
  );
}
function UpGlyph() {
  return (
    <svg className="icon" width={18} height={18} {...glyph} {...stroke}>
      <path d="M6 15l6-6 6 6" />
    </svg>
  );
}
function DownGlyph() {
  return (
    <svg className="icon" width={18} height={18} {...glyph} {...stroke}>
      <path d="M6 9l6 6 6-6" />
    </svg>
  );
}
function TrashGlyph() {
  return (
    <svg className="icon" width={18} height={18} {...glyph} {...stroke}>
      <path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 002 2h6a2 2 0 002-2l1-12M9 7V4h6v3" />
    </svg>
  );
}

const PHONE_QUERY = "(max-width: 639px)";
const REDUCED_QUERY = "(prefers-reduced-motion: reduce)";
/** Closing animation lengths (ms) of the window, plus the margin of the fallback timer. */
const DIALOG_CLOSE_MS = { desktop: 140, phone: 220, reduced: 80, margin: 60 } as const;

/** Grows a text box to its text (the style sheet caps it); a box that is not shown has no measure and keeps its style. */
function autosize(el: HTMLTextAreaElement) {
  el.style.height = "auto";
  if (el.scrollHeight > 0) el.style.height = `${el.scrollHeight + el.offsetHeight - el.clientHeight}px`;
  else el.style.height = "";
}

/**
 * The "pre-treatment questions" window of one treatment: native <dialog> (a bottom sheet on narrow screens), one text
 * box per Yes / No question, with the same checks the server makes. It is drawn outside the hospital form, so nothing
 * in it can submit that form or change its dirty state. Saving replaces every question of the treatment in one request.
 */
export default function HospitalTreatmentQuestions({ hospitalId, item, onSaved, onClose, onStale, onUnauthorized }: Props) {
  const t = useT();
  const uid = useId();
  const dialogRef = useRef<HTMLDialogElement>(null);
  const summaryRef = useRef<HTMLDivElement>(null);
  const [drafts, setDrafts] = useState<QuestionDraft[]>(() => draftsFromQuestions(item.questions ?? []));
  const [initial] = useState(() => draftSignature(drafts));
  const [busy, setBusy] = useState(false);
  const [failure, setFailure] = useState<Failure | null>(null);
  const [announce, setAnnounce] = useState("");
  const [discardAsk, setDiscardAsk] = useState(false);
  const [closing, setClosing] = useState(false);
  const closingRef = useRef(false);
  const returnTo = useRef<HTMLElement | null>(null);
  const pendingFocus = useRef<{ key: number; kind: "up" | "down" } | null>(null);
  const onCloseRef = useRef(onClose);
  useEffect(() => {
    onCloseRef.current = onClose;
  });
  const dirty = draftSignature(drafts) !== initial;
  const infos = rowInfos(drafts);
  const full = drafts.length >= MAX_QUESTIONS;
  const idOf = (key: number, part: string): string => `${uid}-${key}-${part}`;
  const byId = (id: string): HTMLElement | null => document.getElementById(id);

  const sizeAll = () => {
    dialogRef.current?.querySelectorAll<HTMLTextAreaElement>("textarea.htq__input").forEach(autosize);
  };

  // Opens as a modal; the focus goes to the first question box (to the add button when there is none) while the window animates in.
  useEffect(() => {
    const dialog = dialogRef.current;
    if (!dialog) return;
    returnTo.current = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    if (!dialog.open) dialog.showModal();
    sizeAll();
    dialog.querySelector<HTMLElement>("[data-autofocus]")?.focus({ preventScroll: true });
    window.addEventListener("resize", sizeAll);
    return () => {
      window.removeEventListener("resize", sizeAll);
      // Unmounted without the closing animation (the page left): only tidy up.
      if (dialog.open) dialog.close();
      if (!closingRef.current && returnTo.current?.isConnected) returnTo.current.focus();
    };
  }, []);

  // Text boxes follow their text; the focus of a moved row's button is put back once the rows have their new places.
  useLayoutEffect(() => {
    sizeAll();
    const want = pendingFocus.current;
    if (!want) return;
    pendingFocus.current = null;
    const primary = byId(idOf(want.key, want.kind));
    const other = byId(idOf(want.key, want.kind === "up" ? "down" : "up"));
    const target = primary instanceof HTMLButtonElement && !primary.disabled ? primary : other;
    if (target instanceof HTMLButtonElement && !target.disabled) target.focus();
  });

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

  const edit = (key: number, text: string) => {
    setDrafts((prev) => prev.map((d) => (d.key === key ? { ...d, text } : d)));
    setFailure(null);
  };
  const addRow = () => {
    if (drafts.length >= MAX_QUESTIONS || busy) return;
    const row = newQuestionDraft();
    setDrafts((prev) => [...prev, row]);
    setFailure(null);
    requestAnimationFrame(() => {
      const box = byId(idOf(row.key, "text"));
      box?.focus();
      box?.scrollIntoView({ block: "nearest" });
    });
  };
  const removeRow = (i: number) => {
    if (busy) return;
    const next = drafts[i + 1] ?? drafts[i - 1];
    setDrafts((prev) => prev.filter((_, at) => at !== i));
    setFailure(null);
    requestAnimationFrame(() => (next ? byId(idOf(next.key, "text")) : byId(`${uid}-add`))?.focus());
  };
  const moveRow = (i: number, step: -1 | 1) => {
    const to = i + step;
    if (busy || to < 0 || to >= drafts.length) return;
    pendingFocus.current = { key: drafts[i].key, kind: step < 0 ? "up" : "down" };
    setDrafts((prev) => moveDraft(prev, i, to));
    setAnnounce(t("h.e.tq.moved", { from: i + 1, to: to + 1 }));
    setFailure(null);
  };

  /** Puts the refusal on screen: the line above the list, and the named row marked and focused (else the line itself). */
  const show = (next: Failure) => {
    setFailure(next);
    const index = next.error?.index ?? null;
    const row = index === null ? undefined : drafts[index];
    requestAnimationFrame(() => {
      if (row) byId(idOf(row.key, "text"))?.focus();
      else summaryRef.current?.focus();
    });
  };

  const save = async () => {
    if (busy || closingRef.current) return;
    setFailure(null);
    const check = checkQuestions(drafts);
    if (!check.ok) {
      const { code, index, length } = check.error;
      if (code === "too_many") show({ key: "h.e.tq.err.too_many", vars: {}, error: check.error });
      else show({ key: "h.e.tq.err.sum.too_long", vars: { i: (index ?? 0) + 1 }, error: { code, index, length } });
      return;
    }
    setBusy(true);
    const res = await saveTreatmentQuestions(hospitalId, item.id, check.questions);
    setBusy(false);
    if (res.ok) {
      onSaved(res.data.item, res.data.count ?? check.questions.length);
      startClose();
      return;
    }
    if (res.status === 401) {
      onUnauthorized();
      return;
    }
    const f = questionSaveFailure(res, check.rows, drafts);
    if (f.refresh) onStale();
    show({ key: f.key, vars: f.vars, error: f.error });
  };

  const discardSpec: ConfirmSpec | null = discardAsk
    ? { title: t("h.e.tp.discard.title"), body: t("h.e.tq.discard.body"), confirmLabel: t("h.e.tp.discard.confirm"), cancelLabel: t("h.e.tp.discard.keep"), focus: "cancel" }
    : null;

  const titleId = `${uid}-title`;
  const summaryId = `${uid}-sum`;
  const count = drafts.length;
  // Enter would only put a line break into a one-line question (the server turns it into a space): it does nothing.
  const onBoxKeyDown = (e: KeyboardEvent<HTMLTextAreaElement>) => {
    if (e.key === "Enter" && !e.nativeEvent.isComposing) e.preventDefault();
  };

  return createPortal(
    <>
      <dialog
        ref={dialogRef}
        className="dialog dialog--questions"
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
        <div className="htq">
          <span className="htq__handle" aria-hidden="true" />
          <div className="htq__head">
            <div className="htq__headt">
              <h2 id={titleId} className="dialog__title htq__title">{t("h.e.tq.title")}</h2>
              <span className="htp__chip" translate="no" title={item.name}>{item.name}</span>
            </div>
            <button type="button" className="ibtn dialog__close" onClick={requestClose} disabled={busy} aria-label={t("h.e.tq.close")} title={t("m.close")}>
              <Icon name="close" size={18} />
            </button>
          </div>

          <div className="htq__body">
            {failure ? (
              <div ref={summaryRef} id={summaryId} className="htq__alert" role="alert" tabIndex={-1}>
                <AlertGlyph />
                <span>{t(failure.key, failure.vars)}</span>
              </div>
            ) : null}

            <div className="htq__note">
              <InfoGlyph />
              <span>{t("h.e.tq.note")}</span>
            </div>

            <div className="htq__card">
              <div className="htq__bar">
                <div className="htq__barl">
                  <span className="htq__count" aria-live="polite" aria-label={t("h.e.tq.count.aria", { n: count })}>
                    {t("h.e.tq.count")} <span className={full ? "htq__num htq__num--max" : "htq__num"}>{`${count}/${MAX_QUESTIONS}`}</span>
                  </span>
                  <span className="htq__tag">{t("h.e.tq.optional")}</span>
                </div>
                <span className="htq__ans">
                  {t("h.e.tq.answers")}
                  <span className="htq__pill" aria-hidden="true">{t("h.e.tq.yes")}</span>
                  <span className="htq__pill" aria-hidden="true">{t("h.e.tq.no")}</span>
                </span>
              </div>

              {count === 0 ? (
                <div className="htq__empty">
                  <span className="htq__emptyico"><HelpGlyph /></span>
                  <strong>{t("h.e.tq.empty.t")}</strong>
                  <span>{t("h.e.tq.empty.d")}</span>
                </div>
              ) : (
                <ol className="htq__rows">
                  {drafts.map((d, i) => {
                    const info = infos[i];
                    const n = i + 1;
                    const mine = failure?.error && failure.error.index === i ? failure.error : null;
                    const tooLong = mine !== null && mine.code === "too_long";
                    const invalid = mine !== null && (mine.code === "too_long" || mine.code === "invalid_field");
                    const len = questionLength(d.text);
                    const errId = idOf(d.key, "err");
                    const hintId = idOf(d.key, "hint");
                    const describedBy = [tooLong ? errId : mine ? summaryId : "", info.blank || info.duplicateOf !== null ? hintId : ""].filter(Boolean).join(" ") || undefined;
                    return (
                      <li key={d.key} className="htq__row">
                        <label className="htq__no" htmlFor={idOf(d.key, "text")}>
                          <span className="sr-only">{t("h.e.tq.field", { n })}</span>
                          <span aria-hidden="true">{n}</span>
                        </label>
                        <div className="htq__fld">
                          <textarea
                            id={idOf(d.key, "text")}
                            className="input htq__input"
                            rows={1}
                            maxLength={MAX_QUESTION_LENGTH}
                            autoComplete="off"
                            placeholder={t("h.e.tq.ph")}
                            value={d.text}
                            disabled={busy}
                            data-autofocus={i === 0 ? "" : undefined}
                            aria-invalid={invalid ? true : undefined}
                            aria-describedby={describedBy}
                            onChange={(e) => edit(d.key, e.target.value)}
                            onKeyDown={onBoxKeyDown}
                          />
                          {tooLong ? (
                            <p id={errId} className="htq__err" role="alert">
                              <AlertGlyph size={16} />
                              <span>{t("h.e.tq.err.too_long", { n: mine.length ?? questionLength(normalizeQuestion(d.text)) })}</span>
                            </p>
                          ) : null}
                          {info.duplicateOf !== null ? (
                            <p id={hintId} className="htq__warn">
                              <InfoGlyph size={16} />
                              <span>{t("h.e.tq.dup", { n: info.duplicateOf })}</span>
                            </p>
                          ) : info.blank ? (
                            <p id={hintId} className="htq__blank">{t("h.e.tq.blank")}</p>
                          ) : null}
                          {len >= COUNTER_FROM || tooLong ? (
                            <p className="htq__cnt-wrap">
                              <span className={["htq__cnt", tooLong ? "htq__cnt--bad" : len >= COUNTER_WARN_FROM ? "htq__cnt--warn" : ""].filter(Boolean).join(" ")} aria-label={t("h.e.tq.cnt.aria", { n: len })}>
                                {`${len}/${MAX_QUESTION_LENGTH}`}
                              </span>
                            </p>
                          ) : null}
                        </div>
                        <div className="htq__acts">
                          <button
                            type="button"
                            id={idOf(d.key, "up")}
                            className="htq__ib"
                            disabled={busy || i === 0}
                            aria-label={t("h.e.tq.up", { n })}
                            title={t("h.e.tq.up", { n })}
                            onClick={() => moveRow(i, -1)}
                          >
                            <UpGlyph />
                          </button>
                          <button
                            type="button"
                            id={idOf(d.key, "down")}
                            className="htq__ib"
                            disabled={busy || i === count - 1}
                            aria-label={t("h.e.tq.down", { n })}
                            title={t("h.e.tq.down", { n })}
                            onClick={() => moveRow(i, 1)}
                          >
                            <DownGlyph />
                          </button>
                          <button type="button" className="htq__ib" disabled={busy} aria-label={t("h.e.tq.rm", { n })} title={t("h.e.tq.rm", { n })} onClick={() => removeRow(i)}>
                            <TrashGlyph />
                          </button>
                        </div>
                      </li>
                    );
                  })}
                </ol>
              )}
            </div>

            <button type="button" id={`${uid}-add`} className="htq__add" disabled={busy || full} data-autofocus={count === 0 ? "" : undefined} onClick={addRow}>
              <PlusGlyph />
              {full ? (
                <span>{t("h.e.tq.add.max")}</span>
              ) : (
                <span>
                  {t("h.e.tq.add")} <span className="htq__addn">{`(${count}/${MAX_QUESTIONS})`}</span>
                </span>
              )}
            </button>
            <p className="htq__bodynote">{t("h.e.tq.foot")}</p>
            <p className="sr-only" role="status" aria-live="polite">{announce}</p>
          </div>

          <div className="htq__foot">
            <p className="htq__footnote">{t("h.e.tq.foot")}</p>
            <div className="htq__btns">
              <button type="button" className="btn btn--ghost" disabled={busy} onClick={requestClose}>{t("h.e.tp.cancel")}</button>
              <button type="button" className="btn btn--primary" disabled={busy} onClick={() => void save()}>
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
