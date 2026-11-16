"use client";

import { useEffect, useState } from "react";
import { Notice, Section } from "./parts";
import { fetchHospitalStats, type HospitalStats, type SalesPeriod } from "@/lib/api";
import { formatDay, formatNumber } from "@/lib/format";
import { useT } from "@/lib/locale";

type State = { settled: number; stats: HospitalStats | null };

/**
 * Bookings and sales of the current week and month. It loads on its own: when the figures cannot be had,
 * only this card says so and the rest of the page keeps working.
 */
export default function SalesCard({ hospitalId }: { hospitalId: number }) {
  const t = useT();
  const [attempt, setAttempt] = useState(0);
  const [state, setState] = useState<State>({ settled: -1, stats: null });

  useEffect(() => {
    const controller = new AbortController();
    const run = async () => {
      const res = await fetchHospitalStats(hospitalId, controller.signal);
      if (controller.signal.aborted) return;
      setState({ settled: attempt, stats: res.ok ? res.data : null });
    };
    void run();
    return () => controller.abort();
  }, [hospitalId, attempt]);

  const loading = state.settled !== attempt;
  return (
    <Section id="sec-d-sales" title={t("hs.title")} desc={t("hs.desc")}>
      {loading && !state.stats ? (
        <p className="panel panel--empty" role="status">{t("hs.loading")}</p>
      ) : state.stats ? (
        <div className={loading ? "sales is-busy" : "sales"} aria-busy={loading}>
          <Period label={t("hs.week")} period={state.stats.week} />
          <Period label={t("hs.month")} period={state.stats.month} />
        </div>
      ) : (
        <Notice tone="warn" icon="alert" action={<button type="button" className="txtbtn" onClick={() => setAttempt((n) => n + 1)}>{t("state.retry")}</button>}>
          {t("hs.error")}
        </Notice>
      )}
    </Section>
  );
}

function Period({ label, period }: { label: string; period: SalesPeriod }) {
  const t = useT();
  const money = (n: number) => t("hs.money", { n: formatNumber(n) });
  const { bookings, sales } = period;
  const empty = bookings.created === 0 && sales.paid === 0 && sales.refunded === 0;
  return (
    <div className="panel sales__block">
      <h3 className="sales__title">{label}</h3>
      <p className="sales__range">{t("hs.range", { from: formatDay(period.from), to: formatDay(period.to) })}</p>
      {empty ? (
        <p className="sales__empty">{t("hs.empty")}</p>
      ) : (
        <dl className="sales__facts">
          <div>
            <dt>{t("hs.bookings")}</dt>
            <dd className="sales__value">{t("hs.bookingsValue", { n: formatNumber(bookings.created) })}</dd>
            <dd className="sales__note">{t("hs.bookingsNote", { cancelled: formatNumber(bookings.cancelled), active: formatNumber(bookings.active) })}</dd>
          </div>
          <div>
            <dt>{t("hs.sales")}</dt>
            <dd className="sales__value">{money(sales.net)}</dd>
            <dd className="sales__note">{t("hs.salesNote", { paid: money(sales.paid), refunded: money(sales.refunded) })}</dd>
          </div>
        </dl>
      )}
    </div>
  );
}
