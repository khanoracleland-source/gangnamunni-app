"use client";

import { useRouter, useSearchParams } from "next/navigation";
import { useCallback, useEffect, useId, useMemo, useRef, useState, type FormEvent, type ReactNode } from "react";
import { toast } from "sonner";
import AdminShell from "../AdminShell";
import Icon from "../Icon";
import ConfirmDialog, { type ConfirmSpec } from "../hospitals/ConfirmDialog";
import { Notice } from "../hospitals/parts";
import { ErrorScreen, ForbiddenScreen, LoadingScreen, RedirectingScreen, type ErrorKind } from "../StateScreens";
import { showUnitToast } from "./UnitToast";
import { addTreatmentUnit, fetchMe, fetchTreatmentUnits, removeTreatmentUnit, type Me, type TreatmentUnit, type TreatmentUnitsData } from "@/lib/api";
import { PHP_ADMIN_URL } from "@/lib/config";
import { formatNumber } from "@/lib/format";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";
import { UNIT_NAME_MAX, UNIT_SEARCH_MAX, addToastKey, filterUnits, parseUnitsQuery, unitFailureKey, unitNameProblem, unitsHref } from "@/lib/treatmentUnits";

type Phase = "loading" | "ready" | "forbidden" | "redirecting" | "error";
type State = { phase: Phase; errorKind: ErrorKind; me: Me | null; data: TreatmentUnitsData | null };
const INITIAL: State = { phase: "loading", errorKind: "load", me: null, data: null };

/** The price units: the list of units the price forms offer, add / remove (a removed unit can be added again), search. Super admin only. */
export default function TreatmentUnitsPage() {
  const t = useT();
  const router = useRouter();
  const searchParams = useSearchParams();
  const [state, setState] = useState<State>(INITIAL);
  const [attempt, setAttempt] = useState(0);
  // 401 / 403 are final for this page view: stop asking.
  const halted = useRef(false);

  useEffect(() => {
    if (halted.current) return;
    const controller = new AbortController();
    const run = async () => {
      const [me, data] = await Promise.all([fetchMe(controller.signal), fetchTreatmentUnits(controller.signal)]);
      if (controller.signal.aborted) return;
      const statuses = [me, data].map((r) => (r.ok ? 200 : r.status));
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
      if (me.ok && !me.data.features.treatmentUnits) {
        setState({ ...INITIAL, phase: "error", errorKind: "treatmentUnitsOff", me: me.data });
        return;
      }
      if (me.ok && data.ok) {
        setState({ phase: "ready", errorKind: "load", me: me.data, data: data.data });
        return;
      }
      // 404 on "who am I" means the whole panel is off; 404 on the list alone means only this page is.
      const kind: ErrorKind = !me.ok
        ? me.status === 404 ? "disabled" : me.status === 503 ? "session" : "load"
        : !data.ok && data.status === 404 ? "treatmentUnitsOff" : !data.ok && data.status === 503 ? "session" : "load";
      setState({ ...INITIAL, phase: "error", errorKind: kind, me: me.ok ? me.data : null });
    };
    void run();
    return () => controller.abort();
  }, [attempt]);

  const retry = () => setAttempt((n) => n + 1);
  // After a change the list is read again, quietly: the screen stays, only the data is replaced (the server keeps the order).
  const refresh = useCallback(async () => {
    const res = await fetchTreatmentUnits();
    if (res.ok) setState((s) => (s.phase === "ready" ? { ...s, data: res.data } : s));
    else toast.error(t("tu.err.network"));
  }, [t]);

  const { phase, me, data } = state;
  let screen: ReactNode = null;
  if (phase === "loading") screen = <LoadingScreen text={t("tu.loading")} />;
  else if (phase === "redirecting") screen = <RedirectingScreen />;
  else if (phase === "forbidden") screen = <ForbiddenScreen variant="super" />;
  else if (phase === "error") screen = <ErrorScreen kind={state.errorKind} onRetry={retry} retrying={false} />;

  const writable = me?.features.treatmentUnitsWrite === true && data?.tableReady === true;
  const initialSearch = useMemo(() => parseUnitsQuery(searchParams), [searchParams]);

  return (
    <AdminShell admin={me?.admin ?? null} features={me?.features ?? null} title={t("tu.title")} description={t("tu.subtitle")} screen={screen}>
      {data ? (
        <div className="units">
          {!data.tableReady ? (
            <Notice tone="warn" icon="alert">{t("tu.err.tableNotReady")}</Notice>
          ) : me?.features.treatmentUnitsWrite !== true ? (
            <Notice tone="warn" icon="alert">{t("tu.readOnly")}</Notice>
          ) : null}
          <UnitsCard
            units={data.items}
            canWrite={writable}
            initialSearch={initialSearch}
            onSearch={(q) => router.replace(unitsHref(q), { scroll: false })}
            refresh={refresh}
          />
        </div>
      ) : null}
    </AdminShell>
  );
}

function UnitsCard({ units, canWrite, initialSearch, onSearch, refresh }: { units: TreatmentUnit[]; canWrite: boolean; initialSearch: string; onSearch: (q: string) => void; refresh: () => Promise<void> }) {
  const t = useT();
  const ids = useId();
  const nameRef = useRef<HTMLInputElement>(null);
  const searchRef = useRef<HTMLInputElement>(null);
  const [name, setName] = useState("");
  const [problem, setProblem] = useState<MessageKey | null>(null);
  const [adding, setAdding] = useState(false);
  const [search, setSearch] = useState(initialSearch);
  const [deleting, setDeleting] = useState<TreatmentUnit | null>(null);
  const [removing, setRemoving] = useState(false);
  const [removeError, setRemoveError] = useState<string | null>(null);
  // Flags that a state value cannot give: a second Enter or click before the first answer must not send a second request.
  const addingRef = useRef(false);
  const removingRef = useRef(false);

  const visible = useMemo(() => filterUnits(units, search), [units, search]);
  const closeLabel = t("m.close");

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    if (addingRef.current) return;
    const found = unitNameProblem(name);
    if (found) {
      setProblem(found === "required" ? "tu.err.required" : "tu.err.invalid");
      nameRef.current?.focus();
      return;
    }
    addingRef.current = true;
    setAdding(true);
    setProblem(null);
    const res = await addTreatmentUnit(name.trim());
    addingRef.current = false;
    setAdding(false);
    if (res.ok) {
      const { unit, status } = res.data;
      showUnitToast({ tone: status === "exists" ? "info" : "ok", icon: status === "restored" ? "refresh" : undefined, message: t(addToastKey(status), { name: unit.name }), closeLabel });
      setName("");
      nameRef.current?.focus();
      await refresh();
      return;
    }
    const key = unitFailureKey(res);
    if (res.status === 400) {
      setProblem(key);
      nameRef.current?.focus();
    } else {
      showUnitToast({ tone: "error", message: t(key), closeLabel });
    }
  };

  const undo = async (unit: TreatmentUnit) => {
    const res = await addTreatmentUnit(unit.name);
    if (res.ok) showUnitToast({ tone: "ok", icon: "refresh", message: t(addToastKey(res.data.status), { name: res.data.unit.name }), closeLabel });
    else showUnitToast({ tone: "error", message: t(unitFailureKey(res)), closeLabel });
    await refresh();
  };

  const remove = async () => {
    if (!deleting || removingRef.current) return;
    const unit = deleting;
    removingRef.current = true;
    setRemoving(true);
    setRemoveError(null);
    const res = await removeTreatmentUnit(unit.id);
    removingRef.current = false;
    setRemoving(false);
    if (res.ok) {
      setDeleting(null);
      showUnitToast({ tone: "ok", message: t("tu.toast.removed", { name: unit.name }), closeLabel, action: { label: t("tu.toast.undo"), onClick: () => void undo(unit) } });
      await refresh();
      return;
    }
    setRemoveError(t(unitFailureKey(res)));
    // The unit may be gone already: the list should show the truth behind the message.
    if (res.status === 404) await refresh();
  };

  const clearSearch = () => {
    setSearch("");
    onSearch("");
    searchRef.current?.focus();
  };
  const goAdd = () => {
    nameRef.current?.scrollIntoView({ block: "center" });
    nameRef.current?.focus();
  };

  const confirmSpec: ConfirmSpec | null = deleting
    ? { title: t("tu.dialog.title"), name: deleting.name, body: t("tu.dialog.body"), confirmLabel: t("tu.dialog.confirm"), cancelLabel: t("tu.dialog.cancel"), busyLabel: t("tu.dialog.busy"), confirmTone: "reject", focus: "cancel" }
    : null;

  const hintId = `${ids}-hint`;
  const errId = `${ids}-err`;

  return (
    <section className="units__card">
      {canWrite ? (
        <div className="units__add">
          <div>
            <h2 className="units__h2"><label htmlFor={`${ids}-name`}>{t("tu.add.title")}</label></h2>
            <p className="units__sub">{t("tu.add.sub")}</p>
          </div>
          <form onSubmit={(e) => void submit(e)} noValidate>
            <div className="units__row">
              <input
                ref={nameRef}
                id={`${ids}-name`}
                className="input"
                value={name}
                maxLength={UNIT_NAME_MAX}
                placeholder={t("tu.add.placeholder")}
                autoComplete="off"
                aria-invalid={problem ? "true" : undefined}
                aria-describedby={problem ? errId : hintId}
                readOnly={adding}
                aria-busy={adding || undefined}
                onChange={(e) => {
                  setName(e.target.value);
                  if (problem) setProblem(null);
                }}
              />
              <button type="submit" className="btn btn--primary" disabled={adding}>
                <Icon name="plus" size={18} />
                {adding ? t("tu.add.busy") : t("tu.add.button")}
              </button>
            </div>
            {problem ? (
              <p id={errId} className="units__err" role="alert">
                <Icon name="alert" size={16} />
                <span>{t(problem)}</span>
              </p>
            ) : (
              <p id={hintId} className="units__hint">{t("tu.add.help")}</p>
            )}
          </form>
        </div>
      ) : null}

      <div className="units__lib">
        <div className="units__tool">
          <h2 className="units__title">
            {t("tu.list.title")} <span className="units__cnt">{formatNumber(units.length)}</span>
          </h2>
          <div className="units__search">
            <label className="sr-only" htmlFor={`${ids}-search`}>{t("tu.search.label")}</label>
            <Icon name="search" size={20} />
            <input
              ref={searchRef}
              id={`${ids}-search`}
              type="search"
              className="input"
              value={search}
              maxLength={UNIT_SEARCH_MAX}
              placeholder={t("tu.search.placeholder")}
              autoComplete="off"
              disabled={units.length === 0}
              onChange={(e) => {
                setSearch(e.target.value);
                onSearch(e.target.value);
              }}
            />
          </div>
        </div>

        {visible.length > 0 ? (
          <ul className="units__grid" aria-label={t("tu.list.title")}>
            {visible.map((u) => (
              <li key={u.id} className="units__item">
                <span className="units__name" translate="no">{u.name}</span>
                {canWrite ? (
                  <button type="button" className="ibtn units__del" aria-label={t("tu.delete.aria", { name: u.name })} title={t("tu.delete.aria", { name: u.name })} onClick={() => { setRemoveError(null); setDeleting(u); }}>
                    <Icon name="trash" size={20} />
                  </button>
                ) : null}
              </li>
            ))}
          </ul>
        ) : (
          <div className="units__empty" role="status">
            <Icon name={units.length === 0 ? "tag" : "search"} size={28} />
            <p>{units.length === 0 ? t(canWrite ? "tu.empty" : "tu.emptyReadOnly") : t("tu.noResults")}</p>
            {units.length === 0 ? (
              canWrite ? <button type="button" className="btn btn--ghost" onClick={goAdd}><Icon name="plus" size={18} />{t("tu.add.title")}</button> : null
            ) : (
              <button type="button" className="btn btn--ghost" onClick={clearSearch}>{t("tu.search.clear")}</button>
            )}
          </div>
        )}
      </div>

      <div className="units__foot">
        <Icon name="info" size={16} />
        <span>{t("tu.footer")}</span>
      </div>

      <ConfirmDialog
        className="dialog--unit"
        spec={confirmSpec}
        busy={removing}
        error={removeError}
        onConfirm={() => void remove()}
        onClose={() => {
          if (!removing) {
            setDeleting(null);
            setRemoveError(null);
          }
        }}
      />
    </section>
  );
}
