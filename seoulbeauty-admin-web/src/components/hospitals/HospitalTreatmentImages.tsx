"use client";

import { useEffect, useId, useLayoutEffect, useRef, useState, type DragEvent, type ReactNode } from "react";
import { createPortal } from "react-dom";
import Icon from "../Icon";
import ConfirmDialog, { type ConfirmSpec } from "./ConfirmDialog";
import {
  addTreatmentImage,
  deleteTreatmentImage,
  moveTreatmentImage,
  reviewTreatmentImage,
  treatmentImages,
  type HospitalTreatment,
  type TreatmentImage,
  type TreatmentImageFailure,
  type TreatmentImageSet,
} from "@/lib/api";
import {
  IMAGE_ACCEPT,
  MAX_CAPTION,
  MAX_REASON,
  MOVE_HIGHLIGHT_MS,
  checkImageFile,
  checkNewImage,
  checkReason,
  countImages,
  imageFailure,
  patientRole,
  sizeLabel,
  splitImages,
  textLength,
  type AddProblem,
  type ImageRow,
} from "@/lib/hospitalTreatmentImages";
import { mediaUrl } from "@/lib/hospitals";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";

type Props = {
  hospitalId: number;
  /** The treatment whose detail images are shown. */
  item: HospitalTreatment;
  /** The account may add, reorder and delete images (approving / rejecting is decided by the server's `canReview`). */
  canManage: boolean;
  /** The images were read or changed: how many there are now and how many wait for review. */
  onChanged: (counts: { imageCount: number; pendingImageCount: number }) => void;
  /** The window is closed. */
  onClose: () => void;
  /** The server says the treatment is gone: the treatment list should be read again. */
  onStale: () => void;
  onUnauthorized: () => void;
};

type Notice = { tone: "ok" | "err"; text: string };
type RowMode = { id: number; kind: "delete" | "reject" };
type FocusWant = { kind: "up" | "down" | "row" | "rm" | "rej" | "pick"; id: number };

const glyph = { viewBox: "0 0 24 24", "aria-hidden": true, focusable: "false" } as const;
const stroke = { fill: "none", stroke: "currentColor", strokeLinecap: "round", strokeLinejoin: "round" } as const;

function Glyph({ size, width = 2, children }: { size: number; width?: number; children: ReactNode }) {
  return (
    <svg className="icon" width={size} height={size} {...glyph} {...stroke} strokeWidth={width} style={{ flex: "none" }}>
      {children}
    </svg>
  );
}
const AlertGlyph = ({ size = 16 }: { size?: number }) => (
  <Glyph size={size}>
    <circle cx="12" cy="12" r="9" />
    <path d="M12 7v6M12 16.5v.01" />
  </Glyph>
);
const ImageGlyph = ({ size = 18, width = 2 }: { size?: number; width?: number }) => (
  <Glyph size={size} width={width}>
    <rect x="3" y="4" width="18" height="16" rx="2" />
    <circle cx="9" cy="10" r="1.6" />
    <path d="M21 16l-5-5-9 9" />
  </Glyph>
);
const UpGlyph = () => (
  <Glyph size={18}>
    <path d="M6 15l6-6 6 6" />
  </Glyph>
);
const DownGlyph = () => (
  <Glyph size={18}>
    <path d="M6 9l6 6 6-6" />
  </Glyph>
);
const TrashGlyph = ({ size = 18 }: { size?: number }) => (
  <Glyph size={size}>
    <path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" />
  </Glyph>
);
const CheckGlyph = ({ size = 14 }: { size?: number }) => (
  <Glyph size={size} width={2.2}>
    <path d="M5 12l5 5L20 7" />
  </Glyph>
);
const CrossGlyph = ({ size = 14 }: { size?: number }) => (
  <Glyph size={size} width={2.2}>
    <path d="M6 6l12 12M18 6L6 18" />
  </Glyph>
);
const ClockGlyph = () => (
  <Glyph size={14} width={2.2}>
    <circle cx="12" cy="12" r="9" />
    <path d="M12 7v5l3 2" />
  </Glyph>
);
const UploadGlyph = () => (
  <Glyph size={24} width={1.8}>
    <path d="M12 16V4M7 9l5-5 5 5M4 20h16" />
  </Glyph>
);
const PlusGlyph = () => (
  <Glyph size={16} width={2.2}>
    <path d="M12 5v14M5 12h14" />
  </Glyph>
);
const ChevronGlyph = () => (
  <Glyph size={18}>
    <path d="M6 9l6 6 6-6" />
  </Glyph>
);

const PHONE_QUERY = "(max-width: 639px)";
const REDUCED_QUERY = "(prefers-reduced-motion: reduce)";
/** Closing animation lengths (ms) of the window, plus the margin of the fallback timer. */
const DIALOG_CLOSE_MS = { desktop: 140, phone: 220, reduced: 80, margin: 60 } as const;

const cx = (...names: (string | false | null | undefined)[]): string => names.filter(Boolean).join(" ");

/**
 * The "treatment detail page images" window of one treatment: native <dialog> (a bottom sheet on narrow screens). The
 * images are split into what patients see (approved, in the patients' order, with arrows) and what they do not (waiting,
 * rejected), with a preview of the patient page and a card to add a new image (a file or an address). Every action is
 * its own request; the window is drawn outside the hospital form, so nothing in it touches that form.
 */
export default function HospitalTreatmentImages({ hospitalId, item, canManage, onChanged, onClose, onStale, onUnauthorized }: Props) {
  const t = useT();
  const uid = useId();
  const dialogRef = useRef<HTMLDialogElement>(null);
  const fileRef = useRef<HTMLInputElement>(null);
  const [load, setLoad] = useState<{ set: TreatmentImageSet } | { failed: true } | null>(null);
  const [tries, setTries] = useState(0);
  const [busy, setBusy] = useState(false);
  // The running request is the add of a new image (its button says so).
  const [sending, setSending] = useState(false);
  const [notice, setNotice] = useState<Notice | null>(null);
  const [announce, setAnnounce] = useState("");
  const [rowMode, setRowMode] = useState<RowMode | null>(null);
  const [reason, setReason] = useState("");
  const [reasonError, setReasonError] = useState<MessageKey | null>(null);
  const [file, setFile] = useState<File | null>(null);
  const [filePreview, setFilePreview] = useState("");
  const [url, setUrl] = useState("");
  const [caption, setCaption] = useState("");
  const [addProblem, setAddProblem] = useState<AddProblem | null>(null);
  const [dragOver, setDragOver] = useState(false);
  // The preview is open at first on wide screens and closed on a phone (the list comes first there).
  const [previewOpen, setPreviewOpen] = useState(() => typeof window === "undefined" || !window.matchMedia(PHONE_QUERY).matches);
  const [unfolded, setUnfolded] = useState(false);
  const [failedSrc, setFailedSrc] = useState<ReadonlySet<string>>(() => new Set());
  const [flashId, setFlashId] = useState<number | null>(null);
  const [discardAsk, setDiscardAsk] = useState(false);
  const [closing, setClosing] = useState(false);
  const closingRef = useRef(false);
  const returnTo = useRef<HTMLElement | null>(null);
  const pendingFocus = useRef<FocusWant | null>(null);
  const upload = useRef<AbortController | null>(null);
  const flashTimer = useRef(0);
  const previewUrl = useRef("");
  const onCloseRef = useRef(onClose);
  const onChangedRef = useRef(onChanged);
  const onStaleRef = useRef(onStale);
  const onUnauthorizedRef = useRef(onUnauthorized);
  useEffect(() => {
    onCloseRef.current = onClose;
    onChangedRef.current = onChanged;
    onStaleRef.current = onStale;
    onUnauthorizedRef.current = onUnauthorized;
  });

  const idOf = (part: string, id?: number): string => (id === undefined ? `${uid}-${part}` : `${uid}-${part}-${id}`);
  const byId = (id: string): HTMLElement | null => document.getElementById(id);

  const set = load && "set" in load ? load.set : null;
  const items = set?.items ?? null;
  const counts = countImages(items ?? []);
  const { shown, hidden } = splitImages(items ?? []);
  const canReview = set?.canReview === true;

  // Opens as a modal while the window animates in; the object URL of a chosen file and the running request are released when it goes.
  useEffect(() => {
    const dialog = dialogRef.current;
    if (!dialog) return;
    returnTo.current = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    if (!dialog.open) dialog.showModal();
    return () => {
      if (dialog.open) dialog.close();
      if (!closingRef.current && returnTo.current?.isConnected) returnTo.current.focus();
      if (previewUrl.current) URL.revokeObjectURL(previewUrl.current);
      upload.current?.abort();
      window.clearTimeout(flashTimer.current);
    };
  }, []);

  useEffect(() => {
    const controller = new AbortController();
    void treatmentImages(hospitalId, item.id, controller.signal).then((res) => {
      if (controller.signal.aborted) return;
      if (res.ok) setLoad({ set: res.data });
      else if (res.status === 401) onUnauthorizedRef.current();
      else {
        if (res.status === 404) onStaleRef.current();
        setLoad({ failed: true });
      }
    });
    return () => controller.abort();
  }, [hospitalId, item.id, tries]);

  // The treatment row keeps its image chip in step with the list.
  const total = counts.total;
  const pendingTotal = counts.pending;
  useEffect(() => {
    if (items !== null) onChangedRef.current({ imageCount: total, pendingImageCount: pendingTotal });
  }, [items, total, pendingTotal]);

  // The focus of a moved, approved, rejected or added row (or of a button that was pressed) is put back once the rows have their places.
  useLayoutEffect(() => {
    const want = pendingFocus.current;
    if (!want) return;
    pendingFocus.current = null;
    if (want.kind === "pick") {
      byId(idOf("pick"))?.focus();
      return;
    }
    if (want.kind === "row") {
      const row = byId(idOf("row", want.id));
      if (row) {
        row.focus({ preventScroll: true });
        row.scrollIntoView({ block: "nearest" });
      } else byId(idOf("pick"))?.focus();
      return;
    }
    if (want.kind === "up" || want.kind === "down") {
      const primary = byId(idOf(want.kind, want.id));
      const other = byId(idOf(want.kind === "up" ? "down" : "up", want.id));
      const target = primary instanceof HTMLButtonElement && !primary.disabled ? primary : other;
      if (target instanceof HTMLButtonElement && !target.disabled) target.focus();
      else byId(idOf("row", want.id))?.focus({ preventScroll: true });
      return;
    }
    byId(idOf(want.kind, want.id))?.focus();
  });

  // The window of an opened delete confirmation / reject form takes the focus: "cancel" for a delete, the reason box for a reject.
  useEffect(() => {
    if (!rowMode) return;
    const row = byId(idOf("row", rowMode.id));
    row?.querySelector<HTMLElement>(rowMode.kind === "delete" ? "[data-first='cancel']" : "textarea")?.focus();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [rowMode]);

  /** Every way out (X, Esc, backdrop, close, discard) ends here: the closing animation runs, then the window goes. */
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

  const dirty = file !== null || url.trim() !== "" || caption.trim() !== "" || (rowMode?.kind === "reject" && reason.trim() !== "");
  const requestClose = () => {
    if (busy || closingRef.current) return;
    if (dirty) setDiscardAsk(true);
    else startClose();
  };

  const retry = () => {
    setLoad(null);
    setTries((n) => n + 1);
  };

  /** Reads the list again without the loading state (after an answer that says the list on screen is out of date). */
  const reloadQuietly = async () => {
    const res = await treatmentImages(hospitalId, item.id);
    if (res.ok) setLoad({ set: res.data });
  };

  const applyItems = (next: TreatmentImage[]) => setLoad((prev) => (prev && "set" in prev ? { set: { ...prev.set, items: next } } : prev));

  const flash = (id: number) => {
    window.clearTimeout(flashTimer.current);
    setFlashId(id);
    flashTimer.current = window.setTimeout(() => setFlashId(null), MOVE_HIGHLIGHT_MS);
  };

  /** The line on top (it stays in view while the body scrolls); a success line is also read out. */
  const say = (next: Notice | null) => {
    setNotice(next);
    if (next?.tone === "ok") setAnnounce(next.text);
  };

  /** A refused request: its line on top (the add card has its own field lines), and the list read again when it is probably out of date. */
  const refused = (res: TreatmentImageFailure): { field: AddProblem["field"] | null; key: MessageKey } | null => {
    const f = imageFailure(res);
    if (f.unauthorized) {
      onUnauthorizedRef.current();
      return null;
    }
    if (f.refresh) {
      void reloadQuietly();
      if (res.status === 404) onStaleRef.current();
    }
    return { field: f.field, key: f.key };
  };
  const refusedLine = (res: TreatmentImageFailure) => {
    const r = refused(res);
    if (r) say({ tone: "err", text: t(r.key) });
  };

  const move = async (row: ImageRow, direction: "up" | "down") => {
    if (busy || closingRef.current) return;
    setBusy(true);
    say(null);
    const res = await moveTreatmentImage(hospitalId, item.id, row.image.id, direction);
    setBusy(false);
    if (!res.ok) {
      refusedLine(res);
      return;
    }
    applyItems(res.data.items);
    const place = splitImages(res.data.items).shown.findIndex((r) => r.image.id === row.image.id) + 1;
    pendingFocus.current = { kind: direction, id: row.image.id };
    flash(row.image.id);
    setAnnounce(t("h.e.ti.moved", { n: place }));
  };

  const remove = async (row: ImageRow, siblings: readonly ImageRow[]) => {
    if (busy || closingRef.current) return;
    const at = siblings.findIndex((r) => r.image.id === row.image.id);
    const near = siblings[at + 1] ?? siblings[at - 1];
    setBusy(true);
    say(null);
    const res = await deleteTreatmentImage(hospitalId, item.id, row.image.id);
    setBusy(false);
    setRowMode(null);
    if (!res.ok) {
      refusedLine(res);
      return;
    }
    applyItems(res.data.items);
    pendingFocus.current = near ? { kind: "row", id: near.image.id } : { kind: "pick", id: 0 };
    say({ tone: "ok", text: t("h.e.ti.ok.deleted") });
  };

  const approve = async (row: ImageRow) => {
    if (busy || closingRef.current) return;
    setBusy(true);
    say(null);
    const res = await reviewTreatmentImage(hospitalId, item.id, row.image.id, { decision: "approve" });
    setBusy(false);
    if (!res.ok) {
      refusedLine(res);
      return;
    }
    applyItems(res.data.items);
    pendingFocus.current = { kind: "row", id: row.image.id };
    say({ tone: "ok", text: t("h.e.ti.ok.approved") });
  };

  const openReject = (row: ImageRow) => {
    if (busy) return;
    setReason("");
    setReasonError(null);
    setRowMode({ id: row.image.id, kind: "reject" });
  };

  const confirmReject = async (row: ImageRow) => {
    if (busy || closingRef.current) return;
    const problem = checkReason(reason);
    if (problem) {
      setReasonError(problem);
      byId(idOf("reason", row.image.id))?.focus();
      return;
    }
    setReasonError(null);
    setBusy(true);
    say(null);
    const res = await reviewTreatmentImage(hospitalId, item.id, row.image.id, { decision: "reject", reason: reason.trim() });
    setBusy(false);
    if (!res.ok) {
      const r = refused(res);
      if (r) {
        if (r.key === "h.e.ti.e.reason_required" || r.key === "h.e.ti.e.reason_too_long") {
          setReasonError(r.key);
          byId(idOf("reason", row.image.id))?.focus();
        } else {
          setRowMode(null);
          say({ tone: "err", text: t(r.key) });
        }
      }
      return;
    }
    applyItems(res.data.items);
    setRowMode(null);
    setReason("");
    pendingFocus.current = { kind: "row", id: row.image.id };
    say({ tone: "ok", text: t("h.e.ti.ok.rejected") });
  };

  const closeRowMode = (row: ImageRow, kind: "delete" | "reject") => {
    if (busy) return;
    setRowMode(null);
    setReasonError(null);
    pendingFocus.current = { kind: kind === "delete" ? "rm" : "rej", id: row.image.id };
  };

  // ───────── The add card ─────────

  const dropPreview = () => {
    if (previewUrl.current) URL.revokeObjectURL(previewUrl.current);
    previewUrl.current = "";
    setFilePreview("");
  };
  const pick = (next: File | null) => {
    if (!next) return;
    setAddProblem(null);
    const problem = checkImageFile(next);
    if (problem) {
      dropPreview();
      setFile(null);
      setAddProblem({ field: "file", key: problem === "too_large" ? "h.e.ti.e.too_large" : "h.e.ti.e.invalid_image" });
      return;
    }
    dropPreview();
    previewUrl.current = URL.createObjectURL(next);
    setFilePreview(previewUrl.current);
    setFile(next);
  };
  const unpick = () => {
    dropPreview();
    setFile(null);
    setAddProblem(null);
    if (fileRef.current) fileRef.current.value = "";
    requestAnimationFrame(() => byId(idOf("pick"))?.focus());
  };
  const showAddProblem = (problem: AddProblem) => {
    setAddProblem(problem);
    requestAnimationFrame(() => byId(idOf(problem.field === "file" ? "pick" : problem.field === "url" ? "url" : "cap"))?.focus());
  };
  const onDragOver = (e: DragEvent<HTMLDivElement>) => {
    if (busy) return;
    e.preventDefault();
    setDragOver(true);
  };
  const onDrop = (e: DragEvent<HTMLDivElement>) => {
    e.preventDefault();
    setDragOver(false);
    if (busy) return;
    pick(e.dataTransfer.files[0] ?? null);
  };

  const submit = async () => {
    if (busy || closingRef.current) return;
    say(null);
    setAddProblem(null);
    const problem = checkNewImage(file, url, caption);
    if (problem) {
      showAddProblem(problem);
      return;
    }
    const controller = new AbortController();
    upload.current = controller;
    setBusy(true);
    setSending(true);
    const res = await addTreatmentImage(hospitalId, item.id, { file, imageUrl: url.trim(), caption: caption.trim() }, controller.signal);
    setBusy(false);
    setSending(false);
    if (controller.signal.aborted) return;
    if (!res.ok) {
      const r = refused(res);
      if (!r) return;
      if (r.field) showAddProblem({ field: r.field, key: r.key });
      else say({ tone: "err", text: t(r.key) });
      return;
    }
    applyItems(res.data.items);
    dropPreview();
    setFile(null);
    setUrl("");
    setCaption("");
    if (fileRef.current) fileRef.current.value = "";
    const added = res.data.item;
    if (added) {
      pendingFocus.current = { kind: "row", id: added.id };
      flash(added.id);
    }
    say({ tone: "ok", text: t(added?.status === "pending" ? "h.e.ti.ok.addedPending" : "h.e.ti.ok.added") });
  };

  // ───────── Pieces ─────────

  const srcOf = (stored: string): string => {
    const m = mediaUrl(stored);
    return m !== "" && !failedSrc.has(m) ? m : "";
  };
  const markFailed = (m: string) => setFailedSrc((prev) => (prev.has(m) ? prev : new Set(prev).add(m)));

  const thumb = (image: TreatmentImage, no: number, cut: string | null): ReactNode => {
    const src = srcOf(image.url);
    return (
      <div className={cx("htim__th", src === "" && "htim__th--ph")} role={src === "" ? "img" : undefined} aria-label={src === "" ? t("h.e.ti.th.alt", { n: no }) : undefined}>
        {src !== "" ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img className="htim__thimg" src={src} alt={image.caption} loading="lazy" decoding="async" referrerPolicy="no-referrer" onError={() => markFailed(src)} />
        ) : (
          <ImageGlyph size={28} width={1.6} />
        )}
        <span className="htim__thn">{no}</span>
        {cut ? <span className="htim__cut">{cut}</span> : null}
      </div>
    );
  };

  const statusChip = (status: TreatmentImage["status"]): ReactNode => (
    <span className={cx("htim__st", status === "approved" ? "htim__st--a" : status === "pending" ? "htim__st--p" : "htim__st--r")}>
      {status === "approved" ? <CheckGlyph /> : status === "pending" ? <ClockGlyph /> : <CrossGlyph />}
      {t(status === "approved" ? "h.e.ti.st.approved" : status === "pending" ? "h.e.ti.st.pending" : "h.e.ti.st.rejected")}
    </span>
  );

  const reviewBlock = (row: ImageRow): ReactNode => {
    const { image } = row;
    const mode = rowMode && rowMode.id === image.id ? rowMode.kind : null;
    if (mode === "delete") {
      return (
        <div className="htim__cfm" role="alertdialog" aria-label={t("h.e.ti.cfm.aria")}>
          <span className="htim__cfmt">{t("h.e.ti.cfm.t")}</span>
          <div className="htim__cfmb">
            <button type="button" className="btn btn--ghost" data-first="cancel" disabled={busy} onClick={() => closeRowMode(row, "delete")}>{t("h.e.ti.rev.cancel")}</button>
            <button type="button" className="btn htim__btn--d" disabled={busy} onClick={() => void remove(row, row.patientPlace === null ? hidden : shown)}>
              <TrashGlyph size={16} />
              {t("h.e.ti.cfm.yes")}
            </button>
          </div>
        </div>
      );
    }
    if (mode === "reject") {
      const rid = idOf("reason", image.id);
      const errId = idOf("reasonerr", image.id);
      const n = textLength(reason.trim());
      return (
        <>
          <div className="htim__fld">
            <label className="htim__lbl" htmlFor={rid}>
              {t("h.e.ti.rev.reason")} <span className="htim__req" aria-hidden="true">*</span>
            </label>
            <textarea
              id={rid}
              className="htim__ta"
              value={reason}
              placeholder={t("h.e.ti.rev.reason.ph")}
              disabled={busy}
              aria-invalid={reasonError ? true : undefined}
              aria-describedby={reasonError ? errId : undefined}
              onChange={(e) => {
                setReason(e.target.value);
                setReasonError(null);
              }}
            />
            <div className="htim__fldfoot">
              {reasonError ? (
                <p id={errId} className="htim__ferr" role="alert">
                  <AlertGlyph />
                  <span>{t(reasonError)}</span>
                </p>
              ) : (
                <span />
              )}
              <span className="htim__cnt" aria-label={t("h.e.ti.up.cap.cnt", { n })}>{`${n}/${MAX_REASON}`}</span>
            </div>
          </div>
          <div className="htim__rev">
            <button type="button" className="btn btn--ghost" disabled={busy} onClick={() => closeRowMode(row, "reject")}>{t("h.e.ti.rev.cancel")}</button>
            <button type="button" className="btn htim__btn--d" disabled={busy} onClick={() => void confirmReject(row)}>
              <CrossGlyph size={16} />
              {t("h.e.ti.rev.confirm")}
            </button>
          </div>
        </>
      );
    }
    if (image.status === "pending" && canReview) {
      return (
        <div className="htim__rev">
          <button type="button" className="btn btn--primary" disabled={busy} onClick={() => void approve(row)}>
            <CheckGlyph size={16} />
            {t("h.e.ti.rev.approve")}
          </button>
          <button type="button" id={idOf("rej", image.id)} className="btn htim__btn--gd" disabled={busy} onClick={() => openReject(row)}>
            <CrossGlyph size={16} />
            {t("h.e.ti.rev.reject")}
          </button>
        </div>
      );
    }
    if (image.status === "pending") return <p className="htim__hint">{t("h.e.ti.row.pending")}</p>;
    if (image.status === "rejected" && image.rejectReason.trim() !== "") {
      return (
        <div className="htim__why">
          <AlertGlyph />
          <span>
            <b>{t("h.e.ti.why")}</b> · {image.rejectReason}
          </span>
        </div>
      );
    }
    return null;
  };

  const renderRow = (row: ImageRow, index: number, siblings: readonly ImageRow[]): ReactNode => {
    const { image, no, patientPlace } = row;
    const approvedCount = shown.length;
    const role = patientPlace === null ? null : patientRole(patientPlace, approvedCount);
    const cut = role === "first" ? t("h.e.ti.cut.top") : role === "after" ? t("h.e.ti.cut.after") : role === "only" ? t("h.e.ti.cut.full") : null;
    const pc =
      patientPlace === null
        ? t("h.e.ti.pc.off")
        : role === "first"
          ? t("h.e.ti.pc.first", { n: patientPlace })
          : role === "after"
            ? t("h.e.ti.pc.after", { n: patientPlace })
            : t("h.e.ti.pc.only", { n: patientPlace });
    const extra = reviewBlock(row);
    return (
      <li key={image.id} id={idOf("row", image.id)} tabIndex={-1} className={cx("htim__row", flashId === image.id && "htim__row--flash")} aria-label={t("h.e.ti.row.aria", { n: no })}>
        {thumb(image, no, cut)}
        <div className="htim__txt">
          <div className={cx("htim__cap", image.caption.trim() === "" && "htim__cap--none")} title={image.caption.trim() === "" ? undefined : image.caption}>
            {image.caption.trim() === "" ? t("h.e.ti.cap.none") : image.caption}
          </div>
          <div className="htim__meta">
            {statusChip(image.status)}
            <span className="htim__by">{t(image.uploadedBy === "hospital" ? "h.e.ti.by.hospital" : "h.e.ti.by.admin")}</span>
          </div>
          <span className={cx("htim__pc", patientPlace === null && "htim__pc--off")}>{pc}</span>
        </div>
        {canManage ? (
          <div className="htim__acts">
            {patientPlace !== null ? (
              <>
                <button type="button" id={idOf("up", image.id)} className="htim__ib" disabled={busy || index === 0} aria-label={t("h.e.ti.up", { n: no })} title={t("h.e.ti.up", { n: no })} onClick={() => void move(row, "up")}>
                  <UpGlyph />
                </button>
                <button type="button" id={idOf("down", image.id)} className="htim__ib" disabled={busy || index === siblings.length - 1} aria-label={t("h.e.ti.down", { n: no })} title={t("h.e.ti.down", { n: no })} onClick={() => void move(row, "down")}>
                  <DownGlyph />
                </button>
              </>
            ) : null}
            <button type="button" id={idOf("rm", image.id)} className="htim__ib htim__ib--del" disabled={busy} aria-label={t("h.e.ti.rm", { n: no })} title={t("h.e.ti.rm", { n: no })} onClick={() => !busy && setRowMode({ id: image.id, kind: "delete" })}>
              <TrashGlyph />
            </button>
          </div>
        ) : null}
        {extra ? <div className="htim__span">{extra}</div> : null}
      </li>
    );
  };

  // ───────── Patient screen preview ─────────

  const defaultSrc = set?.defaultImageUrl ? srcOf(set.defaultImageUrl) : "";
  const hasDefault = Boolean(set?.defaultImageUrl);

  const previewImage = (image: TreatmentImage, place: number, cropped: boolean): ReactNode => {
    const src = srcOf(image.url);
    return (
      <div key={image.id} className={cx("htim__pimg", src === "" && "htim__pimg--ph")}>
        {src !== "" ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img className={cx("htim__pimgel", cropped && "htim__pimgel--cut")} src={src} alt={image.caption} loading="lazy" decoding="async" referrerPolicy="no-referrer" onError={() => markFailed(src)} />
        ) : (
          <ImageGlyph size={28} width={1.6} />
        )}
        <span className="htim__thn">{place}</span>
      </div>
    );
  };

  const previewFrame = (): ReactNode => {
    const n = shown.length;
    const name = item.name;
    let inner: ReactNode;
    if (n === 0 && !hasDefault) {
      inner = (
        <div className="htim__pph">
          <ImageGlyph size={28} width={1.6} />
          <b>{t("h.e.ti.ph.t", { name })}</b>
          <span>{t("h.e.ti.ph.d")}</span>
        </div>
      );
    } else if (n === 0) {
      inner = (
        <div className="htim__pdet">
          <div className={cx("htim__pimg", defaultSrc === "" && "htim__pimg--ph")}>
            {defaultSrc !== "" ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img className="htim__pimgel" src={defaultSrc} alt={t("h.e.ti.def.alt")} loading="lazy" decoding="async" referrerPolicy="no-referrer" onError={() => markFailed(defaultSrc)} />
            ) : (
              <ImageGlyph size={28} width={1.6} />
            )}
            <span className="htim__thn">{t("h.e.ti.prev.defaultNo")}</span>
            <span className="htim__dtag">{t("h.e.ti.prev.default")}</span>
          </div>
        </div>
      );
    } else {
      const folded = n >= 2 && !unfolded;
      inner = (
        <>
          <div className="htim__pdet">{folded ? previewImage(shown[0].image, 1, true) : shown.map((r, i) => previewImage(r.image, i + 1, false))}</div>
          {folded ? (
            <button type="button" className="htim__pmore" tabIndex={-1} aria-hidden="true">
              {t("h.e.ti.prev.more")}
            </button>
          ) : null}
        </>
      );
    }
    return (
      <div className="htim__pframe">
        <h3>{t("h.e.ti.prev.h")}</h3>
        {inner}
      </div>
    );
  };

  const previewPanelId = idOf("prev");
  const preview = (): ReactNode => (
    <div className="htim__previewbox">
      <button type="button" className="htim__acc" aria-expanded={previewOpen} aria-controls={previewPanelId} onClick={() => setPreviewOpen((v) => !v)}>
        <ImageGlyph size={18} />
        <span>{t("h.e.ti.prev.toggle")}</span>
        <span className={cx("htim__chev", previewOpen && "htim__chev--open")}>
          <ChevronGlyph />
        </span>
      </button>
      {previewOpen ? (
        <div id={previewPanelId} className="htim__prev">
          {shown.length >= 2 ? (
            <div className="htim__segrow">
              <div className="htim__seg" role="group" aria-label={t("h.e.ti.seg.label")}>
                <button type="button" aria-pressed={!unfolded} onClick={() => setUnfolded(false)}>{t("h.e.ti.seg.fold")}</button>
                <button type="button" aria-pressed={unfolded} onClick={() => setUnfolded(true)}>{t("h.e.ti.seg.unfold")}</button>
              </div>
            </div>
          ) : null}
          {previewFrame()}
          <p className="htim__hint htim__prevhint">{t("h.e.ti.prev.hint")}</p>
        </div>
      ) : null}
    </div>
  );

  // ───────── Add card ─────────

  const fileProblem = addProblem?.field === "file" ? addProblem : null;
  const urlProblem = addProblem?.field === "url" ? addProblem : null;
  const capProblem = addProblem?.field === "caption" ? addProblem : null;
  const capLength = textLength(caption.trim());

  const addCard = (): ReactNode => (
    <section className="htim__up" aria-label={t("h.e.ti.up.aria")}>
      <div className="htim__col">
        <h3>{t("h.e.ti.up.title")}</h3>
        <div className={cx("htim__drop", fileProblem && "htim__drop--err", dragOver && "htim__drop--over")} onDragOver={onDragOver} onDragLeave={() => setDragOver(false)} onDrop={onDrop}>
          <UploadGlyph />
          <button type="button" id={idOf("pick")} className="btn btn--ghost" disabled={busy} aria-describedby={[idOf("pickhint"), fileProblem ? idOf("pickerr") : ""].filter(Boolean).join(" ")} onClick={() => fileRef.current?.click()}>
            {t("h.e.ti.up.pick")}
          </button>
          <p id={idOf("pickhint")} className="htim__hint">{t("h.e.ti.up.hint")}</p>
          <input
            ref={fileRef}
            type="file"
            hidden
            accept={IMAGE_ACCEPT}
            onChange={(e) => {
              pick(e.target.files?.[0] ?? null);
              e.target.value = "";
            }}
          />
        </div>
        {fileProblem ? (
          <p id={idOf("pickerr")} className="htim__ferr" role="alert">
            <AlertGlyph />
            <span>{t(fileProblem.key)}</span>
          </p>
        ) : null}
        <div className="htim__fld">
          <label className="htim__lbl" htmlFor={idOf("url")}>{t("h.e.ti.up.url")}</label>
          <input
            id={idOf("url")}
            className="input"
            type="url"
            inputMode="url"
            autoComplete="off"
            placeholder={t("h.e.ti.up.url.ph")}
            value={url}
            disabled={busy || file !== null}
            aria-invalid={urlProblem ? true : undefined}
            aria-describedby={urlProblem ? idOf("urlerr") : file !== null ? idOf("urloff") : undefined}
            onChange={(e) => {
              setUrl(e.target.value);
              if (urlProblem || fileProblem) setAddProblem(null);
            }}
          />
          {urlProblem ? (
            <p id={idOf("urlerr")} className="htim__ferr" role="alert">
              <AlertGlyph />
              <span>{t(urlProblem.key)}</span>
            </p>
          ) : null}
          {file !== null ? <p id={idOf("urloff")} className="htim__hint">{t("h.e.ti.up.url.off")}</p> : null}
        </div>
      </div>
      <div className="htim__col">
        <h3 className="htim__colspace" aria-hidden="true">·</h3>
        {file !== null ? (
          <div className="htim__pv">
            <div className="htim__pvth">
              {filePreview !== "" ? (
                // eslint-disable-next-line @next/next/no-img-element
                <img src={filePreview} alt="" />
              ) : (
                <span role="img" aria-label={t("h.e.ti.up.pv.alt", { name: file.name })}>
                  <ImageGlyph size={22} width={1.6} />
                </span>
              )}
            </div>
            <div className="htim__pvt">
              <span className="htim__pvn" title={file.name}>{file.name}</span>
              <span className="htim__hint">{t("h.e.ti.up.chosen", { size: sizeLabel(file.size) })}</span>
            </div>
            <button type="button" className="htim__ib" disabled={busy} aria-label={t("h.e.ti.up.unpick")} title={t("h.e.ti.up.unpick")} onClick={unpick}>
              <CrossGlyph size={18} />
            </button>
          </div>
        ) : null}
        <div className="htim__fld">
          <label className="htim__lbl" htmlFor={idOf("cap")}>
            {t("h.e.ti.up.cap")} <span className="htim__opt">{t("h.e.ti.up.cap.opt")}</span>
          </label>
          <input
            id={idOf("cap")}
            className="input"
            type="text"
            autoComplete="off"
            placeholder={t("h.e.ti.up.cap.ph")}
            value={caption}
            disabled={busy}
            aria-invalid={capProblem ? true : undefined}
            aria-describedby={capProblem ? idOf("caperr") : undefined}
            onChange={(e) => {
              setCaption(e.target.value);
              if (capProblem) setAddProblem(null);
            }}
          />
          <div className="htim__fldfoot">
            {capProblem ? (
              <p id={idOf("caperr")} className="htim__ferr" role="alert">
                <AlertGlyph />
                <span>{t(capProblem.key, capProblem.vars)}</span>
              </p>
            ) : (
              <span />
            )}
            <span className={cx("htim__cnt", capLength > MAX_CAPTION && "htim__cnt--bad")} aria-label={t("h.e.ti.up.cap.cnt", { n: capLength })}>{`${capLength}/${MAX_CAPTION}`}</span>
          </div>
        </div>
        {!canReview ? <p className="htim__hint">{t("h.e.ti.up.pending")}</p> : null}
        <div className="htim__submit">
          <button type="button" className="btn btn--primary" disabled={busy} onClick={() => void submit()}>
            <PlusGlyph />
            {sending ? t("h.e.ti.up.sending") : t("h.e.ti.up.submit")}
          </button>
        </div>
      </div>
    </section>
  );

  // ───────── Body ─────────

  const body = (): ReactNode => {
    if (load === null) {
      return (
        <>
          <div role="status" aria-live="polite" className="htim__hint htim__loading">{t("h.e.ti.loading")}</div>
          {[0, 1].map((i) => (
            <div key={i} className="htim__row htim__row--sk" aria-hidden="true">
              <span className="htim__sk htim__sk--th" />
              <div className="htim__skt">
                <span className="htim__sk htim__sk--l1" />
                <span className="htim__sk htim__sk--l2" />
              </div>
              <span className="htim__sk htim__sk--btn" />
            </div>
          ))}
        </>
      );
    }
    if (!set) {
      return (
        <div role="alert" className="htim__errbox">
          <AlertGlyph size={18} />
          <span>{t("h.e.ti.loadfail")}</span>
          <button type="button" className="btn btn--ghost" onClick={retry}>
            <Icon name="refresh" size={16} />
            {t("h.e.ti.retry")}
          </button>
        </div>
      );
    }
    const { approved, pending, rejected } = counts;
    return (
      <>
        <div className="htim__sum">
          <b>{approved > 0 ? t("h.e.ti.sum.n", { n: approved }) : t("h.e.ti.sum.none")}</b>
          <span className="htim__by">{t("h.e.ti.sum.by", { p: pending, r: rejected })}</span>
        </div>
        {approved === 0 ? (
          hasDefault ? (
            <div className="htim__defbox">
              <div className={cx("htim__th", defaultSrc === "" && "htim__th--ph")}>
                {defaultSrc !== "" ? (
                  // eslint-disable-next-line @next/next/no-img-element
                  <img className="htim__thimg" src={defaultSrc} alt={t("h.e.ti.def.alt")} loading="lazy" decoding="async" referrerPolicy="no-referrer" onError={() => markFailed(defaultSrc)} />
                ) : (
                  <ImageGlyph size={28} width={1.6} />
                )}
                <span className="htim__cut">{t("h.e.ti.prev.default")}</span>
              </div>
              <div className="htim__deft">
                <b>{t("h.e.ti.def.t")}</b>
                <p>{t("h.e.ti.def.d", { name: item.name })}</p>
              </div>
            </div>
          ) : (
            <div className="htim__empty">
              <span className="htim__ring">
                <ImageGlyph size={22} />
              </span>
              <h3>{t("h.e.ti.empty.t")}</h3>
              <p className="htim__hint">{t("h.e.ti.empty.d")}</p>
            </div>
          )
        ) : null}
        {preview()}
        {shown.length > 0 ? (
          <section className="htim__sec">
            <div className="htim__sech">
              <h3>{t("h.e.ti.sec.on")}</h3>
              <span className="htim__by">{t("h.e.ti.sec.on.n", { n: shown.length })}</span>
            </div>
            {canManage ? <p className="htim__hint">{t("h.e.ti.sec.on.hint")}</p> : null}
            <ol className="htim__list">{shown.map((row, i) => renderRow(row, i, shown))}</ol>
          </section>
        ) : null}
        {hidden.length > 0 ? (
          <section className="htim__sec">
            <div className="htim__sech">
              <h3>{t("h.e.ti.sec.off")}</h3>
              <span className="htim__by">{t("h.e.ti.sec.off.n", { p: pending, r: rejected })}</span>
            </div>
            <p className="htim__hint">{t("h.e.ti.sec.off.hint")}</p>
            <ol className="htim__list">{hidden.map((row, i) => renderRow(row, i, hidden))}</ol>
          </section>
        ) : null}
        {canManage ? addCard() : <p className="htim__hint">{t("h.e.ti.readonly")}</p>}
      </>
    );
  };

  const discardSpec: ConfirmSpec | null = discardAsk
    ? { title: t("h.e.tp.discard.title"), body: t("h.e.ti.discard.body"), confirmLabel: t("h.e.tp.discard.confirm"), cancelLabel: t("h.e.tp.discard.keep"), focus: "cancel" }
    : null;

  const titleId = idOf("title");

  return createPortal(
    <>
      <dialog
        ref={dialogRef}
        className="dialog dialog--images"
        aria-labelledby={titleId}
        aria-busy={busy || closing}
        data-state={closing ? "closing" : undefined}
        onCancel={(e) => {
          // Esc: the window decides (typed but unsent input asks first; a running request cannot be cut off).
          e.preventDefault();
          requestClose();
        }}
        onClick={(e) => {
          if (e.target === e.currentTarget) requestClose();
        }}
      >
        <div className="htim">
          <span className="htim__handle" aria-hidden="true" />
          <div className="htim__head">
            <div className="htim__headt">
              <h2 id={titleId} className="dialog__title htim__title">{t("h.e.ti.title")}</h2>
              <span className="htim__chip htim__chip--head" translate="no" title={item.name}>{item.name}</span>
            </div>
            <button type="button" className="ibtn dialog__close" onClick={requestClose} disabled={busy} aria-label={t("h.e.ti.close")} title={t("m.close")}>
              <Icon name="close" size={18} />
            </button>
          </div>
          <div className="htim__chiprow">
            <span className="htim__chip" translate="no" title={item.name}>{item.name}</span>
          </div>

          <div className="htim__body">
            {notice ? (
              <div className={cx("htim__notice", notice.tone === "ok" ? "htim__notice--ok" : "htim__notice--err")} role={notice.tone === "ok" ? "status" : "alert"}>
                {notice.tone === "ok" ? <CheckGlyph size={16} /> : <AlertGlyph size={18} />}
                <span>{notice.text}</span>
              </div>
            ) : null}
            <p className="htim__note">
              <AlertGlyph />
              <span>{t("h.e.ti.note")}</span>
            </p>
            {body()}
            <p className="sr-only" role="status" aria-live="polite">{announce}</p>
          </div>

          <div className="htim__foot">
            <span className="htim__ftx">{items === null ? "" : t("h.e.ti.foot", { n: counts.total, a: counts.approved, p: counts.pending, r: counts.rejected })}</span>
            <button type="button" className="btn btn--ghost" disabled={busy} onClick={requestClose}>{t("h.e.ti.close")}</button>
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
