"use client";
import { useState } from "react";
import { useRouter } from "next/navigation";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { api, ApiError, assetUrl, qk, type MyProfile } from "@/lib/api";
import { meKey } from "@/lib/me";
import { phpUrl, usePhpBase } from "@/lib/php";
import LoginPrompt from "./LoginPrompt";
import styles from "./MyPages.module.css";

type Err = { field: string; message: string } | null;

function Form({ data }: { data: MyProfile }) {
  const php = usePhpBase();
  const router = useRouter();
  const qc = useQueryClient();
  const [nick, setNick] = useState(data.nick);
  const [name, setName] = useState(data.name);
  const [hp, setHp] = useState(data.hp);
  const [birth, setBirth] = useState(data.birth);
  const [file, setFile] = useState<File | null>(null);
  const [preview, setPreview] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [err, setErr] = useState<Err>(null);
  const [leave, setLeave] = useState(false);
  const dirty = nick !== data.nick || name !== data.name || hp !== data.hp || birth !== data.birth || file !== null;
  const [{ today, oldest }] = useState(() => {
    const d = new Date(Date.now() + 9 * 3600_000).toISOString().slice(0, 10); // KST, like the API
    return { today: d, oldest: `${Number(d.slice(0, 4)) - 120}${d.slice(4)}` };
  });
  const shown = preview ?? assetUrl(data.photoUrl);
  const fieldErr = (f: string) => (err && err.field === f ? <div className={styles.pfErr} role="alert">{err.message}</div> : null);

  const pick = (f: File | null) => {
    if (preview) URL.revokeObjectURL(preview);
    setFile(f);
    setPreview(f ? URL.createObjectURL(f) : null);
  };
  const save = async () => {
    if (saving) return;
    setLeave(false);
    const local = !name.trim() ? { field: "name", message: "이름을 입력해주세요." }
      : !birth ? { field: "birth", message: "올바른 생년월일을 입력해주세요." }
      : !/^[a-zA-Z0-9_]+$/.test(nick.trim()) ? { field: "nick", message: "닉네임은 영문, 숫자, 밑줄(_)만 사용할 수 있습니다." } : null;
    if (local) { setErr(local); return; }
    setSaving(true); setErr(null);
    const form = new FormData();
    form.set("nick", nick.trim()); form.set("name", name.trim()); form.set("hp", hp.trim()); form.set("birth", birth);
    if (file) form.set("photo", file);
    try {
      const r = await api.saveProfile(form);
      qc.invalidateQueries({ queryKey: qk.mypage });
      qc.invalidateQueries({ queryKey: qk.myProfile });
      qc.invalidateQueries({ queryKey: meKey });
      if (r.photoError) { setErr({ field: "photo", message: r.photoError }); return; }
      router.push("/mypage");
    } catch (e) {
      setErr(e instanceof ApiError ? { field: e.field ?? "form", message: e.message } : { field: "form", message: "저장하지 못했어요. 잠시 후 다시 시도해주세요." });
    } finally { setSaving(false); }
  };
  const back = () => { if (dirty) setLeave(true); else router.push("/mypage"); };

  return (
    <div className={styles.wrap}>
      <div className={styles.head}>
        <button type="button" className={styles.back} onClick={back} aria-label="뒤로 가기">←</button>
        <span className={styles.headTitle} style={{ fontSize: 18, fontWeight: 500 }}>내 정보 수정</span>
      </div>
      {err && (err.field === "form" || err.field === "photo") && <div className={styles.pfErr} role="alert" style={{ margin: "12px 16px" }}>{err.message}</div>}
      <div className={styles.pfAvatarArea}>
        <label className={styles.pfAvatarWrap}>
          <span className={styles.pfAvatar}>
            {/* eslint-disable-next-line @next/next/no-img-element */}
            {shown ? <img src={shown} alt="프로필" /> : null}
          </span>
          <span className={styles.pfAvatarBtn} aria-hidden="true">✎</span>
          <input type="file" accept="image/*" className={styles.pfFile} aria-label="프로필 사진 변경" onChange={(e) => pick(e.target.files?.[0] ?? null)} />
        </label>
      </div>
      <div className={styles.pfForm}>
        <div className={styles.pfField}>
          <label htmlFor="pf-nick">닉네임</label>
          <input id="pf-nick" value={nick} onChange={(e) => setNick(e.target.value.replace(/[^a-zA-Z0-9_]/g, ""))} placeholder="영문, 숫자, _ 만 입력" maxLength={50} autoCapitalize="none" />
          {fieldErr("nick")}
        </div>
        <div className={styles.pfField}>
          <label htmlFor="pf-name">이름</label>
          <input id="pf-name" value={name} onChange={(e) => setName(e.target.value)} placeholder="이름 입력" maxLength={50} />
          {fieldErr("name")}
        </div>
        <div className={styles.pfField}>
          <label htmlFor="pf-birth">생년월일</label>
          <input id="pf-birth" type="date" value={birth} min={oldest} max={today} onChange={(e) => setBirth(e.target.value)} autoComplete="bday" />
          {fieldErr("birth")}
        </div>
        <div className={styles.pfField}>
          <label>이메일</label>
          <div className={styles.pfRow}><span>{data.email || "-"}</span><a className={styles.pfChange} href={phpUrl(php, "profile_edit.php?php=1")}>변경</a></div>
        </div>
        <div className={styles.pfField}>
          <label htmlFor="pf-hp">휴대폰 번호</label>
          <input id="pf-hp" type="tel" value={hp} onChange={(e) => setHp(e.target.value)} placeholder="010-0000-0000" maxLength={20} />
          {fieldErr("hp")}
        </div>
        <div className={styles.pfField}>
          <label>비밀번호</label>
          <div className={styles.pfRow}><span>••••••••</span><a className={styles.pfChange} href={phpUrl(php, "profile_edit.php?php=1")}>변경</a></div>
        </div>
      </div>
      <div className={styles.pfBar}><button type="button" className={styles.pfSave} onClick={save} disabled={saving}>{saving ? "저장 중…" : "프로필 저장"}</button></div>
      {leave && (
        <div className={styles.pfModal} role="presentation" onClick={() => setLeave(false)}>
          <div className={styles.pfModalBox} role="dialog" aria-modal="true" aria-labelledby="pf-leave" onClick={(e) => e.stopPropagation()}>
            <h2 id="pf-leave">변경사항을 저장하지 않았어요</h2>
            <p>저장하지 않고 나가면 수정한 내용이 사라져요.</p>
            <button type="button" className={styles.pfSave} onClick={save}>저장</button>
            <button type="button" className={styles.pfDiscard} onClick={() => router.push("/mypage")}>저장하지 않고 나가기</button>
          </div>
        </div>
      )}
    </div>
  );
}

/** profile_edit.php: nickname, name, birth date, phone and photo. Changing the email or password stays in PHP (links). */
export default function ProfileEdit({ initial, anon }: { initial?: MyProfile; anon?: boolean }) {
  const { data, error, refetch } = useQuery({ queryKey: qk.myProfile, queryFn: () => api.myProfile(), initialData: initial, staleTime: 0, retry: false, enabled: !anon });
  const loggedOut = anon || (error instanceof ApiError && error.status === 401);
  if (loggedOut) return <div className={styles.wrap}><LoginPrompt text="로그인하면 내 정보를 수정할 수 있습니다." /></div>;
  if (!data) return <div className={styles.wrap}><div className={styles.empty}>{error ? <>불러오지 못했습니다. <button type="button" className={styles.retry} onClick={() => refetch()}>다시 시도</button></> : "불러오는 중…"}</div></div>;
  // The form keeps its own state from the first load; a refetch (e.g. after saving) does not overwrite what is being typed.
  return <Form data={data} />;
}
