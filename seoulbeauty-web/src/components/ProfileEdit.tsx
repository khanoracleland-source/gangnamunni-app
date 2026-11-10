"use client";
import Loading from "./Loading";
import { useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { api, ApiError, assetUrl, qk, type MyProfile } from "@/lib/api";
import { meKey } from "@/lib/me";
import { cleanNick, nickProblem, NICK_MAX } from "@/lib/nick";
import Link from "next/link";
import LoginPrompt from "./LoginPrompt";
import BottomSheet, { type BottomSheetHandle } from "./BottomSheet";
import { useToast } from "./Toast";
import mp from "./MyPages.module.css";
import styles from "./ProfileEdit.module.css";

type Err = { field: string; message: string } | null;

type Field = "nick" | "name" | "birth" | "hp";
const LABEL: Record<string, string> = { nick: "닉네임", name: "이름", birth: "생년월일", hp: "휴대폰 번호" };
const CAMERA = (
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
    <path d="M4 8h3l1.5-2h7L17 8h3v11H4z" /><circle cx="12" cy="13" r="3.4" />
  </svg>
);

/** 내 정보 수정 (design "어떤 정보를 수정하시겠어요?"): photo + one card per item, each with 변경. 닉네임/이름/생년월일/휴대폰 change in a bottom sheet, 이메일/비밀번호 on their own pages. */
function Form({ data }: { data: MyProfile }) {
  const router = useRouter();
  const qc = useQueryClient();
  const say = useToast();
  const [vals, setVals] = useState({ nick: data.nick, name: data.name, hp: data.hp, birth: data.birth });
  const [file, setFile] = useState<File | null>(null);
  const [preview, setPreview] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [err, setErr] = useState<Err>(null);
  const [leave, setLeave] = useState(false);
  const [edit, setEdit] = useState<Field | null>(null);
  const [draft, setDraft] = useState("");
  const sheetRef = useRef<BottomSheetHandle>(null);
  // Instagram-style live nickname check: format on every key, availability from the server shortly after typing stops
  const [nickState, setNickState] = useState<{ nick: string; ok: boolean; message: string; failed?: boolean } | null>(null);
  const [{ today, oldest }] = useState(() => {
    const d = new Date(Date.now() + 9 * 3600_000).toISOString().slice(0, 10); // KST, like the API
    return { today: d, oldest: `${Number(d.slice(0, 4)) - 120}${d.slice(4)}` };
  });
  const shown = preview ?? assetUrl(data.photoUrl);

  const pick = (f: File | null) => {
    if (preview) URL.revokeObjectURL(preview);
    setFile(f);
    setPreview(f ? URL.createObjectURL(f) : null);
  };
  const validate = (v: typeof vals): Err =>
    // required, but an item that is empty on file and left empty does not block saving the others (same rule as the API)
    !v.name.trim() && data.name.trim() !== "" ? { field: "name", message: "이름을 입력해주세요." }
    : !v.birth && data.birth !== "" ? { field: "birth", message: "올바른 생년월일을 입력해주세요." }
    : v.nick.trim() !== data.nick && nickProblem(v.nick.trim()) ? { field: "nick", message: nickProblem(v.nick.trim()) ?? "" } : null; // an unchanged (older) nickname is not re-checked

  /** The API saves the whole profile at once: send the current values with the changed one (and the picked photo, if any). */
  const save = async (v: typeof vals): Promise<boolean> => {
    if (saving) return false;
    const local = validate(v);
    if (local) { setErr(local); return false; }
    setSaving(true); setErr(null);
    const form = new FormData();
    form.set("nick", v.nick.trim()); form.set("name", v.name.trim()); form.set("hp", v.hp.trim()); form.set("birth", v.birth);
    if (file) form.set("photo", file);
    try {
      const r = await api.saveProfile(form);
      setVals({ ...v, nick: v.nick.trim(), name: v.name.trim(), hp: v.hp.trim() });
      qc.invalidateQueries({ queryKey: qk.mypage });
      qc.invalidateQueries({ queryKey: qk.myProfile });
      qc.invalidateQueries({ queryKey: meKey });
      if (file) pick(null);
      if (r.photoError) { setErr({ field: "photo", message: r.photoError }); return true; }
      say("저장되었습니다.");
      return true;
    } catch (e) {
      setErr(e instanceof ApiError ? { field: e.field ?? "form", message: e.message } : { field: "form", message: "저장하지 못했어요. 잠시 후 다시 시도해주세요." });
      return false;
    } finally { setSaving(false); }
  };

  useEffect(() => {
    if (edit !== "nick") return;
    const v = draft.trim();
    if (v === vals.nick || nickProblem(v)) return;
    let live = true;
    const t = window.setTimeout(() => {
      api.nickCheck(v)
        .then((r) => { if (live) setNickState({ nick: v, ok: r.available, message: r.message }); })
        // network / 429: don't block saving — the save request checks availability again and reports "already in use"
        .catch(() => { if (live) setNickState({ nick: v, ok: true, failed: true, message: "지금은 중복 확인을 할 수 없어요. 저장할 때 다시 확인해요." }); });
    }, 400);
    return () => { live = false; window.clearTimeout(t); };
  }, [draft, edit, vals.nick]);

  const open = (f: Field) => { setErr(null); setDraft(vals[f]); setNickState(null); setEdit(f); };
  const saveField = async () => {
    if (!edit) return;
    if (await save({ ...vals, [edit]: draft })) sheetRef.current?.close();
  };
  const back = () => { if (file) setLeave(true); else router.push("/mypage"); };
  // The API validates the whole profile, so the problem can be in another item than the one being edited (e.g. no birth date on file yet):
  // show it wherever the person is looking — in the open sheet, otherwise at the top — and name the item when it is not the one being edited.
  const errText = err ? (err.field in LABEL && err.field !== edit ? `${LABEL[err.field]}: ${err.message} 해당 항목의 '변경'에서 먼저 입력해주세요.` : err.message) : "";
  const sheetErr = edit ? errText : "";
  // what the nickname sheet says under the input (format problem → availability → hint)
  const nickMsg = ((): { text: string; tone: "bad" | "good" | "hint" } | null => {
    if (edit !== "nick") return null;
    const v = draft.trim();
    if (v === vals.nick) return null;
    if (v === "") return { text: "영문, 숫자, 밑줄(_), 마침표(.)를 사용할 수 있어요.", tone: "hint" };
    const bad = nickProblem(v);
    if (bad) return { text: bad, tone: "bad" };
    if (nickState && nickState.nick === v) return { text: nickState.message, tone: nickState.failed ? "hint" : nickState.ok ? "good" : "bad" };
    return { text: "확인 중…", tone: "hint" };
  })();
  const nickOk = edit !== "nick" || draft.trim() === vals.nick || (!nickProblem(draft.trim()) && nickState?.nick === draft.trim() && nickState.ok);

  const META: Record<Field, { label: string; sheet: string; ph: string }> = {
    nick: { label: "닉네임", sheet: "닉네임 변경", ph: "영문, 숫자, _ . 만 입력" },
    name: { label: "이름", sheet: "이름 변경", ph: "이름 입력" },
    birth: { label: "생년월일", sheet: "생년월일 변경", ph: "" },
    hp: { label: "휴대폰 번호", sheet: "휴대폰 번호 변경", ph: "010-0000-0000" },
  };
  const card = (label: string | null, value: string, action: React.ReactNode) => (
    <div className={styles.card}>
      {label && <div className={styles.cLabel}>{label}</div>}
      <div className={styles.cRow}><span className={styles.cVal}>{value || "-"}</span>{action}</div>
    </div>
  );
  const change = (f: Field) => <button type="button" className={styles.cBtn} onClick={() => open(f)}>변경</button>;
  const shownBirth = vals.birth ? vals.birth.replace(/-/g, ".") : "";

  return (
    <div className={styles.wrap}>
      <div className={mp.hd}>
        <button type="button" className={mp.hdBack} onClick={back} aria-label="뒤로 가기">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7" /></svg>
        </button>
        <span className={styles.title}>어떤 정보를 수정하시겠어요?</span>
        <div className={mp.hdRight} />
      </div>
      {err && !edit && <div className={styles.err} role="alert">{errText}</div>}
      <div className={styles.avatarArea}>
        {/* A div, not a label: the overlay input already receives the tap (a label would make Android WebView open the chooser twice). */}
        <div className={styles.avatarWrap}>
          <span className={styles.avatar}>
            {/* eslint-disable-next-line @next/next/no-img-element */}
            {shown ? <img src={shown} alt="프로필" /> : null}
          </span>
          <span className={styles.camera} aria-hidden="true">{CAMERA}</span>
          <input type="file" accept="image/*" className={styles.file} aria-label="프로필 사진 변경" onChange={(e) => { pick(e.target.files?.[0] ?? null); e.target.value = ""; }} />
        </div>
        <div className={styles.avatarText}>프로필 사진 변경</div>
        {file && <button type="button" className={styles.photoSave} disabled={saving} onClick={() => save(vals)}>{saving ? "저장 중…" : "사진 저장"}</button>}
      </div>
      <div className={styles.cards}>
        {card(META.nick.label, vals.nick, change("nick"))}
        {card(META.name.label, vals.name, change("name"))}
        {card("이메일", data.email, <Link className={styles.cBtn} href="/mypage/profile/email">변경</Link>)}
        {card(META.hp.label, vals.hp, change("hp"))}
        {card(META.birth.label, shownBirth, change("birth"))}
        {!data.social && card(null, "비밀번호 변경", <Link className={styles.cBtn} href="/mypage/profile/password">변경</Link>)}
      </div>

      <BottomSheet ref={sheetRef} open={edit !== null} onClose={() => { setEdit(null); setErr(null); }} label={edit ? META[edit].sheet : "정보 변경"} dismissible={!saving}>
        {edit && (
          <div className={styles.sheet}>
            <h2 className={styles.sheetTitle}>{META[edit].sheet}</h2>
            <input className={styles.input} autoFocus={edit !== "birth"} value={draft} placeholder={META[edit].ph}
              type={edit === "birth" ? "date" : edit === "hp" ? "tel" : "text"}
              min={edit === "birth" ? oldest : undefined} max={edit === "birth" ? today : undefined}
              maxLength={edit === "hp" ? 20 : edit === "nick" ? NICK_MAX : 50} autoCapitalize="none" autoCorrect="off" spellCheck={false}
              onChange={(e) => setDraft(edit === "nick" ? cleanNick(e.target.value) : e.target.value)} />
            {edit === "nick" && <div className={styles.count}>{draft.length} / {NICK_MAX}</div>}
            {nickMsg && !sheetErr && <div className={`${styles.nickMsg} ${nickMsg.tone === "bad" ? styles.bad : nickMsg.tone === "good" ? styles.good : ""}`} role={nickMsg.tone === "bad" ? "alert" : undefined}>{nickMsg.text}</div>}
            {sheetErr && <div className={styles.sheetErr} role="alert">{sheetErr}</div>}
            <div className={styles.btnRow}>
              <button type="button" className={styles.btnGray} disabled={saving} onClick={() => sheetRef.current?.close()}>취소</button>
              <button type="button" className={styles.btnRed} disabled={saving || draft === vals[edit] || !nickOk} onClick={saveField}>{saving ? "저장 중…" : "저장"}</button>
            </div>
          </div>
        )}
      </BottomSheet>

      {leave && (
        <div className={mp.pfModal} role="presentation" onClick={() => setLeave(false)}>
          <div className={mp.pfModalBox} role="dialog" aria-modal="true" aria-labelledby="pf-leave" onClick={(e) => e.stopPropagation()}>
            <h2 id="pf-leave">변경사항을 저장하지 않았어요</h2>
            <p>저장하지 않고 나가면 선택한 사진이 사라져요.</p>
            <button type="button" className={mp.pfSave} onClick={async () => { setLeave(false); if (await save(vals)) router.push("/mypage"); }}>저장</button>
            <button type="button" className={mp.pfDiscard} onClick={() => router.push("/mypage")}>저장하지 않고 나가기</button>
          </div>
        </div>
      )}
    </div>
  );
}

/** profile_edit.php: nickname, name, birth date, phone and photo. Email and password changes have their own pages. */
export default function ProfileEdit({ initial, anon }: { initial?: MyProfile; anon?: boolean }) {
  const { data, error, refetch } = useQuery({ queryKey: qk.myProfile, queryFn: () => api.myProfile(), initialData: initial, staleTime: 0, retry: false, enabled: !anon });
  const loggedOut = anon || (error instanceof ApiError && error.status === 401);
  if (loggedOut) return <div className={mp.wrap}><LoginPrompt text="로그인하면 내 정보를 수정할 수 있습니다." /></div>;
  if (!data) return <div className={mp.wrap}><div className={mp.empty}>{error ? <>불러오지 못했습니다. <button type="button" className={mp.retry} onClick={() => refetch()}>다시 시도</button></> : <Loading />}</div></div>;
  // The form keeps its own state from the first load; a refetch (e.g. after saving) does not overwrite what is being typed.
  return <Form data={data} />;
}
