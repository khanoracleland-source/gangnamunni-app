"use client";

import { useRef, useState, type ReactNode } from "react";
import ConfirmDialog from "../hospitals/ConfirmDialog";
import { showUnitToast } from "../treatment-units/UnitToast";
import { deletePost, deleteReview } from "@/lib/api";
import type { MessageKey } from "@/lib/i18n";
import { useT } from "@/lib/locale";

export type DeleteTarget = { id: number; title: string; revision: string };
type Kind = "review" | "post";

/**
 * Removes one review or post for good. `ask` opens a confirmation that says what goes with it
 * (pictures, likes, comments, reports) and that it cannot be undone; the confirm button is the outlined red one
 * and the cancel button takes the focus first. After any answer that settles the item the list is loaded again
 * and `onSettled` closes the detail window; a failure keeps both open so it can be retried.
 * `dialog` is the confirmation to render next to the detail window.
 */
export function useDelete(kind: Kind, item: DeleteTarget, o: { reload: () => void; onSettled: () => void }): { ask: () => void; busy: boolean; dialog: ReactNode } {
  const t = useT();
  const [asking, setAsking] = useState(false);
  const [working, setWorking] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const lock = useRef(false);

  const toast = (tone: "ok" | "info" | "error", key: MessageKey) => showUnitToast({ tone, message: t(key), closeLabel: t("m.close") });

  const confirm = async () => {
    if (lock.current) return;
    lock.current = true;
    setWorking(true);
    setError(null);
    try {
      const res = await (kind === "review" ? deleteReview : deletePost)(item.id, item.revision);
      if (res.ok) {
        toast("ok", "mod.toast.deleted");
      } else if (res.status === 401) {
        window.location.reload();
        return;
      } else if (res.error === "changed") {
        toast("error", "mod.toast.changed");
      } else if (res.status === 404) {
        toast("error", "mod.toast.missing");
      } else {
        setError(t(res.status === 403 ? "mod.toast.forbidden" : "mod.toast.failed"));
        return;
      }
      setAsking(false);
      o.reload();
      o.onSettled();
    } finally {
      lock.current = false;
      setWorking(false);
    }
  };

  const dialog = (
    <ConfirmDialog
      spec={
        asking
          ? {
              title: t(kind === "review" ? "mod.del.title.rev" : "mod.del.title.post"),
              name: item.title,
              body: t(kind === "review" ? "mod.del.body.rev" : "mod.del.body.post"),
              note: t("mod.del.note"),
              confirmLabel: t("mod.act.delete"),
              cancelLabel: t("tmw.cancel"),
              focus: "cancel",
              confirmTone: "reject",
              busyLabel: t("mod.del.working"),
            }
          : null
      }
      busy={working}
      error={error}
      onConfirm={() => void confirm()}
      onClose={() => {
        if (!working) setAsking(false);
      }}
    />
  );

  return {
    ask: () => {
      setError(null);
      setAsking(true);
    },
    busy: working,
    dialog,
  };
}
