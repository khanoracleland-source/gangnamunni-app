"use client";

import { useEffect, useId, useRef, useState, type FormEvent } from "react";
import { toast } from "sonner";
import Icon from "../Icon";
import { Notice } from "../hospitals/parts";
import { createAdmin, editAdmin, fetchHospitals, type AdminAccount, type WriteFailure } from "@/lib/api";
import {
  ASSIGNABLE_ROLES, NOTIFICATION_TYPES, createBody, editBody, emptyForm, formFromAccount, hasChanges, isKnownRole, serverFieldErrors, validateForm,
  type AdminForm,
} from "@/lib/admins";
import { PHP_ADMIN_URL } from "@/lib/config";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";

type Errors = Record<string, MessageKey>;

const without = (e: Errors, key: string): Errors => {
  if (!(key in e)) return e;
  const next = { ...e };
  delete next[key];
  return next;
};

/**
 * Add an account (`account` empty) or edit one. A super admin may change anything but the login name; every other role
 * edits only its own details. Mount it when the button is pressed, unmount it in `onClose`.
 */
export default function AdminFormDialog({
  account,
  superViewer,
  onClose,
  onSaved,
}: {
  account: AdminAccount | null;
  superViewer: boolean;
  onClose: () => void;
  /** Called after the server accepted the change; `item` is the account as stored. */
  onSaved: (item: AdminAccount) => void;
}) {
  const t = useT();
  const mode = account ? "edit" : "create";
  const mine = account?.isSelf === true;
  const ref = useRef<HTMLDialogElement>(null);
  const titleId = useId();
  const [form, setForm] = useState<AdminForm>(() => (account ? formFromAccount(account) : emptyForm()));
  const [errors, setErrors] = useState<Errors>({});
  const [banner, setBanner] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const sending = useRef(false);

  useEffect(() => {
    const dialog = ref.current;
    if (dialog && !dialog.open) {
      dialog.showModal();
      dialog.querySelector<HTMLElement>("[data-autofocus]")?.focus();
    }
  }, []);

  const set = <K extends keyof AdminForm>(key: K, value: AdminForm[K]) => {
    setForm((f) => ({ ...f, [key]: value }));
    setErrors((e) => without(e, key));
  };
  const toggle = (type: string) =>
    set("notifications", form.notifications.includes(type) ? form.notifications.filter((x) => x !== type) : NOTIFICATION_TYPES.filter((x) => x === type || form.notifications.includes(x)));

  const failure = (res: WriteFailure) => {
    if (res.status === 401) {
      window.location.assign(PHP_ADMIN_URL);
      return;
    }
    if (res.status === 400) {
      setErrors(serverFieldErrors(res));
      setBanner(t("ad.err.fix"));
    } else if (res.status === 409 && res.error === "conflict") {
      const field = res.field === "email" || mode === "edit" ? "email" : "username";
      setErrors({ [field]: field === "email" ? "ad.err.emailTaken" : "ad.err.usernameTaken" });
      setBanner(t("ad.err.fix"));
    } else if (res.status === 409 && res.error === "last_super_admin") setBanner(t("ad.err.lastSuper"));
    else if (res.status === 403 && res.error === "wrong_password") {
      setErrors({ currentPassword: "ad.err.wrong" });
      setForm((f) => ({ ...f, currentPassword: "" }));
    }
    else if (res.status === 403 && res.error === "self_forbidden") setBanner(t("ad.err.selfRole"));
    else if (res.status === 429) setBanner(t("ad.err.limited", { min: Math.max(1, Math.ceil((res.retryAfterSeconds ?? 60) / 60)) }));
    else if (res.status === 403) setBanner(t("ad.err.forbidden"));
    else if (res.status === 404) setBanner(t("ad.err.gone"));
    else if (res.status === 503) setBanner(t("ad.err.unavailable"));
    else if (res.status === 0) setBanner(t("ad.err.network"));
    else setBanner(t("ad.err.server"));
  };

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    if (busy || sending.current) return;
    setBanner(null);
    const problems = validateForm(mode, form, superViewer, account ?? undefined);
    if (Object.keys(problems).length > 0) {
      setErrors(problems);
      setBanner(t("ad.err.fix"));
      return;
    }
    setErrors({});
    if (account) {
      const body = editBody(account, form, superViewer);
      if (!hasChanges(body)) {
        setBanner(t("ad.f.nochange"));
        return;
      }
      sending.current = true;
      setBusy(true);
      const res = await editAdmin(account.id, body);
      sending.current = false;
      setBusy(false);
      if (!res.ok) return failure(res);
      toast.success(t(res.data.passwordChanged ? "ad.ok.passwordChanged" : "ad.ok.saved"));
      onSaved(res.data.item);
      ref.current?.close();
      return;
    }
    sending.current = true;
    setBusy(true);
    const res = await createAdmin(createBody(form));
    sending.current = false;
    setBusy(false);
    if (!res.ok) return failure(res);
    toast.success(t("ad.ok.created"));
    onSaved(res.data.item);
    ref.current?.close();
  };

  const canRole = superViewer && mode === "edit" && !mine;
  const showRole = mode === "create" || canRole;
  const needsHospital = showRole && form.role !== "super_admin";
  const title = mode === "create" ? t("ad.f.title.create") : mine ? t("ad.f.title.mine") : t("ad.f.title.edit");
  const showCurrent = mode === "edit" && !superViewer;

  return (
    <dialog
      ref={ref}
      className="dialog dialog--wide dialog--sheet"
      aria-labelledby={titleId}
      onCancel={(e) => {
        if (busy) e.preventDefault();
      }}
      onClose={onClose}
      onClick={(e) => {
        if (e.target === e.currentTarget && !busy) ref.current?.close();
      }}
    >
      <form className="dialog__box" onSubmit={submit} noValidate>
        <div className="dialog__head">
          <h2 id={titleId} className="dialog__title">{title}</h2>
          <button type="button" className="ibtn dialog__close" onClick={() => ref.current?.close()} disabled={busy} aria-label={t("m.close")} title={t("m.close")}>
            <Icon name="close" />
          </button>
        </div>
        {account ? <p className="dialog__name" translate="no">{account.username}</p> : null}

        <div className="adm-form">
          {mode === "create" ? (
            <>
              <Field id="ad-username" label={t("ad.f.username")} hint={t("ad.f.username.hint")} error={errors.username && t(errors.username)}>
                <input id="ad-username" className="input" value={form.username} onChange={(e) => set("username", e.target.value)} autoComplete="off" autoCapitalize="off" spellCheck={false} maxLength={30} disabled={busy} data-autofocus="" aria-invalid={errors.username ? true : undefined} />
              </Field>
              <Field id="ad-password" label={t("ad.f.password")} hint={t("ad.f.password.hint")} error={errors.password && t(errors.password)}>
                <input id="ad-password" className="input" type="password" value={form.password} onChange={(e) => set("password", e.target.value)} autoComplete="new-password" disabled={busy} aria-invalid={errors.password ? true : undefined} />
              </Field>
            </>
          ) : null}
          <Field id="ad-name" label={t("ad.f.name")} required error={errors.name && t(errors.name)}>
            <input id="ad-name" className="input" value={form.name} onChange={(e) => set("name", e.target.value)} autoComplete="off" disabled={busy} data-autofocus={mode === "edit" ? "" : undefined} aria-invalid={errors.name ? true : undefined} />
          </Field>
          <Field id="ad-phone" label={t("ad.f.phone")} error={errors.phone && t(errors.phone)}>
            <input id="ad-phone" className="input" type="tel" value={form.phone} onChange={(e) => set("phone", e.target.value)} autoComplete="off" disabled={busy} aria-invalid={errors.phone ? true : undefined} />
          </Field>
          <Field id="ad-email" label={t("ad.f.email")} error={errors.email && t(errors.email)} wide>
            <input id="ad-email" className="input" type="email" value={form.email} onChange={(e) => set("email", e.target.value)} autoComplete="off" autoCapitalize="off" disabled={busy} aria-invalid={errors.email ? true : undefined} />
          </Field>

          {showRole ? (
            <Field id="ad-role" label={t("ad.f.role")} error={errors.role && t(errors.role)}>
              <select id="ad-role" className="sel sel--field" value={form.role} onChange={(e) => set("role", e.target.value)} disabled={busy}>
                {[...ASSIGNABLE_ROLES, ...(account && !(ASSIGNABLE_ROLES as readonly string[]).includes(account.role) ? [account.role] : [])].map((r) => (
                  <option key={r} value={r}>{isKnownRole(r) ? t(`role.${r}`) : r}</option>
                ))}
              </select>
            </Field>
          ) : null}
          {needsHospital ? <HospitalPick form={form} error={errors.hospitalId && t(errors.hospitalId)} busy={busy} onPick={(id, name) => setForm((f) => ({ ...f, hospitalId: id, hospitalName: name }))} clearError={() => setErrors((e) => without(e, "hospitalId"))} /> : null}

          {mode === "edit" ? (
            <>
              <Field id="ad-new" label={t("ad.f.newPassword")} hint={t("ad.f.newPassword.hint")} error={errors.newPassword && t(errors.newPassword)} wide>
                <input id="ad-new" className="input" type="password" value={form.newPassword} onChange={(e) => set("newPassword", e.target.value)} autoComplete="new-password" disabled={busy} aria-invalid={errors.newPassword ? true : undefined} />
              </Field>
              {showCurrent ? (
                <Field id="ad-current" label={t("ad.f.currentPassword")} hint={t("ad.f.currentPassword.hint")} error={errors.currentPassword && t(errors.currentPassword)} wide>
                  <input id="ad-current" className="input" type="password" value={form.currentPassword} onChange={(e) => set("currentPassword", e.target.value)} autoComplete="current-password" disabled={busy} aria-invalid={errors.currentPassword ? true : undefined} />
                </Field>
              ) : null}
            </>
          ) : null}

          <fieldset className="adm-form__notif">
            <legend className="field__label">{t("ad.f.notifications")}</legend>
            <p className="field__hint">{t("ad.f.notifications.hint")}</p>
            <div className="adm-checks">
              {NOTIFICATION_TYPES.map((type) => (
                <label key={type} className="check">
                  <input type="checkbox" checked={form.notifications.includes(type)} onChange={() => toggle(type)} disabled={busy} />
                  <span>{t(`ad.nt.${type}`)}</span>
                </label>
              ))}
            </div>
          </fieldset>
        </div>

        {banner ? <div className="dialog__err"><Notice tone="warn" icon="alert">{banner}</Notice></div> : null}
        <div className="dialog__foot">
          <button type="button" className="btn btn--ghost" onClick={() => ref.current?.close()} disabled={busy}>{t("h.c.cancel")}</button>
          <button type="submit" className="btn btn--primary" disabled={busy}>{busy ? t("ad.f.working") : t(mode === "create" ? "ad.f.create" : "ad.f.save")}</button>
        </div>
      </form>
    </dialog>
  );
}

function Field({ id, label, hint, error, required, wide, children }: { id: string; label: string; hint?: string; error?: string | false; required?: boolean; wide?: boolean; children: React.ReactNode }) {
  return (
    <div className={wide ? "field adm-form__wide" : "field"}>
      <label className="field__label" htmlFor={id}>{label}{required ? <span className="field__req" aria-hidden="true">*</span> : null}</label>
      {children}
      {hint ? <span className="field__hint">{hint}</span> : null}
      {error ? <span className="field__error" role="alert">{error}</span> : null}
    </div>
  );
}

/** Search by name, then choose from what the server found (active hospitals). The chosen one stays in the list. */
function HospitalPick({ form, error, busy, onPick, clearError }: { form: AdminForm; error?: string | false; busy: boolean; onPick: (id: number | null, name: string) => void; clearError: () => void }) {
  const t = useT();
  const [search, setSearch] = useState("");
  const [options, setOptions] = useState<{ id: number; name: string }[]>([]);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    const controller = new AbortController();
    const timer = window.setTimeout(async () => {
      const res = await fetchHospitals({ search: search.trim(), treatmentId: 0, active: "1", pending: false, page: 1, perPage: 50 }, controller.signal);
      if (controller.signal.aborted) return;
      setFailed(!res.ok);
      if (res.ok) setOptions(res.data.items.map((h) => ({ id: h.id, name: h.name })));
    }, search ? 300 : 0);
    return () => {
      window.clearTimeout(timer);
      controller.abort();
    };
  }, [search]);

  const list = form.hospitalId !== null && !options.some((o) => o.id === form.hospitalId) ? [{ id: form.hospitalId, name: form.hospitalName }, ...options] : options;
  return (
    <div className="field adm-form__wide">
      <label className="field__label" htmlFor="ad-hsearch">{t("ad.f.hospital")}<span className="field__req" aria-hidden="true">*</span></label>
      <input id="ad-hsearch" className="input" type="search" value={search} onChange={(e) => setSearch(e.target.value)} onKeyDown={(e) => { if (e.key === "Enter") e.preventDefault(); }} placeholder={t("ad.f.hospital.search")} autoComplete="off" disabled={busy} />
      <select
        className="sel sel--field"
        aria-label={t("ad.f.hospital")}
        value={form.hospitalId ?? ""}
        onChange={(e) => {
          const id = Number(e.target.value);
          onPick(id > 0 ? id : null, list.find((o) => o.id === id)?.name ?? "");
          clearError();
        }}
        disabled={busy}
        aria-invalid={error ? true : undefined}
      >
        <option value="">{t("ad.f.hospital.pick")}</option>
        {list.map((o) => <option key={o.id} value={o.id}>{o.name}</option>)}
      </select>
      {failed ? <span className="field__error" role="alert">{t("ad.f.hospital.failed")}</span> : list.length === 0 && search ? <span className="field__hint">{t("ad.f.hospital.none")}</span> : null}
      {error ? <span className="field__error" role="alert">{error}</span> : null}
    </div>
  );
}
