# Cursor AI — 연동 테스트 프롬프트

> `H:\seoul_beauty_project\hms-saas\` 폴더를 Cursor로 열고
> 아래 프롬프트를 Chat에 붙여넣어 실행하세요.

---

## ✅ 테스트 프롬프트 (전체 복사해서 사용)

```
아래 3단계를 순서대로 실행하고 각 결과를 보고해라.

서버 정보:
- SSH: `root@5.104.82.35` (SSH key 사용)
- 서버 소스 경로: /var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas/
- PC 경로: H:\seoul_beauty_project\hms-saas\

---

## 1단계 — 서버·PC·GitHub 소스 동일성 확인

[PC에서]
git log --oneline -3
git status --short

[서버에서 SSH]
cd /var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas
git log --oneline -3
git status --short

[GitHub]
git log --oneline origin/master -3

결과:
- PC 최신 커밋:
- GitHub 최신 커밋:
- 서버 최신 커밋:
- 3자 일치 여부: YES / NO
- 서버 untracked 파일: (있으면 목록, 없으면 없음)
  ※ untracked는 동기화에 영향 없음 — 무시해도 됨

---

## 2단계 — 서버 배포 테스트 (PC → GitHub → 서버)

[PC에서]
README.md 파일 맨 마지막에 빈 줄 1개 추가 후:
git add README.md
git commit -m "test: Cursor 배포 연동 테스트"
git push origin master

[서버에서 SSH]
cd /var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas
git pull origin master

git log --oneline -1 출력해서 PC와 커밋 해시 일치 확인.

결과:
- git push 성공: YES / NO
- 서버 git pull 성공: YES / NO
- PC ↔ 서버 커밋 일치: YES / NO

---

## 3단계 — 테스트 커밋 원복

[PC에서]
README.md에 추가한 빈 줄 제거 후:
git add README.md
git commit -m "test: 테스트 커밋 원복"
git push origin master

[서버에서 SSH]
cd /var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas
git pull origin master

결과:
- 원복 push 성공: YES / NO
- 서버 원복 반영: YES / NO

---

## 최종 보고

### ✅/❌ 1단계 — 동일성 확인
- PC / GitHub / 서버 커밋:
- 3자 일치:

### ✅/❌ 2단계 — 배포 테스트
- push 성공:
- 서버 반영:

### ✅/❌ 3단계 — 원복
- 원복 성공:

3단계 모두 YES면 "연동 정상"으로 보고.
1개라도 NO면 원인과 해결 방법을 함께 보고.
```

---

## 📋 일상 작업 프롬프트 (매번 사용)

```
아래 작업을 완료하고 서버에 반영해라.

[작업 내용]
(여기에 원하는 작업 설명)

규칙: .dev/CLAUDE.md 참조

작업 완료 후:
1. git add / commit / push origin master
2. 서버 SSH 접속 후 아래 실행:
   ssh root@5.104.82.35
   cd /var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas
   git pull origin master
3. 변경한 PHP 파일 php -l 검증
4. https://hms.seoulbeauty.app/hairwang/gangnamunni/ 에서 결과 확인 후 보고
```

---

## 🔧 단독 실행 프롬프트

**서버-PC 동기화 확인:**
```
서버(root@5.104.82.35, SSH key 사용)와 PC(H:\seoul_beauty_project\hms-saas\)의
git log --oneline -1 을 각각 출력해서 커밋 해시가 동일한지 확인하고 보고해라.
```

**서버에 즉시 반영:**
```
서버(root@5.104.82.35, SSH key 사용)에 SSH 접속 후
cd /var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas
git pull origin master
실행하고 결과 보고해라.
```

**서버 백업 생성:**
```
서버(root@5.104.82.35, SSH key 사용)에 SSH 접속 후
/var/www/html/backups/ 에 아래 명령으로 백업 생성하고 파일명과 크기 보고해라.
FNAME="hms-saas-v5.8.0_$(date +%Y%m%d_%H%M%S).tar.gz"
tar -czf /var/www/html/backups/$FNAME /var/www/html/multi-hospital-management-system-saas-hospital/
echo "완료: $FNAME" && ls -lh /var/www/html/backups/$FNAME
```
