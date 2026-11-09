"use client";
import Loading from "./Loading";
import { useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { useQuery, useQueryClient } from "@tanstack/react-query";
import { api, ApiError, qk, type MyProfile } from "@/lib/api";
import { meKey, useLoginUrl } from "@/lib/me";
import styles from "./Register.module.css";

const NAME_RE = /^[\p{L}\p{M} .'-]{2,50}$/u;
const PHONE_RE = /^01[0-9]{8,9}$/;
/** 01012345678 → 010-1234-5678 (the format the profile page stores) */
const dashed = (d: string) => (d.length === 11 ? `${d.slice(0, 3)}-${d.slice(3, 7)}-${d.slice(7)}` : d.length === 10 ? `${d.slice(0, 3)}-${d.slice(3, 6)}-${d.slice(6)}` : d);
/** while typing: the hyphen comes by itself after 010 and after the next four digits (010- / 010-1234- / 010-1234-5678) */
const typing = (d: string) => (d.length < 3 ? d : d.length < 7 ? `${d.slice(0, 3)}-${d.slice(3)}` : d.length === 7 ? `${d.slice(0, 3)}-${d.slice(3)}-` : `${d.slice(0, 3)}-${d.slice(3, 7)}-${d.slice(7)}`);

function Form({ profile }: { profile: MyProfile }) {
  const router = useRouter();
  const qc = useQueryClient();
  const [f, setF] = useState({ name: "", birth: "", phone: "" });
  const [busy, setBusy] = useState(false);
  const [err, setErr] = useState<{ field: string; message: string } | null>(null);
  const sending = useRef(false);
  const [{ today, oldest }] = useState(() => {
    const d = new Date(Date.now() + 9 * 3600_000).toISOString().slice(0, 10); // KST, like the API
    return { today: d, oldest: `${Number(d.slice(0, 4)) - 120}${d.slice(4)}` };
  });
  const set = (k: keyof typeof f, v: string) => setF((p) => ({ ...p, [k]: v }));
  /** deleting a hyphen takes the digit before it with it (else the auto hyphen would come straight back) */
  const setPhone = (raw: string) => setF((p) => {
    let d = raw.replace(/\D/g, "").slice(0, 11);
    if (raw.length < p.phone.length && d === p.phone.replace(/\D/g, "")) d = d.slice(0, -1);
    return { ...p, phone: typing(d) };
  });
  const nameOk = NAME_RE.test(f.name.trim());
  const birthOk = f.birth !== "" && f.birth <= today && f.birth >= oldest;
  const digits = f.phone.replace(/\D/g, "");
  const phoneOk = PHONE_RE.test(digits);
  const ready = nameOk && birthOk && phoneOk;
  const fieldErr = (k: string) => (err && err.field === k ? <p className={styles.bad} role="alert">{err.message}</p> : null);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (sending.current || !ready) return;
    sending.current = true; setBusy(true); setErr(null);
    const form = new FormData();
    form.set("nick", profile.nick); form.set("name", f.name.trim()); form.set("hp", dashed(digits)); form.set("birth", f.birth);
    try {
      await api.saveProfile(form);
      await Promise.all([qc.invalidateQueries({ queryKey: qk.myProfile }), qc.invalidateQueries({ queryKey: qk.mypage }), qc.invalidateQueries({ queryKey: meKey })]);
      router.replace("/");
      return;
    } catch (e2) {
      setErr(e2 instanceof ApiError && e2.status !== 401 ? { field: e2.field ?? "form", message: e2.message } : { field: "form", message: "저장하지 못했어요. 잠시 후 다시 시도해주세요." });
    }
    sending.current = false; setBusy(false);
  };

  return (
    <div className={styles.page}>
      <div className={styles.head} />
      <h1 className={styles.title}>마지막으로 정보를 입력해주세요</h1>
      <form className={styles.form} onSubmit={submit}>
        {err && err.field === "form" && <div className={styles.err} role="alert">{err.message}</div>}
        <div className={styles.group}>
          <label className={styles.label} htmlFor="rp-name">이름</label>
          <input id="rp-name" className={styles.input} type="text" placeholder="이름을 입력해주세요" maxLength={50} autoComplete="name" value={f.name} onChange={(e) => set("name", e.target.value)} />
          <p className={styles.hint}>예약 시 병원에 전달되는 이름이에요</p>
          {fieldErr("name")}
        </div>
        <div className={styles.group}>
          <label className={styles.label} htmlFor="rp-birth">생년월일</label>
          <input id="rp-birth" className={styles.input} type="date" min={oldest} max={today} autoComplete="bday" value={f.birth} onChange={(e) => set("birth", e.target.value)} />
          <p className={styles.hint}>생년월일은 30일에 한 번만 변경할 수 있어요</p>
          {fieldErr("birth")}
        </div>
        <div className={styles.group}>
          <label className={styles.label} htmlFor="rp-phone">휴대폰 번호</label>
          <input id="rp-phone" className={styles.input} type="tel" inputMode="numeric" placeholder="000-0000-0000" maxLength={13} autoComplete="tel" value={f.phone} onChange={(e) => setPhone(e.target.value)} />
          <p className={styles.hint}>예약 안내를 받을 번호예요</p>
          {fieldErr("hp")}
        </div>
        <div className={styles.bottom}>
          <button type="submit" className={styles.submit} disabled={!ready || busy}>{busy ? "저장 중…" : "완료"}</button>
        </div>
      </form>
    </div>
  );
}

/** Right after sign-up (/register): 이름, 생년월일, 휴대폰 — the booking needs them. Already complete → home. */
export default function RegisterProfile() {
  const router = useRouter();
  const login = useLoginUrl();
  const { data, error, refetch } = useQuery({ queryKey: qk.myProfile, queryFn: () => api.myProfile(), staleTime: 0, retry: false });
  const loggedOut = error instanceof ApiError && error.status === 401;
  const complete = !!data && data.name.trim() !== "" && data.hp.trim() !== "" && data.birth !== "";
  useEffect(() => { if (loggedOut) window.location.replace(login("/next/register/profile")); }, [loggedOut, login]);
  useEffect(() => { if (complete) router.replace("/"); }, [complete, router]);
  if (!data || complete) {
    return <div className={styles.page}>{error && !loggedOut ? <p className={styles.bad} style={{ marginTop: 80 }}>불러오지 못했습니다. <button type="button" className={styles.toggle} onClick={() => refetch()}>다시 시도</button></p> : <Loading />}</div>;
  }
  return <Form profile={data} />;
}
