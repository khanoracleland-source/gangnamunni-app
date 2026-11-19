"use client";

import { useCallback, useSyncExternalStore, type ReactNode } from "react";
import Icon from "../Icon";
import type { FieldError } from "@/lib/hospitalApply";
import { useT } from "@/lib/locale";

/** One labelled form field with its hint, error and confirmation lines under it. */
export function Field({
  id,
  label,
  required = false,
  hint,
  error,
  ok,
  span,
  children,
}: {
  id: string;
  label: string;
  required?: boolean;
  hint?: ReactNode;
  error?: string | null;
  ok?: string | null;
  span?: boolean;
  children: ReactNode;
}) {
  return (
    <div className={span ? "field ha-span" : "field"}>
      <label className="field__label" htmlFor={id}>
        {label}
        {required ? <span className="field__req" aria-hidden="true">*</span> : null}
      </label>
      {children}
      {hint && !error ? <p className="field__hint" id={`${id}-h`}>{hint}</p> : null}
      {ok && !error ? (
        <p className="ha-ok" role="status">
          <Icon name="check" size={16} />
          <span>{ok}</span>
        </p>
      ) : null}
      {error ? (
        <p className="ha-err" id={`${id}-e`} role="alert">
          <Icon name="alert" size={16} />
          <span>{error}</span>
        </p>
      ) : null}
    </div>
  );
}

/** The text of a field error in the chosen language. */
export function useErrorText(): (error: FieldError | undefined) => string | null {
  const t = useT();
  return (error) => (error ? t(error.message.key, error.message.vars) : null);
}

// Preview addresses are shared by file and released when the last preview using the file goes away.
const previewUrls = new Map<File, { url: string; users: number }>();

/** A blob: address for a chosen image, created while it is on screen and released afterwards; null for no file or a non-image. */
export function useObjectUrl(file: File | null): string | null {
  const subscribe = useCallback(
    (notify: () => void) => {
      if (!file || !file.type.startsWith("image/")) return () => {};
      let entry = previewUrls.get(file);
      if (!entry) {
        entry = { url: URL.createObjectURL(file), users: 0 };
        previewUrls.set(file, entry);
      }
      entry.users += 1;
      notify();
      return () => {
        const current = previewUrls.get(file);
        if (!current) return;
        current.users -= 1;
        if (current.users <= 0) {
          URL.revokeObjectURL(current.url);
          previewUrls.delete(file);
        }
      };
    },
    [file],
  );
  return useSyncExternalStore(
    subscribe,
    () => (file ? previewUrls.get(file)?.url ?? null : null),
    () => null,
  );
}

/** A small preview of a chosen image (or the file name for a document) with a remove button. */
export function Thumb({
  file,
  onRemove,
  variant = "square",
}: {
  file: File;
  onRemove: () => void;
  variant?: "square" | "main" | "round";
}) {
  const t = useT();
  const url = useObjectUrl(file);
  const isImage = file.type.startsWith("image/");
  return (
    <li className={`ha-thumb ha-thumb--${variant}`}>
      {isImage && url ? (
        // eslint-disable-next-line @next/next/no-img-element -- a blob: preview of a file the admin just chose
        <img src={url} alt="" />
      ) : (
        <Icon name="sheet" size={24} />
      )}
      <span className="ha-thumb__name" title={file.name}>{file.name}</span>
      <button type="button" className="ha-thumb__x" onClick={onRemove} aria-label={t("hap.i.remove", { name: file.name })}>
        <Icon name="close" size={14} />
      </button>
    </li>
  );
}
