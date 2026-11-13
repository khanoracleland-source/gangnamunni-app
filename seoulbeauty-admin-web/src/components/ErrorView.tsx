"use client";

import { useEffect, useState } from "react";
import Icon from "./Icon";
import { PHP_ADMIN_URL } from "@/lib/config";
import { setLocale, useLocale, useT } from "@/lib/locale";
import type { Locale } from "@/lib/i18n";

type Props = {
  error: Error & { digest?: string };
  /** Tries to render the failed part again. */
  reset: () => void;
};

/**
 * Our own screen in place of the framework's "This page couldn't load". The technical block holds
 * only the error's name, message and digest, the page address, the time and the browser string:
 * enough to find the cause next time, nothing secret.
 */
export default function ErrorView({ error, reset }: Props) {
  const t = useT();
  const locale = useLocale();
  const [copied, setCopied] = useState(false);
  const [at] = useState(() => new Date().toISOString());

  useEffect(() => {
    console.error("[admin-web] render error", error);
  }, [error]);

  const url = typeof window !== "undefined" ? window.location.href : "";
  const agent = typeof navigator !== "undefined" ? navigator.userAgent : "";
  const details = [
    `${t("err.techName")}: ${error.name}`,
    `${t("err.techMessage")}: ${error.message}`,
    error.digest ? `${t("err.techDigest")}: ${error.digest}` : "",
    `${t("err.techUrl")}: ${url}`,
    `${t("err.techTime")}: ${at}`,
    `${t("err.techAgent")}: ${agent}`,
  ].filter(Boolean).join("\n");

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(details);
      setCopied(true);
      window.setTimeout(() => setCopied(false), 2000);
    } catch {
      // Clipboard blocked: the text stays selectable on screen.
    }
  };

  return (
    <main className="fatal" role="alert">
      <div className="fatal__box">
        <div className="fatal__top">
          <span className="fatal__icon"><Icon name="alert" size={22} /></span>
          <label className="lang">
            <span className="sr-only">{t("top.language")}</span>
            <select value={locale} onChange={(e) => setLocale(e.target.value as Locale)}>
              <option value="ko">한국어</option>
              <option value="uz">O&apos;zbekcha</option>
            </select>
          </label>
        </div>
        <h1 className="fatal__title">{t("err.title")}</h1>
        <p className="fatal__text">{t("err.body")}</p>
        <p className="fatal__hint">{t("err.hintTranslate")}</p>
        <div className="panel__actions">
          <button type="button" className="btn btn--primary" onClick={reset}>
            <Icon name="refresh" />
            {t("err.retry")}
          </button>
          <button type="button" className="btn btn--secondary" onClick={() => window.location.reload()}>
            {t("err.reload")}
          </button>
          <a className="btn btn--secondary" href={PHP_ADMIN_URL}>
            <Icon name="external" />
            {t("state.legacy")}
          </a>
        </div>
        <details className="fatal__tech">
          <summary>{t("err.tech")}</summary>
          <pre className="fatal__pre">{details}</pre>
          <button type="button" className="btn btn--secondary" onClick={copy}>
            {copied ? t("err.techCopied") : t("err.techCopy")}
          </button>
        </details>
      </div>
    </main>
  );
}
