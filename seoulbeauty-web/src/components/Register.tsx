"use client";
import { useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { useQueryClient } from "@tanstack/react-query";
import { ApiError, api } from "@/lib/api";
import { meKey, useMe } from "@/lib/me";
import { BASE_PATH } from "@/lib/phpLink";
import BottomSheet, { type BottomSheetHandle } from "./BottomSheet";
import styles from "./Register.module.css";

const PW_RE = /^(?=.*[A-Za-z])(?=.*[0-9])(?=.*[^A-Za-z0-9\s])[\x21-\x7E]{9,20}$/;
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const errText = (e: unknown, fallback: string) => (e instanceof ApiError ? (e.body?.message as string | undefined) : undefined) || fallback;

type Agree = { age: boolean; terms: boolean; privacy: boolean; marketing: boolean };
const NONE: Agree = { age: false, terms: false, privacy: false, marketing: false };

/**
 * /register (design: 먼저 이메일을 입력해주세요): e-mail → code by mail → password, then 가입하기 opens the 약관에 동의해주세요 sheet;
 * 동의하고 가입하기 creates the account and signs in, then /register/profile asks 이름 / 생년월일 / 휴대폰 (not here).
 */
export default function Register() {
  const router = useRouter();
  const qc = useQueryClient();
  const me = useMe();
  const [f, setF] = useState({ email: "", code: "", password: "", password2: "" });
  const [show, setShow] = useState({ pw: false, pw2: false });
  const [sent, setSent] = useState(false);
  const [codeBusy, setCodeBusy] = useState(false);
  const [codeMsg, setCodeMsg] = useState<{ ok: boolean; text: string } | null>(null);
  const [sheet, setSheet] = useState(false);
  const [agree, setAgree] = useState<Agree>(NONE);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const sending = useRef(false);
  const sheetRef = useRef<BottomSheetHandle>(null);
  const set = <K extends keyof typeof f>(k: K, v: string) => setF((p) => ({ ...p, [k]: v }));

  useEffect(() => { if (me.data?.loggedIn && !sending.current) router.replace("/"); }, [me.data, router]);

  const sendCode = async () => {
    if (codeBusy) return;
    if (!EMAIL_RE.test(f.email.trim())) { setCodeMsg({ ok: false, text: "올바른 이메일 주소를 입력해주세요." }); return; }
    setCodeBusy(true); setCodeMsg(null);
    try { const r = await api.registerCode(f.email.trim()); setSent(true); setCodeMsg({ ok: true, text: r.message }); }
    catch (e) { setCodeMsg({ ok: false, text: errText(e, "인증코드 발송에 실패했습니다. 잠시 후 다시 시도해주세요.") }); }
    finally { setCodeBusy(false); }
  };

  const pwMsg = f.password && !PW_RE.test(f.password) ? "영문, 숫자, 특수문자를 각각 포함한 9~20자로 입력해주세요." : "";
  const pw2Msg = f.password2 && f.password !== f.password2 ? "비밀번호가 일치하지 않습니다." : "";
  const ready = EMAIL_RE.test(f.email.trim()) && f.code.length === 6 && PW_RE.test(f.password) && f.password === f.password2;
  const allOn = agree.age && agree.terms && agree.privacy && agree.marketing;
  const consent = agree.age && agree.terms && agree.privacy;

  const submit = async () => {
    if (sending.current || !ready || !consent) return;
    sending.current = true; setBusy(true); setError("");
    try {
      const r = await api.register({
        email: f.email.trim(), code: f.code.trim(), password: f.password, password2: f.password2,
        agreeAge14: agree.age, agreeTerms: agree.terms, agreePrivacy: agree.privacy, agreeMarketing: agree.marketing,
      });
      if (r.loggedIn) {
        await qc.invalidateQueries({ queryKey: meKey });
        window.location.replace("/next/register/profile"); // full load: the new PHP session cookie must be used by every page; 이름 / 생년월일 / 휴대폰 come next
      } else {
        // eslint-disable-next-line @next/next/no-location-assign-relative-destination -- full load on purpose
        window.location.assign(`${BASE_PATH}/login`);
      }
      return;
    } catch (err) {
      setError(errText(err, "가입 처리 중 오류가 발생했습니다. 잠시 후 다시 시도해주세요."));
      sheetRef.current?.close(); // the message sits in the form behind the sheet
    }
    sending.current = false; setBusy(false);
  };
  const login = `${BASE_PATH}/login`;
  const tick = (k: keyof Agree) => (e: React.ChangeEvent<HTMLInputElement>) => setAgree((p) => ({ ...p, [k]: e.target.checked }));

  return (
    <div className={styles.page}>
      <div className={styles.head}>
        <button type="button" className={styles.back} aria-label="뒤로 가기" onClick={() => { if (window.history.length > 1) router.back(); else router.push("/login"); }}>
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7" /></svg>
        </button>
      </div>
      <h1 className={styles.title}>먼저 이메일을 입력해주세요</h1>
      <form className={styles.form} onSubmit={(e) => { e.preventDefault(); if (ready) setSheet(true); }}>
        {error && <div className={styles.err} role="alert">{error}</div>}

        <div className={styles.group}>
          <label className={styles.label} htmlFor="rg-email">이메일</label>
          <div className={styles.row}>
            <input id="rg-email" className={styles.input} type="email" placeholder="아이디로 사용될 이메일을 입력해주세요" autoComplete="email" value={f.email} onChange={(e) => { set("email", e.target.value); if (sent) { setSent(false); setCodeMsg(null); } }} />
            <button type="button" className={styles.side} onClick={sendCode} disabled={codeBusy}>{codeBusy ? "발송 중…" : sent ? "재인증하기" : "인증하기"}</button>
          </div>
          <p className={styles.hint}>로그인 및 계정 분실방지를 위해 메일 인증이 필요해요</p>
          {codeMsg && <p className={codeMsg.ok ? styles.ok : styles.bad} role="status">{codeMsg.text}</p>}
        </div>

        <div className={styles.group}>
          <label className={styles.label} htmlFor="rg-code">인증코드</label>
          <input id="rg-code" className={styles.input} type="text" inputMode="numeric" maxLength={6} placeholder="메일로 전송된 코드를 입력해주세요" autoComplete="one-time-code" value={f.code} onChange={(e) => set("code", e.target.value.replace(/\D/g, ""))} />
        </div>

        <div className={`${styles.group} ${styles.gapBig}`}>
          <div className={styles.labelRow}>
            <label className={styles.label} htmlFor="rg-pw">비밀번호</label>
            <button type="button" className={styles.toggle} onClick={() => setShow((p) => ({ ...p, pw: !p.pw }))}>{show.pw ? "가리기" : "표시"}</button>
          </div>
          <input id="rg-pw" className={styles.input} type={show.pw ? "text" : "password"} placeholder="비밀번호를 입력해주세요" maxLength={20} autoComplete="new-password" value={f.password} onChange={(e) => set("password", e.target.value)} />
          <p className={pwMsg ? styles.bad : styles.hint}>{pwMsg || "비밀번호는 숫자, 영문자, 특수문자 포함 9~20자로 입력해주세요"}</p>
        </div>

        <div className={styles.group}>
          <div className={styles.labelRow}>
            <label className={styles.label} htmlFor="rg-pw2">비밀번호 확인</label>
            <button type="button" className={styles.toggle} onClick={() => setShow((p) => ({ ...p, pw2: !p.pw2 }))}>{show.pw2 ? "가리기" : "표시"}</button>
          </div>
          <input id="rg-pw2" className={styles.input} type={show.pw2 ? "text" : "password"} placeholder="비밀번호를 입력해주세요" maxLength={20} autoComplete="new-password" value={f.password2} onChange={(e) => set("password2", e.target.value)} />
          {pw2Msg && <p className={styles.bad}>{pw2Msg}</p>}
        </div>

        <div className={styles.bottom}>
          <div className={styles.login}>이미 서울미녀 회원이신가요?<a href={login}>로그인</a></div>
          <button type="submit" className={styles.submit} disabled={!ready}>가입하기</button>
        </div>
      </form>

      <BottomSheet ref={sheetRef} open={sheet} onClose={() => setSheet(false)} label="약관에 동의해주세요" dismissible={!busy}>
        <div className={styles.sheet}>
          <h2 className={styles.sheetTitle}>약관에 동의해주세요</h2>
          <div className={styles.all}>
            <label><input type="checkbox" className={styles.box} checked={allOn} onChange={(e) => setAgree({ age: e.target.checked, terms: e.target.checked, privacy: e.target.checked, marketing: e.target.checked })} />전체 동의</label>
          </div>
          <div className={styles.items}>
            <div className={styles.item}><label><input type="checkbox" className={styles.box} checked={agree.age} onChange={tick("age")} /><span>(필수) 만 14세 이상이에요</span></label></div>
            <div className={styles.item}>
              <label><input type="checkbox" className={styles.box} checked={agree.terms} onChange={tick("terms")} /><span>(필수) 서비스 이용약관 동의</span></label>
              <a className={styles.view} href="/next/terms" target="_blank" rel="noopener">보기</a>
            </div>
            <div className={styles.item}>
              <label><input type="checkbox" className={styles.box} checked={agree.privacy} onChange={tick("privacy")} /><span>(필수) 개인정보 수집 · 이용 동의</span></label>
              <a className={styles.view} href="/next/privacy" target="_blank" rel="noopener">보기</a>
            </div>
            <div className={styles.item}><label><input type="checkbox" className={styles.box} checked={agree.marketing} onChange={tick("marketing")} /><span><span className={styles.opt}>(선택)</span> 마케팅 정보 수신 동의</span></label></div>
          </div>
          <button type="button" className={styles.agree} disabled={!consent || busy} onClick={submit}>{busy ? "가입 중…" : "동의하고 가입하기"}</button>
        </div>
      </BottomSheet>
    </div>
  );
}
