# 서울미녀 bemor ilovasi — reliz 2.2.0 (build 47)

Sana: 2026-11-13 · Android `com.celebhospital.patient` · iOS `com.celebhospital.patient.public`
Do'konlardagi holat: Google Play production **2.1.0 (45)**, App Store **2.1.1 (46)** → yangi **2.2.0 (47)** ikkalasidan katta.

> Build va do'konga yuklash — faqat siz (foydalanuvchi). Bu hujjat va `scripts/build_release.sh` shuni
> bitta buyruqqa keltiradi. Build bu hujjat yozilgan muhitda **ISHGA TUSHIRILMAGAN** (Flutter yo'q).

---

## 1. Nima o'zgardi

### Foydalanuvchi ko'zi bilan
- Ilova endi to'g'ridan-to'g'ri yangi saytda (`seoulbeauty.app`) ochiladi — sahifalar tezroq yuklanadi.
- Kirish (email / Kakao / Google / Apple) va ilovani yopib-ochganda kirgan holda qolish barqarorroq.
- Eski havolalar (oldingi push xabarlari, ulashilgan havolalar) ham ilovada to'g'ri sahifaga ochiladi.
- `seoulbeauty.app` havolasi (KakaoTalk, xabar, brauzer) bosilganda ilova ochiladi; kirish / ro'yxatdan o'tish / parol tiklash havolalari brauzerda qoladi.
- Yangi o'rnatish va yangi kirishdan keyin ham push bildirishnomalar keladi.
- Ko'rinish va funksiyalar o'zgarmagan.

### Texnik
| Nima | 2.1.x | 2.2.0 |
|---|---|---|
| Ilova xosti | `hms.seoulbeauty.app` | `seoulbeauty.app` (`APP_SITE_HOST`) |
| Boshlang'ich manzil | `/hairwang/gangnamunni/?app=patient` (PHP) | `https://seoulbeauty.app/next/` (Next.js) |
| Tokenlar (verify / refresh / exchange / logout) | PHP `api/app_auth_*.php`, `app_logout.php` | Nest `/next/api/v1/app-auth/{verify,refresh,exchange,logout}` |
| WebView sessiyasiga o'tish | PHP `app_session_land.php` | `/next/api/v1/app-auth/land?url=…` |
| WebView ichidagi bootstrap | PHP `app_auth_bootstrap.php` | `/next/api/v1/app-auth/bootstrap` |
| Bron push'i ochadigan sahifa | `my_bookings.php` | `/next/mypage/bookings` |
| Eski `hms` havolalari | o'sha xostda ochilardi | `NextPilot.canonical` orqali `seoulbeauty.app` dagi shu sahifaga (`/next/api/…` hech qachon ko'chirilmaydi) |
| Qurilma cookie'si `sb_app_device` | PHP server yaratardi | har doim ilova beradi (`getOrCreateDeviceId`) — usiz `bootstrap` 401 |
| App Links / Universal Links | `hms.seoulbeauty.app` + `/hairwang/gangnamunni` | faqat `seoulbeauty.app`, `/next` sahifalari (`/next/api`, `/next/_next`, login/register/password-* bundan mustasno) |
| Push token ro'yxati | PHP sahifa JS'i → `push_fcm/get_android_regid.php` | Next sahifa → Nest `POST /next/api/v1/app/push-token` (+ `/unlink` chiqishda) |
| To'lov (Inicis) qaytishi | `hms.seoulbeauty.app/next` | to'lov boshlangan xost (`INICIS_PUBLIC_HOSTS`), standart `seoulbeauty.app/next` |
| PHP'ga so'rov | bor | **yo'q** |

O'zgargan fayllar (`git diff -- mobile/patient_app`): `pubspec.yaml` (2.1.1+46 → 2.2.0+47),
`analysis_options.yaml` (`patched_plugins/**` chiqarildi), `lib/configs.dart`,
`lib/screens/webview/{app_auth_gate_screen,startup_webview_screen}.dart`,
`lib/services/{deep_link_service,native_auth_service,next_pilot,webview_bridge}.dart`,
`lib/utils/push_notification_service.dart`, `android/app/src/main/AndroidManifest.xml`,
`ios/Runner/Runner.entitlements`, test fayllari. Ilovadan tashqarida shu reliz bilan birga:
`public/.well-known/apple-app-site-association`, `seoulbeauty-api` (push token, Inicis qaytish xosti), `seoulbeauty-web` (push token).
O'zgarMAgan: ruxsatnomalar (INTERNET, ACCESS_NETWORK_STATE, CAMERA, POST_NOTIFICATIONS — do'kon so'rovnomasiga ta'sir yo'q),
Firebase, imzo, minSdk 24 / iOS 15.6. `kivicare2.seoulbeauty.app` (Laravel) dagi eski token chaqiruvi kodda qolgan, lekin push uchun endi unga tayanilmaydi.

---

## 2. Reliz oldi tekshiruv natijasi (kod o'qib)

| Band | Holat |
|---|---|
| Versiya `2.2.0+47`, do'kondagidan katta | ✓ |
| `configs.dart`: test/localhost manzil yo'q, hammasi `https` jonli xostlar | ✓ |
| Android: `versionCode/versionName` Flutter'dan (`local.properties` — `flutter build` uni pubspec'dan yangilaydi; hozir u yerda eski 45 turibdi, build paytida 47 bo'ladi) | ✓ |
| Android release imzo: `key.properties` (4 kalit nomi bor) + keystore topildi; `minifyEnabled` + `shrinkResources` + `proguard-rules.pro` | ✓ |
| iOS: `CFBundleShortVersionString=$(FLUTTER_BUILD_NAME)`, `CFBundleVersion=$(FLUTTER_BUILD_NUMBER)`; team `JZ4MFA48JA`, Automatic | ✓ |
| iOS `Runner.entitlements`: `aps-environment=development` — App Store eksportida Xcode uni `production` qiladi (11-10 dagi IPA `DistributionSummary` da `production`) | ✓ |
| `ios/Podfile`: platform iOS 15.6 (pbxproj bilan bir xil) | ✓ |
| Firebase fayllari joyida (`google-services.json`, `GoogleService-Info.plist`) | ✓ |
| `ExportOptions.plist` → `ios/ExportOptions.plist` ga nusxalandi (`flutter clean` o'chirmasligi uchun; ichida faqat team ID, sir yo'q) | ✓ |
| App Links / Universal Links: manifest va entitlements'da faqat `seoulbeauty.app`; Android va iOS bir xil sahifalarni ochadi | ✓ (serverda `.well-known` shart, 3-bo'lim) |
| `flutter analyze`, `flutter test`, build | **ISHGA TUSHIRILMAGAN** — skript Mac'da bajaradi |

Relizni to'sadigan narsa kodda topilmadi. Shart: **server** tayyor bo'lishi kerak (3-bo'lim).

Mayda eslatmalar (to'smaydi): `configs.dart` da ishlatilmaydigan qoldiq qiymatlar (`APP_APPSTORE_URL` id `0000000000`,
`HELP_LINE_NUMBER`, `FIREBASE_SERVER_CLIENT_ID` — 2.1.x da ham shunday edi); `BUILD_GUIDE.md` eskirgan
(Windows yo'li, paket nomi `com.seoulbeauty.app`, versiya `1.9.0+25`, `kivicare.` xosti, iOS IPA/fvm yo'q) —
bu hujjat va skript uning o'rnini bosadi.

---

## 3. Yuklashdan OLDIN serverda jonli bo'lishi shart

1. `https://seoulbeauty.app/next/` ochiladi (Next.js) — API va web shu relizdagi kod bilan qayta qurilgan.
2. `/next/api/v1/app-auth/` — `verify`, `refresh`, `exchange`, `logout`, `land`, `bootstrap` ishlaydi (`NEST_SESSIONS=1`, `APP_AUTH_NEST=1`, `APP_NATIVE_AUTH_KEY`).
3. Social login: Kakao/Google/Apple konsollarida `seoulbeauty.app` callback'lari, `.env` da `SOCIAL_CALLBACK_HOSTS=seoulbeauty.app`, `SOCIAL_NEST_PROVIDERS` da uchala provayder.
4. Push token: `g5_push_tokens` da `UNIQUE KEY (pt_token)` borligini tekshiring; GRANT bering; `.env` da `APP_PUSH_TOKEN_NEST=1`.
   ```sql
   GRANT INSERT (mb_id, pt_token, pt_platform, pt_device_info, pt_datetime, pt_use), UPDATE (mb_id, pt_platform, pt_device_info, pt_datetime, pt_use) ON hms_saas.g5_push_tokens TO 'sb_api_ro'@'127.0.0.1', 'sb_api_ro'@'localhost';
   ```
5. To'lov qaytishi: `.env` da `INICIS_PUBLIC_BASE=https://seoulbeauty.app/next` va `INICIS_PUBLIC_HOSTS=seoulbeauty.app,hms.seoulbeauty.app` (hms — faqat do'kondagi 2.1.x uchun).
6. Ilova havolalari: `https://seoulbeauty.app/.well-known/assetlinks.json` va `…/apple-app-site-association` — 200, `Content-Type: application/json`, redirect'siz.
7. `hms.seoulbeauty.app` va PHP `app_*` fayllari hali ishlayapti — do'kondagi 2.1.x binarlar yangilanmaguncha kerak.

Tez tekshiruv (Mac terminali):
```bash
B=https://seoulbeauty.app/next
curl -s $B/api/health                                                              # {"status":"ok",...}
curl -s -X POST $B/api/v1/app-auth/bootstrap -H 'X-Native-Auth: 1'                 # 401 login_required
curl -s -X POST $B/api/v1/app/push-token -H 'X-Requested-With: XMLHttpRequest' -H 'Content-Type: application/json' -d '{"token":"x"}'   # 401 login_required (404 = kalit o'chiq)
curl -sI https://seoulbeauty.app/.well-known/assetlinks.json | head -5              # 200, application/json
curl -sI https://seoulbeauty.app/.well-known/apple-app-site-association | head -5   # 200, application/json
```

---

## 4. Build — bitta buyruq (Mac terminali)

```bash
cd ~/Desktop/서울미녀/hms-saas/mobile/patient_app && ./scripts/build_release.sh
```

Skript tartib bilan: muhit tekshiruvi (fvm → Flutter 3.35.4, imzo va Firebase fayllari) → `flutter pub get` →
`flutter analyze` (faqat *error* to'xtatadi) → `flutter test` → `flutter build appbundle --release` →
`flutter build ipa --release --export-options-plist=ios/ExportOptions.plist` → natijani nusxalash.
Analyze yoki test yiqilsa build qilinmaydi.

Natija `~/Desktop/서울미녀-2.2.0+47/`:
- `seoulbeauty-2.2.0-47.aab` — Google Play
- `seoulbeauty-2.2.0-47.ipa` — App Store
- `RELEASE_2.2.0.md`, `build.log` (to'liq chiqish), oxirida hajm va SHA-256

Bayroqlar: `--skip-tests`, `--android-only`, `--ios-only`, `--clean` (boshida `flutter clean`; standartda yo'q).
Biror bosqich tushunarsiz yiqilsa: `./scripts/build_release.sh --clean` bilan qayta urinib ko'ring va `build.log` ni yuboring.

Qo'lda qilmoqchi bo'lsangiz (skript bilan bir xil):
```bash
fvm flutter clean            # ixtiyoriy
fvm flutter pub get
fvm flutter analyze --no-fatal-infos --no-fatal-warnings
fvm flutter test
fvm flutter build appbundle --release
fvm flutter build ipa --release --export-options-plist=ios/ExportOptions.plist
```

iOS eslatma: imzo avtomatik — Xcode'da team `JZ4MFA48JA` Apple ID'si kirgan bo'lishi kerak. Skript oxirida IPA
ichidagi versiyani chiqaradi; `2.2.0 (47)` bo'lmasa ogohlantiradi.

---

## 5. Do'konga yuklash (faqat siz)

**Google Play** — Play Console → 서울미녀 → 프로덕션 → 새 버전 만들기 → `seoulbeauty-2.2.0-47.aab` yuklash →
출시 노트 (7-bo'lim) → 검토 → 출시. Tavsiya: avval 내부 테스트 ga yuklab qurilmada sinang, so'ng shu buildni
프로덕션 ga ko'taring (버전 승급); bosqichli chiqarish (masalan 20%) xavfni kamaytiradi.

**App Store** — Transporter ilovasi → `seoulbeauty-2.2.0-47.ipa` ni tashlang → 전달. 10–30 daqiqada App Store Connect →
TestFlight'da build 47 ko'rinadi → TestFlight orqali qurilmada sinang → 앱 스토어 tab → `+` 버전 **2.2.0** yarating →
빌드 47 ni tanlang → "이 버전의 새로운 기능" (7-bo'lim) → 심사에 제출.
Sharhlovchi uchun: kirish ma'lumotlari (demo hisob) oldingi relizdagidek amal qilishini tekshiring.

---

## 6. Qurilmada sinov ro'yxati (Android va iOS, har birida)

Asosiy:
- [ ] Yangi o'rnatish: ilova `seoulbeauty.app/next/` bosh sahifasini ochadi, qora/oq ekran yo'q
- [ ] **2.1.x ustidan yangilash**: avval kirgan foydalanuvchi kirgan holda qoladi (token bor bo'lsa); bo'lmasa bir marta qayta kiradi (cheklov 1)
- [ ] Email bilan kirish
- [ ] Kakao bilan kirish
- [ ] Google bilan kirish
- [ ] Apple bilan kirish (iOS)
- [ ] Yangi a'zo ro'yxatdan o'tishi (social orqali ham)
- [ ] Ilovani to'liq yopib-ochish → kirgan holda qoladi; 1 kundan keyin ham
- [ ] Chiqish → mypage mehmon holatida; qayta ochganda ham chiqqan holda
- [ ] Push keladi (fon va ochiq holatda); bosilganda to'g'ri sahifa
- [ ] Bron push'i → `예약 내역` (`/next/mypage/bookings`) ochiladi
- [ ] Karta to'lovi (Inicis) Android: karta ilovasi ochiladi, to'lovdan so'ng ilovaga qaytadi, bron "to'langan"
- [ ] Karta to'lovi iOS: xuddi shunday; qaytgandan so'ng foydalanuvchi **kirgan holda**, sahifa `seoulbeauty.app` da
- [ ] To'lovni bekor qilish / qaytarish oqimi

2.2.0 ga xos:
- [ ] Toza o'rnatish → kirish → ilovani to'liq yopib-ochish: login sahifasisiz kirgan holda ochiladi (ilova tokenlari berilgan)
- [ ] Yangi kirishdan keyin sinov push'i keladi; chiqishdan keyin kelmaydi; boshqa a'zo kirsa push unga keladi
- [ ] Bitta a'zo ikkita telefonda: ikkalasiga ham push keladi
- [ ] Tashqi havola `https://seoulbeauty.app/next/hospitals/1` (Notes / KakaoTalk) → ilova ochiladi. Android: `adb shell pm get-app-links com.celebhospital.patient` → `seoulbeauty.app: verified`
- [ ] Ilova o'rnatilgan telefonda brauzerda (Chrome / Safari) Kakao yoki Google bilan kirish va karta to'lovi → oxirigacha brauzerda qoladi, ilovaga sakramaydi
- [ ] Eski havola `https://hms.seoulbeauty.app/hairwang/gangnamunni/…` (eski push yoki ulashilgan) → ilovada `seoulbeauty.app` dagi mos sahifa, kirgan holda
- [ ] Eski PHP uslubidagi havola (`detail.php?…`) → Next sahifasi
- [ ] `wellness://` / `kivicare://` deep link'lar ishlaydi
- [ ] Shifoxona ro'yxati → tafsilot → bron sahifasi; orqaga tugmasi (Android) to'g'ri
- [ ] Rasm yuklash (profil / sharh): kamera va galereya
- [ ] Tashqi havolalar (Kakao kanal, xarita, telefon) tashqi ilovada ochiladi
- [ ] Internet yo'q holatda ochish → xato ekrani, internet qaytgach tiklanadi
- [ ] Sozlamalar → bildirishnoma kalitlari saqlanadi

---

## 7. Do'kon "yangiliklar" matni

**한국어 (asosiy)**
```
• 앱이 더 빠르고 안정적으로 열립니다.
• 로그인 상태가 더 오래, 더 안정적으로 유지됩니다.
• 이전에 받은 알림이나 공유 링크도 앱에서 바로 열립니다.
• 기타 오류 수정 및 사용성 개선
```

**English**
```
• The app opens faster and runs more reliably.
• You stay signed in more consistently.
• Older notifications and shared links now open right in the app.
• Bug fixes and usability improvements
```

---

## 8. Ma'lum cheklovlar

1. **Faqat cookie bilan kirganlar bir marta qayta kiradi.** `hms` xostidagi WebView cookie'si `seoulbeauty.app` ga o'tmaydi;
   ilova tokeni saqlangan foydalanuvchilar avtomatik kiradi, qolganlari yangilanishdan keyin bir marta login qiladi.
2. **Kirish, ro'yxatdan o'tish va parol tiklash havolalari ilovani ochmaydi** (Android va iOS'da bir xil) — brauzerda boshlangan kirish brauzerda tugaydi.
   Parol tiklash xatidagi havola brauzerda ochiladi; yangi parol qo'yilgach foydalanuvchi ilovaga qaytib kiradi.
3. **Eski `hms.seoulbeauty.app` havolalari tashqaridan bosilsa brauzerda ochiladi** (ilova ichida esa `seoulbeauty.app` dagi mos sahifaga o'tadi).
4. **Apple havola fayli keshlanadi**: `apple-app-site-association` o'zgarishi qurilmalarga 24–48 soatgacha kechikib yetishi mumkin.
5. Next'da muqobili yo'q eski PHP sahifalar (`chat.php`, `my_events.php`, `my_coupons.php` va boshqalar): ilova va sayt ularga havola bermaydi;
   faqat qo'lda shunday havola yuborilsa, a'zo u sahifada mehmon ko'rinadi.
6. Hisob o'chirilganda (탈퇴) a'zoning push tokenlari jadvalda qoladi — unga push yuborilmaydi, tozalash keyin.

## 9. Relizdan keyin

- Birinchi 3 kun: Crashlytics, kirish xatolari, to'lov qaytishi, push kelishi va ochilishini kuzating.
- **3 kundan so'ng** (2.2.0 ikkala do'konda chiqib, yangilanish ulushi yetarli bo'lgach) PHP `app_*` fayllari
  (`app_auth_*.php`, `app_session_land.php`, `app_logout.php`) o'chiriladi (`_to_delete/` ga ko'chirish orqali).
  Diqqat: shundan keyin 2.1.x da qolgan foydalanuvchilar kira olmaydi — o'chirishdan oldin eski versiya ulushini
  (Play Console / App Store Connect) tekshiring yoki majburiy yangilashni yoqing.
- 2.1.x foydalanuvchilari qolmagach: `INICIS_PUBLIC_HOSTS` dan `hms.seoulbeauty.app` ni olib tashlash; `hms` xostini o'chirish mumkin.
- `BUILD_GUIDE.md` eskirgan — bu hujjat va `scripts/build_release.sh` uning o'rnini bosadi.
