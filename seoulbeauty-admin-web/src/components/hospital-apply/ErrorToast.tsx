"use client";

import { toast } from "sonner";
import Icon from "../Icon";

export const APPLY_ERROR_ID = "hospital-apply-error";

/** The error notice stays until the admin closes it, so it carries its own close button and, when there is a field to go to, a button for that. */
export function showApplyError({ message, goLabel, closeLabel, onGo }: { message: string; goLabel: string | null; closeLabel: string; onGo: (() => void) | null }): void {
  toast.custom(
    (id) => (
      <div className="ha-toast" role="alert">
        <span className="ha-toast__ic"><Icon name="alert" size={20} /></span>
        <div className="ha-toast__main">
          <p>{message}</p>
          {goLabel && onGo ? (
            <button
              type="button"
              className="txtbtn"
              onClick={() => {
                onGo();
                toast.dismiss(id);
              }}
            >
              {goLabel}
            </button>
          ) : null}
        </div>
        <button type="button" className="ibtn" aria-label={closeLabel} onClick={() => toast.dismiss(id)}>
          <Icon name="close" size={18} />
        </button>
      </div>
    ),
    { id: APPLY_ERROR_ID, duration: Infinity },
  );
}

export const dismissApplyError = (): void => {
  toast.dismiss(APPLY_ERROR_ID);
};
