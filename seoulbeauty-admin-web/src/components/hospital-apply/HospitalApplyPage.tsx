"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from "react";
import AdminShell from "../AdminShell";
import ConfirmDialog from "../hospitals/ConfirmDialog";
import { Notice } from "../hospitals/parts";
import { ErrorScreen, ForbiddenScreen, LoadingScreen, RedirectingScreen, type ErrorKind } from "../StateScreens";
import ApplySummary, { type SummaryItem } from "./ApplySummary";
import BasicSection, { type PhoneState } from "./BasicSection";
import DoctorsSection from "./DoctorsSection";
import DocumentsSection from "./DocumentsSection";
import { dismissApplyError, showApplyError } from "./ErrorToast";
import ImagesSection from "./ImagesSection";
import InfoSection from "./InfoSection";
import SectionCard from "./SectionCard";
import SuccessDialog from "./SuccessDialog";
import TreatmentsSection from "./TreatmentsSection";
import type { IconName } from "../Icon";
import { fetchMe, type Me } from "@/lib/api";
import { PHP_ADMIN_URL } from "@/lib/config";
import {
  PREVIEW_CATEGORIES,
  PREVIEW_TREATMENTS,
  PREVIEW_UNITS,
  SECTION_IDS,
  SECTION_TITLE_KEYS,
  applyCounts,
  emptyDoctor,
  emptyDraft,
  errorSections,
  firstErrorKey,
  isDirty,
  namedDoctors,
  savedPriceCount,
  sectionFilled,
  validateDraft,
  type ApplyCounts,
  type Draft,
  type Errors,
  type FieldError,
  type SectionId,
} from "@/lib/hospitalApply";
import { LAYOUT_PREVIEW, checkPhone, submitApplication } from "@/lib/hospitalApplyApi";
import { useT } from "@/lib/locale";

type Phase = "loading" | "ready" | "forbidden" | "redirecting" | "error";
type State = { phase: Phase; errorKind: ErrorKind; me: Me | null };

const ICONS: Record<SectionId, IconName> = { basic: "people", info: "hospital", treat: "card", img: "grid", doc: "people", file: "edit" };

const reducedMotion = (): boolean => window.matchMedia("(prefers-reduced-motion: reduce)").matches;

/** Registration of a hospital by a super admin: one long page of six folding sections; only the name is required. */
export default function HospitalApplyPage() {
  const t = useT();
  const router = useRouter();
  const [state, setState] = useState<State>({ phase: "loading", errorKind: "load", me: null });
  const [attempt, setAttempt] = useState(0);
  const [draft, setDraft] = useState<Draft>(() => emptyDraft());
  const [formErrors, setFormErrors] = useState<Errors>({});
  const [fileErrors, setFileErrors] = useState<Errors>({});
  const [open, setOpen] = useState<Record<SectionId, boolean>>({ basic: true, info: false, treat: false, img: false, doc: false, file: false });
  const [phone, setPhone] = useState<PhoneState>({ status: "idle" });
  const [busy, setBusy] = useState(false);
  const [done, setDone] = useState<{ name: string; counts: ApplyCounts } | null>(null);
  const [leaveAsk, setLeaveAsk] = useState<{ href: string | null } | null>(null);
  // Set just before leaving on purpose, so the browser prompt and the link guard stay out of the way.
  const leaving = useRef(false);
  const phoneAsked = useRef<string | null>(null);
  const latestPhone = useRef("");
  const [current, setCurrent] = useState<SectionId | null>("basic");
  const doctorSeeded = useRef(false);

  useEffect(() => {
    const controller = new AbortController();
    void (async () => {
      const me = await fetchMe(controller.signal);
      if (controller.signal.aborted) return;
      if (!me.ok) {
        if (me.status === 401) {
          setState((s) => ({ ...s, phase: "redirecting" }));
          window.location.assign(PHP_ADMIN_URL);
        } else if (me.status === 403) setState({ phase: "forbidden", errorKind: "load", me: null });
        else setState({ phase: "error", errorKind: me.status === 404 ? "disabled" : me.status === 503 ? "session" : "load", me: null });
        return;
      }
      setState({ phase: me.data.admin.role === "super_admin" ? "ready" : "forbidden", errorKind: "load", me: me.data });
    })();
    return () => controller.abort();
  }, [attempt]);

  const errors = useMemo<Errors>(() => ({ ...fileErrors, ...formErrors }), [fileErrors, formErrors]);
  const dirty = isDirty(draft);

  // Leaving by closing the tab or reloading loses everything typed: ask first.
  useEffect(() => {
    if (!dirty || done) return;
    const guard = (e: BeforeUnloadEvent) => {
      if (!leaving.current) e.preventDefault();
    };
    // Links inside the panel (the list link, the side menu) leave without a reload: ask before they do.
    const links = (e: MouseEvent) => {
      if (leaving.current || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      const a = (e.target as Element | null)?.closest?.("a[href]") as HTMLAnchorElement | null;
      if (!a || (a.target && a.target !== "_self") || a.hasAttribute("download")) return;
      const url = new URL(a.href, window.location.href);
      if (url.origin === window.location.origin && url.pathname === window.location.pathname && url.search === window.location.search) return;
      e.preventDefault();
      e.stopPropagation();
      setLeaveAsk({ href: url.href });
    };
    window.addEventListener("beforeunload", guard);
    document.addEventListener("click", links, true);
    return () => {
      window.removeEventListener("beforeunload", guard);
      document.removeEventListener("click", links, true);
    };
  }, [dirty, done]);

  useEffect(() => {
    latestPhone.current = draft.phone.trim();
  }, [draft.phone]);

  // Which section is nearest the top of the screen, for the summary's marker.
  useEffect(() => {
    if (state.phase !== "ready") return;
    const nodes = SECTION_IDS.map((id) => document.getElementById(`sec-${id}`)).filter((n): n is HTMLElement => n !== null);
    const observer = new IntersectionObserver(
      (entries) => {
        const seen = entries.filter((e) => e.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0];
        if (seen) setCurrent(seen.target.id.replace("sec-", "") as SectionId);
      },
      { rootMargin: "-20% 0px -60% 0px" },
    );
    nodes.forEach((n) => observer.observe(n));
    return () => observer.disconnect();
  }, [state.phase]);

  const set = useCallback((patch: Partial<Draft>) => {
    setDraft((d) => ({ ...d, ...patch }));
    // A field that is being corrected stops showing its old error.
    setFormErrors((prev) => {
      const keys = Object.keys(patch).filter((k) => k in prev);
      if (keys.length === 0) return prev;
      const next = { ...prev };
      keys.forEach((k) => delete next[k]);
      return next;
    });
    if ("phone" in patch) setPhone({ status: "idle" });
  }, []);

  const setFileError = useCallback((key: string, error: FieldError | null) => {
    setFileErrors((prev) => {
      if (error === null) {
        if (!(key in prev)) return prev;
        const next = { ...prev };
        delete next[key];
        return next;
      }
      return { ...prev, [key]: error };
    });
  }, []);

  const toggle = (id: SectionId) => {
    setOpen((o) => {
      const next = { ...o, [id]: !o[id] };
      return next;
    });
    if (id === "doc" && !doctorSeeded.current) {
      doctorSeeded.current = true;
      setDraft((d) => (d.doctors.length === 0 ? { ...d, doctors: [emptyDoctor()] } : d));
    }
  };

  const jump = (id: SectionId) => {
    setOpen((o) => ({ ...o, [id]: true }));
    if (id === "doc" && !doctorSeeded.current) {
      doctorSeeded.current = true;
      setDraft((d) => (d.doctors.length === 0 ? { ...d, doctors: [emptyDoctor()] } : d));
    }
    window.setTimeout(() => document.getElementById(`sec-${id}`)?.scrollIntoView({ behavior: reducedMotion() ? "auto" : "smooth", block: "start" }), 60);
  };

  const focusField = (focusId: string) => {
    const el = document.getElementById(focusId);
    if (!el) return;
    el.scrollIntoView({ behavior: reducedMotion() ? "auto" : "smooth", block: "center" });
    el.focus({ preventScroll: true });
  };

  const runCheckPhone = async () => {
    const value = draft.phone.trim();
    // Leaving the field and pressing the button are one gesture: ask once.
    if (value === "" || phoneAsked.current === value) return;
    if ((phone.status === "ok" || phone.status === "dup") && phone.phone === value) return;
    phoneAsked.current = value;
    setPhone({ status: "checking", phone: value });
    const res = await checkPhone(value);
    if (phoneAsked.current === value) phoneAsked.current = null;
    // The number was changed while the answer was on its way: the answer is not about this field any more.
    if (latestPhone.current !== value) return;
    setPhone(!res.ok ? { status: "fail", phone: value } : res.duplicate ? { status: "dup", phone: value, name: res.hospitalName ?? "" } : { status: "ok", phone: value });
  };

  const reportErrors = (all: Errors) => {
    const keys = Object.keys(all);
    const sections = errorSections(all);
    setOpen((o) => {
      const next = { ...o };
      sections.forEach((id) => (next[id] = true));
      return next;
    });
    const first = firstErrorKey(all);
    const firstError = first ? all[first] : null;
    const fields = Array.from(new Set(keys.map((k) => t(all[k].label)))).join(", ");
    const message =
      sections.length === 1
        ? t("hap.toast.one", { section: t(SECTION_TITLE_KEYS[sections[0]]), n: keys.length, fields })
        : t("hap.toast.many", { n: keys.length, fields });
    const go = () => {
      if (!firstError) return;
      setOpen((o) => ({ ...o, [firstError.section]: true }));
      window.setTimeout(() => focusField(firstError.focusId), 80);
    };
    showApplyError({ message, goLabel: t("hap.toast.go"), closeLabel: t("hap.toast.close"), onGo: go });
    go();
  };

  const submit = async () => {
    if (busy) return;
    const found = validateDraft(draft);
    setFormErrors(found);
    // A refused file is not part of the form (it was never added), so it does not hold the registration back.
    let all: Errors = { ...found };

    // A phone number that is filled must not belong to another hospital.
    const value = draft.phone.trim();
    if (value !== "" && !(phone.status === "ok" && phone.phone === value)) {
      setBusy(true);
      const res = await checkPhone(value);
      setBusy(false);
      if (res.ok && res.duplicate) {
        setPhone({ status: "dup", phone: value, name: res.hospitalName ?? "" });
        all = { ...all, phone: { section: "basic", focusId: "f-phone", message: { key: "hap.f.phoneDup", vars: { name: res.hospitalName ?? "" } }, label: "hap.fn.phone" } };
        setFormErrors((p) => ({ ...p, phone: all.phone }));
      } else if (res.ok) setPhone({ status: "ok", phone: value });
      else setPhone({ status: "fail", phone: value });
    } else if (phone.status === "dup" && phone.phone === value) {
      all = { ...all, phone: { section: "basic", focusId: "f-phone", message: { key: "hap.f.phoneDup", vars: { name: phone.name } }, label: "hap.fn.phone" } };
    }

    if (Object.keys(all).length > 0) {
      reportErrors(all);
      return;
    }
    dismissApplyError();
    setBusy(true);
    const result = await submitApplication(draft);
    setBusy(false);
    if (result.ok) setDone({ name: draft.name.trim(), counts: applyCounts(draft) });
    else showApplyError({ message: t("hap.toast.server"), goLabel: null, closeLabel: t("hap.toast.close"), onGo: null });
  };

  const leave = useCallback(() => {
    leaving.current = true;
    dismissApplyError();
    router.push("/hospitals");
  }, [router]);

  const confirmLeave = () => {
    const href = leaveAsk?.href ?? null;
    if (href === null) {
      leave();
      return;
    }
    leaving.current = true;
    dismissApplyError();
    window.location.assign(href);
  };

  const cancel = () => (dirty ? setLeaveAsk({ href: null }) : leave());

  useEffect(() => () => dismissApplyError(), []);

  const retry = () => setAttempt((n) => n + 1);
  const { phase, me } = state;
  let screen: ReactNode = null;
  if (phase === "loading") screen = <LoadingScreen text={t("hap.loading")} />;
  else if (phase === "redirecting") screen = <RedirectingScreen />;
  else if (phase === "forbidden") screen = <ForbiddenScreen variant="super" />;
  else if (phase === "error") screen = <ErrorScreen kind={state.errorKind} onRetry={retry} retrying={false} />;

  const errCount = (id: SectionId) => Object.values(errors).filter((e) => e.section === id).length;
  const nameOk = draft.name.trim() !== "";

  const note = (id: SectionId): string => {
    switch (id) {
      case "basic":
        return nameOk ? t("hap.sm.reqDone") : t("hap.sm.req");
      case "treat":
        return draft.treatments.length > 0 ? t("hap.sm.nTreat", { n: draft.treatments.length }) : t("hap.sm.opt");
      case "img":
        return sectionFilled("img", draft) ? t("hap.sm.nImg", { n: (draft.profileImage ? 1 : 0) + draft.interiorImages.length }) : t("hap.sm.opt");
      case "doc":
        return namedDoctors(draft).length > 0 ? t("hap.sm.nDoc", { n: namedDoctors(draft).length }) : t("hap.sm.opt");
      case "file":
        return sectionFilled("file", draft) ? t("hap.sm.nFile", { n: (draft.certBusiness ? 1 : 0) + (draft.certMedical ? 1 : 0) }) : t("hap.sm.opt");
      default:
        return t("hap.sm.opt");
    }
  };

  const items: SummaryItem[] = SECTION_IDS.map((id) => ({
    id,
    state: errCount(id) > 0 ? "err" : sectionFilled(id, draft) ? "done" : "todo",
    note: note(id),
  }));
  const filled = SECTION_IDS.filter((id) => sectionFilled(id, draft)).length;

  const pillOf = (id: SectionId): { tone: "on" | "off" | "err"; text: string } | null => {
    const n = errCount(id);
    if (n > 0) return { tone: "err", text: t("hap.err.pill", { n }) };
    if (id === "basic") return nameOk ? { tone: "on", text: t("hap.sm.reqDone") } : { tone: "off", text: t("hap.sm.req") };
    return sectionFilled(id, draft) ? { tone: "on", text: t("hap.sm.done") } : { tone: "off", text: t("hap.sm.opt") };
  };

  const summaryOf = (id: SectionId): string | null => {
    switch (id) {
      case "basic":
        return draft.name.trim() || null;
      case "info":
        return draft.address.trim() || null;
      case "treat": {
        const prices = savedPriceCount(draft);
        return draft.treatments.length > 0 ? `${t("hap.sm.nTreat", { n: draft.treatments.length })}${prices > 0 ? ` · ${t("hap.ok.chipPrice", { n: prices })}` : ""}` : null;
      }
      case "doc":
        return namedDoctors(draft).length > 0 ? t("hap.sm.nDoc", { n: namedDoctors(draft).length }) : null;
      default:
        return null;
    }
  };

  const section = (id: SectionId, body: ReactNode) => (
    <SectionCard key={id} id={id} icon={ICONS[id]} title={t(SECTION_TITLE_KEYS[id])} pill={pillOf(id)} summary={summaryOf(id)} open={open[id]} error={errCount(id) > 0} onToggle={() => toggle(id)}>
      {body}
    </SectionCard>
  );

  return (
    <AdminShell
      admin={me?.admin ?? null}
      features={me?.features ?? null}
      title={t("hap.title")}
      description={t("hap.desc")}
      screen={screen}
      actions={phase === "ready" ? <Link className="btn btn--ghost" href="/hospitals">{t("hap.toList")}</Link> : null}
    >
      {phase === "ready" ? (
        <div className="ha">
          {LAYOUT_PREVIEW ? (
            <div className="ha__preview">
              <Notice tone="warn" icon="info">{t("hap.preview")}</Notice>
            </div>
          ) : null}
          <div className="ha__grid">
            <div className="ha__main">
              {section("basic", <BasicSection draft={draft} set={set} errors={errors} phone={phone} onCheckPhone={() => void runCheckPhone()} />)}
              {section("info", <InfoSection draft={draft} set={set} errors={errors} />)}
              {section("treat", <TreatmentsSection draft={draft} set={set} errors={errors} setFileError={setFileError} categories={PREVIEW_CATEGORIES} options={PREVIEW_TREATMENTS} units={PREVIEW_UNITS} />)}
              {section("img", <ImagesSection draft={draft} set={set} errors={errors} setFileError={setFileError} />)}
              {section("doc", <DoctorsSection draft={draft} set={set} errors={errors} setFileError={setFileError} />)}
              {section("file", <DocumentsSection draft={draft} set={set} errors={errors} setFileError={setFileError} />)}
            </div>
            <ApplySummary name={draft.name} filled={filled} items={items} current={current} busy={busy} onJump={jump} onSubmit={() => void submit()} onCancel={cancel} />
          </div>
        </div>
      ) : null}

      {done ? <SuccessDialog name={done.name} counts={done.counts} onGo={leave} /> : null}
      <ConfirmDialog
        spec={leaveAsk !== null ? { title: t("hap.leave.title"), body: t("hap.leave.body"), confirmLabel: t("hap.leave.go"), cancelLabel: t("hap.leave.stay"), focus: "cancel" } : null}
        onConfirm={confirmLeave}
        onClose={() => setLeaveAsk(null)}
      />
    </AdminShell>
  );
}
