"use client";

import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { useEffect, useRef, useState, type ReactNode } from "react";
import AdminShell from "../AdminShell";
import Icon from "../Icon";
import { ErrorScreen, ForbiddenScreen, LoadingScreen, NotFoundScreen, RedirectingScreen, type ErrorKind } from "../StateScreens";
import ConfirmDialog, { type ConfirmSpec } from "./ConfirmDialog";
import DeleteHospitalDialog from "./DeleteHospitalDialog";
import HospitalEdit from "./HospitalEdit";
import ImageDialog, { type ZoomImage } from "./ImageDialog";
import { ActivePill, Chips, GradePill, Notice, Pill, Section } from "./parts";
import SalesCard from "./SalesCard";
import {
  fetchHospital,
  fetchMe,
  reviewHospitalRevisions,
  setHospitalActive,
  type HospitalDetail as HospitalDetailData,
  type HospitalDoctor,
  type HospitalImage,
  type Me,
} from "@/lib/api";
import { PHP_ADMIN_URL } from "@/lib/config";
import { formatDayTime, formatNumber } from "@/lib/format";
import { DAY_KEYS, failureKey } from "@/lib/hospitalEdit";
import {
  WEEKDAYS,
  dayHours,
  externalHref,
  hasBusinessHours,
  legacyDocumentUrl,
  legacyEditUrl,
  lunchBreak,
  mediaUrl,
  specialClosedDates,
  specialOpenDates,
} from "@/lib/hospitals";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";

type Phase = "loading" | "ready" | "forbidden" | "redirecting" | "notFound" | "error";

type State = {
  phase: Phase;
  errorKind: ErrorKind;
  me: Me | null;
  detail: HospitalDetailData | null;
  /** Which request (retry count) the state above answers. */
  settled: number;
};

const INITIAL: State = { phase: "loading", errorKind: "load", me: null, detail: null, settled: -1 };

const STATUS_KEYS = new Map<string, MessageKey>([
  ["approved", "h.status.approved"],
  ["pending", "h.status.pending"],
  ["rejected", "h.status.rejected"],
]);

/** Image kinds the old panel knows, in the order they are shown; any other stored kind follows under its own name. */
const IMAGE_TYPES = ["main", "logo", "exterior", "interior", "facility", "etc"] as const;
const IMAGE_TYPE_KEYS = new Map<string, MessageKey>(IMAGE_TYPES.map((type): [string, MessageKey] => [type, `h.img.type.${type}`]));

const DOCTOR_STATUS_KEYS = new Map<string, MessageKey>([
  ["active", "h.doc.status.active"],
  ["inactive", "h.doc.status.inactive"],
  ["pending", "h.doc.status.pending"],
]);

/** Fields a hospital can change in its application; a change of any other field is listed under its stored name. */
const REVISION_FIELD_KEYS = new Map<string, MessageKey>([
  ["name", "h.f.name"],
  ["owner_name", "h.f.owner"],
  ["business_number", "h.f.businessNumber"],
  ["phone", "h.f.phone"],
  ["email", "h.f.email"],
  ["contact_name", "h.rev.contactName"],
  ["contact_phone", "h.rev.contactPhone"],
  ["address", "h.f.address"],
  ["introduction", "h.f.intro"],
  ["website", "h.f.website"],
  ["sns_url", "h.f.sns"],
  ["kakao_channel_url", "h.f.kakao"],
  ["business_hours", "h.hours.title"],
  ["treatment_tags", "h.tags.treatments"],
]);

/** Values longer than this are cut in the row and offered in full underneath. */
const REVISION_VALUE_MAX = 80;
const clip = (value: string): string => (value.length > REVISION_VALUE_MAX ? `${value.slice(0, REVISION_VALUE_MAX)}…` : value);
const snake = (field: string): string => field.replace(/[A-Z]/g, (c) => `_${c.toLowerCase()}`);

type Flash = "saved" | "active" | "inactive" | "reviewed";
const FLASH_KEYS: Record<Flash, MessageKey> = {
  saved: "h.e.flash.saved",
  active: "h.e.flash.active",
  inactive: "h.e.flash.inactive",
  reviewed: "h.e.flash.reviewed",
};

/** Something that needs a yes first: switching the hospital on or off, or marking its changes as reviewed. */
type Confirm = "deactivate" | "activate" | "review";

/**
 * Everything stored about one hospital. With the server's write switch on, the same page turns into the edit form
 * (`?edit=1`); otherwise it is read-only and editing happens in the old panel.
 */
export default function HospitalDetail({ id }: { id: string }) {
  const t = useT();
  const router = useRouter();
  const searchParams = useSearchParams();
  const [state, setState] = useState<State>(INITIAL);
  const [attempt, setAttempt] = useState(0);
  const [zoom, setZoom] = useState<ZoomImage | null>(null);
  const [flash, setFlash] = useState<Flash | null>(null);
  const [confirm, setConfirm] = useState<Confirm | null>(null);
  const [working, setWorking] = useState(false);
  const [confirmError, setConfirmError] = useState<MessageKey | null>(null);
  const [deleting, setDeleting] = useState(false);
  // 401 / 403 are final for this page view: stop asking.
  const halted = useRef(false);
  // Only a plain positive number can be a hospital; anything else is answered like a missing one, without asking.
  const hospitalId = /^[1-9]\d{0,8}$/.test(id) ? Number(id) : 0;
  const busy = state.settled !== attempt && state.phase !== "forbidden" && state.phase !== "redirecting";

  useEffect(() => {
    if (halted.current) return;
    const controller = new AbortController();
    const run = async () => {
      const [me, detail] = await Promise.all([
        fetchMe(controller.signal),
        hospitalId > 0 ? fetchHospital(hospitalId, controller.signal) : Promise.resolve({ ok: false as const, status: 404 }),
      ]);
      if (controller.signal.aborted) return;
      const statuses = [me, detail].map((r) => (r.ok ? 200 : r.status));

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
      // The server says the page is switched off: show that, whatever the hospital request answered.
      if (me.ok && !me.data.features.hospitals) {
        setState({ ...INITIAL, phase: "error", errorKind: "hospitalsOff", me: me.data, settled: attempt });
        return;
      }
      if (me.ok && detail.ok) {
        setState({ phase: "ready", errorKind: "load", me: me.data, detail: detail.data, settled: attempt });
        return;
      }
      if (!me.ok) {
        const kind: ErrorKind = me.status === 404 ? "disabled" : me.status === 503 ? "session" : "load";
        setState({ ...INITIAL, phase: "error", errorKind: kind, settled: attempt });
        return;
      }
      const status = detail.ok ? 200 : detail.status;
      if (status === 404) {
        // Missing, not approved, or not this admin's hospital: the server answers the same for all three.
        setState({ ...INITIAL, phase: "notFound", me: me.data, settled: attempt });
        return;
      }
      setState({ ...INITIAL, phase: "error", errorKind: status === 503 ? "session" : "load", me: me.data, settled: attempt });
    };
    void run();
    return () => controller.abort();
  }, [hospitalId, attempt]);

  const retry = () => setAttempt((n) => n + 1);
  const { phase, me, detail } = state;

  let screen: ReactNode = null;
  if (phase === "loading") screen = <LoadingScreen text={t("h.d.loading")} />;
  else if (phase === "redirecting") screen = <RedirectingScreen />;
  else if (phase === "forbidden") screen = <ForbiddenScreen variant="hospitals" />;
  else if (phase === "notFound") screen = <NotFoundScreen backHref="/hospitals" backLabel={t("h.d.back")} />;
  else if (phase === "error") screen = <ErrorScreen kind={state.errorKind} onRetry={retry} retrying={busy} />;

  const h = detail?.hospital;
  // Changing data is offered only while the server has the write switch on; the server checks the account again on every request.
  const canWrite = me?.features.hospitalsWrite === true;
  const isSuper = me?.admin.role === "super_admin" && me.admin.scope === "all";
  // Deleting is offered to a super admin only, and only while the server has that switch on.
  const canDelete = me?.features.hospitalsDelete === true && canWrite && isSuper;
  const editing = canWrite && searchParams.get("edit") === "1";
  const viewHref = h ? `/hospitals/${h.id}` : "/hospitals";

  const runConfirm = async () => {
    if (!h || !confirm || working) return;
    setWorking(true);
    setConfirmError(null);
    const res = confirm === "review" ? await reviewHospitalRevisions(h.id) : await setHospitalActive(h.id, confirm === "activate");
    setWorking(false);
    if (res.ok) {
      setFlash(confirm === "review" ? "reviewed" : confirm === "activate" ? "active" : "inactive");
      setConfirm(null);
      retry();
      return;
    }
    if (res.status === 401) {
      halted.current = true;
      window.location.assign(PHP_ADMIN_URL);
      return;
    }
    setConfirmError(failureKey(res.status));
  };
  const askConfirm = (what: Confirm) => {
    setConfirmError(null);
    setConfirm(what);
  };
  const confirmSpec: ConfirmSpec | null =
    confirm === "deactivate"
      ? { title: t("h.act.deactivate.title"), body: t("h.act.deactivate.body"), confirmLabel: t("h.act.deactivate"), cancelLabel: t("h.c.cancel"), focus: "cancel" }
      : confirm === "activate"
        ? { title: t("h.act.activate.title"), body: t("h.act.activate.body"), confirmLabel: t("h.act.activate"), cancelLabel: t("h.c.cancel"), focus: "cancel" }
        : confirm === "review"
          ? { title: t("h.rev.confirm.title"), body: t("h.rev.confirm.body"), confirmLabel: t("h.rev.confirm.ok"), cancelLabel: t("h.c.cancel"), focus: "cancel" }
          : null;

  const actions = h ? (
    <div className="page-actions">
      <Link className="btn btn--ghost" href="/hospitals">{t("h.d.back")}</Link>
      {canWrite ? (
        editing ? null : (
          <Link className="btn btn--primary" href={`${viewHref}?edit=1`} title={t("h.d.editBtnTitle")} onClick={() => setFlash(null)}>
            <Icon name="edit" size={18} />
            <span>{t("h.d.editBtn")}</span>
          </Link>
        )
      ) : (
        <a className="btn btn--ghost" href={legacyEditUrl(h.id)} title={t("h.d.editHint")}>
          <Icon name="external" size={18} />
          <span>{t("h.d.edit")}</span>
        </a>
      )}
    </div>
  ) : null;
  const reviewable = canWrite && isSuper && !!h && (h.hasUnreviewedChanges || (detail?.revisions.length ?? 0) > 0);
  const revisionsDesc = reviewable ? t("h.rev.descWrite") : canWrite ? t("h.rev.descView") : t("h.rev.desc");

  return (
    <AdminShell
      admin={me?.admin ?? null}
      features={me?.features ?? null}
      title={h ? h.name || t("h.noName") : t("h.d.title")}
      description={editing ? t("h.e.desc") : t("h.d.desc")}
      actions={actions}
      screen={screen}
    >
      {detail && h && editing ? (
        <HospitalEdit
          key={`${h.id}-${state.settled}`}
          hospital={h}
          onSaved={() => {
            setFlash("saved");
            router.replace(viewHref);
            retry();
          }}
          onCancel={() => router.replace(viewHref)}
          onReload={retry}
          imageCount={detail.images.length}
          imagesEnabled={me?.features.hospitalsImages === true}
          doctorCount={detail.doctors.length}
          doctorsEnabled={me?.features.hospitalsDoctors === true}
          treatmentsEnabled={me?.features.hospitalsTreatments === true}
          treatmentImagesEnabled={me?.features.hospitalsTreatmentsImages === true}
        />
      ) : null}
      {detail && h && !editing ? (
        <>
          <div role="status">
            {flash ? <Notice icon="check">{t(FLASH_KEYS[flash])}</Notice> : null}
          </div>
          <Notice>{canWrite ? (me?.admin.scope === "own" ? t("h.d.viewHintOwn") : t("h.d.viewHint")) : me?.admin.scope === "own" ? t("h.d.readOnlyOwn") : t("h.d.readOnly")}</Notice>
          {h.hasUnreviewedChanges ? (
            <Notice tone="warn" icon="alert" action={detail.revisions.length > 0 ? <a className="txtbtn" href="#sec-d-revisions">{t("h.rev.jump")}</a> : undefined}>
              {t("h.rev.notice")}
            </Notice>
          ) : null}

          <SalesCard hospitalId={h.id} />
          <BasicInfo detail={detail} />
          <StatusInfo detail={detail} onToggle={canWrite && isSuper ? (active) => askConfirm(active ? "activate" : "deactivate") : null} />
          <Hours hours={h.businessHours} />

          <Section id="sec-d-tags" title={t("h.tags.title")} desc={t("h.tags.desc")}>
            <dl className="panel dl">
              <Field label={t("h.tags.categories")}><Chips items={h.categories} /></Field>
              <Field label={t("h.tags.badges")}><Chips items={h.badges} /></Field>
              <Field label={t("h.tags.treatments")}><Chips items={h.treatmentTags} /></Field>
              <Field label={t("h.tags.subways")}><Chips items={h.subwayNames} /></Field>
            </dl>
          </Section>

          {/* Documents belong to a registration request: a hospital that was not created through one has none to show. */}
          {h.appliedBy ? (
            <Section id="sec-d-docs" title={t("h.docs.title")} desc={t("h.docs.desc")}>
              <ul className="docs">
                <DocumentRow name={t("h.docs.business")} submitted={detail.documents.business} href={legacyDocumentUrl(h.id, "business")} />
                <DocumentRow name={t("h.docs.medical")} submitted={detail.documents.medical} href={legacyDocumentUrl(h.id, "medical")} />
              </ul>
            </Section>
          ) : null}

          <Images detail={detail} onZoom={setZoom} />

          <Section id="sec-d-doctors" title={t("h.doc.title")} desc={t("h.doc.desc")}>
            {detail.doctors.length > 0 ? (
              <ul className="doctors">
                {detail.doctors.map((doctor) => (
                  <Doctor key={doctor.id} doctor={doctor} onZoom={setZoom} />
                ))}
              </ul>
            ) : (
              <p className="panel panel--empty">{t("h.doc.empty")}</p>
            )}
          </Section>

          <Section id="sec-d-revisions" title={t("h.rev.title")} desc={revisionsDesc}>
            {reviewable ? (
              <div className="section__tools">
                <button type="button" className="btn btn--primary" onClick={() => askConfirm("review")}>
                  <Icon name="check" size={18} />
                  <span>{t("h.rev.confirmBtn")}</span>
                </button>
              </div>
            ) : null}
            {detail.revisions.length > 0 ? (
              <ul className="revs">
                {detail.revisions.map((rev) => {
                  const key = REVISION_FIELD_KEYS.get(snake(rev.field));
                  const long = rev.oldValue.length > REVISION_VALUE_MAX || rev.newValue.length > REVISION_VALUE_MAX;
                  return (
                    <li key={rev.id} className="rev">
                      <p className="rev__field">{key ? t(key) : rev.field}</p>
                      <p className="rev__change">
                        <del className="rev__old" title={t("h.rev.old")}>{clip(rev.oldValue) || "—"}</del>
                        <span className="rev__arrow" aria-hidden="true">→</span>
                        <ins className="rev__new" title={t("h.rev.new")}>{clip(rev.newValue) || "—"}</ins>
                      </p>
                      <p className="rev__time">{formatDayTime(rev.changedAt)}</p>
                      {long ? (
                        <details className="rev__full">
                          <summary>{t("h.rev.full")}</summary>
                          <dl>
                            <dt>{t("h.rev.old")}</dt>
                            <dd className="prewrap">{rev.oldValue || "—"}</dd>
                            <dt>{t("h.rev.new")}</dt>
                            <dd className="prewrap">{rev.newValue || "—"}</dd>
                          </dl>
                        </details>
                      ) : null}
                    </li>
                  );
                })}
              </ul>
            ) : (
              <p className="panel panel--empty">{t("h.rev.empty")}</p>
            )}
          </Section>

          {canDelete ? (
            <Section id="sec-d-danger" title={t("hd.zone.title")} desc={t("hd.zone.desc")}>
              <div className="danger-zone">
                <button type="button" className="btn btn--danger" onClick={() => setDeleting(true)} title={t("hd.zone.btnTitle")}>
                  <Icon name="trash" size={18} />
                  <span>{t("hd.zone.btn")}</span>
                </button>
              </div>
            </Section>
          ) : null}
          {deleting ? (
            <DeleteHospitalDialog
              hospitalId={h.id}
              hospitalName={h.name}
              isActive={h.isActive}
              updatedAt={h.updatedAt}
              onClose={() => setDeleting(false)}
              onDeactivated={() => {
                setDeleting(false);
                setFlash("inactive");
                retry();
              }}
              onReload={() => {
                setDeleting(false);
                retry();
              }}
            />
          ) : null}

          <ImageDialog image={zoom} onClose={() => setZoom(null)} />
          <ConfirmDialog
            spec={confirmSpec}
            busy={working}
            error={confirmError ? t(confirmError) : null}
            onConfirm={runConfirm}
            onClose={() => {
              if (!working) setConfirm(null);
            }}
          />
        </>
      ) : null}
    </AdminShell>
  );
}

/** One labelled value. A missing value is drawn as a dash, so a row never collapses. */
function Field({ label, wide, children }: { label: string; wide?: boolean; children: ReactNode }) {
  return (
    <div className={wide ? "dl__item dl__item--wide" : "dl__item"}>
      <dt>{label}</dt>
      <dd>{children}</dd>
    </div>
  );
}

const orDash = (value: string): string => value.trim() || "—";

/** A web address the hospital entered: a link when it is a real http(s) address, plain text otherwise. */
function WebLink({ value }: { value: string }) {
  const href = externalHref(value);
  if (!value.trim()) return "—";
  if (!href) return value;
  return <a className="link" href={href} target="_blank" rel="noopener noreferrer">{value}</a>;
}

function BasicInfo({ detail }: { detail: HospitalDetailData }) {
  const t = useT();
  const h = detail.hospital;
  const contact = [h.contactName.trim(), h.contactPhone.trim()].filter(Boolean).join(" / ");
  return (
    <Section id="sec-d-basic" title={t("h.basic.title")} desc={t("h.basic.desc")}>
      <dl className="panel dl">
        <Field label={t("h.f.name")}>{orDash(h.name)}</Field>
        <Field label={t("h.f.owner")}>{orDash(h.ownerName)}</Field>
        <Field label={t("h.f.businessNumber")}>{orDash(h.businessNumber)}</Field>
        <Field label={t("h.f.email")}>{orDash(h.email)}</Field>
        <Field label={t("h.f.phone")}>{orDash(h.phone)}</Field>
        <Field label={t("h.f.contact")}>{orDash(contact)}</Field>
        <Field label={t("h.f.address")} wide>{orDash(h.address)}</Field>
        <Field label={t("h.f.website")}><WebLink value={h.website} /></Field>
        <Field label={t("h.f.sns")}><WebLink value={h.snsUrl} /></Field>
        <Field label={t("h.f.kakao")}><WebLink value={h.kakaoChannelUrl} /></Field>
        <Field label={t("h.f.intro")} wide><span className="prewrap">{orDash(h.introduction)}</span></Field>
        {h.appliedBy ? (
          <>
            <Field label={t("h.f.appliedBy")}>{h.appliedBy}</Field>
            <Field label={t("h.f.appliedAt")}>{formatDayTime(h.appliedAt)}</Field>
          </>
        ) : null}
      </dl>
    </Section>
  );
}

/** `onToggle(nextActive)` is given only to a super admin while changes are allowed: the hospital's switch is then a button. */
function StatusInfo({ detail, onToggle }: { detail: HospitalDetailData; onToggle: ((nextActive: boolean) => void) | null }) {
  const t = useT();
  const h = detail.hospital;
  const statusKey = STATUS_KEYS.get(h.status);
  const location = h.latitude !== null && h.longitude !== null && (h.latitude !== 0 || h.longitude !== 0) ? `${h.latitude}, ${h.longitude}` : "—";
  return (
    <Section id="sec-d-status" title={t("h.status.title")} desc={t("h.status.desc")}>
      <dl className="panel dl dl--3">
        <Field label={t("h.status.review")}><Pill tone={h.status === "approved" ? "on" : "off"}>{statusKey ? t(statusKey) : h.status || "—"}</Pill></Field>
        <Field label={t("h.col.active")}><ActivePill active={h.isActive} /></Field>
        <Field label={t("h.status.grade")}><GradePill grade={h.adGrade} long /></Field>
        <Field label={t("h.col.rating")}>{t("h.status.ratingValue", { rating: h.rating.toFixed(1) })}</Field>
        <Field label={t("h.status.reviews")}>{t("h.status.reviewsValue", { n: formatNumber(h.reviewCount) })}</Field>
        <Field label={t("h.status.showRating")}><Pill tone={h.showRating ? "on" : "off"}>{h.showRating ? t("h.status.shown") : t("h.status.hidden")}</Pill></Field>
        <Field label={t("h.status.location")}>{location}</Field>
        <Field label={t("h.col.created")}>{formatDayTime(h.createdAt)}</Field>
        <Field label={t("h.status.updated")}>{formatDayTime(h.updatedAt)}</Field>
      </dl>
      {onToggle ? (
        <div className="status-actions">
          <button type="button" className="btn btn--ghost" onClick={() => onToggle(!h.isActive)}>
            {h.isActive ? t("h.act.deactivate") : t("h.act.activate")}
          </button>
        </div>
      ) : null}
    </Section>
  );
}

function Hours({ hours }: { hours: Record<string, unknown> }) {
  const t = useT();
  if (!hasBusinessHours(hours)) {
    return (
      <Section id="sec-d-hours" title={t("h.hours.title")} desc={t("h.hours.desc")}>
        <p className="panel panel--empty">{t("h.hours.empty")}</p>
      </Section>
    );
  }
  const lunch = lunchBreak(hours);
  const closedDates = specialClosedDates(hours);
  const openDates = specialOpenDates(hours);
  return (
    <Section id="sec-d-hours" title={t("h.hours.title")} desc={t("h.hours.desc")}>
      <div className="panel">
        <ul className="hours">
          {WEEKDAYS.map((day) => {
            const info = dayHours(hours[day]);
            return (
              <li key={day} className="hours__row">
                <span className="hours__day">{t(DAY_KEYS[day])}</span>
                <span className="hours__time">{info.open ? info.hours || "—" : "—"}</span>
                {info.open ? (
                  <Pill tone="on">{info.biweekly === "" ? t("h.hours.open") : t(`h.hours.biweekly.${info.biweekly}`)}</Pill>
                ) : (
                  <Pill tone="off">{t(`h.hours.${info.reason}`)}</Pill>
                )}
              </li>
            );
          })}
        </ul>
        <dl className="dl dl--3 hours__extra">
          <Field label={t("h.hours.lunch")}>{lunch === null ? t("h.hours.none") : lunch || "—"}</Field>
          <Field label={t("h.hours.closedDates")}>{closedDates.length > 0 ? closedDates.join(", ") : t("h.hours.none")}</Field>
          <Field label={t("h.hours.openDates")}>{openDates.length > 0 ? openDates.join(", ") : t("h.hours.none")}</Field>
        </dl>
      </div>
    </Section>
  );
}

function DocumentRow({ name, submitted, href }: { name: string; submitted: boolean; href: string }) {
  const t = useT();
  return (
    <li className="panel doc">
      <div className="doc__text">
        <p className="doc__name">{name}</p>
        <p className="doc__note">{t("h.docs.required")}</p>
      </div>
      <Pill tone={submitted ? "on" : "warn"}>{submitted ? t("h.docs.submitted") : t("h.docs.missing")}</Pill>
      {submitted ? (
        <a className="txtbtn" href={href} target="_blank" rel="noopener noreferrer" aria-label={t("h.docs.openOf", { name })}>
          <Icon name="external" size={16} />
          <span>{t("h.docs.open")}</span>
        </a>
      ) : null}
    </li>
  );
}

/** A picture that opens at full size when pressed. The button takes its name from the picture's alt text. */
function Thumb({ src, alt, className, onZoom }: { src: string; alt: string; className?: string; onZoom: (image: ZoomImage) => void }) {
  const t = useT();
  return (
    <button type="button" className={className ? `thumb ${className}` : "thumb"} title={t("h.img.zoom")} onClick={() => onZoom({ src, alt })}>
      {/* eslint-disable-next-line @next/next/no-img-element -- stored upload from the old panel or an outside host: shown as it is, no optimizer */}
      <img src={src} alt={alt} loading="lazy" decoding="async" />
    </button>
  );
}

function Images({ detail, onZoom }: { detail: HospitalDetailData; onZoom: (image: ZoomImage) => void }) {
  const t = useT();
  const h = detail.hospital;
  const name = h.name || t("h.noName");
  const typeLabel = (type: string): string => {
    const key = IMAGE_TYPE_KEYS.get(type);
    return key ? t(key) : type || t("h.img.type.etc");
  };

  const cover = [
    { key: "main", label: t("h.img.main"), src: mediaUrl(h.mainImageUrl) },
    { key: "profile", label: t("h.img.profile"), src: mediaUrl(h.profileImageUrl) },
  ].filter((item) => item.src !== "");

  // Known kinds first, in a fixed order; unknown kinds after them in the order they first appear.
  const byType = new Map<string, HospitalImage[]>();
  for (const image of detail.images) {
    if (mediaUrl(image.url) === "") continue;
    const type = image.type.trim();
    byType.set(type, [...(byType.get(type) ?? []), image]);
  }
  const order = [...IMAGE_TYPES.filter((type) => byType.has(type)), ...[...byType.keys()].filter((type) => !(IMAGE_TYPES as readonly string[]).includes(type))];
  const count = [...byType.values()].reduce((sum, items) => sum + items.length, 0);

  return (
    <Section id="sec-d-images" title={t("h.img.title")} desc={t("h.img.desc")}>
      {cover.length === 0 && count === 0 ? <p className="panel panel--empty">{t("h.img.empty")}</p> : null}
      {cover.length > 0 ? (
        <div className="panel gallery">
          <h3 className="gallery__title">{t("h.img.cover")}</h3>
          <ul className="gallery__grid">
            {cover.map((item) => (
              <li key={item.key} className="gallery__item">
                <Thumb src={item.src} alt={t("h.img.altCover", { name, kind: item.label })} onZoom={onZoom} />
                <p className="gallery__cap">{item.label}</p>
              </li>
            ))}
          </ul>
        </div>
      ) : null}
      {order.map((type) => {
        const items = byType.get(type) ?? [];
        const label = typeLabel(type);
        return (
          <div key={type || "-"} className="panel gallery">
            <h3 className="gallery__title">{t("h.img.group", { type: label, n: items.length })}</h3>
            <ul className="gallery__grid">
              {items.map((image, index) => (
                <li key={image.id} className="gallery__item">
                  <Thumb src={mediaUrl(image.url)} alt={t("h.img.alt", { name, type: label, n: index + 1 })} onZoom={onZoom} />
                  <p className="gallery__cap">
                    <span>{t("h.img.order", { n: image.sortOrder })}</span>
                    {image.isActive ? null : <Pill tone="off">{t("h.img.hidden")}</Pill>}
                  </p>
                </li>
              ))}
            </ul>
          </div>
        );
      })}
    </Section>
  );
}

function Doctor({ doctor, onZoom }: { doctor: HospitalDoctor; onZoom: (image: ZoomImage) => void }) {
  const t = useT();
  const photo = mediaUrl(doctor.photoUrl);
  const name = doctor.name || t("h.noName");
  const statusKey = DOCTOR_STATUS_KEYS.get(doctor.status);
  return (
    <li className="panel doctor">
      {photo ? <Thumb className="doctor__photo" src={photo} alt={t("h.doc.photoAlt", { name })} onZoom={onZoom} /> : null}
      <div className="doctor__text">
        <p className="doctor__head">
          <span className="doctor__name">{name}</span>
          {doctor.status ? <Pill tone={doctor.status === "active" ? "on" : "off"}>{statusKey ? t(statusKey) : doctor.status}</Pill> : null}
        </p>
        <p className="doctor__spec">{doctor.specialty.trim() || t("h.doc.noSpecialty")}</p>
        <dl className="doctor__facts">
          <div>
            <dt>{t("h.doc.experience")}</dt>
            <dd>{t("h.doc.years", { n: doctor.experience })}</dd>
          </div>
          {doctor.qualification.trim() ? (
            <div>
              <dt>{t("h.doc.qualification")}</dt>
              <dd className="prewrap">{doctor.qualification}</dd>
            </div>
          ) : null}
        </dl>
        {doctor.bio.trim() ? <p className="doctor__bio prewrap">{doctor.bio}</p> : null}
      </div>
    </li>
  );
}
