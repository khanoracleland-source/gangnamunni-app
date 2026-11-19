"use client";

import { Field, useErrorText } from "./fields";
import { formatBusinessNumber, type Draft, type Errors } from "@/lib/hospitalApply";
import { useT } from "@/lib/locale";

export type PhoneState =
  | { status: "idle" }
  | { status: "checking"; phone: string }
  | { status: "ok"; phone: string }
  | { status: "dup"; phone: string; name: string }
  | { status: "fail"; phone: string };

/** 기본 정보: only the hospital name is required; the phone is checked for a duplicate, the email for its shape. */
export default function BasicSection({
  draft,
  set,
  errors,
  phone: reported,
  onCheckPhone,
}: {
  draft: Draft;
  set: (patch: Partial<Draft>) => void;
  errors: Errors;
  phone: PhoneState;
  onCheckPhone: () => void;
}) {
  const t = useT();
  const text = useErrorText();
  // An answer belongs to the number it was asked about; once the field holds another number it is stale.
  const phone: PhoneState = reported.status !== "idle" && reported.phone !== draft.phone.trim() ? { status: "idle" } : reported;
  const phoneBusy = phone.status === "checking";
  const phoneError = phone.status === "dup" ? t("hap.f.phoneDup", { name: phone.name }) : text(errors.phone) ?? (phone.status === "fail" ? t("hap.f.phoneFail") : null);

  return (
    <div className="form">
      <Field id="f-name" label={t("hap.f.name")} required error={text(errors.name)}>
        <input id="f-name" className="input" type="text" maxLength={255} value={draft.name} aria-invalid={errors.name ? true : undefined} aria-describedby={errors.name ? "f-name-e" : undefined} autoComplete="off" onChange={(e) => set({ name: e.target.value })} />
      </Field>
      <Field id="f-owner" label={t("hap.f.owner")}>
        <input id="f-owner" className="input" type="text" maxLength={100} value={draft.ownerName} autoComplete="off" onChange={(e) => set({ ownerName: e.target.value })} />
      </Field>
      <Field id="f-biz" label={t("hap.f.biz")}>
        <input id="f-biz" className="input" type="text" inputMode="numeric" maxLength={12} placeholder={t("hap.f.bizPh")} value={draft.businessNumber} autoComplete="off" onChange={(e) => set({ businessNumber: formatBusinessNumber(e.target.value) })} />
      </Field>
      <Field
        id="f-phone"
        label={t("hap.f.phone")}
        hint={phoneBusy ? t("hap.f.phoneBusy") : t("hap.f.phoneHint")}
        ok={phone.status === "ok" ? t("hap.f.phoneOk") : null}
        error={phoneError}
      >
        <div className="ha-inrow">
          <input id="f-phone" className="input" type="tel" inputMode="tel" maxLength={20} value={draft.phone} autoComplete="tel" aria-invalid={phoneError ? true : undefined} aria-describedby={phoneError ? "f-phone-e" : "f-phone-h"} onChange={(e) => set({ phone: e.target.value })} onBlur={onCheckPhone} />
          <button type="button" className="btn btn--ghost" onClick={onCheckPhone} disabled={phoneBusy || draft.phone.trim() === ""}>
            {t("hap.f.phoneCheck")}
          </button>
        </div>
      </Field>
      <Field id="f-email" label={t("hap.f.email")} hint={t("hap.f.emailHint")} error={text(errors.email)}>
        <input id="f-email" className="input" type="email" maxLength={191} value={draft.email} autoComplete="off" aria-invalid={errors.email ? true : undefined} aria-describedby={errors.email ? "f-email-e" : "f-email-h"} onChange={(e) => set({ email: e.target.value })} />
      </Field>
      <Field id="f-cname" label={t("hap.f.cname")}>
        <input id="f-cname" className="input" type="text" maxLength={100} value={draft.contactName} autoComplete="off" onChange={(e) => set({ contactName: e.target.value })} />
      </Field>
      <Field id="f-cphone" label={t("hap.f.cphone")}>
        <input id="f-cphone" className="input" type="tel" inputMode="tel" maxLength={20} value={draft.contactPhone} autoComplete="off" onChange={(e) => set({ contactPhone: e.target.value })} />
      </Field>
    </div>
  );
}
