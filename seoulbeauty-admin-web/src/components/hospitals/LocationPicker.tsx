"use client";

import { useCallback, useEffect, useRef, useState, type KeyboardEvent } from "react";
import Icon from "../Icon";
import { Notice } from "./parts";
import { mapConfig } from "@/lib/api";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";

/* eslint-disable @typescript-eslint/no-explicit-any -- the Kakao Maps SDK is an untyped global loaded at runtime */
declare global { interface Window { kakao?: any } }

const SDK_TIMEOUT_MS = 8000;
/** Seoul city hall: where an empty map starts. */
const DEFAULT_CENTER: [number, number] = [37.5665, 126.978];
const LEVEL_PLACED = 3;
const LEVEL_EMPTY = 6;

function withTimeout<T>(p: Promise<T>, ms: number): Promise<T> {
  return new Promise<T>((resolve, reject) => {
    const timer = window.setTimeout(() => reject(new Error("timeout")), ms);
    p.then(
      (v) => { window.clearTimeout(timer); resolve(v); },
      (e) => { window.clearTimeout(timer); reject(e); },
    );
  });
}

// One SDK load per page: a wrong key or an unregistered domain leaves the SDK silent, so both steps race a timeout.
let sdk: { key: string; promise: Promise<any> } | null = null;

function loadKakao(key: string): Promise<any> {
  if (sdk && sdk.key === key) return sdk.promise;
  const promise = (async () => {
    if (!window.kakao?.maps) {
      await withTimeout(new Promise<void>((resolve, reject) => {
        const el = document.createElement("script");
        el.src = `https://dapi.kakao.com/v2/maps/sdk.js?appkey=${encodeURIComponent(key)}&libraries=services&autoload=false`;
        el.async = true;
        el.onload = () => resolve();
        el.onerror = () => { el.remove(); reject(new Error("script")); };
        document.head.appendChild(el);
      }), SDK_TIMEOUT_MS);
    }
    const k = window.kakao;
    if (!k?.maps) throw new Error("kakao");
    await withTimeout(new Promise<void>((resolve) => k.maps.load(resolve)), SDK_TIMEOUT_MS);
    if (!k.maps.services) throw new Error("services");
    return k;
  })();
  sdk = { key, promise };
  promise.catch(() => { if (sdk?.promise === promise) sdk = null; });
  return promise;
}

/** Calls `fn` once: the first of the SDK answer and the timeout wins. */
function once<A extends unknown[]>(fn: (...args: A) => void, onTimeout: () => void): (...args: A) => void {
  let done = false;
  window.setTimeout(() => { if (!done) { done = true; onTimeout(); } }, SDK_TIMEOUT_MS);
  return (...args) => { if (!done) { done = true; fn(...args); } };
}

/** Both fields hold a usable coordinate pair, or null. */
function validPair(lat: string, lng: string): [number, number] | null {
  if (lat.trim() === "" || lng.trim() === "") return null;
  const a = Number(lat);
  const b = Number(lng);
  if (!Number.isFinite(a) || !Number.isFinite(b) || Math.abs(a) > 90 || Math.abs(b) > 180) return null;
  return [a, b];
}

type Status = "idle" | "config" | "loading" | "ready" | "unavailable";
type Item = { title: string; addr: string; y: number; x: number };
type Note = { key: MessageKey; n?: number };

type Props = {
  /** The card that holds the picker is open. The map is only loaded the first time this is true. */
  open: boolean;
  address: string;
  lat: string;
  lng: string;
  /** A place was chosen or an address was standardised: write it into the address field. */
  onAddress: (value: string) => void;
  /** The marker moved: both coordinates, six decimals. */
  onCoords: (lat: string, lng: string) => void;
  /** The session is gone (the map key request answered 401). */
  onUnauthorized: () => void;
  /** Goes up each time the address field was edited and left: a quiet address lookup when there are no coordinates yet. */
  syncTick: number;
};

/**
 * Search box, address conversion and a Kakao map with a draggable marker for the hospital position.
 * The latitude and longitude fields stay in the form: this component only fills them and follows them.
 * Without a usable map (no key, blocked or slow SDK) it shows a note and the fields alone remain.
 */
export default function LocationPicker({ open, address, lat, lng, onAddress, onCoords, onUnauthorized, syncTick }: Props) {
  const t = useT();
  const mapEl = useRef<HTMLDivElement>(null);
  const searchEl = useRef<HTMLInputElement>(null);
  const kakaoRef = useRef<any>(null);
  const mapRef = useRef<any>(null);
  const markerRef = useRef<any>(null);
  const geocoderRef = useRef<any>(null);
  const placesRef = useRef<any>(null);
  const centerRef = useRef<[number, number]>(DEFAULT_CENTER);
  // The pair this component wrote last: the follow-the-fields effect ignores it, so a click does not re-centre the map.
  const emitted = useRef("");
  const started = useRef(false);
  const mounted = useRef(false);
  const handledTick = useRef(0);
  const searchSeq = useRef(0);
  const geoSeq = useRef(0);
  const latest = useRef({ address, lat, lng, onAddress, onCoords, onUnauthorized });

  const [status, setStatus] = useState<Status>("idle");
  const [query, setQuery] = useState("");
  const [items, setItems] = useState<Item[] | null>(null);
  const [note, setNote] = useState<Note | null>(null);

  useEffect(() => {
    latest.current = { address, lat, lng, onAddress, onCoords, onUnauthorized };
  });

  useEffect(() => {
    mounted.current = true;
    return () => {
      mounted.current = false;
    };
  }, []);

  /** Puts the marker at a point, writes the pair into the form and optionally centres / zooms the map. */
  const moveTo = useCallback((y: number, x: number, opts: { center: boolean; level?: number }) => {
    const kk = kakaoRef.current;
    const map = mapRef.current;
    const marker = markerRef.current;
    if (!kk || !map || !marker) return;
    const pos = new kk.maps.LatLng(y, x);
    marker.setPosition(pos);
    marker.setMap(map);
    if (opts.center) {
      map.setCenter(pos);
      centerRef.current = [y, x];
    }
    if (opts.level) map.setLevel(opts.level);
    const a = y.toFixed(6);
    const b = x.toFixed(6);
    emitted.current = `${a}|${b}`;
    latest.current.onCoords(a, b);
  }, []);

  const build = useCallback((kk: any) => {
    const el = mapEl.current;
    if (!el) return;
    const pair = validPair(latest.current.lat, latest.current.lng);
    const c = pair ?? DEFAULT_CENTER;
    centerRef.current = c;
    const center = new kk.maps.LatLng(c[0], c[1]);
    const map = new kk.maps.Map(el, { center, level: pair ? LEVEL_PLACED : LEVEL_EMPTY, scrollwheel: false });
    map.addControl(new kk.maps.ZoomControl(), kk.maps.ControlPosition.RIGHT);
    // Without coordinates there is no marker yet: the first click or search result places it.
    const marker = new kk.maps.Marker({ position: center, draggable: true });
    if (pair) marker.setMap(map);
    kk.maps.event.addListener(marker, "dragend", () => {
      const p = marker.getPosition();
      moveTo(p.getLat(), p.getLng(), { center: false });
    });
    kk.maps.event.addListener(map, "click", (e: any) => moveTo(e.latLng.getLat(), e.latLng.getLng(), { center: false }));
    kk.maps.event.addListener(map, "dragend", () => {
      const m = map.getCenter();
      centerRef.current = [m.getLat(), m.getLng()];
    });
    kakaoRef.current = kk;
    mapRef.current = map;
    markerRef.current = marker;
    geocoderRef.current = new kk.maps.services.Geocoder();
    placesRef.current = new kk.maps.services.Places();
  }, [moveTo]);

  // The SDK is fetched the first time the card is opened, never while it is folded.
  useEffect(() => {
    if (!open || started.current) return;
    started.current = true;
    const start = async () => {
      setStatus("config");
      const cfg = await mapConfig();
      if (!mounted.current) return;
      if (!cfg.ok) {
        latest.current.onUnauthorized();
        return;
      }
      if (cfg.data.provider !== "kakao" || !cfg.data.key) {
        setStatus("unavailable");
        return;
      }
      setStatus("loading");
      try {
        const kk = await loadKakao(cfg.data.key);
        if (!mounted.current) return;
        build(kk);
        setStatus("ready");
      } catch {
        if (mounted.current) setStatus("unavailable");
      }
    };
    void start();
  }, [open, build]);

  // The map was drawn while the card was still sliding open (or the window changed size): measure it again and keep the centre.
  useEffect(() => {
    if (!open || status !== "ready") return;
    const refit = () => {
      const map = mapRef.current;
      const kk = kakaoRef.current;
      if (!map || !kk) return;
      map.relayout();
      map.setCenter(new kk.maps.LatLng(centerRef.current[0], centerRef.current[1]));
    };
    refit();
    const timer = window.setTimeout(refit, 320);
    const panel = mapEl.current?.closest(".ecard__panel") ?? null;
    const onEnd = (e: Event) => {
      if ((e as TransitionEvent).propertyName === "grid-template-rows") refit();
    };
    panel?.addEventListener("transitionend", onEnd);
    window.addEventListener("resize", refit);
    return () => {
      window.clearTimeout(timer);
      panel?.removeEventListener("transitionend", onEnd);
      window.removeEventListener("resize", refit);
    };
  }, [open, status]);

  // Typed coordinates move the marker and the centre; the pair this component wrote itself is skipped.
  useEffect(() => {
    if (status !== "ready") return;
    if (`${lat}|${lng}` === emitted.current) return;
    emitted.current = "";
    const kk = kakaoRef.current;
    const map = mapRef.current;
    const marker = markerRef.current;
    if (!kk || !map || !marker) return;
    if (lat.trim() === "" && lng.trim() === "") {
      marker.setMap(null);
      return;
    }
    const pair = validPair(lat, lng);
    if (!pair) return;
    const pos = new kk.maps.LatLng(pair[0], pair[1]);
    marker.setPosition(pos);
    marker.setMap(map);
    map.setCenter(pos);
    centerRef.current = pair;
  }, [lat, lng, status]);

  /** Address field text → coordinates (address search first, then a place-name search). `silent`: no messages, address text untouched. */
  const geocodeAddress = useCallback((silent: boolean) => {
    if (!geocoderRef.current || !placesRef.current) return;
    const kk = kakaoRef.current;
    const q = latest.current.address.trim();
    if (q === "") {
      if (!silent) setNote({ key: "h.e.map.addrEmpty" });
      return;
    }
    // A hospital that already has a position is never moved by a quiet lookup.
    if (silent && validPair(latest.current.lat, latest.current.lng)) return;
    if (!silent) {
      setQuery(q);
      setItems(null);
    }
    const id = ++geoSeq.current;
    const live = () => id === geoSeq.current && mounted.current;
    const fail = () => {
      if (!silent && live()) setNote({ key: "h.e.map.geoFail" });
    };
    const found = (y: number, x: number, standard: string) => {
      if (!live()) return;
      if (!silent && standard) latest.current.onAddress(standard);
      moveTo(y, x, { center: true, level: LEVEL_PLACED });
      if (!silent) setNote({ key: "h.e.map.geoOk" });
    };
    if (!silent) setNote({ key: "h.e.map.searching" });
    const byPlace = () => {
      placesRef.current.keywordSearch(q, once((data: any[], st: string) => {
        if (st === kk.maps.services.Status.OK && data.length) found(Number(data[0].y), Number(data[0].x), data[0].road_address_name || data[0].address_name || q);
        else fail();
      }, fail));
    };
    geocoderRef.current.addressSearch(q, once((res: any[], st: string) => {
      if (st === kk.maps.services.Status.OK && res.length) found(Number(res[0].y), Number(res[0].x), res[0].road_address ? res[0].road_address.address_name : res[0].address_name);
      else byPlace();
    }, byPlace));
  }, [moveTo]);

  // Opened without a saved position: the search box shows the address and one quiet lookup places the marker (not saved until the form is).
  const openedLookup = useRef(false);
  useEffect(() => {
    if (status !== "ready" || openedLookup.current) return;
    openedLookup.current = true;
    const { address: addr, lat: la, lng: ln } = latest.current;
    if (addr.trim() === "") return;
    setQuery((q) => (q === "" ? addr.trim() : q));
    if (!validPair(la, ln)) geocodeAddress(true);
  }, [status, geocodeAddress]);

  // The address field was edited and left while the map could not answer yet: the quiet lookup runs once the map is ready.
  useEffect(() => {
    if (status !== "ready" || syncTick === handledTick.current) return;
    handledTick.current = syncTick;
    geocodeAddress(true);
  }, [syncTick, status, geocodeAddress]);

  const search = () => {
    const q = query.trim();
    const kk = kakaoRef.current;
    if (q === "" || !kk || status !== "ready") return;
    const id = ++searchSeq.current;
    setItems(null);
    setNote({ key: "h.e.map.searching" });
    const finish = (list: Item[]) => {
      if (id !== searchSeq.current || !mounted.current) return;
      setItems(list);
      setNote(list.length > 0 ? { key: "h.e.map.count", n: list.length } : { key: "h.e.map.empty" });
    };
    const byAddress = () => {
      geocoderRef.current.addressSearch(q, once((res: any[], st: string) => {
        if (st === kk.maps.services.Status.OK && res.length) {
          finish(res.map((r) => ({ title: r.address_name, addr: r.road_address ? r.road_address.address_name : "", y: Number(r.y), x: Number(r.x) })));
        } else finish([]);
      }, () => finish([])));
    };
    placesRef.current.keywordSearch(q, once((data: any[], st: string) => {
      if (st === kk.maps.services.Status.OK && data.length) {
        finish(data.map((d) => ({ title: d.place_name, addr: d.road_address_name || d.address_name, y: Number(d.y), x: Number(d.x) })));
      } else byAddress();
    }, byAddress));
  };

  const pick = (item: Item) => {
    if (!Number.isFinite(item.y) || !Number.isFinite(item.x)) return;
    latest.current.onAddress(item.addr || item.title || query.trim());
    moveTo(item.y, item.x, { center: true, level: LEVEL_PLACED });
    searchSeq.current++;
    setItems(null);
    setNote({ key: "h.e.map.picked" });
    searchEl.current?.focus();
  };

  const onKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
    if (e.key !== "Enter") return;
    // Enter here searches; it never submits the hospital form. A key that only confirms a Korean syllable is not a search.
    e.preventDefault();
    if (e.nativeEvent.isComposing) return;
    search();
  };

  const ready = status === "ready";
  return (
    <div className="mp">
      {status === "loading" || ready ? (
        <div className="mp__top">
          <span className="pill pill--info">{t("h.e.map.badge")}</span>
        </div>
      ) : null}
      {status === "unavailable" ? (
        <Notice icon="info">{t("h.e.map.unavailable")}</Notice>
      ) : (
        <>
          <div className="mp__search">
            <div className="field mp__q">
              <label className="field__label" htmlFor="f-mapq">{t("h.e.map.searchLabel")}</label>
              <input
                id="f-mapq"
                ref={searchEl}
                className="input"
                type="text"
                autoComplete="off"
                placeholder={t("h.e.map.placeholder")}
                value={query}
                disabled={!ready}
                onChange={(e) => setQuery(e.target.value)}
                onKeyDown={onKeyDown}
              />
            </div>
            <div className="mp__btns">
              <button type="button" className="btn btn--primary" disabled={!ready} onClick={search}>{t("h.e.map.search")}</button>
              <button type="button" className="btn btn--ghost" disabled={!ready} onClick={() => geocodeAddress(false)}>
                <Icon name="pin" size={18} />
                <span>{t("h.e.map.geocode")}</span>
              </button>
            </div>
          </div>
          <p className="field__hint">{t("h.e.map.hint")}</p>
          {items && items.length > 0 ? (
            <ul className="mp__list" role="list" aria-label={t("h.e.map.results")}>
              {items.map((item, index) => (
                <li key={`${index}-${item.y}-${item.x}`}>
                  <button type="button" className="mp__item" onClick={() => pick(item)}>
                    <span className="mp__name">{item.title}</span>
                    {item.addr ? <span className="mp__addr">{item.addr}</span> : null}
                  </button>
                </li>
              ))}
            </ul>
          ) : null}
          <p className="field__hint mp__status" role="status" aria-live="polite">{note ? t(note.key, note.n === undefined ? undefined : { n: note.n }) : ""}</p>
          <div className="mp__mapbox">
            <div ref={mapEl} className="mp__map" translate="no" role="group" aria-label={t("h.e.map.mapLabel")} />
            {ready ? null : <div className="mp__loading">{t("h.e.map.loading")}</div>}
          </div>
        </>
      )}
    </div>
  );
}
