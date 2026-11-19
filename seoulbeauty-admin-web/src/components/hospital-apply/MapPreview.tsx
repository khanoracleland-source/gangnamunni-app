"use client";

import { useRef, useState, type KeyboardEvent, type PointerEvent } from "react";
import Icon from "../Icon";
import { useT } from "@/lib/locale";

/** The centre of the sample map and how many degrees the picture spans. */
const CENTER = { lat: 37.5006, lng: 127.0364 };
const SPAN = 0.02;
const NUDGE = 0.0004;

const clamp = (value: number, min: number, max: number): number => Math.min(max, Math.max(min, value));
const toPercent = (lat: number, lng: number): { x: number; y: number } => ({
  x: clamp(50 + ((lng - CENTER.lng) / SPAN) * 100, 4, 96),
  y: clamp(50 - ((lat - CENTER.lat) / SPAN) * 100, 8, 96),
});

/**
 * The location picture. It scrolls the page with a vertical swipe (the map itself never captures the gesture);
 * only the marker is dragged. The picture is a drawn stand-in for the Kakao map; the position it reports is in degrees.
 */
export default function MapPreview({ lat, lng, onMove }: { lat: number | null; lng: number | null; onMove: (lat: number, lng: number) => void }) {
  const t = useT();
  const boxRef = useRef<HTMLDivElement>(null);
  const [zoom, setZoom] = useState(1);
  const [dragging, setDragging] = useState(false);
  const placed = lat !== null && lng !== null;
  const pin = placed ? toPercent(lat, lng) : null;

  const moveTo = (clientX: number, clientY: number) => {
    const box = boxRef.current;
    if (!box) return;
    const rect = box.getBoundingClientRect();
    const x = clamp((clientX - rect.left) / rect.width, 0.04, 0.96);
    const y = clamp((clientY - rect.top) / rect.height, 0.08, 0.96);
    onMove(CENTER.lat - (y - 0.5) * SPAN, CENTER.lng + (x - 0.5) * SPAN);
  };
  const onDown = (e: PointerEvent<HTMLButtonElement>) => {
    e.currentTarget.setPointerCapture(e.pointerId);
    setDragging(true);
  };
  const onDrag = (e: PointerEvent<HTMLButtonElement>) => {
    if (dragging) moveTo(e.clientX, e.clientY);
  };
  const onKey = (e: KeyboardEvent<HTMLButtonElement>) => {
    if (!placed) return;
    const step: Record<string, [number, number]> = { ArrowUp: [NUDGE, 0], ArrowDown: [-NUDGE, 0], ArrowLeft: [0, -NUDGE], ArrowRight: [0, NUDGE] };
    const delta = step[e.key];
    if (!delta) return;
    e.preventDefault();
    onMove(lat + delta[0], lng + delta[1]);
  };

  return (
    <div className="ha-map" ref={boxRef} role="group" aria-label={t("hap.map.aria")}>
      <svg className="ha-map__bg" viewBox="0 0 600 300" preserveAspectRatio="xMidYMid slice" aria-hidden="true" style={{ transform: `scale(${zoom})` }}>
        <rect width="600" height="300" className="ha-map__land" />
        <path d="M0 210 C120 190 180 240 300 215 S500 170 600 200 V300 H0Z" className="ha-map__water" />
        <g className="ha-map__road ha-map__road--minor">
          <path d="M60 0 V300M180 0 V300M420 0 V300M540 0 V300" />
          <path d="M0 60 H600M0 140 H600" />
        </g>
        <g className="ha-map__road">
          <path d="M0 100 C150 90 250 130 400 110 S560 90 600 96" />
          <path d="M300 0 C310 90 290 190 310 300" />
        </g>
      </svg>
      <div className="ha-map__zoom">
        <button type="button" aria-label={t("hap.map.zoomIn")} onClick={() => setZoom((z) => Math.min(2, z + 0.25))}><Icon name="plus" size={18} /></button>
        <button type="button" aria-label={t("hap.map.zoomOut")} onClick={() => setZoom((z) => Math.max(1, z - 0.25))}><Icon name="minus" size={18} /></button>
      </div>
      {pin ? (
        <button
          type="button"
          className={dragging ? "ha-pin is-drag" : "ha-pin"}
          style={{ left: `${pin.x}%`, top: `${pin.y}%` }}
          aria-label={t("hap.map.marker")}
          onPointerDown={onDown}
          onPointerMove={onDrag}
          onPointerUp={() => setDragging(false)}
          onPointerCancel={() => setDragging(false)}
          onKeyDown={onKey}
        >
          <svg viewBox="0 0 40 48" width="40" height="48" aria-hidden="true">
            <path d="M20 46C20 46 4 29 4 18a16 16 0 0 1 32 0c0 11-16 28-16 28z" fill="currentColor" />
            <circle cx="20" cy="18" r="6" fill="var(--surface)" />
          </svg>
        </button>
      ) : null}
      <p className="ha-map__hint">
        <Icon name="pin" size={14} />
        <span>{placed ? t("hap.map.hint") : t("hap.map.empty")}</span>
      </p>
    </div>
  );
}
