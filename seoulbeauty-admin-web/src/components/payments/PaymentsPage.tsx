"use client";

import { useSearchParams } from "next/navigation";
import { useEffect, useState, type ReactNode } from "react";
import AdminShell from "../AdminShell";
import Icon from "../Icon";
import { Notice } from "../hospitals/parts";
import CompareCharts from "./CompareCharts";
import PaymentDrawer from "./PaymentDrawer";
import RefundHistory from "./RefundHistory";
import { GroupTable, PaymentRows, Tabs, type Tab } from "./PaymentsTables";
import SimplePayments from "./PaymentsSimple";
import { ErrorScreen, ForbiddenScreen, LoadingScreen, RedirectingScreen, type ErrorKind } from "../StateScreens";
import { fetchMe, fetchPaymentBoard, fetchPaymentHospitals, fetchPayments, type ApiResult, type Me, type PaymentList } from "@/lib/api";
import { todayIso } from "@/lib/bookings";
import { PHP_ADMIN_URL } from "@/lib/config";
import { percentOf } from "@/lib/format";
import { useT } from "@/lib/locale";
import { defaultFilters, filtersFromSearch, queryKey, rangeProblem, toQuery, won, type Filters } from "@/lib/payments";

type Phase = "loading" | "ready" | "forbidden" | "redirecting" | "error";
const leaveToLogin = () => window.location.assign(PHP_ADMIN_URL);

/** Booking payments: filters and the money panel (net received = counted + held). */
export default function PaymentsPage() {
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
        if (!res.data.features.payments) {
          setErrorKind("paymentsOff");
          setPhase("error");
        } else setPhase("ready");
        return;
      }
      if (res.status === 401) {
        setPhase("redirecting");
        leaveToLogin();
      } else if (res.status === 403) setPhase("forbidden");
      else {
        setErrorKind(res.status === 404 ? "disabled" : res.status === 503 ? "session" : "load");
        setPhase("error");
      }
    })();
    return () => controller.abort();
  }, [attempt]);

  let screen: ReactNode = null;
  if (phase === "loading") screen = <LoadingScreen text={t("pay.loading")} />;
  else if (phase === "redirecting") screen = <RedirectingScreen />;
  else if (phase === "forbidden") screen = <ForbiddenScreen variant="hospitals" />;
  else if (phase === "error") screen = <ErrorScreen kind={errorKind} onRetry={() => { setPhase("loading"); setAttempt((n) => n + 1); }} retrying={false} />;

  return (
    <AdminShell admin={me?.admin ?? null} features={me?.features ?? null} title={t("pay.title")} description={t("pay.subtitle")} screen={screen}>
      {phase === "ready" && me ? <Board me={me} /> : null}
    </AdminShell>
  );
}

function Board({ me }: { me: Me }) {
  const t = useT();
  const own = me.admin.scope === "own";
  const today = todayIso();
  const searchParams = useSearchParams();
  // A link (the dashboard cards) may carry the date range; without one it is the month so far.
  const [filters, setFilters] = useState<Filters>(() => filtersFromSearch(searchParams, today));
  const [qInput, setQInput] = useState("");
  const [tick, setTick] = useState(0);
  const [page, setPage] = useState(1);
  const [tab, setTab] = useState<Tab>("list");
  const [openId, setOpenId] = useState<number | null>(null);
  const [historyKey, setHistoryKey] = useState(0);
  const [hospitals, setHospitals] = useState<{ id: number; name: string }[]>([]);
  const [got, setGot] = useState<{ key: string; res: ApiResult<PaymentList> } | null>(null);
  // null = not known yet; false = the fee switch is off on the server (404): the page keeps its old shape.
  const [feesOn, setFeesOn] = useState<boolean | null>(null);

  const problem = rangeProblem(filters.from, filters.to);
  const query = toQuery(filters, page);
  const key = `${queryKey(query)}#${tick}`;

  // Only a 404 means the fee switch is off. Any other failure (network, 5xx) is tried again a couple of times before the old shape is shown.
  const [probe, setProbe] = useState(0);
  useEffect(() => {
    const c = new AbortController();
    let timer = 0;
    void fetchPaymentBoard(0, "week", c.signal).then((res) => {
      if (c.signal.aborted) return;
      if (res.ok) setFeesOn(true);
      else if (res.status === 401) leaveToLogin();
      else if (res.status === 404 || probe >= 2) setFeesOn(false);
      else timer = window.setTimeout(() => setProbe((n) => n + 1), 2000);
    });
    return () => { c.abort(); window.clearTimeout(timer); };
  }, [probe]);

  useEffect(() => {
    if (problem || feesOn !== false) return;
    const c = new AbortController();
    void fetchPayments(query, c.signal).then((res) => {
      if (c.signal.aborted) return;
      if (!res.ok && res.status === 401) {
        leaveToLogin();
        return;
      }
      setGot({ key, res });
      // The server settles on a page that exists; follow it.
      if (res.ok && res.data.page !== query.page) setPage(res.data.page);
    });
    return () => c.abort();
    // `query` is rebuilt from `filters` on every render; `key` stands for it.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key, problem, feesOn]);

  useEffect(() => {
    const c = new AbortController();
    void fetchPaymentHospitals(c.signal).then((res) => {
      if (!c.signal.aborted && res.ok) setHospitals(res.data);
    });
    return () => c.abort();
  }, []);

  // The search text is applied a moment after typing stops.
  useEffect(() => {
    if (qInput.trim() === filters.q.trim()) return;
    const id = window.setTimeout(() => {
      setFilters((f) => ({ ...f, q: qInput }));
      setPage(1);
    }, 350);
    return () => window.clearTimeout(id);
  }, [qInput, filters.q]);

  const set = (patch: Partial<Filters>) => {
    setFilters((f) => ({ ...f, ...patch }));
    setPage(1);
  };
  const initial = defaultFilters(today);
  const reset = () => {
    setFilters(initial);
    setQInput("");
    setPage(1);
  };
  const dirty = JSON.stringify(filters) !== JSON.stringify(initial) || qInput !== "";

  const summary = !problem && feesOn === false && got?.res.ok ? got.res.data.summary : null;
  const loading = !problem && feesOn === false && got?.key !== key;
  const failed = !problem && feesOn === false && got?.key === key && !got.res.ok;
  const unavailable = failed && got?.res.ok === false && got.res.status === 503;
  const hospitalName = own ? (hospitals[0]?.name ?? "") : "";

  const effTab: Tab = own && tab === "hospitals" ? "list" : tab;

  // The fee switch is on: the simple page. Until it is known nothing else is drawn.
  if (feesOn === null) return <p className="muted" role="status">{t("pay.loading")}</p>;
  if (feesOn) return <SimplePayments own={own} isSuper={me.admin.role === "super_admin"} hospitals={hospitals} hospitalName={hospitalName} />;

  const net = summary ? summary.received - summary.refunded : 0;
  const recPct = summary ? Math.round(percentOf(summary.recognized, net) ?? 0) : 0;
  const heldPct = summary && net > 0 ? 100 - recPct : 0;

  return (
    <div className="pay-page">
      {own && hospitalName ? <p className="bk-chip"><Icon name="hospital" size={14} />{hospitalName}</p> : null}

      <form className="filters bk-filters" role="search" onSubmit={(e) => e.preventDefault()}>
        <label className="field"><span className="field__label">{t("pay.f.from")}</span>
          <input className="input" type="date" required value={filters.from} max={filters.to || undefined} onChange={(e) => set({ from: e.target.value })} />
        </label>
        <label className="field"><span className="field__label">{t("pay.f.to")}</span>
          <input className="input" type="date" required value={filters.to} min={filters.from || undefined} onChange={(e) => set({ to: e.target.value })} />
        </label>
        {!own ? (
          <label className="field field--hosp"><span className="field__label">{t("pay.f.hospital")}</span>
            <select className="sel sel--field" value={filters.hospitalId} onChange={(e) => set({ hospitalId: Number(e.target.value) })}>
              <option value={0}>{t("pay.f.hospitalAll")}</option>
              {hospitals.map((h) => <option key={h.id} value={h.id}>{h.name}</option>)}
            </select>
          </label>
        ) : null}
        <label className="field bk-filters__q"><span className="field__label">{t("pay.f.q")}</span>
          <input className="input" type="search" maxLength={60} placeholder={t("pay.f.qPh")} value={qInput} onChange={(e) => setQInput(e.target.value)} />
        </label>
        <button type="button" className="btn btn--ghost bk-filters__reset" disabled={!dirty} onClick={reset}>{t("pay.f.reset")}</button>
      </form>

      {problem ? <Notice tone="warn" icon="alert">{t(`pay.range.${problem}`)}</Notice> : null}

      {failed ? (
        <Notice tone="warn" icon="alert" action={unavailable ? undefined : <button type="button" className="btn btn--ghost btn--sm" onClick={() => setTick((n) => n + 1)}>{t("pay.err.retry")}</button>}>
          {t(unavailable ? "pay.err.unavailable" : "pay.err.list")}
        </Notice>
      ) : null}

      {!problem && got === null ? <p className="muted" role="status">{t("pay.loading")}</p> : null}

      {summary ? (
        <section className={`pay-money${loading ? " bk-busy" : ""}`} aria-label={t("pay.sum.net")} aria-busy={loading}>
          <div className="pay-money__net">
            <span className="stat__label">{t("pay.sum.net")}</span>
            <span className="pay-money__value">{won(net)}<small>원</small></span>
            <span className="muted">{t("pay.sum.netNote", { received: won(summary.received), refunded: won(summary.refunded) })}</span>
          </div>
          <div className="pay-bar" role="img" aria-label={t("pay.sum.bar", { recognized: String(recPct), held: String(heldPct) })}>
            {net > 0 ? <span className="pay-bar__rec" style={{ width: `${recPct}%` }} /> : null}
            {net > 0 && heldPct > 0 ? <span className="pay-bar__held" style={{ width: `${heldPct}%` }} /> : null}
          </div>
          <dl className="pay-money__parts">
            <div className="pay-part pay-part--rec">
              <dt>{t("pay.sum.recognized")}</dt>
              <dd>{won(summary.recognized)}<small>원</small></dd>
              <dd className="muted">{t("pay.sum.recognizedNote")}</dd>
            </div>
            <div className="pay-part pay-part--held">
              <dt>{t("pay.sum.held")}</dt>
              <dd>{won(summary.held)}<small>원</small></dd>
              <dd className="muted">{t("pay.sum.heldNote")}</dd>
            </div>
          </dl>
          {summary.stuck > 0 ? <Notice tone="warn" icon="alert">{t("pay.sum.stuck", { amount: won(summary.stuck) })}</Notice> : null}
        </section>
      ) : null}

      <CompareCharts hospitalId={filters.hospitalId} />

      {!problem ? (
        <section className="pay-list">
          <Tabs tab={effTab} own={own} onTab={setTab} />
          <div id="pay-panel" role="tabpanel" aria-labelledby={`pay-tab-${effTab}`}>
            {effTab !== "list" && effTab !== "refunds" && filters.q.trim() ? <p className="muted pay-qnote">{t("pay.groupNoQ")}</p> : null}
            {effTab === "list" ? (
              got?.res.ok ? <PaymentRows list={got.res.data} loading={loading} own={own} dirty={dirty} onPage={setPage} onReset={reset} onOpen={setOpenId} /> : null
            ) : effTab === "refunds" ? (
              <RefundHistory key={historyKey} own={own} />
            ) : (
              <GroupTable kind={effTab} hospitalId={filters.hospitalId} from={filters.from} to={filters.to} />
            )}
          </div>
        </section>
      ) : null}

      {openId !== null ? <PaymentDrawer key={openId} id={openId} onClose={() => setOpenId(null)} onChanged={() => { setTick((n) => n + 1); setHistoryKey((n) => n + 1); }} /> : null}
    </div>
  );
}
