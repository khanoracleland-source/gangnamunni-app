"use client";

import { useEffect, useRef, useState } from "react";
import AdminShell from "../AdminShell";
import { toast } from "sonner";
import ConfirmDialog, { type ConfirmSpec } from "../hospitals/ConfirmDialog";
import { Notice, Pill, Section, ActivePill } from "../hospitals/parts";
import AdminFormDialog from "./AdminFormDialog";
import DeleteAdminDialog from "./DeleteAdminDialog";
import { ErrorScreen, ForbiddenScreen, LoadingScreen, RedirectingScreen, type ErrorKind } from "../StateScreens";
import { fetchAdmins, fetchMe, setAdminActive, type AdminAccount, type AdminListData, type Me } from "@/lib/api";
import { PHP_ADMIN_URL, PHP_BASE_URL } from "@/lib/config";
import { formatDayTime } from "@/lib/format";
import { groupAccounts, isKnownRole, passedDays } from "@/lib/admins";
import { useT } from "@/lib/locale";

type Phase = "loading" | "ready" | "forbidden" | "redirecting" | "error";
type State = { phase: Phase; errorKind: ErrorKind; me: Me | null; list: AdminListData | null };

const INITIAL: State = { phase: "loading", errorKind: "load", me: null, list: null };

export default function AdminList() {
  const t = useT();
  const [state, setState] = useState<State>(INITIAL);
  const [attempt, setAttempt] = useState(0);
  const halted = useRef(false);

  useEffect(() => {
    if (halted.current) return;
    const controller = new AbortController();
    const run = async () => {
      const [me, list] = await Promise.all([fetchMe(controller.signal), fetchAdmins(controller.signal)]);
      if (controller.signal.aborted) return;
      const statuses = [me, list].map((r) => (r.ok ? 200 : r.status));
      if (statuses.includes(401)) {
        halted.current = true;
        setState((s) => ({ ...s, phase: "redirecting" }));
        window.location.assign(PHP_ADMIN_URL);
        return;
      }
      if (statuses.includes(403)) {
        halted.current = true;
        setState({ ...INITIAL, phase: "forbidden" });
        return;
      }
      // The server says the page is switched off: show that, whatever the list request answered.
      if (me.ok && !me.data.features.admins) {
        setState({ ...INITIAL, phase: "error", errorKind: "adminsOff", me: me.data });
        return;
      }
      if (me.ok && list.ok) {
        setState({ phase: "ready", errorKind: "load", me: me.data, list: list.data });
        return;
      }
      // 404 on "who am I" means the whole panel is off; 404 on the list alone means only this page is.
      const kind: ErrorKind = !me.ok
        ? me.status === 404 ? "disabled" : me.status === 503 ? "session" : "load"
        : !list.ok && list.status === 404 ? "adminsOff" : !list.ok && list.status === 503 ? "session" : "load";
      setState({ ...INITIAL, phase: "error", errorKind: kind, me: me.ok ? me.data : null });
    };
    void run();
    return () => controller.abort();
  }, [attempt]);

  const { phase, me, list } = state;
  let screen = undefined;
  if (phase === "loading") screen = <LoadingScreen text={t("ad.loading")} />;
  else if (phase === "redirecting") screen = <RedirectingScreen />;
  else if (phase === "forbidden") screen = <ForbiddenScreen />;
  else if (phase === "error") screen = <ErrorScreen kind={state.errorKind} onRetry={() => setAttempt((n) => n + 1)} retrying={false} />;

  const groups = list ? groupAccounts(list.items) : null;

  // Writing is on and the viewer may write: a super admin everywhere, every other role on its own account.
  const canWrite = me?.features.adminsWrite === true && list !== null;
  const superViewer = list?.scope === "all";
  const [form, setForm] = useState<{ account: AdminAccount | null } | null>(null);
  const [removing, setRemoving] = useState<AdminAccount | null>(null);
  const [ask, setAsk] = useState<{ account: AdminAccount; next: boolean } | null>(null);
  const [working, setWorking] = useState(false);
  const [askError, setAskError] = useState<string | null>(null);
  const sendingActive = useRef(false);
  const reload = () => setAttempt((n) => n + 1);
  const replace = (item: AdminAccount) => {
    setState((s) => (s.list ? { ...s, list: { ...s.list, items: s.list.items.map((a) => (a.id === item.id ? item : a)) } } : s));
  };

  const confirmActive = async () => {
    if (!ask || working || sendingActive.current) return;
    sendingActive.current = true;
    setWorking(true);
    setAskError(null);
    const res = await setAdminActive(ask.account.id, ask.next);
    sendingActive.current = false;
    setWorking(false);
    if (res.ok) {
      replace(res.data.item);
      toast.success(t(ask.next ? "ad.ok.activated" : "ad.ok.deactivated"));
      setAsk(null);
      return;
    }
    if (res.status === 401) {
      halted.current = true;
      window.location.assign(PHP_ADMIN_URL);
      return;
    }
    setAskError(
      res.status === 409 && res.error === "last_super_admin" ? t("ad.act.last")
        : res.status === 403 && res.error === "self_forbidden" ? t("ad.act.self")
        : res.status === 403 ? t("ad.err.forbidden")
        : res.status === 404 ? t("ad.err.gone")
        : res.status === 503 ? t("ad.err.unavailable")
        : res.status === 0 ? t("ad.err.network")
        : t("ad.act.fail"),
    );
  };
  const askSpec: ConfirmSpec | null = ask
    ? {
        title: t(ask.next ? "ad.act.activate.title" : "ad.act.deactivate.title"),
        name: ask.account.name || ask.account.username,
        body: t(ask.next ? "ad.act.activate.body" : "ad.act.deactivate.body", { days: list?.deleteAfterDays ?? 5 }),
        confirmLabel: t(ask.next ? "ad.act.activate" : "ad.act.deactivate"),
        cancelLabel: t("h.c.cancel"),
        focus: "cancel",
      }
    : null;
  const ctl: Controls | null = canWrite
    ? {
        superViewer,
        switching: working ? ask?.account.id ?? null : null,
        onToggle: (account) => { setAskError(null); setAsk({ account, next: !account.isActive }); },
        onEdit: (account) => setForm({ account }),
        onDelete: setRemoving,
      }
    : null;

  return (
    <AdminShell
      admin={me?.admin ?? null}
      features={me?.features ?? null}
      title={t("ad.title")}
      description={t("ad.desc")}
      screen={screen}
      actions={canWrite && superViewer ? <button type="button" className="btn btn--primary" onClick={() => setForm({ account: null })}>{t("ad.add")}</button> : undefined}
    >
      {list && groups ? (
        <>
          {!canWrite ? (
            <Notice action={<a className="txtbtn" href={`${PHP_BASE_URL}admin_admins.php`}>{t("ad.readonlyLink")}</a>}>
              {t("ad.readonly")}
            </Notice>
          ) : null}
          {list.scope === "self" ? <Notice>{t("ad.scope.self")}</Notice> : null}
          {groups.ready > 0 ? <Notice tone="warn" icon="alert">{t("ad.ready.notice", { n: groups.ready, days: list.deleteAfterDays })}</Notice> : null}

          {list.scope === "all" ? (
            <>
              <p className="adm-count" role="status">{t("ad.count", { total: list.total, active: groups.active.length, inactive: groups.inactive.length })}</p>
              <Section id="sec-ad-active" title={t("ad.sec.active")} desc={t("ad.sec.activeDesc")}>
                <AccountTable items={groups.active} waiting={false} deleteAfterDays={list.deleteAfterDays} empty={t("ad.empty.active")} ctl={ctl} />
              </Section>
              <Section id="sec-ad-inactive" title={t("ad.sec.inactive")} desc={t("ad.sec.inactiveDesc", { days: list.deleteAfterDays })}>
                <AccountTable items={groups.inactive} waiting deleteAfterDays={list.deleteAfterDays} empty={t("ad.empty.inactive")} ctl={ctl} />
              </Section>
            </>
          ) : (
            <Section id="sec-ad-self" title={t("ad.sec.self")} desc={t("ad.sec.selfDesc")}>
              <AccountTable items={list.items} waiting={false} deleteAfterDays={list.deleteAfterDays} empty={t("ad.empty.active")} ctl={ctl} />
            </Section>
          )}
          <ConfirmDialog spec={askSpec} busy={working} error={askError} onConfirm={confirmActive} onClose={() => { if (!working) setAsk(null); }} />
          {form ? (
            <AdminFormDialog
              account={form.account}
              superViewer={superViewer}
              onClose={() => setForm(null)}
              onSaved={(item) => {
                if (form.account) replace(item);
                reload(); // a new account, or my own name in the header
              }}
            />
          ) : null}
          {removing ? <DeleteAdminDialog account={removing} deleteAfterDays={list.deleteAfterDays} onClose={() => setRemoving(null)} onDeleted={reload} onStale={reload} /> : null}
        </>
      ) : null}
    </AdminShell>
  );
}

type Controls = {
  superViewer: boolean;
  /** The account whose state change is being sent. */
  switching: number | null;
  onToggle: (account: AdminAccount) => void;
  onEdit: (account: AdminAccount) => void;
  onDelete: (account: AdminAccount) => void;
};

function AccountTable({ items, waiting, deleteAfterDays, empty, ctl }: { items: AdminAccount[]; waiting: boolean; deleteAfterDays: number; empty: string; ctl: Controls | null }) {
  const t = useT();
  if (items.length === 0) {
    return (
      <div className="empty">
        <p>{empty}</p>
      </div>
    );
  }
  // Below 900px each row is a card (see .tbl in globals.css); data-label carries the column name.
  return (
    <table className="tbl tbl--adm" role="table">
      <caption className="sr-only">{t("ad.list.caption")}</caption>
      <thead role="rowgroup">
        <tr role="row">
          <th role="columnheader" scope="col" className="adm__account">{t("ad.col.account")}</th>
          <th role="columnheader" scope="col" className="adm__role">{t("ad.col.role")}</th>
          <th role="columnheader" scope="col" className="adm__hospital">{t("ad.col.hospital")}</th>
          {waiting ? (
            <th role="columnheader" scope="col" className="adm__wait">{t("ad.col.wait")}</th>
          ) : (
            <th role="columnheader" scope="col" className="adm__login">{t("ad.col.lastLogin")}</th>
          )}
          <th role="columnheader" scope="col" className="adm__notif">{t("ad.col.notifications")}</th>
          <th role="columnheader" scope="col" className="adm__status">{t("ad.col.status")}</th>
          {ctl ? <th role="columnheader" scope="col" className="adm__acts">{t("ad.actions")}</th> : null}
        </tr>
      </thead>
      <tbody role="rowgroup">
        {items.map((a) => (
          <Row key={a.id} account={a} waiting={waiting} deleteAfterDays={deleteAfterDays} ctl={ctl} />
        ))}
      </tbody>
    </table>
  );
}

function Row({ account: a, waiting, deleteAfterDays, ctl }: { account: AdminAccount; waiting: boolean; deleteAfterDays: number; ctl: Controls | null }) {
  const t = useT();
  const name = a.name || a.username;
  return (
    <tr role="row" className={waiting && a.deleteReady ? "is-ready" : undefined}>
      <td role="cell" className="adm__account" data-label={t("ad.col.account")}>
        <span className="adm__name">
          {name}
          {a.isSelf ? <span className="adm__self"><Pill tone="plain">{t("ad.self")}</Pill></span> : null}
        </span>
        <span className="adm__sub">{a.username}</span>
      </td>
      <td role="cell" className="adm__role" data-label={t("ad.col.role")}>
        <Pill tone={a.role === "super_admin" ? "info" : "plain"}>{isKnownRole(a.role) ? t(`role.${a.role}`) : a.role || "—"}</Pill>
      </td>
      <td role="cell" className="adm__hospital" data-label={t("ad.col.hospital")}>{a.hospitalName || "—"}</td>
      {waiting ? (
        <td role="cell" className="adm__wait" data-label={t("ad.col.wait")}>
          <Countdown account={a} total={deleteAfterDays} />
        </td>
      ) : (
        <td role="cell" className="adm__login" data-label={t("ad.col.lastLogin")}>
          {a.lastLoginAt ? formatDayTime(a.lastLoginAt) : <span className="muted">{t("ad.never")}</span>}
        </td>
      )}
      <td role="cell" className="adm__notif" data-label={t("ad.col.notifications")}>
        {t(a.notificationsCustom ? "ad.notif.custom" : "ad.notif.count", { n: a.notifications.length })}
      </td>
      <td role="cell" className="adm__status" data-label={t("ad.col.status")}>
        {ctl && ctl.superViewer && !a.isSelf ? (
          <span className="act">
            <ActivePill active={a.isActive} />
            <button
              type="button"
              role="switch"
              className="switch"
              aria-checked={a.isActive}
              aria-label={t("ad.act.switch", { name })}
              aria-disabled={ctl.switching === a.id || undefined}
              aria-busy={ctl.switching === a.id || undefined}
              onClick={() => {
                if (ctl.switching !== a.id) ctl.onToggle(a);
              }}
            >
              <span className="switch__thumb" aria-hidden="true" />
            </button>
          </span>
        ) : (
          <ActivePill active={a.isActive} />
        )}
      </td>
      {ctl ? (
        <td role="cell" className="adm__acts">
          <span className="adm__btns">
            <button type="button" className="txtbtn" onClick={() => ctl.onEdit(a)}>{t(a.isSelf ? "ad.editMine" : "ad.edit")}</button>
            {ctl.superViewer && !a.isSelf && !a.isActive ? (
              <button type="button" className="txtbtn txtbtn--danger" onClick={() => ctl.onDelete(a)}>{t("ad.delete")}</button>
            ) : null}
          </span>
        </td>
      ) : null}
    </tr>
  );
}

/** Days passed since the account was switched off, out of the days it must wait. The text always says it; the colour only repeats it. */
function Countdown({ account: a, total }: { account: AdminAccount; total: number }) {
  const t = useT();
  if (a.inactiveDays === null) return <span className="muted">{t("ad.wait.unknown")}</span>;
  const passed = passedDays(a.inactiveDays, total);
  const ready = a.deleteReady;
  return (
    <span className="adm__count">
      <span className="adm__pips" aria-hidden="true">
        {Array.from({ length: total }, (_, i) => (
          <i key={i} className={i < passed ? (ready ? "adm__pip adm__pip--ready" : "adm__pip adm__pip--on") : "adm__pip"} />
        ))}
      </span>
      {ready ? (
        <span className="pill adm__ready">{t("ad.wait.ready", { days: a.inactiveDays })}</span>
      ) : (
        <span>{t("ad.wait.days", { days: a.inactiveDays, left: Math.max(0, total - a.inactiveDays) })}</span>
      )}
    </span>
  );
}
