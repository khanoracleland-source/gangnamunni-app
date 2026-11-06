"use client";
import { useRouter } from "next/navigation";
import styles from "./Legal.module.css";

/**
 * Renders a legal document. `html` is first-party static markup from src/content/legal.ts (never user input).
 * Links to the sibling documents carry data-route and are routed client-side so they stay under the /next base path.
 */
/** `plain`: the 07-06 look (13px grey text, bold article titles, no coloured headings; the 시행일 line moves to the row above the text). */
export default function LegalDoc({ html, lang, plain }: { html: string; lang?: "ko" | "en"; plain?: boolean }) {
  const router = useRouter();
  return (
    <div
      className={plain ? `${styles.doc} ${styles.docPlain}` : styles.doc}
      data-lang={lang}
      dangerouslySetInnerHTML={{ __html: html }}
      onClick={(e) => {
        const a = (e.target as HTMLElement).closest("a[data-route]");
        const to = a?.getAttribute("data-route");
        if (to && to.startsWith("/")) { e.preventDefault(); router.push(to); }
      }}
    />
  );
}
