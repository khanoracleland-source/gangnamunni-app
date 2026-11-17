"use client";

import { useEffect, useId, useRef, useState, type ChangeEvent, type KeyboardEvent } from "react";
import ConfirmDialog from "./ConfirmDialog";
import Icon from "../Icon";
import { DocumentRow, Notice } from "./parts";
import { deleteHospitalDocument, uploadHospitalDocument, type DocumentFailure, type DocumentKey, type HospitalDocuments } from "@/lib/api";
import { DOCUMENT_ACCEPT, checkDocumentFile, documentFailureKey, formatFileSize } from "@/lib/hospitalDocuments";
import { legacyDocumentUrl } from "@/lib/hospitals";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";

const DOCS: readonly DocumentKey[] = ["business", "medical"];

type Panel = { doc: DocumentKey; file: File | null; problem: MessageKey | null };
type Status = { tone: "ok" | "err"; text: string } | null;

type Props = {
  hospitalId: number;
  documents: HospitalDocuments;
  /** The server lets this admin change the documents of this hospital. */
  editable: boolean;
  /** The server says the admin session ended. */
  onUnauthorized: () => void;
};

/**
 * The submitted documents on the edit page: each row opens a panel in place to upload, replace or delete the file.
 * Only one panel is open at a time, and nothing else can be started while a request is running.
 */
export default function HospitalDocuments({ hospitalId, documents: initial, editable: initialEditable, onUnauthorized }: Props) {
  const t = useT();
  const uid = useId();
  const [documents, setDocuments] = useState(initial);
  const [editable, setEditable] = useState(initialEditable);
  const [panel, setPanel] = useState<Panel | null>(null);
  const [uploading, setUploading] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [confirm, setConfirm] = useState<DocumentKey | null>(null);
  const [confirmError, setConfirmError] = useState<string | null>(null);
  const [status, setStatus] = useState<Status>(null);
  // The row whose button takes focus after the next render (every caller also changes state, so a render follows).
  const focusReq = useRef<DocumentKey | null>(null);
  const setFocusReq = (req: { doc: DocumentKey }) => {
    focusReq.current = req.doc;
  };
  const request = useRef<AbortController | null>(null);
  const pickRef = useRef<HTMLInputElement>(null);
  const onUnauthorizedRef = useRef(onUnauthorized);
  useEffect(() => {
    onUnauthorizedRef.current = onUnauthorized;
  });
  useEffect(() => () => request.current?.abort(), []);

  const busy = uploading || deleting;
  const ids = (doc: DocumentKey) => ({ name: `${uid}-${doc}-name`, edit: `${uid}-${doc}-edit`, del: `${uid}-${doc}-del`, panel: `${uid}-${doc}-panel` });
  const nameOf = (doc: DocumentKey): string => t(doc === "business" ? "h.docs.business" : "h.docs.medical");

  // The panel has just opened: the file chooser takes focus.
  const openedDoc = panel?.doc;
  useEffect(() => {
    if (openedDoc) pickRef.current?.focus();
  }, [openedDoc]);

  // Focus goes back to the button that opened the panel; when that button is gone, to the row's name.
  useEffect(() => {
    const doc = focusReq.current;
    if (!doc) return;
    focusReq.current = null;
    const { edit, name } = ids(doc);
    (document.getElementById(edit) ?? document.getElementById(name))?.focus();
  });

  const toggle = (doc: DocumentKey) => {
    if (busy) return;
    setStatus(null);
    if (panel?.doc === doc) {
      setPanel(null);
      setFocusReq({ doc });
      return;
    }
    setPanel({ doc, file: null, problem: null });
  };
  const closePanel = () => {
    if (busy || !panel) return;
    const { doc } = panel;
    setPanel(null);
    setFocusReq({ doc });
  };

  const onPick = (e: ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    e.target.value = "";
    if (!file || !panel) return;
    const problem = checkDocumentFile(file);
    if (problem) {
      setPanel({ ...panel, file: null, problem: problem === "too_large" ? "h.docs.err.too_large" : "h.docs.err.invalid_file" });
      return;
    }
    setPanel({ ...panel, file, problem: null });
  };

  // A failure that says this admin may not change the documents: the rows go back to view only.
  const forbidden = (failure: DocumentFailure): boolean => failure.error === "forbidden_documents" || failure.status === 403;

  const upload = async () => {
    if (busy || !panel || !panel.file) return;
    const { doc, file } = panel;
    const replacing = documents[doc];
    setStatus(null);
    const controller = new AbortController();
    request.current = controller;
    setUploading(true);
    const res = await uploadHospitalDocument(hospitalId, doc, file, controller.signal);
    if (controller.signal.aborted) return;
    request.current = null;
    setUploading(false);
    if (res.ok) {
      setDocuments(res.documents);
      setPanel(null);
      setStatus({ tone: "ok", text: t(replacing ? "h.docs.ok.replaced" : "h.docs.ok.uploaded", { name: nameOf(doc) }) });
      setFocusReq({ doc });
      return;
    }
    if (res.status === 401) {
      onUnauthorizedRef.current();
      return;
    }
    const key = documentFailureKey(res);
    if (forbidden(res)) {
      setEditable(false);
      setPanel(null);
      setStatus({ tone: "err", text: t(key) });
      setFocusReq({ doc });
      return;
    }
    // A wrong file is dropped from the panel; any other failure keeps the choice so the same button tries again.
    const dropFile = res.error === "invalid_file" || res.error === "too_large";
    setPanel({ doc, file: dropFile ? null : file, problem: key });
  };

  const remove = async () => {
    if (deleting || confirm === null) return;
    const doc = confirm;
    setConfirmError(null);
    setStatus(null);
    setDeleting(true);
    const res = await deleteHospitalDocument(hospitalId, doc);
    setDeleting(false);
    if (res.ok) {
      setDocuments(res.documents);
      setConfirm(null);
      setPanel(null);
      setStatus({ tone: "ok", text: t("h.docs.ok.deleted", { name: nameOf(doc) }) });
      setFocusReq({ doc });
      return;
    }
    if (res.status === 401) {
      onUnauthorizedRef.current();
      return;
    }
    if (forbidden(res)) {
      setEditable(false);
      setConfirm(null);
      setPanel(null);
      setStatus({ tone: "err", text: t(documentFailureKey(res)) });
      setFocusReq({ doc });
      return;
    }
    setConfirmError(t(documentFailureKey(res)));
  };

  const onPanelKey = (e: KeyboardEvent<HTMLDivElement>) => {
    if (e.key !== "Escape") return;
    e.stopPropagation();
    if (!busy) closePanel();
  };

  const renderPanel = (doc: DocumentKey) => {
    if (!panel || panel.doc !== doc) return null;
    const replacing = documents[doc];
    const label = t(replacing ? "h.docs.replace" : "h.docs.upload");
    return (
      <div className="docup" id={ids(doc).panel} role="group" aria-labelledby={ids(doc).name} onKeyDown={onPanelKey}>
        <p className="field__hint">{t("h.docs.hint")}</p>
        {replacing ? <p className="field__hint">{t("h.docs.replaceNote")}</p> : null}
        <div className="docup__pick">
          <label className="txtbtn" aria-disabled={busy ? "true" : undefined}>
            <input ref={pickRef} type="file" className="sr-only" accept={DOCUMENT_ACCEPT} onChange={onPick} disabled={busy} aria-label={t("h.docs.pick")} />
            <Icon name="sheet" size={16} />
            <span>{t("h.docs.pick")}</span>
          </label>
          {panel.file ? (
            <span className="docup__file">
              <Icon name="sheet" size={16} />
              <span className="docup__name" title={panel.file.name} translate="no">{panel.file.name}</span>
              <span className="docup__size">{formatFileSize(panel.file.size)}</span>
            </span>
          ) : (
            <span className="docup__file docup__file--none">{t("h.docs.noFile")}</span>
          )}
        </div>
        {panel.problem ? <Notice tone="warn" icon="alert">{t(panel.problem)}</Notice> : null}
        {uploading ? <p className="sr-only" role="status">{t("h.docs.uploadingStatus")}</p> : null}
        <div className="docup__acts">
          <button type="button" className="btn btn--primary" onClick={() => void upload()} disabled={busy || !panel.file} aria-busy={uploading || undefined}>
            {uploading ? <Icon name="refresh" size={18} className="is-spinning" /> : null}
            <span>{uploading ? t("h.docs.uploading") : label}</span>
          </button>
          <button type="button" className="btn btn--ghost" onClick={closePanel} disabled={busy}>{t("h.docs.cancel")}</button>
        </div>
      </div>
    );
  };

  const row = (doc: DocumentKey) => {
    const name = nameOf(doc);
    const submitted = documents[doc];
    const open = panel?.doc === doc;
    const edit = editable
      ? {
          nameId: ids(doc).name,
          active: open,
          actions: (
            <>
              <button
                type="button"
                id={ids(doc).edit}
                className={submitted ? "txtbtn" : "txtbtn doc__wide"}
                onClick={() => toggle(doc)}
                disabled={busy}
                aria-expanded={open}
                aria-controls={open ? ids(doc).panel : undefined}
                aria-label={t(submitted ? "h.docs.replaceOf" : "h.docs.uploadOf", { name })}
              >
                <Icon name="sheet" size={16} />
                <span>{t(submitted ? "h.docs.replace" : "h.docs.upload")}</span>
              </button>
              {submitted ? (
                <button
                  type="button"
                  id={ids(doc).del}
                  className="txtbtn txtbtn--danger"
                  onClick={() => {
                    setConfirmError(null);
                    setConfirm(doc);
                  }}
                  disabled={busy}
                  aria-label={t("h.docs.deleteOf", { name })}
                >
                  <Icon name="trash" size={16} />
                  <span>{t("h.docs.delete")}</span>
                </button>
              ) : null}
            </>
          ),
          panel: renderPanel(doc),
        }
      : undefined;
    return <DocumentRow key={doc} name={name} submitted={submitted} href={legacyDocumentUrl(hospitalId, doc)} edit={edit} />;
  };

  const confirmName = confirm ? nameOf(confirm) : "";
  return (
    <>
      <ul className="docs">{DOCS.map(row)}</ul>
      <div className="doc__status" role="status" aria-live="polite">
        {status ? <Notice tone={status.tone === "ok" ? "ok" : "warn"} icon={status.tone === "ok" ? "check" : "alert"}>{status.text}</Notice> : null}
      </div>
      {editable ? null : <p className="field__hint doc__hint">{t("h.docs.viewOnly")}</p>}
      <ConfirmDialog
        spec={
          confirm
            ? { title: t("h.docs.delTitle", { name: confirmName }), body: t("h.docs.delBody"), confirmLabel: t("h.docs.delete"), cancelLabel: t("h.docs.cancel"), focus: "cancel" }
            : null
        }
        busy={deleting}
        error={confirmError}
        onConfirm={() => void remove()}
        onClose={() => {
          if (deleting) return;
          setConfirm(null);
          setConfirmError(null);
        }}
      />
    </>
  );
}
