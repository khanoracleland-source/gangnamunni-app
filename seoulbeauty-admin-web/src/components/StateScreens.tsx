import type { ReactNode } from "react";
import Icon, { type IconName } from "./Icon";
import { PHP_ADMIN_URL } from "@/lib/config";

function StatePanel({ icon, title, children, actions, role }: { icon: IconName; title: string; children: ReactNode; actions: ReactNode; role: "alert" | "status" }) {
  return (
    <section className="panel" role={role}>
      <h2 className="panel__head">
        <Icon name={icon} size={16} />
        {title}
      </h2>
      <div className="panel__body">
        <p className="panel__text">{children}</p>
        <div className="panel__actions">{actions}</div>
      </div>
    </section>
  );
}

const backLink = (
  <a className="btn btn--secondary" href={PHP_ADMIN_URL}>
    <Icon name="external" />
    기존 관리자로 이동
  </a>
);

/** Signed in, but not as a super admin. */
export function ForbiddenScreen() {
  return (
    <StatePanel icon="lock" title="최고관리자만 접근할 수 있습니다" actions={backLink} role="alert">
      이 페이지는 최고관리자 계정으로만 볼 수 있습니다. 다른 계정으로 로그인했다면 기존 관리자에서 로그아웃한 뒤 다시 로그인해 주세요.
    </StatePanel>
  );
}

export type ErrorKind = "disabled" | "session" | "network";

const ERROR_TEXT: Record<ErrorKind, { title: string; body: string }> = {
  // 404: the admin API is switched off on the server
  disabled: { title: "관리자 패널이 아직 열리지 않았습니다", body: "새 관리자 패널이 서버에서 비활성화되어 있습니다. 활성화된 뒤 다시 시도해 주세요." },
  // 503: the API could not confirm who is signed in
  session: { title: "로그인 상태를 확인할 수 없습니다", body: "로그인 확인 서버가 응답하지 않습니다. 잠시 후 다시 시도해 주세요." },
  network: { title: "데이터를 불러오지 못했습니다", body: "서버에 연결할 수 없거나 응답이 올바르지 않습니다. 잠시 후 다시 시도해 주세요." },
};

export function ErrorScreen({ kind, onRetry, retrying }: { kind: ErrorKind; onRetry: () => void; retrying: boolean }) {
  const text = ERROR_TEXT[kind];
  return (
    <StatePanel
      icon="alert"
      title={text.title}
      role="alert"
      actions={
        <>
          <button type="button" className="btn btn--warning" onClick={onRetry} disabled={retrying}>
            <Icon name="refresh" className={retrying ? "is-spinning" : undefined} />
            다시 시도
          </button>
          {backLink}
        </>
      }
    >
      {text.body}
    </StatePanel>
  );
}

/** Shown for the moment between a 401 and the browser leaving for the PHP login page. */
export function RedirectingScreen() {
  return (
    <StatePanel icon="lock" title="로그인이 필요합니다" actions={backLink} role="status">
      로그인 페이지로 이동하는 중입니다…
    </StatePanel>
  );
}
