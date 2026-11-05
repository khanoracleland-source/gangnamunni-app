"use client";
import { useEffect, useRef, useState } from "react";
import type { HospitalDetail } from "@/lib/api";
import styles from "./HospitalMap.module.css";

/* eslint-disable @typescript-eslint/no-explicit-any -- the three map SDKs are untyped globals loaded at runtime */
declare global { interface Window { kakao?: any; google?: any; L?: any } }

const loaded = new Map<string, Promise<void>>();
/** Load an external script once (SDKs are only fetched when a map is actually on screen). */
function loadScript(src: string): Promise<void> {
  if (!loaded.has(src)) {
    loaded.set(src, new Promise<void>((resolve, reject) => {
      const el = document.createElement("script");
      el.src = src; el.async = true;
      el.onload = () => resolve(); el.onerror = () => { loaded.delete(src); reject(new Error("script")); };
      document.head.appendChild(el);
    }));
  }
  return loaded.get(src)!;
}
function loadCss(href: string) {
  if (document.querySelector(`link[href="${href}"]`)) return;
  const el = document.createElement("link"); el.rel = "stylesheet"; el.href = href; document.head.appendChild(el);
}

type Props = { hospital: Pick<HospitalDetail, "name" | "address" | "latitude" | "longitude" | "map"> };

/**
 * detail.php 오시는 길 (includes/kakao_map.php) for the Next detail page, drawn in the 05-04 frame (rounded 153px map,
 * no map-app buttons — the design has none). Same rules as PHP: the address is geocoded first and the stored
 * coordinates are the fallback, so the marker matches the address shown; provider kakao → google → osm.
 */
export default function HospitalMap({ hospital: h }: Props) {
  const box = useRef<HTMLDivElement>(null);
  const [status, setStatus] = useState<"loading" | "ok" | "error">("loading");
  const hasCoords = !!(h.latitude && h.longitude);
  const addr = h.address ?? "";
  const canShow = hasCoords || addr !== "";
  // an API without the map field (older build / cached answer) → OSM, never a crash
  const provider = h.map?.provider ?? "osm", key = h.map?.key ?? null;

  useEffect(() => {
    if (!canShow || !box.current) return;
    let cancelled = false;
    const el = box.current;
    const done = () => { if (!cancelled) setStatus("ok"); };
    const fail = () => { if (!cancelled) setStatus("error"); };
    const fallback = (): [number, number] => (hasCoords ? [h.latitude!, h.longitude!] : [37.5665, 126.978]);

    // A wrong key or an unregistered domain leaves the SDK silent (Kakao's maps.load never calls back, Google fires
    // gm_authFailure), so every provider step races a timeout and falls through to OSM instead of a skeleton forever.
    const withTimeout = <T,>(p: Promise<T>, ms: number) => Promise.race([p, new Promise<T>((_, rej) => window.setTimeout(() => rej(new Error("timeout")), ms))]);

    const kakao = async (key: string) => {
      await withTimeout(loadScript(`https://dapi.kakao.com/v2/maps/sdk.js?appkey=${encodeURIComponent(key)}&libraries=services&autoload=false`), 8000);
      const k = window.kakao; if (!k?.maps) throw new Error("kakao");
      await withTimeout(new Promise<void>((r) => k.maps.load(r)), 8000);
      const draw = (lat: number, lng: number) => {
        if (cancelled) return;
        const center = new k.maps.LatLng(lat, lng);
        const map = new k.maps.Map(el, { center, level: 3, draggable: true, scrollwheel: false });
        const marker = new k.maps.Marker({ map, position: center });
        if (h.name) new k.maps.InfoWindow({ content: `<div style="padding:5px 10px;font-size:12px;white-space:nowrap;">${h.name.replace(/[<>&]/g, "")}</div>` }).open(map, marker);
        window.setTimeout(() => map.relayout(), 300);
        done();
      };
      const geocode = () => new Promise<[number, number] | null>((r) => {
        if (!addr) { r(null); return; }
        new k.maps.services.Geocoder().addressSearch(addr, (res: any[], st: string) => {
          if (st === k.maps.services.Status.OK && res[0]) { r([+res[0].y, +res[0].x]); return; }
          new k.maps.services.Places().keywordSearch(h.name || addr, (d: any[], st2: string) => r(st2 === k.maps.services.Status.OK && d[0] ? [+d[0].y, +d[0].x] : null));
        });
      });
      const g = await withTimeout(geocode(), 8000).catch(() => null);
      if (g) draw(g[0], g[1]); else if (hasCoords) draw(h.latitude!, h.longitude!); else throw new Error("kakao-geocode");
    };

    const google = async (key: string) => {
      let authFailed = false;
      (window as any).gm_authFailure = () => { authFailed = true; };
      await withTimeout(loadScript(`https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(key)}`), 8000);
      const g = window.google; if (!g?.maps || authFailed) throw new Error("google");
      const draw = (lat: number, lng: number) => {
        if (cancelled) return;
        const p = { lat, lng };
        const map = new g.maps.Map(el, { center: p, zoom: 16, mapTypeControl: false, streetViewControl: false, fullscreenControl: false });
        const marker = new g.maps.Marker({ map, position: p });
        if (h.name) new g.maps.InfoWindow({ content: `<div style="padding:4px 8px;font-size:12px;white-space:nowrap;">${h.name.replace(/[<>&]/g, "")}</div>` }).open(map, marker);
        done();
      };
      const geo = addr ? await withTimeout(new Promise<[number, number] | null>((r) => {
        new g.maps.Geocoder().geocode({ address: addr }, (res: any[], st: string) => r(st === "OK" && res[0] ? [res[0].geometry.location.lat(), res[0].geometry.location.lng()] : null));
      }), 8000).catch(() => null) : null;
      if (authFailed) throw new Error("google-auth");
      if (geo) draw(geo[0], geo[1]); else { const [a, b] = fallback(); draw(a, b); }
    };

    const osm = async () => {
      loadCss("https://unpkg.com/leaflet@1.9.4/dist/leaflet.css");
      await withTimeout(loadScript("https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"), 8000);
      const L = window.L; if (!L) throw new Error("leaflet");
      const draw = (lat: number, lng: number) => {
        if (cancelled) return;
        const map = L.map(el, { zoomControl: true, scrollWheelZoom: false }).setView([lat, lng], 16);
        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", { attribution: "&copy; OpenStreetMap", maxZoom: 19 }).addTo(map);
        const m = L.marker([lat, lng]).addTo(map);
        if (h.name) m.bindPopup(`<b>${h.name.replace(/[<>&]/g, "")}</b>`).openPopup();
        window.setTimeout(() => map.invalidateSize(), 300);
        done();
      };
      let geo: [number, number] | null = null;
      if (addr || h.name) {
        try {
          const r = await withTimeout(fetch(`https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(addr || h.name)}&format=json&limit=1&countrycodes=kr`), 6000);
          const j = await r.json();
          if (Array.isArray(j) && j[0]) geo = [parseFloat(j[0].lat), parseFloat(j[0].lon)];
        } catch { /* fall back to stored coords */ }
      }
      if (geo) draw(geo[0], geo[1]); else { const [a, b] = fallback(); draw(a, b); }
    };

    (async () => {
      try {
        if (provider === "kakao" && key) await kakao(key);
        else if (provider === "google" && key) await google(key);
        else await osm();
      } catch { try { if (!cancelled) await osm(); } catch { fail(); } }
    })();
    return () => { cancelled = true; };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [provider, key, h.latitude, h.longitude, addr, h.name]);

  if (!canShow) return null;
  return (
    <div className={styles.wrap}>
      <div className={styles.mapBox}>
        <div ref={box} className={styles.map} aria-label="병원 위치 지도" role="img" />
        {status === "loading" && <div className={`${styles.overlay} skeleton`} aria-hidden="true" />}
        {status === "error" && <div className={styles.overlay}>지도를 불러오지 못했습니다.</div>}
      </div>
    </div>
  );
}
