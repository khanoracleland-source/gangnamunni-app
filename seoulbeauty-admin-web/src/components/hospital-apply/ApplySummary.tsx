"use client";

import { useState } from "react";
import Icon from "../Icon";
import { SECTION_TITLE_KEYS, type SectionId } from "@/lib/hospitalApply";
import { useT } from "@/lib/locale";

export type SummaryItem = { id: SectionId; state: "done" | "err" | "todo"; note: string };

type Props = {
  name: string;
  filled: number;
  items: SummaryItem[];
  current: SectionId | null;
  busy: boolean;
  onJump: (id: SectionId) => void;
  onSubmit: () => void;
  onCancel: () => void;
};

function StateIcon({ state }: { state: SummaryItem["state"] }) {
  const t = useT();
  const label = state === "done" ? t("hap.sm.done") : state === "err" ? t("hap.sm.error") : t("hap.sm.todo");
  return (
    <span className={`ha-st ha-st--${state}`}>
      {state === "done" ? <Icon name="check" size={16} /> : state === "err" ? <Icon name="alert" size={16} /> : <span className="ha-st__dot" />}
      <span className="ha-sr">{label}</span>
    </span>
  );
}

function Checklist({ items, current, onJump, label }: Pick<Props, "items" | "current" | "onJump"> & { label: string }) {
  const t = useT();
  return (
    <nav className="ha-chk" aria-label={label}>
      {items.map((item) => (
        <a
          key={item.id}
          href={`#sec-${item.id}`}
          className={current === item.id ? "is-cur" : undefined}
          aria-current={current === item.id ? "true" : undefined}
          onClick={(e) => {
            e.preventDefault();
            onJump(item.id);
          }}
        >
          <StateIcon state={item.state} />
          <span>{t(SECTION_TITLE_KEYS[item.id])}</span>
          <span className="ha-chk__n">{item.note}</span>
        </a>
      ))}
    </nav>
  );
}

function Progress({ filled, total }: { filled: number; total: number }) {
  const t = useT();
  return <div className="ha-bar" role="img" aria-label={t("hap.sm.progressAria", { n: filled, total })}><i style={{ width: `${(filled / total) * 100}%` }} /></div>;
}

/**
 * Where the form stands. Wide screens: a sticky panel beside the form. Narrow screens: a folding strip under the
 * top bar and a fixed bar of buttons at the bottom. Only the hospital name is required; the progress is not a condition.
 */
export default function ApplySummary({ name, filled, items, current, busy, onJump, onSubmit, onCancel }: Props) {
  const t = useT();
  const [stripOpen, setStripOpen] = useState(false);
  const total = items.length;

  const buttons = (block: boolean) => (
    <>
      <button type="button" className={block ? "btn btn--primary btn--block" : "btn btn--primary"} onClick={onSubmit} disabled={busy}>
        {busy ? t("hap.sm.submitting") : t("hap.sm.submit")}
      </button>
      <button type="button" className={block ? "btn btn--ghost btn--block" : "btn btn--ghost"} onClick={onCancel} disabled={busy}>
        {t("hap.sm.cancel")}
      </button>
    </>
  );

  return (
    <>
      <aside className="ha-sum" aria-label={t("hap.sm.label")}>
        <div>
          <p className={name.trim() === "" ? "ha-sum__name is-empty" : "ha-sum__name"}>{name.trim() === "" ? t("hap.sm.noName") : name.trim()}</p>
          <p className="ha-sum__state"><span className="pill pill--off">{t("hap.sm.inactive")}</span></p>
        </div>
        <div>
          <div className="ha-sum__meta">
            <span>{t("hap.sm.progress")}</span>
            <b>{filled} / {total}</b>
          </div>
          <Progress filled={filled} total={total} />
        </div>
        <Checklist items={items} current={current} onJump={onJump} label={t("hap.sm.nav")} />
        <p className="ha-sum__note">
          <Icon name="info" size={16} />
          <span>{t("hap.sm.note")}</span>
        </p>
        <div className="ha-sum__btns">{buttons(true)}</div>
      </aside>

      <div className="ha-mstrip">
        <button type="button" className="ha-mstrip__btn" aria-expanded={stripOpen} aria-controls="ha-mstrip-body" onClick={() => setStripOpen((v) => !v)}>
          <span className="ha-mstrip__main">
            <span><b>{t("hap.sm.strip")}</b> · {t("hap.sm.stripSub", { n: filled })}</span>
            <Progress filled={filled} total={total} />
          </span>
          <Icon name="chevron" size={18} className="ha-mstrip__chev" />
        </button>
        {stripOpen ? (
          <div id="ha-mstrip-body" className="ha-mstrip__body">
            <Checklist
              items={items}
              current={current}
              label={t("hap.sm.nav")}
              onJump={(id) => {
                setStripOpen(false);
                onJump(id);
              }}
            />
          </div>
        ) : null}
      </div>

      <div className="ha-mbar">
        <button type="button" className="btn btn--ghost ha-mbar__cancel" onClick={onCancel} disabled={busy}>{t("hap.sm.cancel")}</button>
        <button type="button" className="btn btn--primary ha-mbar__go" onClick={onSubmit} disabled={busy}>{busy ? t("hap.sm.submitting") : t("hap.sm.submit")}</button>
      </div>
    </>
  );
}
