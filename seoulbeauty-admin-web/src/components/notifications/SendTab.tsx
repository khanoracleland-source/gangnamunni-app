"use client";

import { useEffect, useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { Notice } from "../hospitals/parts";
import { showUnitToast } from "../treatment-units/UnitToast";
import MemberPicker from "./MemberPicker";
import LinkPicker, { type PickedLink } from "./LinkPicker";
import { LedgerRow, PushCard } from "./NtParts";
import { ConfirmSendDialog, ScheduleDialog } from "./SendDialogs";
import type { Me } from "@/lib/api";
import { PHP_ADMIN_URL } from "@/lib/config";
import { formatNumber } from "@/lib/format";
import { useT } from "@/lib/locale";
import {
  MAX_MESSAGE, MAX_SELECTED, MAX_TITLE, NOTIFICATION_TYPES, TARGETS, isMarketingType, lengthOf, newRequestId, notificationsHref, parseMemberIds, typeKey, writeErrorKey,
  type NotificationType, type Target,
} from "@/lib/notifications";
import { previewNotification, sendNotification, type Audience, type NotificationOptions, type Preview, type SendInput } from "@/lib/notificationsApi";

const leaveToLogin = () => window.location.assign(PHP_ADMIN_URL);
type Got = { key: string; res: { ok: true; data: Preview } | { ok: false; status: number; error: string } };

/** The "send" tab: who gets it, what it says, where it leads, what the server will do (preview) and the buttons that send or plan it. */
export default function SendTab({ me, options, onPlanned }: { me: Me; options: NotificationOptions | null; onPlanned: () => void }) {
  const t = useT();
  const router = useRouter();
  const [target, setTarget] = useState<Target>("all");
  const [type, setType] = useState<NotificationType>("general");
  const [eventId, setEventId] = useState(0);
  const [memberId, setMemberId] = useState("");
  const [membersText, setMembersText] = useState("");
  const [title, setTitle] = useState("");
  const [message, setMessage] = useState("");
  const [link, setLink] = useState<PickedLink>(null);
  const [tick, setTick] = useState(0);
  const [got, setGot] = useState<Got | null>(null);
  const [dialog, setDialog] = useState<"send" | "plan" | null>(null);
  const [snap, setSnap] = useState<{ input: SendInput; resolved: number } | null>(null);
  const [sentOnce, setSentOnce] = useState(false);
  const [testId, setTestId] = useState("");
  const [testBusy, setTestBusy] = useState(false);
  const [testMsg, setTestMsg] = useState<{ ok: boolean; text: string } | null>(null);

  const canSend = me.features.notificationsSend && options?.sendEnabled === true;
  const canPlan = canSend && me.features.notificationsSchedule && options?.scheduleEnabled === true;
  const members = useMemo(() => parseMemberIds(membersText), [membersText]);

  // The audience the server is asked about; null while the admin has not finished choosing it.
  const audience: Audience | null = useMemo(() => {
    if (target === "specific_event") return eventId > 0 ? { target, type, eventId } : null;
    if (target === "individual") return memberId.trim() !== "" ? { target, type, memberId: memberId.trim() } : null;
    if (target === "selected") return members.length > 0 && members.length <= MAX_SELECTED ? { target, type, memberIds: members } : null;
    return { target, type };
  }, [target, type, eventId, memberId, members]);
  const key = audience ? `${JSON.stringify(audience)}#${tick}` : "";

  // The counts are asked a moment after the choice stops changing; an older answer never replaces a newer one.
  useEffect(() => {
    if (!audience) return;
    let live = true;
    const id = window.setTimeout(() => {
      void previewNotification(audience).then((res) => {
        if (!live) return;
        if (!res.ok && res.status === 401) leaveToLogin();
        setGot({ key, res: res.ok ? res : { ok: false, status: res.status, error: res.error } });
      });
    }, 350);
    return () => { live = false; window.clearTimeout(id); };
    // `audience` is rebuilt on every change; `key` stands for it.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key]);

  const loading = audience !== null && got?.key !== key;
  const preview = audience && got?.key === key && got.res.ok ? got.res.data : null;
  const previewError = audience && got?.key === key && !got.res.ok ? got.res : null;
  const reachable = preview ? preview.inApp + preview.pushMembers : 0;
  const ready = canSend && preview !== null && reachable > 0 && title.trim() !== "" && lengthOf(title) <= MAX_TITLE && lengthOf(message) <= MAX_MESSAGE;
  const input: SendInput | null = audience ? { ...audience, title: title.trim(), message: message.trim(), link: link?.path ?? "" } : null;

  // The dialog works on what was on screen when it opened: a refreshed preview must not pull it away from under the admin.
  const openDialog = (kind: "send" | "plan") => {
    if (!input || !preview) return;
    setSnap({ input, resolved: preview.resolved });
    setDialog(kind);
  };
  const resetContent = () => { setTitle(""); setMessage(""); setLink(null); setSentOnce(false); };
  const closeDialog = () => { setDialog(null); if (sentOnce) resetContent(); };

  const test = async () => {
    if (!canSend || testBusy || testId.trim() === "" || title.trim() === "") return;
    setTestBusy(true);
    setTestMsg(null);
    const res = await sendNotification({ target: "individual", memberId: testId.trim(), type, title: title.trim(), message: message.trim(), link: link?.path ?? "" }, newRequestId(), 0, true);
    if (res.ok) setTestMsg({ ok: true, text: t("nt.test.ok", { id: testId.trim(), inApp: res.data.inApp, push: res.data.pushSent }) });
    else if (res.status === 401) leaveToLogin();
    else setTestMsg({ ok: false, text: t(writeErrorKey(res)) });
    setTestBusy(false);
  };

  return (
    <div className="nt-grid">
      <div className="nt-form">
        <section className="nt-sec" aria-labelledby="nt-h-aud">
          <h2 className="nt-h" id="nt-h-aud">{t("nt.f.aud")}</h2>
          <div role="radiogroup" aria-labelledby="nt-h-aud" className="nt-opts">
            {TARGETS.map((x) => (
              <label key={x} className="nt-opt">
                <input type="radio" name="nt-target" checked={target === x} onChange={() => setTarget(x)} />
                <span>{t(`nt.target.${x}`)}</span>
              </label>
            ))}
          </div>
          {target === "specific_event" ? (
            options && options.events.length === 0 ? (
              <p className="nt-fld__h">{t("nt.f.noEvents")}</p>
            ) : (
              <select className="sel nt-ctl" value={eventId} onChange={(e) => setEventId(Number(e.target.value))} aria-label={t("nt.f.event")}>
                <option value={0}>{t("nt.f.event")}</option>
                {(options?.events ?? []).map((ev) => <option key={ev.id} value={ev.id}>{ev.title}</option>)}
              </select>
            )
          ) : null}
          {target === "individual" ? <MemberPicker multi={false} ids={memberId.trim() === "" ? [] : [memberId.trim()]} onAdd={setMemberId} onRemove={() => setMemberId("")} onUnauthorized={leaveToLogin} /> : null}
          {target === "selected" ? (
            <>
              <MemberPicker multi ids={members} onAdd={(id) => setMembersText((x) => (parseMemberIds(x).includes(id) ? x : `${x.trim()}${x.trim() === "" ? "" : "\n"}${id}`))} onRemove={(id) => setMembersText(members.filter((x) => x !== id).join("\n"))} onUnauthorized={leaveToLogin} />
              <p className={members.length > MAX_SELECTED ? "nt-fld__h nt-fld__h--bad" : "nt-fld__h"}>{t("nt.f.membersCount", { n: formatNumber(members.length), max: MAX_SELECTED })}</p>
              <details className="nt-mp__bulk">
                <summary>{t("nt.m.bulk")}</summary>
                <textarea className="input nt-ctl nt-ta" value={membersText} onChange={(e) => setMembersText(e.target.value)} placeholder={t("nt.f.membersPh")} aria-label={t("nt.f.membersPh")} rows={4} />
              </details>
            </>
          ) : null}
        </section>

        <section className="nt-sec" aria-labelledby="nt-h-body">
          <h2 className="nt-h" id="nt-h-body">{t("nt.f.body")}</h2>
          <div role="radiogroup" aria-label={t("nt.f.type")} className="nt-chips">
            {NOTIFICATION_TYPES.map((x) => (
              <label key={x} className="nt-chip">
                <input type="radio" name="nt-type" checked={type === x} onChange={() => setType(x)} />
                <span>{t(typeKey(x))}</span>
                {isMarketingType(x) ? <em>{t("nt.f.ad")}</em> : null}
              </label>
            ))}
          </div>
          <div className="nt-lab"><label htmlFor="nt-title">{t("nt.f.title")}</label><span className={lengthOf(title) > MAX_TITLE ? "nt-cnt nt-cnt--bad" : "nt-cnt"}>{lengthOf(title)}/{MAX_TITLE}</span></div>
          <input id="nt-title" className="input" value={title} onChange={(e) => setTitle(e.target.value)} placeholder={t("nt.f.titlePh")} autoComplete="off" />
          <div className="nt-lab"><label htmlFor="nt-msg">{t("nt.f.msg")}</label><span className={lengthOf(message) > MAX_MESSAGE ? "nt-cnt nt-cnt--bad" : "nt-cnt"}>{lengthOf(message)}/{MAX_MESSAGE}</span></div>
          <textarea id="nt-msg" className="input nt-ta" value={message} onChange={(e) => setMessage(e.target.value)} placeholder={t("nt.f.msgPh")} rows={4} />
        </section>

        <section className="nt-sec" aria-labelledby="nt-h-link">
          <h2 className="nt-h" id="nt-h-link">{t("nt.link.h")}</h2>
          <LinkPicker value={link} onChange={setLink} disabled={false} onUnauthorized={leaveToLogin} />
          <p className="nt-fld__h">{t("nt.link.note")}</p>
        </section>
      </div>

      <aside className="nt-side" aria-labelledby="nt-h-prev">
        <h2 className="nt-h" id="nt-h-prev">{t("nt.p.h")}</h2>
        <PushCard title={title} message={message} />
        <div className="nt-ledger" aria-live="polite" aria-busy={loading}>
          {audience === null ? <p className="nt-fld__h">{t("nt.p.need")}</p> : null}
          {loading ? <p className="nt-fld__h">{t("nt.p.loading")}</p> : null}
          {previewError ? <Notice tone="warn" icon="alert">{t(writeErrorKey({ ok: false, status: previewError.status, fields: {}, updatedAt: "", error: previewError.error, blockers: [], deletableAfter: "" }))}</Notice> : null}
          {preview ? (
            <>
              <dl className="nt-led">
                <LedgerRow label={t("nt.led.resolved")} value={t("nt.unit.people", { n: formatNumber(preview.resolved) })} />
                <LedgerRow label={t("nt.led.inapp")} value={t("nt.unit.people", { n: formatNumber(preview.inApp) })} sub={preview.skippedInApp > 0 ? t("nt.led.skipSetting", { n: formatNumber(preview.skippedInApp) }) : undefined} />
                <LedgerRow label={t("nt.led.push")} value={t("nt.unit.people", { n: formatNumber(preview.pushMembers) })} big sub={t("nt.led.tokens", { m: formatNumber(preview.pushWithTokens), n: formatNumber(preview.pushTokens) })} />
                {preview.skippedPush > 0 ? <LedgerRow label={t("nt.led.skipPush")} value={t("nt.unit.people", { n: formatNumber(preview.skippedPush) })} /> : null}
                {preview.nightSkipped > 0 ? <LedgerRow label={t("nt.led.night")} value={t("nt.unit.people", { n: formatNumber(preview.nightSkipped) })} /> : null}
              </dl>
              {!preview.settingsAvailable ? <Notice tone="warn" icon="alert">{t("nt.p.noSettings")}</Notice> : null}
              {preview.marketing ? <Notice icon="info">{t(preview.night ? "nt.p.nightAd" : "nt.p.ad")}</Notice> : null}
              {preview.topic ? <Notice icon="info">{t("nt.p.topic")}</Notice> : null}
              {reachable === 0 ? <Notice tone="warn" icon="alert">{t("nt.err.nobody_allowed")}</Notice> : null}
            </>
          ) : null}
        </div>
        <div className="nt-acts">
          <span className="nt-acts__send">
            <button type="button" className="btn btn--primary" disabled={!ready} onClick={() => openDialog("send")}>
              {preview ? t("nt.btn.sendN", { n: formatNumber(preview.resolved) }) : t("nt.btn.send")}
            </button>
          </span>
          {canPlan ? <button type="button" className="btn btn--ghost" disabled={!ready} onClick={() => openDialog("plan")}>{t("nt.btn.plan")}</button> : null}
        </div>
        {canSend ? (
          <div className="nt-test">
            <p className="nt-test__h">{t("nt.test.h")}</p>
            <div className="nt-test__row">
              <input className="input" value={testId} onChange={(e) => setTestId(e.target.value)} placeholder={t("nt.test.ph")} aria-label={t("nt.test.ph")} maxLength={100} autoComplete="off" />
              <button type="button" className="btn btn--ghost" onClick={() => void test()} disabled={testBusy || testId.trim() === "" || title.trim() === ""} aria-busy={testBusy}>{t("nt.test.btn")}</button>
            </div>
            {testMsg ? <p className={testMsg.ok ? "nt-test__m" : "nt-test__m nt-test__m--bad"} role="status">{testMsg.text}</p> : null}
          </div>
        ) : null}
      </aside>

      <div className="nt-bbar">
        <div className="nt-bbar__tx">
          <span>{t("nt.bar.push")}</span>
          <strong>{preview ? t("nt.unit.people", { n: formatNumber(preview.pushMembers) }) : "—"}</strong>
          <span>{preview ? t("nt.bar.inapp", { n: formatNumber(preview.inApp) }) : " "}</span>
        </div>
        <button type="button" className="btn btn--primary nt-bbar__btn" disabled={!ready} onClick={() => openDialog("send")}>{t("nt.btn.send")}</button>
      </div>

      {dialog === "send" && snap ? (
        <ConfirmSendDialog input={snap.input} resolved={snap.resolved} onClose={closeDialog} onUnauthorized={leaveToLogin} onStale={() => setTick((n) => n + 1)} onDone={() => setSentOnce(true)}
          onHistory={() => { setDialog(null); resetContent(); router.replace(notificationsHref("history"), { scroll: false }); }} />
      ) : null}
      {dialog === "plan" && snap ? (
        <ScheduleDialog input={snap.input} onClose={closeDialog} onUnauthorized={leaveToLogin}
          onPlanned={() => {
            setDialog(null);
            resetContent();
            showUnitToast({ tone: "ok", message: t("nt.s.ok"), closeLabel: t("nt.close") });
            onPlanned();
          }} />
      ) : null}
    </div>
  );
}
