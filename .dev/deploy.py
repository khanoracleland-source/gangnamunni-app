# -*- coding: utf-8 -*-
"""
deploy.py — PC에서 git push 후 서버 자동 git pull
사용법: python .dev/deploy.py
"""
import paramiko, sys
sys.stdout.reconfigure(encoding="utf-8", errors="replace")

SERVER   = "5.104.82.35"
USER     = "root"
PASSWORD = "<REMOVED_SSH_PASSWORD>"
SRC_PATH = "/var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas"
GH_TOKEN_FILE = "/root/.gh_token"

def run(ssh, cmd):
    _, o, e = ssh.exec_command(cmd)
    out = o.read().decode("utf-8", "replace").strip()
    err = e.read().decode("utf-8", "replace").strip()
    return out, err

def main():
    print("=" * 50)
    print("서울미녀 HMS — 서버 배포 (git pull)")
    print("=" * 50)

    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(SERVER, username=USER, password=PASSWORD, timeout=30)
    print(f"✅ SSH 접속: {SERVER}")

    # git pull
    print("\n🔄 git pull 실행 중...")
    out, _ = run(ssh, f"cd {SRC_PATH} && git pull origin master 2>&1")
    print(out)

    # 결과 확인
    if "Already up to date" in out:
        print("\n✅ 서버 이미 최신 상태")
    elif "Fast-forward" in out or "Updating" in out:
        print("\n✅ 서버 반영 완료")
    else:
        print("\n⚠️  예상치 못한 결과 — 위 메시지 확인 필요")

    # 서버 최신 커밋 확인
    out, _ = run(ssh, f"cd {SRC_PATH} && git log --oneline -3")
    print(f"\n📋 서버 최신 커밋:\n{out}")

    # PHP 문법 확인 (gangnamunni 변경된 파일)
    out, _ = run(ssh, f"cd {SRC_PATH} && git diff HEAD~1 --name-only 2>/dev/null | grep '\\.php$' | head -5")
    if out:
        print(f"\n🔍 PHP 문법 검사 중...")
        for php_file in out.splitlines():
            result, _ = run(ssh, f"php -l {SRC_PATH}/{php_file} 2>&1 | tail -1")
            icon = "✅" if "No syntax errors" in result else "❌"
            print(f"  {icon} {php_file}")

    ssh.close()
    print("\n[DONE]")

if __name__ == "__main__":
    main()
