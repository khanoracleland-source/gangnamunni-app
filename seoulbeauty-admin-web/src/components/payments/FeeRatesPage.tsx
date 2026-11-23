"use client";

import Link from "next/link";
import { useEffect, useState, type FormEvent, type ReactNode } from "react";
import AdminShell from "../AdminShell";
import Icon from "../Icon";
import { Notice } from "../hospitals/parts";
import { ErrorScreen, ForbiddenScreen, LoadingScreen, RedirectingScreen, type ErrorKind } from "../StateScreens";
import { createFeeRate, deleteFeeRate, fetchFeeRates, fetchMe, type ApiResult, type FeeRateItem, type FeeRates, type Me } from "@/lib/api";
import { PHP_ADMIN_URL } from "@/lib/config";
import { useT } from "@/lib/locale";
import { addDays, bpToPercent, methodKey, percentToBp } from "@/lib/paymentFees";

type Phase = "loading" | "ready" | "forbidden" | "redirecting" | "error";
const METHODS = ["card", "bank_transfer"] as const;
const leaveToLogin = () => window.location.assign(PHP_ADMIN_URL);

/** The fee rates per payment method: super admin only. A rate applies from its start date; payments already made keep the rate they had. */
export default function FeeRatesPage() {
  const t = useT();
  const [phase, setPhase] = useState<Phase>("loading");
  const [errorKind, setErrorKind] = useState<ErrorKind>("load");
  const [me, setMe] = useState<Me | null>(null);
  const [attempt, setAttempt] = useState(0);

  useEffect(() => {
    const controller = new AbortController();
    void (async () => {
      const res = await fetchMe(controller.signal);
      if (controller.signal.aborted) return;
      if (res.ok) {
        setMe(res.data);
        if (res.data.admin.role !== "super_admin") setPhase("forbidden");
        else if (!res.data.features.payments) { setErrorKind("paymentsOff"); setPhase("error"); }
        else setPhase("ready");
        return;
      }
      if (res.status === 401) { setPhase("redirecting"); leaveToLogin(); }
      else if (res.status === 403) setPhase("forbidden");
      else { setErrorKind(res.status === 404 ? "disabled" : res.status === 503 ? "session" : "load"); setPhase("error"); }
    })();
    return () => controller.abort();
  }, [attempt]);

  let screen: ReactNode = null;
  if (phase === "loading") screen = <LoadingScreen text={t("pay.loading")} />;
  else if (phase === "redirecting") screen = <RedirectingScreen />;
  else if (phase === "forbidden") screen = <ForbiddenScreen variant="hospitals" />;
  else if (phase === "error") screen = <ErrorScreen kind={errorKind} onRetry={() => { setPhase("loading"); setAttempt((n) => n + 1); }} retrying={false} />;

  return (
    <AdminShell admin={me?.admin ?? null} features={me?.features ?? null} title={t("pay.rates.title")} description={t("pay.rates.subtitle")} screen={screen}>
      {phase === "ready" ? <Rates /> : null}
    </AdminShell>
  );
}

type Status = "active" | "scheduled" | "ended";
function statusOf(row: FeeRateItem, all: FeeRateItem[], today: string): Status {
  if (row.effectiveFrom > today) return "scheduled";
  const newer = all.some((r) => r.payMethod === row.payMethod && r.effectiveFrom > row.effectiveFrom && r.effectiveFrom <= today);
  return newer ? "ended" : "active";
}

function Rates() {
  const t = useT();
  const [tick, setTick] = useState(0);
  const [got, setGot] = useState<ApiResult<FeeRates> | null>(null);
  useEffect(() => {
    const c = new AbortController();
    void fetchFeeRates(c.signal).then((res) => {
      if (c.signal.aborted) return;
      if (!res.ok && res.status === 401) { leaveToLogin(); return; }
      setGot(res);
    });
    return () => c.abort();
  }, [tick]);

  if (got === null) return <p className="muted" role="status">{t("pay.loading")}</p>;
  if (!got.ok) {
    const off = got.status === 404;
    return <Notice tone="warn" icon="alert" action={off ? undefined : <button type="button" className="btn btn--ghost btn--sm" onClick={() => { setGot(null); setTick((n) => n + 1); }}>{t("pay.err.retry")}</button>}>{t(off ? "pay.rates.off" : "pay.err.list")}</Notice>;
  }
  return <RatesBody data={got.data} onChanged={() => setTick((n) => n + 1)} />;
}

function RatesBody({ data, onChanged }: { data: FeeRates; onChanged: () => void }) {
  const t = useT();
  const today = data.today;
  const tomorrow = addDays(today, 1);
  const [method, setMethod] = useState<(typeof METHODS)[number]>("card");
  const [percent, setPercent] = useState("");
  const [from, setFrom] = useState(tomorrow);
  const [busy, setBusy] = useState(false);
  const [problem, setProblem] = useState<string | null>(null);
  const [fieldErr, setFieldErr] = useState<Record<string, string>>({});
  const [confirmId, setConfirmId] = useState<number | null>(null);
  const [done, setDone] = useState<string | null>(null);

  const hasRate = data.items.some((r) => r.payMethod === method);
  const bp = percentToBp(percent);
  const minDate = hasRate ? tomorrow : "2020-01-01";

  const fail = (status: number, error: string, fields: Record<string, string>) => {
    setFieldErr(fields);
    setProblem(status === 401 || status === 403 ? "pay.rates.err.session" : status === 409 ? "pay.rates.err.conflict" : status === 503 ? "pay.rates.err.unavailable" : Object.keys(fields).length ? "pay.rates.err.fields" : error === "not_found" ? "pay.rates.err.notFound" : "pay.rates.err.generic");
  };

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    setDone(null);
    setProblem(null);
    setFieldErr({});
    if (bp === null) { setFieldErr({ rateBp: "bad_value" }); setProblem("pay.rates.err.fields"); return; }
    setBusy(true);
    const res = await createFeeRate({ payMethod: method, rateBp: bp, effectiveFrom: from });
    setBusy(false);
    if (res.ok) { setDone(t("pay.rates.added", { method: t(methodKey(method)), rate: bpToPercent(bp), from })); setPercent(""); onChanged(); return; }
    fail(res.status, res.error, res.fields);
  };

  const remove = async (id: number) => {
    setBusy(true);
    setProblem(null);
    setDone(null);
    const res = await deleteFeeRate(id);
    setBusy(false);
    setConfirmId(null);
    if (res.ok) { setDone(t("pay.rates.removed")); onChanged(); return; }
    fail(res.status, res.error, res.fields);
  };

  return (
    <div className="pay-page">
      <p><Link href="/payments" className="btn btn--ghost btn--sm"><Icon name="chevron" size={14} />{t("pay.rates.back")}</Link></p>
      <p className="muted">{t("pay.rates.rule")}</p>

      <div className="pay-boxes">
        {METHODS.map((m) => {
          const c = data.current[m];
          return (
            <div key={m} className="pay-box">
              <div className="pay-box__label">{t(methodKey(m))}</div>
              <div className="pay-box__value">{c ? bpToPercent(c.rateBp) : "—"}<small>%</small></div>
              <div className="muted pay-box__sub">{c ? t("pay.rates.since", { from: c.effectiveFrom ?? "—" }) : t("pay.rates.none")}</div>
            </div>
          );
        })}
      </div>

      {done ? <Notice tone="ok" icon="check">{done}</Notice> : null}
      {problem ? <Notice tone="warn" icon="alert">{t(problem as never)}</Notice> : null}

      <div className="pay-rates-grid">
        <section aria-label={t("pay.rates.history")}>
          <h2 className="pay-board__title">{t("pay.rates.history")}</h2>
          <table className="pay-gtbl">
            <thead><tr><th scope="col">{t("pay.col.method")}</th><th scope="col" className="pay-r">{t("pay.rates.rate")}</th><th scope="col">{t("pay.rates.from")}</th><th scope="col">{t("pay.col.status")}</th><th scope="col"><span className="sr-only">{t("pay.rates.remove")}</span></th></tr></thead>
            <tbody>
              {data.items.map((r) => {
                const st = statusOf(r, data.items, today);
                return (
                  <tr key={r.id}>
                    <th scope="row">{t(methodKey(r.payMethod))}</th>
                    <td className="pay-r">{bpToPercent(r.rateBp)}%</td>
                    <td className="pay-nowrap">{r.effectiveFrom}</td>
                    <td><span className={`bk-st bk-st--${st === "active" ? "completed" : st === "scheduled" ? "pending" : "refunded"}`}>{t(`pay.rates.st.${st}` as never)}</span></td>
                    <td className="pay-r">
                      {st === "scheduled" ? (confirmId === r.id ? (
                        <span className="pay-confirm">
                          <button type="button" className="btn btn--ghost btn--sm pay-danger" disabled={busy} onClick={() => void remove(r.id)}>{t("pay.rates.removeYes")}</button>
                          <button type="button" className="btn btn--ghost btn--sm" disabled={busy} onClick={() => setConfirmId(null)}>{t("pay.rates.cancel")}</button>
                        </span>
                      ) : <button type="button" className="btn btn--ghost btn--sm" disabled={busy} onClick={() => setConfirmId(r.id)}><Icon name="trash" size={14} />{t("pay.rates.remove")}</button>) : null}
                    </td>
                  </tr>
                );
              })}
              {data.items.length === 0 ? <tr><td colSpan={5} className="muted">{t("pay.rates.empty")}</td></tr> : null}
            </tbody>
          </table>
        </section>

        <form className="pay-rates-form" onSubmit={(e) => void submit(e)} noValidate>
          <h2 className="pay-board__title">{t("pay.rates.add")}</h2>
          <label className="field"><span className="field__label">{t("pay.col.method")}</span>
            <select className="sel sel--field" value={method} onChange={(e) => { setMethod(e.target.value as (typeof METHODS)[number]); setFrom(data.items.some((r) => r.payMethod === e.target.value) ? tomorrow : from); }}>
              {METHODS.map((m) => <option key={m} value={m}>{t(methodKey(m))}</option>)}
            </select>
          </label>
          <label className="field"><span className="field__label">{t("pay.rates.rate")} (%)</span>
            <input className="input" inputMode="decimal" placeholder="1.43" value={percent} aria-invalid={fieldErr.rateBp ? true : undefined} onChange={(e) => setPercent(e.target.value)} />
            {fieldErr.rateBp ? <span className="field__error">{t("pay.rates.err.rate")}</span> : <span className="muted">{t("pay.rates.rateHint")}</span>}
          </label>
          <label className="field"><span className="field__label">{t("pay.rates.from")}</span>
            <input className="input" type="date" min={minDate} max={addDays(today, 90)} value={from} aria-invalid={fieldErr.effectiveFrom ? true : undefined} onChange={(e) => setFrom(e.target.value)} />
            {fieldErr.effectiveFrom ? <span className="field__error">{t(`pay.rates.err.${fieldErr.effectiveFrom === "not_after_latest" ? "notAfter" : fieldErr.effectiveFrom === "must_be_future" ? "mustFuture" : "date"}` as never)}</span> : <span className="muted">{t(hasRate ? "pay.rates.fromHintNext" : "pay.rates.fromHintFirst")}</span>}
          </label>
          {bp !== null && from ? <p className="muted pay-preview">{t("pay.rates.preview", { method: t(methodKey(method)), rate: bpToPercent(bp), from, example: String(Math.floor((50000 * bp) / 10000)) })}</p> : null}
          <button type="submit" className="btn btn--primary" disabled={busy || bp === null || !from}>{t("pay.rates.save")}</button>
        </form>
      </div>
    </div>
  );
}
