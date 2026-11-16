"use client";

import { useEffect, useRef, useState, type ChangeEvent } from "react";
import Icon from "../Icon";
import { Pill } from "./parts";
import { hospitalImages, uploadHospitalImages, type HospitalImage, type HospitalImageSet } from "@/lib/api";
import {
  DETAIL_LIMIT,
  INTERIOR_ACCEPT,
  MAIN_ACCEPT,
  checkImageFile,
  sameFile,
  slotsLeft,
  uploadFailureKey,
} from "@/lib/hospitalImages";
import { legacyImagesUrl, mediaUrl } from "@/lib/hospitals";
import type { MessageKey, Vars } from "@/lib/i18n";
import { useT } from "@/lib/locale";

const TYPE_KEYS = new Map<string, MessageKey>([
  ["main", "h.img.type.main"],
  ["logo", "h.img.type.logo"],
  ["exterior", "h.img.type.exterior"],
  ["interior", "h.img.type.interior"],
  ["facility", "h.img.type.facility"],
  ["etc", "h.img.type.etc"],
]);

type Problem = { key: MessageKey; vars?: Vars };
type Picked = { id: number; file: File; url: string };
type Status = { tone: "info" | "ok" | "err"; text: string } | null;

type Props = {
  hospitalId: number;
  /** The card has been opened at least once: the images are read then, not before. */
  opened: boolean;
  /** The stored images were read or changed. `updatedAt` is the hospital's new change time after an upload. */
  onChange: (info: { count: number; updatedAt?: string }) => void;
  /** The server says the admin session ended. */
  onUnauthorized: () => void;
};

/**
 * The body of the "add hospital images" card: choose a main image and detail images, preview them, and send
 * them in one request. It has its own save button and does not touch the hospital form's own changes.
 */
export default function HospitalImages({ hospitalId, opened, onChange, onUnauthorized }: Props) {
  const t = useT();
  // null = not answered yet.
  const [load, setLoad] = useState<{ set: HospitalImageSet } | { failed: true } | null>(null);
  const [tries, setTries] = useState(0);
  const [main, setMain] = useState<Picked | null>(null);
  const [interior, setInterior] = useState<Picked[]>([]);
  const [mainProblem, setMainProblem] = useState<Problem | null>(null);
  const [interiorProblems, setInteriorProblems] = useState<Problem[]>([]);
  const [uploading, setUploading] = useState(false);
  const [status, setStatus] = useState<Status>(null);

  // Object URLs of the previews: every one made is released when it is dropped and when the card goes away.
  const urls = useRef(new Set<string>());
  const nextId = useRef(1);
  const upload = useRef<AbortController | null>(null);
  const onChangeRef = useRef(onChange);
  const onUnauthorizedRef = useRef(onUnauthorized);
  useEffect(() => {
    onChangeRef.current = onChange;
    onUnauthorizedRef.current = onUnauthorized;
  });

  useEffect(() => {
    const held = urls.current;
    return () => {
      held.forEach((url) => URL.revokeObjectURL(url));
      held.clear();
      upload.current?.abort();
    };
  }, []);

  useEffect(() => {
    if (!opened) return;
    const controller = new AbortController();
    void hospitalImages(hospitalId, controller.signal).then((res) => {
      if (controller.signal.aborted) return;
      if (res.ok) {
        setLoad({ set: res.data });
        onChangeRef.current({ count: res.data.items.length });
      } else if (res.status === 401) {
        onUnauthorizedRef.current();
      } else {
        setLoad({ failed: true });
      }
    });
    return () => controller.abort();
  }, [opened, hospitalId, tries]);

  const retry = () => {
    setLoad(null);
    setTries((n) => n + 1);
  };

  const pick = (file: File): Picked => {
    const url = URL.createObjectURL(file);
    urls.current.add(url);
    return { id: nextId.current++, file, url };
  };
  const drop = (item: Picked | null) => {
    if (!item) return;
    URL.revokeObjectURL(item.url);
    urls.current.delete(item.url);
  };

  const set = load && "set" in load ? load.set : null;
  const stored = set ? set.detailCount : 0;
  const limit = set ? set.detailLimit : DETAIL_LIMIT;
  const left = slotsLeft(stored, interior.length, limit);
  const full = set !== null && stored >= limit;
  const busy = uploading || set === null;
  const nothing = main === null && interior.length === 0;

  const onMain = (e: ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    e.target.value = "";
    if (!file) return;
    setStatus(null);
    const problem = checkImageFile(file, "main");
    if (problem) {
      setMainProblem({ key: problem === "too_large" ? "h.e.img.err.largeMain" : "h.e.img.err.typeMain" });
      return;
    }
    setMainProblem(null);
    drop(main);
    setMain(pick(file));
  };
  const clearMain = () => {
    drop(main);
    setMain(null);
    setMainProblem(null);
  };

  const onInterior = (e: ChangeEvent<HTMLInputElement>) => {
    const incoming = Array.from(e.target.files ?? []);
    e.target.value = "";
    if (incoming.length === 0) return;
    setStatus(null);
    const accepted: File[] = [];
    let badType = 0;
    let tooLarge = 0;
    let overflow = 0;
    for (const file of incoming) {
      if (interior.some((item) => sameFile(item.file, file)) || accepted.some((other) => sameFile(other, file))) continue;
      const problem = checkImageFile(file, "interior");
      if (problem === "invalid_type") badType += 1;
      else if (problem === "too_large") tooLarge += 1;
      else if (accepted.length < left) accepted.push(file);
      else overflow += 1;
    }
    const problems: Problem[] = [];
    if (badType > 0) problems.push({ key: "h.e.img.err.skipType", vars: { n: badType } });
    if (tooLarge > 0) problems.push({ key: "h.e.img.err.skipLarge", vars: { n: tooLarge } });
    if (overflow > 0) problems.push({ key: "h.e.img.err.over", vars: { n: overflow, left } });
    setInteriorProblems(problems);
    if (accepted.length > 0) setInterior((prev) => [...prev, ...accepted.map(pick)]);
  };
  const removeInterior = (item: Picked) => {
    drop(item);
    setInterior((prev) => prev.filter((other) => other.id !== item.id));
    setInteriorProblems([]);
  };

  const save = async () => {
    if (busy || nothing) return;
    setStatus(null);
    setMainProblem(null);
    setInteriorProblems([]);
    const controller = new AbortController();
    upload.current = controller;
    setUploading(true);
    const res = await uploadHospitalImages(hospitalId, { main: main?.file, interior: interior.map((item) => item.file) }, controller.signal);
    if (controller.signal.aborted) return;
    upload.current = null;
    setUploading(false);
    if (res.ok) {
      drop(main);
      interior.forEach(drop);
      setMain(null);
      setInterior([]);
      setLoad({ set: res.data });
      setStatus({ tone: "ok", text: t("h.e.img.saved", { n: res.data.added }) });
      // Only a main-image upload moves the hospital's change time; for interior-only uploads the server value may include someone else's edit.
      onChangeRef.current({ count: res.data.items.length, updatedAt: main ? res.data.updatedAt || undefined : undefined });
      return;
    }
    if (res.status === 401) {
      onUnauthorizedRef.current();
      return;
    }
    const failure = uploadFailureKey(res);
    const key = failure.key === "h.e.img.err.many" && res.remaining == null ? "h.e.img.err.manyAny" : failure.key;
    const problem: Problem = { key, vars: { n: res.remaining ?? 0 } };
    if (failure.field === "main") setMainProblem(problem);
    else if (failure.field === "interior") setInteriorProblems([problem]);
    setStatus({ tone: "err", text: failure.field === "all" ? t(failure.key, problem.vars) : t("h.e.img.err.short") });
  };

  const typeLabel = (type: string): string => {
    const key = TYPE_KEYS.get(type.trim());
    return key ? t(key) : type.trim() || "—";
  };
  const currentMain = set?.mainUrl ? mediaUrl(set.mainUrl) : "";

  if (load === null) return <p className="field__hint" role="status">{t("h.e.img.loading")}</p>;
  if (set === null) {
    return (
      <div className="himg__retry">
        <p className="field__error" role="alert">{t("h.e.img.loadFail")}</p>
        <button type="button" className="btn btn--ghost" onClick={retry}>
          <Icon name="refresh" size={18} />
          <span>{t("h.e.img.retry")}</span>
        </button>
      </div>
    );
  }

  return (
    <div className="himg">
      <div className="himg__grid">
        <div className="himg__panel" role="group" aria-labelledby={`himg-main-t-${hospitalId}`}>
          <div className="himg__head">
            <Icon name="image" size={18} />
            <h3 className="himg__title" id={`himg-main-t-${hospitalId}`}>{t("h.e.img.main")}</h3>
          </div>
          <div className="himg__pickrow">
            <label className={uploading ? "btn btn--ghost himg__pick himg__pick--off" : "btn btn--ghost himg__pick"}>
              <input type="file" className="sr-only" accept={MAIN_ACCEPT} onChange={onMain} disabled={uploading} aria-label={t("h.e.img.pickMainLabel")} />
              <span>{t("h.e.img.pick")}</span>
            </label>
            {main ? <span className="himg__fname" translate="no">{main.file.name}</span> : <span className="himg__fname">{t("h.e.img.noFile")}</span>}
          </div>
          <p className="field__hint">{t("h.e.img.mainHint")}</p>
          {mainProblem ? <p className="field__error" role="alert">{t(mainProblem.key, mainProblem.vars)}</p> : null}
          <div className="himg__previews">
            {currentMain ? (
              <figure className="himg__fig">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img className="himg__img" src={currentMain} alt={t("h.e.img.currentAlt")} loading="lazy" decoding="async" />
                <figcaption className="himg__cap">{t("h.e.img.current")}</figcaption>
              </figure>
            ) : (
              <p className="field__hint">{t("h.e.img.noMain")}</p>
            )}
            {main ? (
              <figure className="himg__fig">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img className="himg__img" src={main.url} alt={t("h.e.img.newAlt")} />
                <button type="button" className="himg__rm" onClick={clearMain} disabled={uploading} aria-label={t("h.e.img.removeNamed", { name: main.file.name })}>
                  <Icon name="close" size={16} />
                </button>
                <figcaption className="himg__cap">{t("h.e.img.new")}</figcaption>
              </figure>
            ) : null}
          </div>
        </div>

        <div className="himg__panel" role="group" aria-labelledby={`himg-int-t-${hospitalId}`}>
          <div className="himg__head">
            <Icon name="image" size={18} />
            <h3 className="himg__title" id={`himg-int-t-${hospitalId}`}>{t("h.e.img.interior")}</h3>
            <Pill tone="plain">{t("h.e.img.counter", { n: stored + interior.length, max: limit })}</Pill>
          </div>
          <div className="himg__pickrow">
            <label className={uploading || full ? "btn btn--ghost himg__pick himg__pick--off" : "btn btn--ghost himg__pick"}>
              <input type="file" className="sr-only" accept={INTERIOR_ACCEPT} multiple onChange={onInterior} disabled={uploading || left === 0} aria-label={t("h.e.img.pickInteriorLabel")} />
              <span>{t("h.e.img.pick")}</span>
            </label>
            <span className="himg__fname">{interior.length > 0 ? t("h.e.img.chosen", { n: interior.length }) : t("h.e.img.noFile")}</span>
          </div>
          <p className="field__hint">{t("h.e.img.interiorHint", { n: left })}</p>
          {full ? <p className="himg__full">{t("h.e.img.full", { n: limit })}</p> : null}
          {interiorProblems.map((problem) => (
            <p key={problem.key} className="field__error" role="alert">{t(problem.key, problem.vars)}</p>
          ))}
          {interior.length > 0 ? (
            <ul className="himg__previews himg__list" translate="no">
              {interior.map((item) => (
                <li key={item.id} className="himg__fig">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img className="himg__img" src={item.url} alt={item.file.name} />
                  <button type="button" className="himg__rm" onClick={() => removeInterior(item)} disabled={uploading} aria-label={t("h.e.img.removeNamed", { name: item.file.name })}>
                    <Icon name="close" size={16} />
                  </button>
                </li>
              ))}
            </ul>
          ) : null}
        </div>
      </div>

      <div className="himg__bar">
        <p className={status ? `himg__status himg__status--${status.tone}` : "himg__status"} role="status" aria-live="polite">
          {uploading ? t("h.e.img.uploading") : status ? status.text : ""}
        </p>
        <button type="button" className="btn btn--primary" onClick={() => void save()} disabled={busy || nothing}>
          {uploading ? t("h.e.img.uploading") : t("h.e.img.save")}
        </button>
      </div>

      <div className="himg__saved">
        {set.items.length > 0 ? (
          <ul className="himg__list">
            {set.items.map((image: HospitalImage) => (
              <li key={image.id} className="himg__fig">
                {/* eslint-disable-next-line @next/next/no-img-element */}
                <img className="himg__img" src={mediaUrl(image.url)} alt="" loading="lazy" decoding="async" />
                <p className="himg__cap">
                  <span>{typeLabel(image.type)}</span>
                  {image.isActive ? null : <Pill tone="off">{t("h.img.hidden")}</Pill>}
                </p>
              </li>
            ))}
          </ul>
        ) : (
          <p className="field__hint">{t("h.e.img.empty")}</p>
        )}
        <a className="btn btn--ghost himg__manage" href={legacyImagesUrl(hospitalId)}>
          <Icon name="external" size={18} />
          <span>{t("h.e.img.manage")}</span>
        </a>
      </div>
    </div>
  );
}
