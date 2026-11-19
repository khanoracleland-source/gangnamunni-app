"use client";

import HoursEditor from "./HoursEditor";
import MapPreview from "./MapPreview";
import { Field, useErrorText } from "./fields";
import Icon from "../Icon";
import type { Draft, Errors } from "@/lib/hospitalApply";
import { useT } from "@/lib/locale";

/** The position a "find on map" press puts the marker at while the real address lookup is not connected. */
const FOUND = { lat: 37.5006, lng: 127.0364 };

/** 병원 정보: address with the map, opening hours, introduction and links. All of it is optional. */
export default function InfoSection({ draft, set, errors }: { draft: Draft; set: (patch: Partial<Draft>) => void; errors: Errors }) {
  const t = useT();
  const text = useErrorText();
  const placed = draft.lat !== null && draft.lng !== null;
  const find = () => {
    if (draft.address.trim() === "") return;
    set({ lat: FOUND.lat, lng: FOUND.lng });
  };

  return (
    <div className="ha-groups">
      <div className="ha-group">
        <h3 className="ha-group__h">{t("hap.g.address")}</h3>
        <Field id="f-address" label={t("hap.f.address")}>
          <div className="ha-inrow ha-inrow--wrap">
            <input id="f-address" className="input" type="text" maxLength={500} placeholder={t("hap.f.addressPh")} value={draft.address} autoComplete="street-address" onChange={(e) => set({ address: e.target.value })} onKeyDown={(e) => { if (e.key === "Enter") { e.preventDefault(); find(); } }} />
            <button type="button" className="btn btn--ghost" onClick={find} disabled={draft.address.trim() === ""}>
              <Icon name="search" size={16} />
              {placed ? t("hap.map.again") : t("hap.map.find")}
            </button>
          </div>
        </Field>
        <MapPreview lat={draft.lat} lng={draft.lng} onMove={(lat, lng) => set({ lat, lng })} />
        {placed ? (
          <div className="ha-coords">
            <span className="pill pill--plain">{t("hap.map.lat", { v: draft.lat!.toFixed(5) })}</span>
            <span className="pill pill--plain">{t("hap.map.lng", { v: draft.lng!.toFixed(5) })}</span>
          </div>
        ) : null}
      </div>

      <div className="ha-group">
        <h3 className="ha-group__h">{t("hap.g.hours")}</h3>
        <HoursEditor hours={draft.hours} onChange={(hours) => set({ hours })} />
      </div>

      <div className="ha-group">
        <h3 className="ha-group__h">{t("hap.g.intro")}</h3>
        <div className="form">
          <Field id="f-intro" label={t("hap.f.intro")} span>
            <textarea id="f-intro" className="input textarea ha-textarea" maxLength={2000} rows={4} value={draft.introduction} onChange={(e) => set({ introduction: e.target.value })} />
          </Field>
          <Field id="f-website" label={t("hap.f.website")} error={text(errors.website)}>
            <input id="f-website" className="input" type="url" inputMode="url" placeholder="https://" value={draft.website} autoComplete="off" aria-invalid={errors.website ? true : undefined} aria-describedby={errors.website ? "f-website-e" : undefined} onChange={(e) => set({ website: e.target.value })} />
          </Field>
          <Field id="f-sns" label={t("hap.f.sns")}>
            <input id="f-sns" className="input" type="text" placeholder="https://instagram.com/..." value={draft.snsUrl} autoComplete="off" onChange={(e) => set({ snsUrl: e.target.value })} />
          </Field>
        </div>
      </div>
    </div>
  );
}
