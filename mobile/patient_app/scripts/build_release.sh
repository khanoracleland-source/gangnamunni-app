#!/usr/bin/env bash
# 서울미녀 bemor ilovasi — reliz build skripti (Mac terminalida ishga tushiriladi).
#
#   ./scripts/build_release.sh                 analyze + test + .aab + .ipa
#   ./scripts/build_release.sh --skip-tests    testlarsiz
#   ./scripts/build_release.sh --android-only  faqat Google Play (.aab)
#   ./scripts/build_release.sh --ios-only      faqat App Store (.ipa)
#   ./scripts/build_release.sh --clean         boshida `flutter clean` (standartda QILINMAYDI)
#
# Natija: ~/Desktop/서울미녀-<versiya>+<build>/ (aab, ipa, RELEASE_<versiya>.md, build.log).
# Skript do'konga hech narsa YUKLAMAYDI, versiyani o'zgartirmaydi, hech narsani commit qilmaydi.
# Kalitlar (key.properties, .jks) mazmuni o'qib chiqarilmaydi — faqat mavjudligi tekshiriladi.
# macOS'dagi bash 3.2 bilan mos (mapfile / assotsiativ massiv ishlatilmagan).

set -euo pipefail

SKIP_TESTS=0
DO_ANDROID=1
DO_IOS=1
DO_CLEAN=0

usage() {
  sed -n '2,12p' "$0" | sed 's/^# \{0,1\}//'
}

for arg in "$@"; do
  case "$arg" in
    --skip-tests)   SKIP_TESTS=1 ;;
    --android-only) DO_IOS=0 ;;
    --ios-only)     DO_ANDROID=0 ;;
    --clean)        DO_CLEAN=1 ;;
    -h|--help)      usage; exit 0 ;;
    *) echo "XATO: noma'lum bayroq: $arg" >&2; usage >&2; exit 2 ;;
  esac
done

if [ "$DO_ANDROID" -eq 0 ] && [ "$DO_IOS" -eq 0 ]; then
  echo "XATO: --android-only va --ios-only birga ishlatilmaydi." >&2
  exit 2
fi

# --- joylashuv: skript qayerdan chaqirilsa ham mobile/patient_app ichida ishlaymiz ---
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
APP_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$APP_DIR"

if [ ! -f pubspec.yaml ]; then
  echo "XATO: pubspec.yaml topilmadi ($APP_DIR)." >&2
  exit 1
fi

# --- versiya: pubspec.yaml dagi `version: x.y.z+build` ---
VERSION_LINE="$( (grep -E '^version:[[:space:]]*' pubspec.yaml || true) | head -n 1 | sed -E 's/^version:[[:space:]]*//' | tr -d '[:space:]')"
VERSION_NAME="${VERSION_LINE%%+*}"
BUILD_NUMBER="${VERSION_LINE##*+}"
case "$VERSION_LINE" in
  *+*) ;;
  *) echo "XATO: pubspec.yaml da versiya 'x.y.z+build' ko'rinishida emas: '$VERSION_LINE'" >&2; exit 1 ;;
esac
case "$BUILD_NUMBER" in
  ''|*[!0-9]*) echo "XATO: build raqami son emas: '$BUILD_NUMBER'" >&2; exit 1 ;;
esac

OUT="$HOME/Desktop/서울미녀-${VERSION_NAME}+${BUILD_NUMBER}"
mkdir -p "$OUT"
LOG="$OUT/build.log"
# Shu ishga tushirishdan OLDIN qurilgan (eski) fayllarni natija deb olmaslik uchun vaqt belgisi.
MARKER="$OUT/.build_start"

AAB_SRC="build/app/outputs/bundle/release/app-release.aab"
AAB_DST="$OUT/seoulbeauty-${VERSION_NAME}-${BUILD_NUMBER}.aab"
IPA_DST="$OUT/seoulbeauty-${VERSION_NAME}-${BUILD_NUMBER}.ipa"
EXPORT_PLIST="ios/ExportOptions.plist"
RELEASE_DOC="RELEASE_${VERSION_NAME}.md"

step() {
  echo
  echo "════════════════════════════════════════════════════════════"
  echo "▶ $1"
  echo "════════════════════════════════════════════════════════════"
}

fail() {
  echo
  echo "✗ XATO: $1" >&2
  echo "  To'liq log: $LOG" >&2
  exit 1
}

main() {
  touch "$MARKER"
  echo "서울미녀 reliz build — ${VERSION_NAME}+${BUILD_NUMBER}"
  echo "Boshlandi: $(date '+%Y-%m-%d %H:%M:%S')"
  echo "Loyiha:    $APP_DIR"
  echo "Natija:    $OUT"
  echo "Rejim:     android=$DO_ANDROID ios=$DO_IOS skip-tests=$SKIP_TESTS clean=$DO_CLEAN"

  # ---------------------------------------------------------------- 0. muhit
  step "0/7  Muhit va old tekshiruv"

  if command -v fvm >/dev/null 2>&1; then
    FLUTTER="fvm flutter"      # .fvmrc dagi versiya (3.35.4) ishlatiladi
  elif command -v flutter >/dev/null 2>&1; then
    FLUTTER="flutter"
    echo "OGOHLANTIRISH: fvm topilmadi — tizimdagi 'flutter' ishlatiladi (.fvmrc: $(tr -d '[:space:]' < .fvmrc 2>/dev/null || echo '?'))."
  else
    fail "na 'fvm', na 'flutter' topildi. Flutter o'rnating yoki PATH ni tekshiring (bu skript Mac terminalida ishlaydi)."
  fi
  echo "Flutter buyrug'i: $FLUTTER"
  $FLUTTER --version || fail "'$FLUTTER --version' ishlamadi (fvm bo'lsa: 'fvm install' ni ishga tushiring)."

  # Firebase fayllari (mazmuni o'qilmaydi)
  [ -f android/app/google-services.json ]     || fail "android/app/google-services.json yo'q."
  [ -f ios/Runner/GoogleService-Info.plist ]  || fail "ios/Runner/GoogleService-Info.plist yo'q."

  if [ "$DO_ANDROID" -eq 1 ]; then
    [ -f android/key.properties ] || fail "android/key.properties yo'q — release imzosiz .aab ni Play qabul qilmaydi."
    # Faqat kalit NOMLARI tekshiriladi; qiymatlar ekranga/logga chiqmaydi.
    for k in storePassword keyPassword keyAlias storeFile; do
      grep -q "^${k}=" android/key.properties || fail "android/key.properties da '${k}' yo'q."
    done
    # storeFile yo'li android/app ga nisbatan (build.gradle: file(...)). Yo'l logga yozilmaydi.
    STORE_FILE="$(grep '^storeFile=' android/key.properties | head -n 1 | cut -d= -f2- | tr -d '\r')"
    if ! ( cd android/app && [ -f "$STORE_FILE" ] ); then
      fail "key.properties dagi storeFile ko'rsatgan keystore (.jks) topilmadi."
    fi
    unset STORE_FILE
    echo "✓ Android imzo fayllari joyida (key.properties + keystore)."
  fi

  if [ "$DO_IOS" -eq 1 ]; then
    [ "$(uname -s)" = "Darwin" ] || fail "iOS build faqat macOS'da ishlaydi. (--android-only bilan urinib ko'ring.)"
    command -v xcodebuild >/dev/null 2>&1 || fail "xcodebuild topilmadi — Xcode o'rnatilmagan."
    if [ -f "$EXPORT_PLIST" ]; then
      echo "✓ $EXPORT_PLIST bor — IPA shu sozlama bilan eksport qilinadi."
    else
      echo "OGOHLANTIRISH: $EXPORT_PLIST yo'q — Flutter standart (app-store) eksportidan foydalanadi."
    fi
  fi

  # ---------------------------------------------------------------- 1. clean
  if [ "$DO_CLEAN" -eq 1 ]; then
    step "1/7  flutter clean (--clean so'ralgan)"
    $FLUTTER clean || fail "flutter clean yiqildi."
  else
    step "1/7  flutter clean — O'TKAZIB YUBORILDI (kerak bo'lsa --clean)"
  fi

  # ---------------------------------------------------------------- 2. pub get
  step "2/7  flutter pub get"
  $FLUTTER pub get || fail "flutter pub get yiqildi."

  # ---------------------------------------------------------------- 3. analyze
  # analysis_options.yaml bir qancha lint'larni 'warning' darajasiga ko'targan va eski (kivicare)
  # kodda shunday ogohlantirishlar ko'p. Shuning uchun info/warning relizni to'xtatmaydi
  # (--no-fatal-infos --no-fatal-warnings); haqiqiy XATO (error) bo'lsa analyze 0 dan farqli
  # kod qaytaradi va build boshlanmaydi.
  step "3/7  flutter analyze (faqat error to'xtatadi)"
  $FLUTTER analyze --no-fatal-infos --no-fatal-warnings || fail "flutter analyze xato topdi — build qilinmadi."

  # ---------------------------------------------------------------- 4. test
  if [ "$SKIP_TESTS" -eq 1 ]; then
    step "4/7  flutter test — O'TKAZIB YUBORILDI (--skip-tests)"
    echo "OGOHLANTIRISH: testlar ishga tushirilmadi. Reliz uchun testsiz build tavsiya etilmaydi."
  else
    step "4/7  flutter test"
    $FLUTTER test || fail "flutter test yiqildi — build qilinmadi."
  fi

  # ---------------------------------------------------------------- 5. Android
  if [ "$DO_ANDROID" -eq 1 ]; then
    step "5/7  Android: flutter build appbundle --release"
    $FLUTTER build appbundle --release || fail "appbundle build yiqildi."
    [ -f "$AAB_SRC" ] || fail "$AAB_SRC hosil bo'lmadi."
    [ "$AAB_SRC" -nt "$MARKER" ] || fail "$AAB_SRC eski (shu ishga tushirishda qayta qurilmagan)."
    cp "$AAB_SRC" "$AAB_DST"
    echo "✓ $AAB_DST"
    # Release kaliti bilan imzolanganini tekshirish (sertifikat mazmuni chiqarilmaydi).
    if command -v unzip >/dev/null 2>&1; then
      # Ro'yxat avval o'zgaruvchiga olinadi: `unzip | grep -q` pipefail ostida SIGPIPE tufayli yolg'on xato berishi mumkin.
      AAB_LIST="$(unzip -l "$AAB_DST" 2>/dev/null || true)"
      if printf '%s\n' "$AAB_LIST" | grep -E 'META-INF/.*\.(RSA|EC|DSA)$' >/dev/null; then
        echo "✓ .aab imzolangan (META-INF ichida imzo bloki bor)."
      else
        echo "OGOHLANTIRISH: .aab ichida imzo bloki topilmadi — Play Console rad etishi mumkin."
      fi
    fi
  else
    step "5/7  Android — O'TKAZIB YUBORILDI (--ios-only)"
  fi

  # ---------------------------------------------------------------- 6. iOS
  if [ "$DO_IOS" -eq 1 ]; then
    step "6/7  iOS: flutter build ipa --release"
    if [ -f "$EXPORT_PLIST" ]; then
      $FLUTTER build ipa --release --export-options-plist="$EXPORT_PLIST" || fail "ipa build yiqildi (imzo: Xcode'da Apple ID / team JZ4MFA48JA kirganini tekshiring)."
    else
      $FLUTTER build ipa --release || fail "ipa build yiqildi (imzo: Xcode'da Apple ID / team JZ4MFA48JA kirganini tekshiring)."
    fi
    # IPA nomi ilova nomidan olinadi (서울미녀.ipa) — shu ishga tushirishda yaratilgan eng yangisini olamiz.
    IPA_SRC=""
    for f in build/ios/ipa/*.ipa; do
      [ -f "$f" ] || continue
      [ "$f" -nt "$MARKER" ] || continue
      if [ -z "$IPA_SRC" ] || [ "$f" -nt "$IPA_SRC" ]; then
        IPA_SRC="$f"
      fi
    done
    [ -n "$IPA_SRC" ] || fail "build/ios/ipa/ ichida yangi .ipa topilmadi (arxiv build/ios/archive/ da qolgan bo'lishi mumkin — Xcode Organizer'dan eksport qiling)."
    cp "$IPA_SRC" "$IPA_DST"
    echo "✓ $IPA_DST  (manba: $IPA_SRC)"
    # IPA ichidagi versiya kutilganiga mosligini tekshirish.
    # (ExportOptions'da manageAppVersionAndBuildNumber=true — Xcode build raqamini o'zi oshirishi mumkin.)
    if command -v unzip >/dev/null 2>&1 && [ -x /usr/libexec/PlistBuddy ]; then
      TMP_PLIST="$(mktemp -t sbinfo)"
      if unzip -p "$IPA_DST" 'Payload/Runner.app/Info.plist' > "$TMP_PLIST" 2>/dev/null && [ -s "$TMP_PLIST" ]; then
        IPA_VER="$(/usr/libexec/PlistBuddy -c 'Print :CFBundleShortVersionString' "$TMP_PLIST" 2>/dev/null || echo '?')"
        IPA_BUILD="$(/usr/libexec/PlistBuddy -c 'Print :CFBundleVersion' "$TMP_PLIST" 2>/dev/null || echo '?')"
        IPA_BID="$(/usr/libexec/PlistBuddy -c 'Print :CFBundleIdentifier' "$TMP_PLIST" 2>/dev/null || echo '?')"
        echo "IPA ichida: $IPA_BID  $IPA_VER ($IPA_BUILD)"
        if [ "$IPA_VER" != "$VERSION_NAME" ] || [ "$IPA_BUILD" != "$BUILD_NUMBER" ]; then
          echo "OGOHLANTIRISH: IPA versiyasi pubspec'dan farq qiladi (kutilgan ${VERSION_NAME} (${BUILD_NUMBER}))."
        fi
      fi
      rm -f "$TMP_PLIST"
    fi
  else
    step "6/7  iOS — O'TKAZIB YUBORILDI (--android-only)"
  fi

  # ---------------------------------------------------------------- 7. natija
  step "7/7  Natija"
  if [ -f "$RELEASE_DOC" ]; then
    cp "$RELEASE_DOC" "$OUT/"
  else
    echo "OGOHLANTIRISH: $RELEASE_DOC topilmadi — nusxalanmadi."
  fi

  echo "Papka: $OUT"
  ls -lh "$OUT"
  echo
  echo "SHA-256:"
  for f in "$AAB_DST" "$IPA_DST"; do
    if [ -f "$f" ] && [ "$f" -nt "$MARKER" ]; then
      shasum -a 256 "$f"
    fi
  done
  echo
  echo "✓ TAYYOR — ${VERSION_NAME}+${BUILD_NUMBER}   ($(date '+%Y-%m-%d %H:%M:%S'))"
  echo "Keyingi qadam (faqat siz): .aab → Play Console, .ipa → Transporter. Qarang: $OUT/$RELEASE_DOC"
}

# Butun chiqish ekranga ham, build.log ga ham yoziladi; pipefail tufayli main ning xato kodi saqlanadi.
main 2>&1 | tee "$LOG"
