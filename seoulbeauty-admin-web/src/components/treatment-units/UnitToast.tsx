"use client";

import { toast } from "sonner";
import Icon from "../Icon";
import type { IconName } from "../Icon";

export type UnitToastTone = "ok" | "info" | "error";
const ICONS: Record<UnitToastTone, IconName> = { ok: "check", info: "info", error: "alert" };

/** A short notice of the units page: a status icon, the sentence, an optional action (undo) and a 44px close button. Goes away by itself after 5 seconds. */
export function showUnitToast({
  tone,
  message,
  icon,
  closeLabel,
  action,
}: {
  tone: UnitToastTone;
  message: string;
  icon?: IconName;
  closeLabel: string;
  action?: { label: string; onClick: () => void };
}): void {
  toast.custom(
    (id) => (
      <div className={`units-toast units-toast--${tone}`} role="status">
        <span className="units-toast__ic"><Icon name={icon ?? ICONS[tone]} size={20} /></span>
        <p className="units-toast__tx">{message}</p>
        {action ? (
          <div className="units-toast__acts">
            <button
              type="button"
              className="units-toast__act"
              onClick={() => {
                toast.dismiss(id);
                action.onClick();
              }}
            >
              {action.label}
            </button>
          </div>
        ) : null}
        <button type="button" className="ibtn units-toast__cl" aria-label={closeLabel} title={closeLabel} onClick={() => toast.dismiss(id)}>
          <Icon name="close" size={18} />
        </button>
      </div>
    ),
    { duration: 5000 },
  );
}
