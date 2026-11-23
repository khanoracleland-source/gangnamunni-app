"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import Icon from "../Icon";
import { Notice } from "../hospitals/parts";
import HospitalCombo from "./HospitalCombo";
import { GroupTable } from "./PaymentsTables";
import { PeriodBoard, Sections, Totals } from "./PaymentStages";
import type { BoardPeriod, SectionStage } from "@/lib/api";
import { useT } from "@/lib/locale";
import { rangeProblem } from "@/lib/payments";

/** The payments page: the hospital, this week or month, the three cards (보류, 반영, 입금) and the list of the chosen card. */
export default function SimplePayments({ own, isSuper, hospitals, hospitalName }: { own: boolean; isSuper: boolean; hospitals: { id: number; name: string }[]; hospitalName: string }) {
  const t = useT();
  const [hospitalId, setHospitalId] = useState(0);
  const [period, setPeriod] = useState<BoardPeriod>("week");
  const [stage, setStage] = useState<SectionStage>("held");
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [qInput, setQInput] = useState("");
  const [q, setQ] = useState("");
  const [range, setRange] = useState<{ from: string; to: string } | null>(null);
  const [moreOpen, setMoreOpen] = useState(false);

  // Dates count only when both are chosen and make a valid range.
  const problem = from && to ? rangeProblem(from, to) : null;
  const custom = from && to && !problem ? { from, to } : null;
  const dirty = from !== "" || to !== "" || qInput !== "";

  // The search text is applied a moment after typing stops.
  useEffect(() => {
    if (qInput.trim() === q.trim()) return;
    const id = window.setTimeout(() => setQ(qInput), 350);
    return () => window.clearTimeout(id);
  }, [qInput, q]);

  const pick = (p: BoardPeriod) => { setPeriod(p); setFrom(""); setTo(""); };
  const reset = () => { setFrom(""); setTo(""); setQInput(""); setQ(""); };

  return (
    <div className="pay-page pay-simple">
      <div className="pay-simple__top">
        {own ? (
          hospitalName ? <p className="bk-chip"><Icon name="hospital" size={14} />{hospitalName}</p> : <span />
        ) : (
          <div className="pay-simple__hosp"><HospitalCombo hospitals={hospitals} value={hospitalId} onChange={setHospitalId} /></div>
        )}
        {isSuper ? <Link href="/payments/fee-rates" className="btn btn--ghost btn--sm"><Icon name="gear" size={14} />{t("pay.rates.link")}</Link> : null}
      </div>

      <div className="pay-board__head">
        <div className="pay-seg" role="group" aria-label={t("pay.board.period")}>
          {(["week", "month"] as const).map((p) => (
            <button key={p} type="button" className="pay-seg__btn" aria-pressed={!custom && period === p} onClick={() => pick(p)}>{t(p === "week" ? "pay.board.week" : "pay.board.month")}</button>
          ))}
        </div>
        {range ? <span className="muted pay-board__range">{range.from} ~ {range.to}</span> : null}
      </div>

      <details className="pay-details pay-filter" open={dirty || undefined}>
        <summary>{t("pay.filter.more")}</summary>
        <div className="filters bk-filters">
          <label className="field"><span className="field__label">{t("pay.f.from")}</span>
            <input className="input" type="date" value={from} max={to || undefined} onChange={(e) => setFrom(e.target.value)} />
          </label>
          <label className="field"><span className="field__label">{t("pay.f.to")}</span>
            <input className="input" type="date" value={to} min={from || undefined} onChange={(e) => setTo(e.target.value)} />
          </label>
          <label className="field bk-filters__q"><span className="field__label">{t("pay.f.q")}</span>
            <input className="input" type="search" maxLength={60} placeholder={t("pay.f.qPh")} value={qInput} onChange={(e) => setQInput(e.target.value)} />
          </label>
          <button type="button" className="btn btn--ghost bk-filters__reset" disabled={!dirty} onClick={reset}>{t("pay.f.reset")}</button>
        </div>
      </details>
      {problem ? <Notice tone="warn" icon="alert">{t(`pay.range.${problem}` as never)}</Notice> : null}

      <PeriodBoard hospitalId={hospitalId} period={period} custom={custom} stage={stage} onStage={setStage} tick={0} onRange={setRange} />
      <Sections filters={{ hospitalId, q }} stage={stage} range={range} own={own} tick={0} />

      {isSuper ? (
        <details className="pay-details" onToggle={(e) => setMoreOpen((e.currentTarget as HTMLDetailsElement).open)}>
          <summary>{t("pay.more")}</summary>
          {moreOpen && range ? (
            <div className="pay-more">
              <GroupTable kind="hospitals" hospitalId={hospitalId} from={range.from} to={range.to} />
              <Totals hospitalId={hospitalId} from={range.from} to={range.to} tick={0} />
            </div>
          ) : null}
        </details>
      ) : null}
    </div>
  );
}
