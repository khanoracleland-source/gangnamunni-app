"use client";

import { useEffect, useId, useRef, useState, type FormEvent } from "react";
import { createPortal } from "react-dom";
import Icon from "../Icon";
import { failureKey, reasonKey } from "./writeErrors";
import { createCategory, updateCategory, type CategoryChange, type CategoryInput, type TreatmentCategory } from "@/lib/api";
import { useT } from "@/lib/locale";

/** Add or edit one category: native <dialog>, a bottom sheet below 640px. The server has the last word on every rule. */
export default function CategoryDialog({
  category,
  nextOrder,
  onClose,
  onSaved,
}: {
  category: TreatmentCategory | null;
  /** The order value offered for a new category (after the last one). */
  nextOrder: number;
  onClose: () => void;
  onSaved: (change: CategoryChange, created: boolean) => void;
}) {
  const t = useT();
  const uid = useId();
  const ref = useRef<HTMLDialogElement>(null);
  const [name, setName] = useState(category?.name ?? "");
  const [icon, setIcon] = useState(category?.icon ?? "");
  const [order, setOrder] = useState(String(category ? category.sortOrder : nextOrder));
  const [busy, setBusy] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [general, setGeneral] = useState<string | null>(null);
  const id = (part: string) => `${uid}-${part}`;

  useEffect(() => {
    const dialog = ref.current;
    if (!dialog) return;
    const returnTo = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    if (!dialog.open) dialog.showModal();
    dialog.querySelector<HTMLElement>("[data-autofocus]")?.focus({ preventScroll: true });
    return () => {
      if (dialog.open) dialog.close();
      if (returnTo?.isConnected) returnTo.focus();
    };
  }, []);

  const clear = (key: string) => setErrors((e) => (key in e ? Object.fromEntries(Object.entries(e).filter(([k]) => k !== key)) : e));

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    if (busy) return;
    const local: Record<string, string> = {};
    if (name.trim() === "") local.name = t("tmw.err.required");
    const sort = order.trim() === "" ? 0 : /^\d{1,9}$/.test(order.trim()) ? Number(order.trim()) : Number.NaN;
    if (Number.isNaN(sort)) local.sortOrder = t("tmw.err.bad_format");
    if (Object.keys(local).length > 0) {
      setErrors(local);
      setGeneral(null);
      document.getElementById(id(Object.keys(local)[0]))?.focus();
      return;
    }
    const input: CategoryInput = { name: name.trim(), icon: icon.trim(), sortOrder: sort };
    setBusy(true);
    setGeneral(null);
    const res = category ? await updateCategory(category.id, input) : await createCategory(input);
    setBusy(false);
    if (res.ok) {
      onSaved(res.data, category === null);
      return;
    }
    if (res.status === 400 && res.error === "invalid_field" && res.field) {
      setErrors({ [res.field]: t(reasonKey(res.reason)) });
      document.getElementById(id(res.field))?.focus();
      return;
    }
    setGeneral(t(failureKey(res)));
  };

  const aria = (key: string) => ({ id: id(key), "aria-invalid": errors[key] ? true : undefined, "aria-describedby": errors[key] ? id(`${key}-err`) : undefined });
  const title = category ? t("tmc.dlg.edit") : t("tmc.dlg.create");

  return createPortal(
    <dialog
      ref={ref}
      className="dialog dialog--prices dialog--alias"
      aria-labelledby={id("title")}
      onCancel={(e) => {
        if (busy) e.preventDefault();
      }}
      onClose={onClose}
      onClick={(e) => {
        if (e.target === e.currentTarget && !busy) onClose();
      }}
    >
      <form className="htp tm__form" onSubmit={submit} noValidate>
        <span className="htp__handle" aria-hidden="true" />
        <div className="htp__head">
          <div className="htp__headt">
            <h2 id={id("title")} className="dialog__title htp__title">{title}</h2>
          </div>
          <button type="button" className="ibtn dialog__close" onClick={onClose} disabled={busy} aria-label={t("tmw.close")} title={t("tmw.close")}>
            <Icon name="close" size={18} />
          </button>
        </div>
        <div className="htp__body tm__form-body">
          <div className="field">
            <label className="field__label" htmlFor={id("name")}>{t("tmc.f.name")}</label>
            <input className="input" maxLength={100} autoComplete="off" value={name} onChange={(e) => { setName(e.target.value); clear("name"); }} data-autofocus="" {...aria("name")} />
            {errors.name ? <p className="field__error" id={id("name-err")} role="alert">{errors.name}</p> : null}
          </div>
          <div className="field">
            <label className="field__label" htmlFor={id("icon")}>{t("tmc.f.icon")}</label>
            <input className="input" maxLength={100} autoComplete="off" spellCheck={false} value={icon} onChange={(e) => { setIcon(e.target.value); clear("icon"); }} {...aria("icon")} />
            <p className="field__hint">{t("tmc.f.iconHint")}</p>
            {errors.icon ? <p className="field__error" id={id("icon-err")} role="alert">{errors.icon}</p> : null}
          </div>
          <div className="field">
            <label className="field__label" htmlFor={id("sortOrder")}>{t("tmc.f.order")}</label>
            <input className="input" inputMode="numeric" maxLength={9} autoComplete="off" value={order} onChange={(e) => { setOrder(e.target.value); clear("sortOrder"); }} {...aria("sortOrder")} />
            <p className="field__hint">{t("tmc.f.orderHint")}</p>
            {errors.sortOrder ? <p className="field__error" id={id("sortOrder-err")} role="alert">{errors.sortOrder}</p> : null}
          </div>
        </div>
        <div className="tm__alias-foot tm__form-foot">
          {general ? <p className="field__error tm__form-error" role="alert">{general}</p> : <span />}
          <span className="tm__form-btns">
            <button type="button" className="btn btn--ghost" onClick={onClose} disabled={busy}>{t("tmw.cancel")}</button>
            <button type="submit" className="btn btn--primary" disabled={busy}>{busy ? t("tmw.saving") : t("tmw.save")}</button>
          </span>
        </div>
      </form>
    </dialog>,
    document.body,
  );
}
