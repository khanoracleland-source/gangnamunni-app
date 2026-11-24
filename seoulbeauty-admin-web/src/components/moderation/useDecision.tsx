"use client";

import { useRef, useState, type ReactNode } from "react";
import ConfirmDialog from "../hospitals/ConfirmDialog";
import { showUnitToast } from "../treatment-units/UnitToast";
import { decidePost, decideReview, type ModerationDecision, type ModerationState, type WriteResult } from "@/lib/api";
import { useT } from "@/lib/locale";

export type DecisionTarget = { id: number; title: string; revision: string };
type Kind = "review" | "post";

/**
 * Approve / reject one pending item. "승인" goes straight through; "반려" first asks in a dialog.
 * `reload` fetches the list again after every answer, so a decided item leaves the pending list
 * and the counts follow. `dialog` is the confirmation to render once next to the list.
 */
export function useDecision(kind: Kind, reload: () => void): {
  busy: boolean;
  approve: (item: DecisionTarget) => void;
  askReject: (item: DecisionTarget) => void;
  dialog: ReactNode;
} {
  const t = useT();
  const [working, setWorking] = useState(false);
  const [rejecting, setRejecting] = useState<DecisionTarget | null>(null);
  const [dialogError, setDialogError] = useState<string | null>(null);
  const lock = useRef(false);

  const toast = (tone: "ok" | "info" | "error", key: Parameters<typeof t>[0]) => showUnitToast({ tone, message: t(key), closeLabel: t("m.close") });

  /** Returns true when the answer settled the item (done, or no longer waiting), false for a failure worth retrying. */
  const run = async (item: DecisionTarget, decision: ModerationDecision): Promise<boolean> => {
    if (lock.current) return false;
    lock.current = true;
    setWorking(true);
    try {
      const send: (id: number, d: ModerationDecision, rev: string) => Promise<WriteResult<{ id: number; state: ModerationState }>> = kind === "review" ? decideReview : decidePost;
      const res = await send(item.id, decision, item.revision);
      if (res.ok) {
        toast("ok", decision === "approve" ? "mod.toast.approved" : "mod.toast.rejected");
        reload();
        return true;
      }
      if (res.status === 401) {
        window.location.reload();
        return false;
      }
      if (res.error === "changed") toast("error", "mod.toast.changed");
      else if (res.error === "already_done") toast("info", "mod.toast.done");
      else if (res.status === 404) toast("error", "mod.toast.missing");
      else {
        const key = res.status === 403 ? "mod.toast.forbidden" : "mod.toast.failed";
        if (decision === "reject") setDialogError(t(key));
        else toast("error", key);
        return false;
      }
      reload();
      return true;
    } finally {
      lock.current = false;
      setWorking(false);
    }
  };

  const approve = (item: DecisionTarget) => void run(item, "approve");
  const askReject = (item: DecisionTarget) => {
    setDialogError(null);
    setRejecting(item);
  };
  const close = () => {
    if (!working) setRejecting(null);
  };
  const confirmReject = async () => {
    if (!rejecting) return;
    if (await run(rejecting, "reject")) setRejecting(null);
  };

  const dialog = (
    <ConfirmDialog
      spec={
        rejecting
          ? {
              title: t("mod.rej.title"),
              name: rejecting.title,
              body: t(kind === "review" ? "mod.rej.body.rev" : "mod.rej.body.post"),
              confirmLabel: t("mod.act.reject"),
              cancelLabel: t("tmw.cancel"),
              focus: "cancel",
              confirmTone: "reject",
              busyLabel: t("mod.act.working"),
            }
          : null
      }
      busy={working}
      error={dialogError}
      onConfirm={() => void confirmReject()}
      onClose={close}
    />
  );

  return { busy: working, approve, askReject, dialog };
}

/** The two buttons under a pending card. */
export function DecisionActions({ busy, onApprove, onReject }: { busy: boolean; onApprove: () => void; onReject: () => void }) {
  const t = useT();
  return (
    <div className="mod-acts">
      <button type="button" className="btn btn--ghost btn--reject" onClick={onReject} disabled={busy}>{t("mod.act.reject")}</button>
      <button type="button" className="btn btn--primary" onClick={onApprove} disabled={busy}>{t("mod.act.approve")}</button>
    </div>
  );
}
