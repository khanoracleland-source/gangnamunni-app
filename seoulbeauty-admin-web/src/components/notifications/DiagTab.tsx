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
import { fetchDiagnostics, type Diagnostics } from "@/lib/notificationsApi";

const leaveToLogin = () => window.location.assign(PHP_ADMIN_URL);

/** Health of the push tokens: how many work, how many are dead, who has several devices. Read only. */
export default function DiagTab() {
  const t = useT();
  const [tick, setTick] = useState(0);
  const [got, setGot] = useState<{ key: number; res: ApiResult<Diagnostics> } | null>(null);

  useEffect(() => {
    const c = new AbortController();
    void fetchDiagnostics(c.signal).then((res) => {
      if (c.signal.aborted) return;
      if (!res.ok && res.status === 401) leaveToLogin();
      setGot({ key: tick, res });
    });
    return () => c.abort();
  }, [tick]);

  const loading = got?.key !== tick;
  const d = got?.res.ok ? got.res.data : null;
  const failed = got !== null && !got.res.ok;
  const n = (v: number) => formatNumber(v);

  return (
    <div className="nt-diag">
      <div className="nt-toolbar">
        <h2 className="nt-toolbar__n nt-auto__h">{t("nt.g.title")}</h2>
        <button type="button" className="btn btn--ghost" onClick={() => setTick((x) => x + 1)} disabled={loading}><Icon name="refresh" size={16} /> {t("nt.h.refresh")}</button>
      </div>
      {failed ? <Notice tone="warn" icon="alert" action={<button type="button" className="btn btn--ghost" onClick={() => setTick((x) => x + 1)}>{t("nt.h.retry")}</button>}>{t("nt.h.failed")}</Notice> : null}
      {loading && !d ? <div className="nt-skel" aria-hidden="true"><i /><i /><i /></div> : null}
      {d ? (
        <div className="nt-diag__grid" aria-busy={loading}>
          <section className="nt-led">
            <h3 className="nt-dv__h">{t("nt.g.tokens")}</h3>
            <dl>
              <LedgerRow label={t("nt.g.active")} value={n(d.summary.active)} big />
              <LedgerRow label={t("nt.g.dead")} value={n(d.summary.dead)} />
              <LedgerRow label={t("nt.g.total")} value={n(d.summary.total)} />
              <LedgerRow label="Android" value={n(d.summary.android)} />
              <LedgerRow label="iOS" value={n(d.summary.ios)} />
              <LedgerRow label="Web" value={n(d.summary.web)} />
              <LedgerRow label={t("nt.g.noMember")} value={n(d.summary.withoutMember)} />
              <LedgerRow label={t("nt.g.iosAndroid")} value={n(d.summary.iosMarkedAndroid)} />
              <LedgerRow label={t("nt.g.dups")} value={n(d.duplicates.length)} />
            </dl>
          </section>
          <section>
            <h3 className="nt-dv__h">{t("nt.g.multi")}</h3>
            {d.multiDevices.length === 0 ? <p className="nt-empty">{t("nt.g.none")}</p> : (
              <ul className="nt-rc">
                {d.multiDevices.map((r) => (
                  <li key={`${r.memberId}-${r.platform}`} className="nt-rc__r">
                    <span className="nt-rc__id">{r.memberId}</span>
                    <span className="nt-rc__ch">{r.platform} · {t("nt.g.devices", { n: r.count })}</span>
                    <span className="nt-rc__ch">{minuteOf(r.lastSeen)}</span>
                  </li>
                ))}
              </ul>
            )}
            <h3 className="nt-dv__h">{t("nt.g.recent")}</h3>
            {d.recent.length === 0 ? <p className="nt-empty">{t("nt.g.none")}</p> : (
              <ul className="nt-rc">
                {d.recent.map((r, i) => (
                  <li key={`${r.memberId}-${r.at}-${i}`} className="nt-rc__r">
                    <span className="nt-rc__id">{r.memberId}</span>
                    <span className="nt-rc__ch">{r.platform} · {r.tokenStart}…</span>
                    <span className={`nt-st nt-st--${r.active ? "sent" : "failed"}`}>{t(r.active ? "nt.g.on" : "nt.g.off")}</span>
                    <span className="nt-rc__code">{minuteOf(r.at)}</span>
                  </li>
                ))}
              </ul>
            )}
          </section>
        </div>
      ) : null}
    </div>
  );
}
