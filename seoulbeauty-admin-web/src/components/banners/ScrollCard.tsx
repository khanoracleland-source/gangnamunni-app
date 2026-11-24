"use client";

import { useEffect, useId, useRef, useState } from "react";
import Icon from "../Icon";
import { showUnitToast } from "../treatment-units/UnitToast";
import { fetchBannerScroll, saveBannerScroll } from "@/lib/api";
import { SCROLL_MAX, SCROLL_MIN } from "@/lib/banners";
import { PHP_ADMIN_URL } from "@/lib/config";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";

/** The auto-scroll switch and interval of the home banners. Loaded on its own, so a failure here never hides the list. */
export default function ScrollCard({ canWrite }: { canWrite: boolean }) {
  const t = useT();
  const id = useId();
  const [loaded, setLoaded] = useState(false);
  const [enabled, setEnabled] = useState(true);
  const [seconds, setSeconds] = useState("10");
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<MessageKey | null>(null);
  const lock = useRef(false);

  useEffect(() => {
    const controller = new AbortController();
    void (async () => {
      const res = await fetchBannerScroll(controller.signal);
      if (controller.signal.aborted) return;
      if (res.ok) {
        setEnabled(res.data.enabled);
        setSeconds(String(res.data.intervalSeconds));
        setLoaded(true);
      } else if (res.status === 401) {
        window.location.assign(PHP_ADMIN_URL);
      }
    })();
    return () => controller.abort();
  }, []);

  const toast = (tone: "ok" | "error", key: MessageKey) => showUnitToast({ tone, message: t(key), closeLabel: t("m.close") });
  const n = Number(seconds);
  const valid = /^\d{1,2}$/.test(seconds) && n >= SCROLL_MIN && n <= SCROLL_MAX;

  const save = async () => {
    if (lock.current || !canWrite) return;
    if (enabled && !valid) {
      setError("ban.scroll.err");
      return;
    }
    setError(null);
    lock.current = true;
    setSaving(true);
    try {
      const res = await saveBannerScroll({ enabled, intervalSeconds: valid ? n : 10 });
      if (res.ok) {
        setEnabled(res.data.enabled);
        setSeconds(String(res.data.intervalSeconds));
        toast("ok", "ban.toast.scroll");
      } else if (res.status === 401) {
        window.location.reload();
      } else if (res.status === 400) {
        setError("ban.scroll.err");
      } else {
        toast("error", res.status === 403 ? "ban.toast.forbidden" : res.status === 503 ? "ban.toast.unavailable" : "ban.toast.failed");
      }
    } finally {
      lock.current = false;
      setSaving(false);
    }
  };

  const off = !enabled;
  return (
    <section className="ban-scroll" aria-labelledby={`${id}-t`} aria-busy={!loaded || undefined}>
      <div className="ban-scroll__text">
        <h2 id={`${id}-t`} className="ban-scroll__title">{t("ban.scroll.title")}</h2>
        <p className="ban-scroll__desc">{t("ban.scroll.desc")}</p>
      </div>
      <div className="ban-scroll__ctl">
        <label className="ban-scroll__on">
          <button type="button" role="switch" className="switch" aria-checked={enabled} aria-label={t("ban.scroll.on")} disabled={!loaded || !canWrite || saving} onClick={() => setEnabled((v) => !v)}>
            <span className="switch__thumb" aria-hidden="true" />
          </button>
          <span>{t("ban.scroll.on")}</span>
        </label>
        <div className={off ? "ban-scroll__iv is-off" : "ban-scroll__iv"}>
          <label htmlFor={`${id}-s`} className="ban-scroll__lbl">{t("ban.scroll.interval")}</label>
          <input
            id={`${id}-s`}
            className="input ban-scroll__num"
            type="number"
            inputMode="numeric"
            min={SCROLL_MIN}
            max={SCROLL_MAX}
            step={1}
            value={seconds}
            disabled={!loaded || !canWrite || off || saving}
            aria-invalid={error !== null || undefined}
            aria-describedby={error ? `${id}-e` : undefined}
            onChange={(e) => {
              setSeconds(e.target.value);
              setError(null);
            }}
          />
          <span className="ban-scroll__unit">{t("ban.scroll.sec")}</span>
        </div>
        {canWrite ? (
          <button type="button" className="btn btn--primary btn--sm" onClick={() => void save()} disabled={!loaded || saving} aria-busy={saving || undefined}>
            {t(saving ? "ban.scroll.saving" : "ban.scroll.save")}
          </button>
        ) : null}
      </div>
      {error ? (
        <p id={`${id}-e`} className="ban-err ban-scroll__err" role="alert">
          <Icon name="alert" size={14} />
          {t(error)}
        </p>
      ) : null}
    </section>
  );
}
