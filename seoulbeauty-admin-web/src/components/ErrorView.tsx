"use client";

import { useEffect, useState } from "react";
import Icon from "./Icon";
import { PHP_ADMIN_URL } from "@/lib/config";
import LangSelect from "./LangSelect";
import { useT } from "@/lib/locale";

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
      <div className="fatal__lang"><LangSelect /></div>
      <div className="state__in">
        <div className="state__ring"><Icon name="alert" size={22} /></div>
        <h1>{t("err.title")}</h1>
        <p>{t("err.body")}</p>
        <p className="state__hint">{t("err.hintTranslate")}</p>
        <div className="state__acts">
          <button type="button" className="btn btn--primary" onClick={reset}>
            {t("err.retry")}
          </button>
          <button type="button" className="btn btn--ghost" onClick={() => window.location.reload()}>
            {t("err.reload")}
          </button>
          <a className="btn btn--ghost" href={PHP_ADMIN_URL}>
            {t("state.legacy")}
          </a>
        </div>
        <details className="state__tech">
          <summary>{t("err.tech")}</summary>
          <div className="state__techbody">
            <pre className="state__pre">{details}</pre>
            <button type="button" className="txtbtn" onClick={copy}>
              {copied ? t("err.techCopied") : t("err.techCopy")}
            </button>
          </div>
        </details>
      </div>
    </main>
  );
}
