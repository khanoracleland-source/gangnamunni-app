# -*- coding: utf-8 -*-
"""
sync_check.py — 서버·GitHub·PC 3자 동일성 확인
사용법: python .dev/sync_check.py
"""
import paramiko, subprocess, sys
sys.stdout.reconfigure(encoding="utf-8", errors="replace")

SERVER   = "5.104.82.35"
USER     = "root"
SRC_PATH = "/var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas"
PC_PATH  = r"H:\seoul_beauty_project\hms-saas"

def run_ssh(ssh, cmd):
    _, o, e = ssh.exec_command(cmd)
    return o.read().decode("utf-8", "replace").strip()

def run_local(cmd):
    result = subprocess.run(cmd, shell=True, capture_output=True, text=True, cwd=PC_PATH)
    return result.stdout.strip()

def main():
    print("=" * 55)
    print("서울미녀 HMS — 서버·PC·GitHub 동일성 체크")
    print("=" * 55)

    # PC 커밋
    pc_commit = run_local("git log --oneline -1")
    pc_status = run_local("git status --short")
    print(f"\n📁 PC  최신 커밋: {pc_commit}")
    if pc_status:
        print(f"   ⚠️  미커밋 변경: {pc_status[:100]}")
    else:
        print(f"   ✅ 미커밋 없음 (clean)")

    # GitHub 커밋
    gh_commit = run_local("git log --oneline origin/master -1")
    print(f"\n🌐 GitHub 최신 커밋: {gh_commit}")

    # 서버 커밋
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(SERVER, username=USER, allow_agent=True, look_for_keys=True, timeout=30)
    srv_commit = run_ssh(ssh, f"cd {SRC_PATH} && git fetch origin master 2>/dev/null; git log --oneline -1")
    srv_status = run_ssh(ssh, f"cd {SRC_PATH} && git status --short | grep -v '^?' | head -5")
    print(f"\n🖥️  서버 최신 커밋: {srv_commit}")
    if srv_status:
        print(f"   ⚠️  서버 미커밋 변경:\n{srv_status}")
    else:
        print(f"   ✅ 서버 미커밋 없음 (clean)")

    # 해시 비교
    pc_hash  = pc_commit.split()[0] if pc_commit else ""
    gh_hash  = gh_commit.split()[0] if gh_commit else ""
    srv_hash = srv_commit.split()[0] if srv_commit else ""

    print("\n" + "=" * 55)
    print("📊 동일성 결과")
    print("=" * 55)

    all_ok = True

    def check(label, a, b):
        nonlocal all_ok
        ok = a == b
        if not ok: all_ok = False
        icon = "✅" if ok else "❌"
        print(f"  {icon} {label}: {'일치' if ok else f'불일치 ({a} vs {b})'}")

    check("PC ↔ GitHub ", pc_hash, gh_hash)
    check("PC ↔ 서버   ", pc_hash, srv_hash)
    check("GitHub ↔ 서버", gh_hash, srv_hash)

    print()
    if all_ok:
        print("✅ 3자 완전 동기화 — 정상")
    else:
        print("❌ 동기화 불일치 — 아래 명령으로 해결:")
        if pc_hash != gh_hash:
            print("   PC → GitHub: git push origin master")
        if srv_hash != gh_hash:
            print("   서버 반영:   python .dev/deploy.py")

    # 서버 상태 추가
    print("\n📋 서버 상태")
    apache  = run_ssh(ssh, "systemctl is-active httpd")
    artisan = run_ssh(ssh, "systemctl is-active hms-artisan")
    disk    = run_ssh(ssh, "df -h /var/www/html | tail -1 | awk '{print $3\"/\"$2\" (\"$5\" 사용)\"}'")
    print(f"  Apache:      {'✅' if apache == 'active' else '❌'} {apache}")
    print(f"  HMS artisan: {'✅' if artisan == 'active' else '❌'} {artisan}")
    print(f"  디스크:      {disk}")

    ssh.close()
    print("\n[DONE]")

if __name__ == "__main__":
    main()
