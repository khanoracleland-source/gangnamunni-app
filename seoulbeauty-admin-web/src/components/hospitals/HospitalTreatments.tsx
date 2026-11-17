"use client";

import { useEffect, useRef, useState, type KeyboardEvent, type ReactNode } from "react";
import Icon from "../Icon";
import ConfirmDialog, { type ConfirmSpec } from "./ConfirmDialog";
import HospitalTreatmentPrices from "./HospitalTreatmentPrices";
import { Pill } from "./parts";
import {
  assignHospitalTreatments,
  hospitalTreatments,
  patchHospitalTreatment,
  removeHospitalTreatment,
  type HospitalTreatment,
  type HospitalTreatmentSet,
  type WriteFailure,
} from "@/lib/api";
import {
  ALL_CATEGORIES,
  ASSIGN_LIMIT,
  MIN_AGE_MAX,
  categoryPills,
  minAgeText,
  notePreview,
  parseMinAge,
  priceLabel,
  removalImpact,
  treatmentFailure,
  visibleAvailable,
  type TreatmentAction,
} from "@/lib/hospitalTreatments";
import { canEditPrices } from "@/lib/hospitalTreatmentPrices";
import { formatNumber } from "@/lib/format";
import { legacyTreatmentsUrl } from "@/lib/hospitals";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";

type Status = { tone: "ok" | "err"; text: string } | null;
/** How many price options a row shows before "+N". */
const PRICE_PREVIEW = 2;

type Props = {
  hospitalId: number;
  /** The card has been opened at least once: the treatments are read then, not before. */
  opened: boolean;
  /** The number of assigned treatments was read or changed. */
  onChange: (info: { count: number }) => void;
  /** The server says the admin session ended. */
  onUnauthorized: () => void;
};

/** The set after a change: the flags the change answer does not carry stay as they were. */
function merged(prev: HospitalTreatmentSet, next: HospitalTreatmentSet): HospitalTreatmentSet {
  return {
    ...next,
    canManage: next.canManage ?? prev.canManage,
    minAgeSupported: next.minAgeSupported ?? prev.minAgeSupported,
    removeEnabled: next.removeEnabled ?? prev.removeEnabled,
    pricesEnabled: next.pricesEnabled ?? prev.pricesEnabled,
    priceUnits: next.priceUnits ?? prev.priceUnits,
  };
}

const withItems = (set: HospitalTreatmentSet, items: HospitalTreatment[]): HospitalTreatmentSet => ({
  ...set,
  items,
  count: items.length,
  promotedCount: items.filter((item) => item.isPromoted).length,
});

/**
 * The body of the "add / manage treatments" card: the assigned treatments (promotion star, minimum age, unassign),
 * and a panel to assign more. Every action is its own request; the hospital form's own changes are never touched.
 * Prices have their own window (when switched on); images and pre-visit questions stay in the old panel and are only counted here.
 */
export default function HospitalTreatments({ hospitalId, opened, onChange, onUnauthorized }: Props) {
  const t = useT();
  // null = not answered yet.
  const [load, setLoad] = useState<{ set: HospitalTreatmentSet } | { failed: true } | null>(null);
  const [tries, setTries] = useState(0);
  const [status, setStatus] = useState<Status>(null);
  // Rows with a request in flight; their buttons wait.
  const [pending, setPending] = useState<Record<number, true>>({});
  const [ages, setAges] = useState<Record<number, string>>({});
  const [ageErrors, setAgeErrors] = useState<Record<number, MessageKey>>({});
  // Rows whose price options are all shown.
  const [expanded, setExpanded] = useState<Record<number, boolean>>({});

  const [addOpen, setAddOpen] = useState(false);
  const [category, setCategory] = useState(ALL_CATEGORIES);
  const [query, setQuery] = useState("");
  const [selected, setSelected] = useState<Set<number>>(() => new Set());
  const [assigning, setAssigning] = useState(false);
  const [addError, setAddError] = useState<MessageKey | null>(null);

  // The treatment whose prices are being edited.
  const [priceFor, setPriceFor] = useState<HospitalTreatment | null>(null);

  const [confirmFor, setConfirmFor] = useState<HospitalTreatment | null>(null);
  const [removing, setRemoving] = useState(false);
  const [removeError, setRemoveError] = useState<MessageKey | null>(null);
  const selectAllRef = useRef<HTMLInputElement>(null);

  const onChangeRef = useRef(onChange);
  const onUnauthorizedRef = useRef(onUnauthorized);
  useEffect(() => {
    onChangeRef.current = onChange;
    onUnauthorizedRef.current = onUnauthorized;
  });

  useEffect(() => {
    if (!opened) return;
    const controller = new AbortController();
    void hospitalTreatments(hospitalId, controller.signal).then((res) => {
      if (controller.signal.aborted) return;
      if (res.ok) setLoad({ set: res.data });
      else if (res.status === 401) onUnauthorizedRef.current();
      else setLoad({ failed: true });
    });
    return () => controller.abort();
  }, [opened, hospitalId, tries]);

  const set = load && "set" in load ? load.set : null;
  const count = set?.count;
  useEffect(() => {
    if (count !== undefined) onChangeRef.current({ count });
  }, [count]);

  const visible = set ? visibleAvailable(set.available, category, query) : [];
  const visibleSelected = visible.filter((item) => selected.has(item.id)).length;
  useEffect(() => {
    if (selectAllRef.current) selectAllRef.current.indeterminate = visibleSelected > 0 && visibleSelected < visible.length;
  }, [visibleSelected, visible.length]);

  const retry = () => {
    setLoad(null);
    setTries((n) => n + 1);
  };

  /** Reads the list again without a loading state: used when a failure says the screen is out of date. */
  const refresh = async () => {
    const res = await hospitalTreatments(hospitalId);
    if (res.ok) {
      setLoad((prev) => ({ set: prev && "set" in prev ? merged(prev.set, res.data) : res.data }));
      setSelected((prev) => new Set([...prev].filter((id) => res.data.available.some((a) => a.id === id))));
    } else if (res.status === 401) onUnauthorizedRef.current();
  };

  const apply = (id: number, change: Partial<HospitalTreatment>) =>
    setLoad((prev) => {
      if (!prev || !("set" in prev)) return prev;
      return { set: withItems(prev.set, prev.set.items.map((item) => (item.id === id ? { ...item, ...change } : item))) };
    });
  const mark = (id: number, on: boolean) =>
    setPending((prev) => {
      if (on) return { ...prev, [id]: true };
      const { [id]: _done, ...rest } = prev;
      void _done;
      return rest;
    });
  const failure = (res: WriteFailure, action: TreatmentAction) => {
    const f = treatmentFailure(res, action);
    if (f.refresh) void refresh();
    return f;
  };

  const togglePromoted = async (item: HospitalTreatment) => {
    if (!set?.canManage || pending[item.id]) return;
    const next = !item.isPromoted;
    setStatus(null);
    mark(item.id, true);
    apply(item.id, { isPromoted: next });
    const res = await patchHospitalTreatment(hospitalId, item.id, { isPromoted: next });
    mark(item.id, false);
    if (res.ok) {
      if (res.data.item) apply(item.id, { isPromoted: res.data.item.isPromoted });
      return;
    }
    apply(item.id, { isPromoted: !next });
    if (res.status === 401) {
      onUnauthorizedRef.current();
      return;
    }
    const f = failure(res, "promote");
    setStatus({ tone: "err", text: t("h.e.tr.err.promote", { name: item.name, reason: t(f.key) }) });
  };

  const saveAge = async (item: HospitalTreatment) => {
    const typed = ages[item.id];
    if (typed === undefined || !set?.canManage || pending[item.id]) return;
    setStatus(null);
    const parsed = parseMinAge(typed);
    if (!parsed.ok) {
      setAgeErrors((prev) => ({ ...prev, [item.id]: "h.e.tr.err.age" }));
      return;
    }
    setAgeErrors((prev) => {
      const { [item.id]: _gone, ...rest } = prev;
      void _gone;
      return rest;
    });
    mark(item.id, true);
    const res = await patchHospitalTreatment(hospitalId, item.id, { minAge: parsed.value });
    mark(item.id, false);
    if (res.ok) {
      apply(item.id, { minAge: res.data.item ? res.data.item.minAge : parsed.value });
      setAges((prev) => {
        const { [item.id]: _saved, ...rest } = prev;
        void _saved;
        return rest;
      });
      setStatus({ tone: "ok", text: t("h.e.tr.age.saved", { name: item.name }) });
      return;
    }
    if (res.status === 401) {
      onUnauthorizedRef.current();
      return;
    }
    const f = failure(res, "age");
    setAgeErrors((prev) => ({ ...prev, [item.id]: f.key }));
    setStatus({ tone: "err", text: t(f.key) });
  };

  const assign = async () => {
    if (assigning || !set) return;
    setStatus(null);
    const ids = [...selected];
    if (ids.length === 0) {
      setAddError("h.e.tr.err.none");
      return;
    }
    if (ids.length > ASSIGN_LIMIT) {
      setAddError("h.e.tr.err.tooMany");
      return;
    }
    setAddError(null);
    setAssigning(true);
    const res = await assignHospitalTreatments(hospitalId, ids);
    setAssigning(false);
    if (res.ok) {
      setLoad((prev) => ({ set: prev && "set" in prev ? merged(prev.set, res.data) : res.data }));
      setSelected(new Set());
      setStatus({ tone: "ok", text: res.data.skipped > 0 ? t("h.e.tr.added.skipped", { n: res.data.added, m: res.data.skipped }) : t("h.e.tr.added", { n: res.data.added }) });
      return;
    }
    if (res.status === 401) {
      onUnauthorizedRef.current();
      return;
    }
    setAddError(failure(res, "assign").key);
  };

  const pricesSaved = (saved: HospitalTreatment | null) => {
    // The window closes itself (it calls onClose when its closing animation ends).
    const id = priceFor?.id;
    setStatus({ tone: "ok", text: t("h.e.tp.saved") });
    if (saved && id === saved.id) {
      setLoad((prev) => (prev && "set" in prev ? { set: withItems(prev.set, prev.set.items.map((item) => (item.id === saved.id ? saved : item))) } : prev));
    } else void refresh();
  };

  const confirmRemove = async () => {
    const item = confirmFor;
    if (!item || removing) return;
    setRemoving(true);
    setRemoveError(null);
    const res = await removeHospitalTreatment(hospitalId, item.id);
    setRemoving(false);
    if (res.ok) {
      setLoad((prev) => ({ set: prev && "set" in prev ? merged(prev.set, res.data) : res.data }));
      setAges((prev) => {
        const { [item.id]: _gone, ...rest } = prev;
        void _gone;
        return rest;
      });
      setConfirmFor(null);
      setStatus({
        tone: "ok",
        text: res.data.removed ? t("h.e.tr.rm.done", { name: item.name, prices: res.data.deleted.priceOptions, images: res.data.deleted.images }) : t("h.e.tr.rm.already", { name: item.name }),
      });
      return;
    }
    if (res.status === 401) {
      onUnauthorizedRef.current();
      return;
    }
    setRemoveError(failure(res, "remove").key);
  };

  if (load === null) return <p className="field__hint" role="status">{t("h.e.tr.loading")}</p>;
  if (set === null) {
    return (
      <div className="htr__retry">
        <p className="field__error" role="alert">{t("h.e.tr.loadFail")}</p>
        <button type="button" className="btn btn--ghost" onClick={retry}>
          <Icon name="refresh" size={18} />
          <span>{t("h.e.tr.retry")}</span>
        </button>
      </div>
    );
  }

  const canManage = set.canManage === true;
  const canRemove = canManage && set.removeEnabled === true;
  const priceEdit = canEditPrices(set);
  const ageShown = set.minAgeSupported === true;
  const pills = categoryPills(set.available);
  const won = (n: number): string => (n > 0 ? t("h.e.tr.won", { n: formatNumber(n) }) : t("h.e.tr.price.missing"));

  const askRemove = (item: HospitalTreatment) => {
    setRemoveError(null);
    setConfirmFor(item);
  };
  const confirmSpec: ConfirmSpec | null = confirmFor
    ? {
        title: t("h.e.tr.rm.title"),
        body: t("h.e.tr.rm.body", { name: confirmFor.name, prices: removalImpact(confirmFor).priceOptions, images: removalImpact(confirmFor).images }),
        confirmLabel: t("h.e.tr.rm.confirm"),
        cancelLabel: t("h.e.tr.rm.cancel"),
        focus: "cancel",
      }
    : null;

  // Enter inside these boxes must not send the surrounding hospital form.
  const enterKey = (action?: () => void) => (e: KeyboardEvent<HTMLInputElement>) => {
    if (e.key !== "Enter") return;
    e.preventDefault();
    action?.();
  };

  const star = (item: HospitalTreatment): ReactNode => (
    <button
      type="button"
      className="htr__star"
      aria-pressed={item.isPromoted}
      aria-label={t("h.e.tr.star", { name: item.name })}
      title={t("h.e.tr.star.title")}
      disabled={!canManage || pending[item.id] === true}
      onClick={() => void togglePromoted(item)}
    >
      <Icon name="star" size={22} />
    </button>
  );

  const prices = (item: HospitalTreatment): ReactNode => {
    if (item.priceOptions.length === 0) return <span className="htr__noprice">{t("h.e.tr.price.none")}</span>;
    const open = expanded[item.id] === true;
    const rest = item.priceOptions.length - PRICE_PREVIEW;
    // Collapsed: the representative price always shows in the preview, whatever its position.
    const shown = open
      ? item.priceOptions
      : [...item.priceOptions].sort((x, y) => Number(y.isRepresentative) - Number(x.isRepresentative)).slice(0, PRICE_PREVIEW);
    return (
      <>
        <ul className="htr__prices">
          {shown.map((o, i) => {
            const label = priceLabel(o);
            const struck = o.regularPrice > 0 && o.regularPrice !== o.price;
            return (
              <li key={i} className="htr__pr" translate="no">
                {o.isRepresentative || label ? (
                  <span className="htr__prl">
                    {o.isRepresentative ? <span className="htr__rep">{t("h.e.tr.price.rep")}</span> : null}
                    {label}
                  </span>
                ) : null}
                <span className="htr__prv">
                  {struck ? (
                    <del className="htr__reg">
                      <span className="sr-only">{t("h.e.tr.price.regular")} </span>
                      {won(o.regularPrice)}
                    </del>
                  ) : null}
                  <strong>{won(o.price)}</strong>
                </span>
              </li>
            );
          })}
        </ul>
        {rest > 0 ? (
          <button
            type="button"
            className="htr__more"
            aria-expanded={open}
            aria-label={open ? t("h.e.tr.price.less") : t("h.e.tr.price.moreAria", { n: rest })}
            onClick={() => setExpanded((prev) => ({ ...prev, [item.id]: !open }))}
          >
            {open ? t("h.e.tr.price.less") : t("h.e.tr.price.more", { n: rest })}
          </button>
        ) : null}
      </>
    );
  };

  const meta = (item: HospitalTreatment): ReactNode => (
    <div className="htr__meta">
      <span className="htr__chip" title={t("h.e.tr.col.by")}>
        <Icon name="people" size={14} />
        <span className="sr-only">{t("h.e.tr.col.by")}: </span>
        {item.registeredBy === "admin" ? t("h.e.tr.by.admin") : t("h.e.tr.by.hospital")}
      </span>
      <span className="htr__chip" title={t("h.e.tr.col.img")}>
        <Icon name="image" size={14} />
        <span className="sr-only">{t("h.e.tr.col.img")}: </span>
        {t("h.e.tr.img.count", { n: item.imageCount })}
        {item.pendingImageCount > 0 ? <span className="htr__review">{t("h.e.tr.img.pending", { n: item.pendingImageCount })}</span> : null}
      </span>
      <span className="htr__chip" title={t("h.e.tr.col.q")}>
        <Icon name="help" size={14} />
        <span className="sr-only">{t("h.e.tr.col.q")}: </span>
        {t("h.e.tr.q.count", { n: item.questionCount })}
      </span>
    </div>
  );

  const age = (item: HospitalTreatment): ReactNode => {
    if (!ageShown) return null;
    if (!canManage) return <span className="htr__chip">{item.minAge ? t("h.e.tr.age.readonly", { n: item.minAge }) : t("h.e.tr.age.none")}</span>;
    const value = ages[item.id] ?? minAgeText(item.minAge);
    const changed = value.trim() !== minAgeText(item.minAge);
    const errId = `htr-${hospitalId}-${item.id}-age-err`;
    const error = ageErrors[item.id];
    return (
      <div className="htr__age">
        <div className="htr__agebox">
          <span className="htr__agefield">
            <input
              id={`htr-${hospitalId}-${item.id}-age`}
              className="input htr__ageinput"
              type="text"
              inputMode="numeric"
              maxLength={3}
              autoComplete="off"
              placeholder={t("h.e.tr.age.ph")}
              aria-label={t("h.e.tr.age.label", { name: item.name })}
              aria-invalid={error ? true : undefined}
              aria-describedby={error ? errId : undefined}
              value={value}
              disabled={pending[item.id] === true}
              onChange={(e) => {
                setAges((prev) => ({ ...prev, [item.id]: e.target.value }));
                setAgeErrors((prev) => {
                  const { [item.id]: _cleared, ...rest } = prev;
                  void _cleared;
                  return rest;
                });
                setStatus(null);
              }}
              onKeyDown={enterKey(() => void saveAge(item))}
            />
            <span className="htr__ageunit" aria-hidden="true">{t("h.e.tr.age.unit")}</span>
          </span>
          <button
            type="button"
            className="btn btn--ghost htr__agesave"
            disabled={!changed || pending[item.id] === true}
            aria-label={t("h.e.tr.age.saveFor", { name: item.name })}
            title={t("h.e.tr.age.save")}
            onClick={() => void saveAge(item)}
          >
            <Icon name="check" size={18} />
          </button>
        </div>
        {error ? <p id={errId} className="field__error" role="alert">{t(error, { max: MIN_AGE_MAX })}</p> : null}
      </div>
    );
  };

  const removeBtn = (item: HospitalTreatment): ReactNode =>
    canRemove ? (
      <button type="button" className="btn htr__rm" disabled={pending[item.id] === true || removing} aria-label={t("h.e.tr.rm.btnFor", { name: item.name })} onClick={() => askRemove(item)}>
        <Icon name="trash" size={16} />
        <span>{t("h.e.tr.rm.btn")}</span>
      </button>
    ) : null;

  const tooMany = selected.size > ASSIGN_LIMIT;

  return (
    <div className="htr">
      <p className="field__hint">{t("h.e.tr.intro")}</p>
      {!canManage ? <p className="field__hint">{t("h.e.tr.readonly")}</p> : null}

      <section className="htr__sec" aria-labelledby={`htr-${hospitalId}-lt`}>
        <header className="htr__head">
          <div className="htr__headl">
            <h3 className="htr__title" id={`htr-${hospitalId}-lt`}>{t("h.e.tr.title")}</h3>
            <span className="htr__count" translate="no">{set.count}</span>
          </div>
          <div className="htr__headr">
            <span className="htr__chip htr__chip--promo" translate="no">
              <Icon name="star" size={14} />
              {t("h.e.tr.stat.promo", { m: set.promotedCount })}
            </span>
            {canManage ? (
              <button type="button" className="btn btn--primary" aria-expanded={addOpen} aria-controls={`htr-${hospitalId}-add`} onClick={() => setAddOpen((v) => !v)}>
                <Icon name={addOpen ? "close" : "clipboard"} size={18} />
                <span>{addOpen ? t("h.e.tr.add.close") : t("h.e.tr.add")}</span>
              </button>
            ) : null}
          </div>
        </header>

        <p className={status ? `htr__status htr__status--${status.tone}` : "htr__status"} role="status" aria-live="polite">{status ? status.text : ""}</p>

        {set.items.length === 0 ? (
          <p className="htr__empty">{canManage ? t("h.e.tr.empty") : t("h.e.tr.emptyReadonly")}</p>
        ) : (
          <ul className="htr__list">
            {set.items.map((item, i) => (
              <li key={item.id} className="htr__row">
                <div className="htr__main">
                  <span className="htr__no" aria-hidden="true">{i + 1}</span>
                  <div className="htr__id">
                    <strong className="htr__name" translate="no">{item.name}</strong>
                    {item.hashtag || item.categoryName ? (
                      <div className="htr__sub">
                        {item.hashtag ? <span className="htr__tag" translate="no">{item.hashtag}</span> : null}
                        {item.categoryName ? <Pill tone="info">{item.categoryName}</Pill> : null}
                      </div>
                    ) : null}
                    {item.note ? <p className="htr__note" translate="no" title={item.note}>{notePreview(item.note)}</p> : null}
                  </div>
                </div>
                <div className="htr__priceblock">
                  {prices(item)}
                  {priceEdit ? (
                    <button type="button" className="btn btn--ghost htr__pedit" disabled={pending[item.id] === true} aria-label={t("h.e.tp.openerFor", { name: item.name })} onClick={() => setPriceFor(item)}>
                      <Icon name="coin" size={16} />
                      <span>{t("h.e.tp.opener")}</span>
                    </button>
                  ) : null}
                </div>
                <div className="htr__side">
                  {meta(item)}
                  <div className="htr__ctl">
                    {star(item)}
                    {age(item)}
                    {removeBtn(item)}
                  </div>
                </div>
              </li>
            ))}
          </ul>
        )}
      </section>

      {canManage && addOpen ? (
        <section className="htr__panel htr__panel--add" id={`htr-${hospitalId}-add`} aria-labelledby={`htr-${hospitalId}-at`}>
          <h3 className="htr__subtitle" id={`htr-${hospitalId}-at`}>{t("h.e.tr.add.title", { n: set.available.length })}</h3>
          {set.available.length === 0 ? (
            <p className="htr__empty">{t("h.e.tr.add.allDone")}</p>
          ) : (
            <>
              <div className="htr__pills" role="group" aria-label={t("h.e.tr.cat.aria")}>
                <button type="button" className="htr__pill" aria-pressed={category === ALL_CATEGORIES} onClick={() => setCategory(ALL_CATEGORIES)}>
                  {t("h.e.tr.cat.all", { n: set.available.length })}
                </button>
                {pills.map((pill) => (
                  <button key={pill.key} type="button" className="htr__pill" aria-pressed={category === pill.key} onClick={() => setCategory(pill.key)}>
                    {t("h.e.tr.cat.item", { name: pill.name ?? t("h.e.tr.cat.none"), n: pill.count })}
                  </button>
                ))}
              </div>

              <div className="htr__tools">
                <div className="htr__search">
                  <label className="sr-only" htmlFor={`htr-${hospitalId}-q`}>{t("h.e.tr.search")}</label>
                  <input
                    id={`htr-${hospitalId}-q`}
                    className="input"
                    type="search"
                    autoComplete="off"
                    placeholder={t("h.e.tr.search.ph")}
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    onKeyDown={enterKey()}
                  />
                </div>
                <label className="check htr__all">
                  <input
                    ref={selectAllRef}
                    type="checkbox"
                    checked={visible.length > 0 && visibleSelected === visible.length}
                    disabled={visible.length === 0 || assigning}
                    onChange={(e) =>
                      setSelected((prev) => {
                        const next = new Set(prev);
                        for (const item of visible) {
                          if (e.target.checked) next.add(item.id);
                          else next.delete(item.id);
                        }
                        return next;
                      })
                    }
                  />
                  <span>{t("h.e.tr.selectAll")}</span>
                </label>
              </div>

              {visible.length === 0 ? (
                <p className="htr__empty">{t("h.e.tr.noMatch")}</p>
              ) : (
                <ul className="htr__pick" tabIndex={0} aria-label={t("h.e.tr.pick.aria")}>
                  {visible.map((item) => (
                    <li key={item.id}>
                      <label className={selected.has(item.id) ? "htr__opt htr__opt--on" : "htr__opt"}>
                        <input
                          type="checkbox"
                          checked={selected.has(item.id)}
                          disabled={assigning}
                          onChange={(e) => {
                            setAddError(null);
                            setSelected((prev) => {
                              const next = new Set(prev);
                              if (e.target.checked) next.add(item.id);
                              else next.delete(item.id);
                              return next;
                            });
                          }}
                        />
                        <span className="htr__optt">
                          <strong translate="no">{item.name}</strong>
                          <span className="htr__optm">
                            {item.hashtag ? <span className="htr__tag" translate="no">{item.hashtag}</span> : null}
                            <span className="htr__optc" translate="no">{item.categoryName ?? t("h.e.tr.cat.none")}</span>
                          </span>
                        </span>
                      </label>
                    </li>
                  ))}
                </ul>
              )}

              <div className="htr__foot">
                <div className="htr__footmsg">
                  {tooMany ? <p className="field__hint">{t("h.e.tr.err.tooMany", { max: ASSIGN_LIMIT })}</p> : null}
                  {addError ? <p className="field__error" role="alert">{t(addError, { max: ASSIGN_LIMIT })}</p> : null}
                </div>
                <button type="button" className="btn btn--primary" disabled={assigning} onClick={() => void assign()}>
                  <Icon name="check" size={18} />
                  <span>{assigning ? t("h.e.tr.adding") : t("h.e.tr.submit", { n: selected.size })}</span>
                </button>
              </div>
            </>
          )}
        </section>
      ) : null}

      <div className="htr__manage">
        <p className="field__hint">{t(priceEdit ? "h.e.tr.manageHintPrices" : "h.e.tr.manageHint")}</p>
        <a className="btn btn--ghost" href={legacyTreatmentsUrl(hospitalId)}>
          <Icon name="external" size={18} />
          <span>{t(priceEdit ? "h.e.tr.managePrices" : "h.e.tr.manage")}</span>
        </a>
      </div>

      {priceEdit && priceFor ? (
        <HospitalTreatmentPrices
          key={priceFor.id}
          hospitalId={hospitalId}
          item={priceFor}
          units={set.priceUnits ?? []}
          onSaved={pricesSaved}
          onClose={() => setPriceFor(null)}
          onStale={() => void refresh()}
          onUnauthorized={() => onUnauthorizedRef.current()}
        />
      ) : null}

      <ConfirmDialog spec={confirmSpec} busy={removing} error={removeError ? t(removeError) : null} onConfirm={() => void confirmRemove()} onClose={() => setConfirmFor(null)} />
    </div>
  );
}
