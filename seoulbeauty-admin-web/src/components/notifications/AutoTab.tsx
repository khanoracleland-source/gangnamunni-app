"use client";

import { useEffect, useState } from "react";
import Icon from "../Icon";
import { Notice } from "../hospitals/parts";
import { LedgerRow } from "./NtParts";
import type { ApiResult } from "@/lib/api";
import { PHP_ADMIN_URL } from "@/lib/config";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/locale";
import { minuteOf } from "@/lib/members";
import { fetchAutomatic, type AutomaticInfo } from "@/lib/notificationsApi";

const leaveToLogin = () => window.location.assign(PHP_ADMIN_URL);

/** What the server sends by itself: today the visit reminder. Read only. */
export default function AutoTab() {
  const t = useT();
  const [tick, setTick] = useState(0);
  const [got, setGot] = useState<{ key: number; res: ApiResult<AutomaticInfo> } | null>(null);

  useEffect(() => {
    const c = new AbortController();
    void fetchAutomatic(c.signal).then((res) => {
      if (c.signal.aborted) return;
      if (!res.ok && res.status === 401) leaveToLogin();
      setGot({ key: tick, res });
    });
    return () => c.abort();
  }, [tick]);

  const loading = got?.key !== tick;
  const v = got?.res.ok ? got.res.data.visitReminder : null;
  const failed = got !== null && !got.res.ok;

  return (
    <div className="nt-auto">
      <div className="nt-toolbar">
        <h2 className="nt-toolbar__n nt-auto__h">{t("nt.a.title")}</h2>
        <button type="button" className="btn btn--ghost" onClick={() => setTick((n) => n + 1)} disabled={loading}><Icon name="refresh" size={16} /> {t("nt.h.refresh")}</button>
      </div>
      {failed ? <Notice tone="warn" icon="alert" action={<button type="button" className="btn btn--ghost" onClick={() => setTick((n) => n + 1)}>{t("nt.h.retry")}</button>}>{t("nt.h.failed")}</Notice> : null}
      {loading && !v ? <div className="nt-skel" aria-hidden="true"><i /><i /><i /></div> : null}
      {v && !v.available ? <Notice tone="warn" icon="alert">{t("nt.a.unavailable")}</Notice> : null}
      {v && v.available ? (
        <section className="nt-led" aria-busy={loading}>
          <p className="nt-a__lead">{t("nt.a.visit", { time: v.startsAt })}</p>
          <dl>
            <LedgerRow label={t("nt.a.today")} value={formatNumber(v.sentToday)} big />
            <LedgerRow label={t("nt.a.yesterday")} value={formatNumber(v.sentYesterday)} />
            <LedgerRow label={t("nt.a.last")} value={v.lastSentAt ? minuteOf(v.lastSentAt) : t("nt.a.none")} />
          </dl>
          <p className="nt-a__note">{t("nt.a.note")}</p>
        </section>
      ) : null}
    </div>
  );
}
