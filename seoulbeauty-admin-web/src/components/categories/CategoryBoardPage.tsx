"use client";

import Link from "next/link";
import { useCallback, useEffect, useId, useMemo, useRef, useState, useSyncExternalStore, type FormEvent, type ReactNode } from "react";
import AdminShell from "../AdminShell";
import Icon from "../Icon";
import ConfirmDialog from "../hospitals/ConfirmDialog";
import { ErrorScreen, ForbiddenScreen, LoadingScreen, RedirectingScreen, type ErrorKind } from "../StateScreens";
import { showUnitToast } from "../treatment-units/UnitToast";
import { failureKey } from "../treatments/writeErrors";
import AssignDialog from "./AssignDialog";
import CategoryPanel from "./CategoryPanel";
import CategoryRow from "./CategoryRow";
import NameDialog from "./NameDialog";
import {
  assignBoardTreatments,
  createCategory,
  deleteBoardCategory,
  detachBoardTreatment,
  fetchCategoryBoard,
  fetchMe,
  reorderCategories,
  setCategoryActive,
  type BoardCategory,
  type BoardData,
  type Me,
  type WriteFailure,
  type WriteResult,
} from "@/lib/api";
import { CATEGORY_NAME_MAX, checkCategoryName, homeRanks, movedIds, nextCategoryOrder, type NameProblem } from "@/lib/categoryBoard";
import { PHP_ADMIN_URL } from "@/lib/config";
import { formatNumber } from "@/lib/format";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";

type Phase = "loading" | "ready" | "forbidden" | "redirecting" | "error";
type State = { phase: Phase; errorKind: ErrorKind; me: Me | null; data: BoardData | null };
const INITIAL: State = { phase: "loading", errorKind: "load", me: null, data: null };
const PROBLEMS: Record<NameProblem, MessageKey> = { empty: "cb.add.empty", long: "cb.add.long", dup: "cb.add.dup" };

const WIDE = "(min-width: 900px)";
const subscribeWide = (cb: () => void) => {
  const mq = window.matchMedia(WIDE);
  mq.addEventListener("change", cb);
  return () => mq.removeEventListener("change", cb);
};
const readWide = () => window.matchMedia(WIDE).matches;
const serverWide = () => false;

/** The categories of the app's home menu: their order, and which treatments each one holds. Super admin only; editing needs the write switches. */
export default function CategoryBoardPage() {
  const t = useT();
  const uid = useId();
  const [state, setState] = useState<State>(INITIAL);
  const [attempt, setAttempt] = useState(0);
  const halted = useRef(false);
  const [openId, setOpenId] = useState(0);
  const wide = useSyncExternalStore(subscribeWide, readWide, serverWide);
  const [busy, setBusy] = useState(false);
  const [live, setLive] = useState("");
  const [conflict, setConflict] = useState(false);
  const [assigning, setAssigning] = useState<BoardCategory | null>(null);
  const [renaming, setRenaming] = useState<BoardCategory | null>(null);
  const [deleting, setDeleting] = useState<BoardCategory | null>(null);
  const [addName, setAddName] = useState("");
  const [addError, setAddError] = useState<string | null>(null);
  const pendingFocus = useRef<string | null>(null);
  const lock = useRef(false);
  const noneRef = useRef<HTMLElement>(null);
  const listRef = useRef<HTMLHeadingElement>(null);
  const addRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (halted.current) return;
    const controller = new AbortController();
    const run = async () => {
      const [me, board] = await Promise.all([fetchMe(controller.signal), fetchCategoryBoard(controller.signal)]);
      if (controller.signal.aborted) return;
      const statuses = [me, board].map((r) => (r.ok ? 200 : r.status));
      if (statuses.includes(401)) {
        halted.current = true;
        setState((s) => ({ ...s, phase: "redirecting" }));
        window.location.assign(PHP_ADMIN_URL);
        return;
      }
      if (statuses.includes(403) || (me.ok && me.data.admin.scope !== "all")) {
        halted.current = true;
        setState({ ...INITIAL, phase: "forbidden" });
        return;
      }
      if (me.ok && !me.data.features.categoryBoard) {
        setState({ ...INITIAL, phase: "error", errorKind: "categoryBoardOff", me: me.data });
        return;
      }
      if (me.ok && board.ok) {
        setState({ phase: "ready", errorKind: "load", me: me.data, data: board.data });
        return;
      }
      const kind: ErrorKind = !me.ok
        ? me.status === 404 ? "disabled" : me.status === 503 ? "session" : "load"
        : !board.ok && board.status === 404 ? "categoryBoardOff" : !board.ok && board.status === 503 ? "session" : "load";
      setState({ ...INITIAL, phase: "error", errorKind: kind, me: me.ok ? me.data : null });
    };
    void run();
    return () => controller.abort();
  }, [attempt]);

  const { phase, me, data } = state;
  const canWrite = me?.features.categoryBoardWrite === true;
  const ranks = useMemo(() => (data ? homeRanks(data.categories) : new Map<number, number>()), [data]);
  const closeLabel = t("m.close");
  const toast = useCallback((tone: "ok" | "error", message: string) => showUnitToast({ tone, message, closeLabel }), [closeLabel]);

  // Moves focus where a finished change wants it, once the new list is on screen.
  useEffect(() => {
    const want = pendingFocus.current;
    if (!want) return;
    pendingFocus.current = null;
    const el = document.querySelector<HTMLElement>(`[data-cb="${want}"]`);
    if (el && !(el instanceof HTMLButtonElement && el.disabled)) el.focus();
    else if (want.startsWith("up-") || want.startsWith("down-")) {
      const id = want.slice(want.indexOf("-") + 1);
      document.querySelector<HTMLElement>(`[data-cb="${want.startsWith("up-") ? "down" : "up"}-${id}"]`)?.focus();
    } else if (want.startsWith("un-")) listRef.current?.focus();
  }, [data]);

  const reload = useCallback(async (): Promise<boolean> => {
    const res = await fetchCategoryBoard();
    if (res.ok) {
      setState((s) => ({ ...s, data: res.data }));
      return true;
    }
    pendingFocus.current = null;
    toast("error", t(res.status === 0 ? "tmw.err.network" : "tmw.err.generic"));
    return false;
  }, [t, toast]);

  /** A refused change: say why, and read the list again when somebody else may have changed it. */
  const refused = useCallback(
    async (res: WriteFailure) => {
      if (res.status === 503 && res.error === "uncategorized_unsupported") toast("error", t("cb.err.unsupported"));
      else if (res.status === 404 || (res.status === 409 && res.error !== "duplicate")) {
        setConflict(true);
        toast("error", t("cb.err.changed"));
      } else toast("error", t(failureKey(res)));
      if (res.status === 404 || res.status === 409) await reload();
    },
    [reload, t, toast],
  );

  /** One change at a time: the list is locked while it runs. */
  const run = useCallback(
    async <T,>(action: () => Promise<WriteResult<T>>, done: (data: T) => void | Promise<void>): Promise<boolean> => {
      if (lock.current) return false;
      lock.current = true;
      setBusy(true);
      setConflict(false);
      try {
        const res = await action();
        if (res.ok) {
          await done(res.data);
          return true;
        }
        await refused(res);
        return false;
      } finally {
        lock.current = false;
        setBusy(false);
      }
    },
    [refused],
  );

  const show = (board: BoardData) => setState((s) => ({ ...s, data: board }));

  const move = (cat: BoardCategory, dir: -1 | 1) => {
    if (!data) return;
    const ids = movedIds(data.categories, cat.id, dir);
    if (!ids) return;
    pendingFocus.current = `${dir < 0 ? "up" : "down"}-${cat.id}`;
    void run(
      async () => {
        const res = await reorderCategories(ids);
        return res.ok ? ({ ok: true, data: null } as WriteResult<null>) : res;
      },
      async () => {
        await reload();
      },
    ).then((ok) => {
      if (ok) {
        const order = ids.filter((id) => data.categories.find((c) => c.id === id)?.isActive);
        const place = order.indexOf(cat.id) + 1;
        setLive(cat.isActive && place > 0 ? t("cb.moved", { name: cat.name, n: place }) : t("cb.movedHidden", { name: cat.name }));
      } else pendingFocus.current = null;
    });
  };

  const toggleActive = (cat: BoardCategory, next: boolean) =>
    void run(
      async () => {
        const res = await setCategoryActive(cat.id, next);
        return res.ok ? ({ ok: true, data: null } as WriteResult<null>) : res;
      },
      async () => {
        await reload();
      },
    );

  const removeChip = (cat: BoardCategory, treatmentId: number) => {
    const at = cat.treatments.findIndex((x) => x.id === treatmentId);
    const next = cat.treatments[at + 1] ?? cat.treatments[at - 1];
    const name = cat.treatments[at]?.name ?? "";
    pendingFocus.current = next ? `chip-${next.id}` : `add-${cat.id}`;
    void run(() => detachBoardTreatment(cat.id, treatmentId), (board) => {
      show(board);
      toast("ok", t("cb.ok.removed", { name }));
    }).then((ok) => {
      if (!ok) pendingFocus.current = null;
    });
  };

  const assignOne = (treatmentId: number, name: string, target: BoardCategory) => {
    const list = data?.uncategorized ?? [];
    const at = list.findIndex((x) => x.id === treatmentId);
    const next = list[at + 1] ?? list[at - 1];
    pendingFocus.current = next ? `un-${next.id}` : "un-head";
    void run(() => assignBoardTreatments(target.id, [treatmentId]), (board) => {
      show(board);
      setLive(t("cb.ok.assigned", { n: 1 }) + ` ${name} → ${target.name}`);
      toast("ok", t("cb.ok.assigned", { n: 1 }));
    }).then((ok) => {
      if (!ok) pendingFocus.current = null;
    });
  };

  const addCategory = async (e: FormEvent) => {
    e.preventDefault();
    if (!data || busy) return;
    const problem = checkCategoryName(addName, data.categories);
    if (problem) {
      setAddError(t(PROBLEMS[problem]));
      addRef.current?.focus();
      return;
    }
    const ok = await run(
      async () => {
        const res = await createCategory({ name: addName.trim(), icon: "", sortOrder: nextCategoryOrder(data.categories) });
        return res.ok ? ({ ok: true, data: null } as WriteResult<null>) : res;
      },
      async () => {
        setAddName("");
        setAddError(null);
        toast("ok", t("cb.ok.created"));
        await reload();
      },
    );
    if (!ok) addRef.current?.focus();
  };

  const confirmDelete = async () => {
    if (!deleting) return;
    const target = deleting;
    const ok = await run(() => deleteBoardCategory(target.id), (res) => {
      show({ categories: res.categories, uncategorized: res.uncategorized });
      toast("ok", t("cb.ok.deleted", { n: formatNumber(res.detached) }));
      setOpenId((id) => (id === target.id ? 0 : id));
      pendingFocus.current = null;
    });
    setDeleting(null);
    if (ok) window.setTimeout(() => (noneRef.current ?? listRef.current)?.focus(), 0);
  };

  let screen: ReactNode = null;
  if (phase === "loading") screen = <LoadingScreen text={t("cb.loading")} />;
  else if (phase === "redirecting") screen = <RedirectingScreen />;
  else if (phase === "forbidden") screen = <ForbiddenScreen variant="hospitals" />;
  else if (phase === "error") screen = <ErrorScreen kind={state.errorKind} onRetry={() => setAttempt((n) => n + 1)} retrying={false} />;

  const categories = data?.categories ?? [];
  const uncategorized = data?.uncategorized ?? [];
  const treatmentTotal = categories.reduce((n, c) => n + c.treatments.length, 0) + uncategorized.length;
  const activeCount = categories.filter((c) => c.isActive).length;
  // A wide screen always shows one category in the side panel (the first until one is picked); a narrow one opens at most one row.
  const selectedId = wide ? (categories.some((c) => c.id === openId) ? openId : (categories[0]?.id ?? 0)) : openId;
  const selectedCategory = categories.find((c) => c.id === selectedId) ?? null;
  const panel = (cat: BoardCategory, inline: boolean) => (
    <CategoryPanel
      key={cat.id}
      category={cat}
      rank={ranks.get(cat.id) ?? null}
      inline={inline}
      canWrite={canWrite}
      busy={busy}
      onAdd={() => setAssigning(cat)}
      onRename={() => setRenaming(cat)}
      onDelete={() => setDeleting(cat)}
      onRemove={(tid) => removeChip(cat, tid)}
    />
  );

  return (
    <AdminShell admin={me?.admin ?? null} features={me?.features ?? null} title={t("cb.title")} description={t("cb.desc")} screen={screen}>
      {phase === "ready" && data ? (
        <div className="cb-wrap">
          <div className="cb-top">
            <ul className="cb-sum" role="group" aria-label={t("cb.stats.group")}>
              <li>{t("cb.stat.categories")} <b>{formatNumber(categories.length)}</b></li>
              <li>{t("cb.stat.active")} <b>{formatNumber(activeCount)}</b></li>
              <li>{t("cb.stat.treatments")} <b>{formatNumber(treatmentTotal)}</b></li>
              <li>
                {uncategorized.length > 0 ? (
                  <button
                    type="button"
                    className="cb-sum__none"
                    aria-label={t("cb.stat.noneAria", { n: formatNumber(uncategorized.length) })}
                    onClick={() => {
                      noneRef.current?.scrollIntoView({ block: "start", behavior: "smooth" });
                      noneRef.current?.focus({ preventScroll: true });
                    }}
                  >
                    <span className="stat__dot" aria-hidden="true" />{t("cb.stat.none")} <b>{formatNumber(uncategorized.length)}</b>
                  </button>
                ) : (
                  <>{t("cb.stat.none")} <b>0</b></>
                )}
              </li>
            </ul>
            <form className="cb-add" onSubmit={addCategory} noValidate>
              <label className="sr-only" htmlFor={`${uid}-add`}>{t("cb.add.label")}</label>
              <input
                id={`${uid}-add`}
                ref={addRef}
                className="input"
                value={addName}
                placeholder={t("cb.add.placeholder")}
                maxLength={CATEGORY_NAME_MAX * 2}
                autoComplete="off"
                disabled={!canWrite || busy}
                aria-invalid={addError ? true : undefined}
                aria-describedby={`${uid}-add-m`}
                onChange={(e) => {
                  setAddName(e.target.value);
                  setAddError(null);
                }}
              />
              <button type="submit" className="btn btn--primary" disabled={!canWrite || busy}>
                <Icon name="plus" size={16} /> {t("cb.add.btn")}
              </button>
              <p id={`${uid}-add-m`} className={addError ? "field__error" : "sr-only"} role={addError ? "alert" : undefined}>{addError ?? t("cb.add.hint")}</p>
            </form>
          </div>

          {!canWrite ? (
            <div className="notice" role="note">
              <Icon name="lock" size={18} />
              <div className="notice__text">
                <span className="notice__title">{t("cb.ro.title")}</span>
                <span className="notice__body">{t("cb.ro.body")}</span>
              </div>
            </div>
          ) : null}

          {conflict ? (
            <div className="cb-notice-err" role="alert">
              <Icon name="alert" size={18} />
              <div className="cb-notice-err__t">
                <strong>{t("cb.err.conflict")}</strong>
                <span>{t("cb.err.conflictBody")}</span>
              </div>
              <button type="button" className="btn btn--ghost" onClick={() => { setConflict(false); void reload(); }}>
                <Icon name="refresh" size={16} /> {t("cb.err.reload")}
              </button>
            </div>
          ) : null}

          <section className="section" aria-labelledby={`${uid}-list`}>
            <h2 id={`${uid}-list`} ref={listRef} tabIndex={-1} className="sr-only">{t("cb.section")}</h2>
            {categories.length === 0 ? (
              <div className="empty">
                <p>{t("cb.empty.title")}</p>
                {canWrite ? <button type="button" className="btn btn--primary" onClick={() => addRef.current?.focus()}>{t("cb.empty.cta")}</button> : null}
              </div>
            ) : (
              <div className={busy ? "cb-split is-busy" : "cb-split"}>
                <div className="cb-lcol">
                  <div className="cb-cols" aria-hidden="true">
                    <span>{t("cb.col.order")}</span>
                    <span className="cb-cols__name">{t("cb.stat.categories")}</span>
                    <span className="cb-cols__n">{t("cb.stat.treatments")}</span>
                    <span className="cb-cols__s">{t("cb.active")}</span>
                  </div>
                  <ul className="cb-list">
                    {categories.map((cat, i) => {
                      const selected = selectedId === cat.id;
                      return (
                        <CategoryRow
                          key={cat.id}
                          category={cat}
                          rank={ranks.get(cat.id) ?? null}
                          index={i}
                          total={categories.length}
                          selected={selected}
                          wide={wide}
                          canWrite={canWrite}
                          busy={busy}
                          onSelect={() => (wide ? setOpenId(cat.id) : setOpenId((id) => (id === cat.id ? 0 : cat.id)))}
                          onMove={(dir) => move(cat, dir)}
                          onActive={(next) => toggleActive(cat, next)}
                        >
                          {!wide && selected ? panel(cat, true) : null}
                        </CategoryRow>
                      );
                    })}
                  </ul>
                </div>
                {wide && selectedCategory ? <aside className="cb-aside">{panel(selectedCategory, false)}</aside> : null}
              </div>
            )}
          </section>

          {uncategorized.length > 0 ? (
            <section className="cb-un" ref={noneRef} tabIndex={-1} aria-labelledby={`${uid}-un`} aria-describedby={`${uid}-un-d`} data-cb="un-head">
              <header className="cb-un__head">
                <h2 id={`${uid}-un`}>{t("cb.un.title")} <b>{formatNumber(uncategorized.length)}</b></h2>
                <p id={`${uid}-un-d`}>{t("cb.un.desc")}</p>
              </header>
              <ul>
                {uncategorized.map((tr) => (
                  <li key={tr.id}>
                    <span className="cb-un__n" id={`${uid}-u${tr.id}`} translate="no">
                      {tr.name}
                      {!tr.isActive ? <span className="pill pill--off">{t("cb.hidden")}</span> : null}
                    </span>
                    <select
                      className="sel sel--field"
                      data-cb={`un-${tr.id}`}
                      value=""
                      disabled={!canWrite || busy || categories.length === 0}
                      aria-label={t("cb.un.selectFor", { name: tr.name })}
                      aria-describedby={`${uid}-un-d`}
                      onChange={(e) => {
                        const target = categories.find((c) => c.id === Number(e.target.value));
                        if (target) assignOne(tr.id, tr.name, target);
                      }}
                    >
                      <option value="">{t("cb.un.select")}</option>
                      {categories.map((c) => (
                        <option key={c.id} value={c.id}>{c.name}</option>
                      ))}
                    </select>
                  </li>
                ))}
              </ul>
            </section>
          ) : null}

          <p className="sr-only" role="status" aria-live="polite">{live}</p>
          <p className="cb-foot">
            <Link href="/treatments" className="txtbtn">{t("cb.foot.master")}</Link>
          </p>
        </div>
      ) : null}

      {assigning && data ? (
        <AssignDialog
          category={assigning}
          data={data}
          onClose={() => setAssigning(null)}
          onFailed={(res) => {
            setAssigning(null);
            void refused(res);
          }}
          onSaved={(board, n) => {
            show(board);
            setAssigning(null);
            toast("ok", t("cb.ok.assigned", { n: formatNumber(n) }));
          }}
        />
      ) : null}
      {renaming && data ? (
        <NameDialog
          category={renaming}
          all={data.categories}
          onClose={() => setRenaming(null)}
          onFailed={(res) => {
            setRenaming(null);
            void refused(res);
          }}
          onSaved={() => {
            setRenaming(null);
            toast("ok", t("cb.ok.renamed"));
            void reload();
          }}
        />
      ) : null}
      <ConfirmDialog
        spec={
          deleting
            ? {
                title: t("cb.del.title"),
                name: deleting.name,
                body: deleting.treatments.length > 0 ? t("cb.del.body", { n: formatNumber(deleting.treatments.length) }) : t("cb.del.bodyEmpty"),
                note: t("cb.del.note"),
                confirmLabel: t("tmw.del.confirm"),
                cancelLabel: t("tmw.cancel"),
                focus: "cancel",
                confirmTone: "reject",
              }
            : null
        }
        busy={busy}
        onConfirm={() => void confirmDelete()}
        onClose={() => {
          if (!busy) setDeleting(null);
        }}
      />
    </AdminShell>
  );
}
