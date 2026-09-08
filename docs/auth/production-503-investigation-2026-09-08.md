# Production exchange 503 tekshiruvi — 2026-09-08

## Bevosita tasdiqlangan natija

Tasodifiy yaroqsiz tokenlar bilan productionga qayta so'rov yuborildi.
Mijoz akkaunti yoki mavjud credential ishlatilmadi.

| Endpoint | Natija |
|---|---|
| `app_auth_exchange.php` | HTTP 503, JSON `success:false`, `error:exchange_unavailable` |
| `app_auth_verify.php` | HTTP 401, `access_token_invalid` |
| `app_auth_refresh.php` | HTTP 401, `refresh_invalid` |

503 oddiy HTML proxy xabari emas, auth API xato shaklida qaytyapti.
Production tuzatilgan deb hisoblanmaydi. Bitta muvaffaqiyatsiz probe barcha
workerlar, qurilmalar va tarixiy login urinishlarining holatini isbotlamaydi.

## Nima aniq, nima hali noma'lum

Exchange quyidagi tartibda ishlaydi: DB ulanishi/vaqt zonasi → worker kaliti →
`payload` sxemasi/eskirgan payload tozalash → exchange kodini tekshirish →
member/config → tokenlar → shifrlangan payload → commit.

Yaroqsiz kod normal holatda `exchange_invalid`ga olib keladi. Lekin undan
oldingi kalit yoki payload tekshiruvi yiqilsa 503 chiqadi. Lokal haqiqiy
PHP/MySQL sinovida **kalit yo'qligi ham, payload ustuni yo'qligi ham aynan
shu tashqi 503** javobini berdi. Shuning uchun faqat bu HTTP natija bilan
ikkalasidan qaysi biri productionda xato ekanini aytib bo'lmaydi.

Verify/refreshning yaroqsiz credentialni rad etishi kalit, payload, token
INSERT, email konfiguratsiyasi yoki yangi bridge berilishi ishlashini
isbotlamaydi. Uchta smoke PASS — zarur boshlang'ich shart, to'liq reliz testi emas.

## Diagnostika yaxshilandi — hozircha lokal kodda

Exchange logiga faqat `stage`, exception klassi va raqamli kod qo'shildi.
Kalit, token, SQL matni yoki exception message yozilmaydi.

| Log stage | Tekshiriladigan joy |
|---|---|
| `connection` | DB ulanishi, charset, timezone |
| `encryption_key` | Aynan HTTP PHP workeridagi APP_NATIVE_AUTH_KEY |
| `payload_schema` | API ishlatayotgan bazadagi payload ustuni va UPDATE huquqi |
| `exchange_claim` | Token sxemasi, claim/lookup so'rovlari |
| `member_config` | g5_member va g5_config |
| `token_issuance` | Native token turlari, INSERT/DELETE huquqlari |
| `payload_decryption` / `payload_storage` | OpenSSL, kalit barqarorligi, payload |
| `commit` / `response_encoding` | Tranzaksiya va JSON javobi |

O'qish uchun server preflight qo'shildi:

```sh
php scripts/auth/inspect_native_auth.php /actual/path/to/hairwang_full
```

Skript kerakli fayllar va SHA256lari, CLI key formati, OpenSSL, haqiqiy API
`dbconfig.php` ulanishi, ustunlar, enum turlari, InnoDB va g5_configni tekshiradi.
U bazani o'zgartirmaydi va maxfiy qiymatlarni chiqarmaydi. CLI muhitini
tekshiradi; PHP-FPM/Apache worker muhitini tasdiqlamaydi. HTTP endpoint qilib
joylashtirilmasin. Server va lokal fayl hashlarini solishtirish qaysi kod
productionda ishlayotganini tekshirishga yordam beradi.

**46 HTTP/preflight regressiya tekshiruvi o'tdi.** Bunga kalit yo'qligi,
payload yo'qligi, DB ulanishi xatosi va log maxfiy tokenni chiqarmasligi kiradi.
PHP lint va diff whitespace tekshiruvi o'tdi.

## Deploy haqidagi da'volarga aniqlik

- Lokal working tree hali commit qilinmagan. Lekin commit/push har qanday
  deploy usulining texnik sharti emas: bevosita rsync uncommitted faylni ham
  uzatishi mumkin. Nazorat qilinadigan bir xil revisionni joylashtirish kerak.
- Repodagi `deploy/deploy_to_production.sh` serverdagi `hairwang_dev/`ni butun
  `hairwang_full/`ga rsync qiladi. Bu faqat gangnamunni emas. Ammo aynan shu
  skript productiondagi bir daqiqalik sync ekaniga dalil yo'q; faol cron,
  service yoki tashqi sync konfiguratsiyasi olinmadi.
- Mazkur skript native migratsiyalarni qo'llamaydi va worker keyni o'rnatmaydi.
  Faqat fayl nusxalash yetarli emas. Undagi PHP lint faqat 10 faylni tekshiradi
  va nusxalashdan keyin bajariladi; uni to'liq native-auth preflight deb bo'lmaydi.
- Native API `hairwang_full/dbconfig.php`, Gnuboard common esa
  `data/dbconfig.php`, Laravel migratsiyasi esa Laravel DB connectionini
  ishlatadi. Productionda ularning aynan kerakli bazaga qarayotganini
  tekshirmasdan `artisan migrate` bajarish muammoni yopganini isbotlamaydi.
- Gangnamunni tashqarisidagi common/config/lib/bbs/extend fayllar va yangi
  required include fayllar ham mos versiyada bo'lishi kerak.

## Foydalanuvchi qayta login qilishi haqida

503 paytida native token saqlanmaydi; eski ilovaning no-token holatida cookie
loginni tozalashi muammoni kuchaytiradi. Bu ikki mexanizm kodda tasdiqlangan.
Lekin barcha eski foydalanuvchi majburan bir marta qayta login qilishi shart emas.
Yangi kod yaroqli cookie sessiyasini saqlaydi va authenticated bootstrap orqali
native tokenga o'tkaza oladi. Cookie ham yo'qolgan/eskirgan bo'lsa qayta login kerak.

## Productiondagi aniq sababni yopish uchun yetishmayotgan dalil

Production SSH aliasi/hosti, HTTP PHP error logi va faol deploy/sync
konfiguratsiyasi hali berilmadi. Hozir server konfiguratsiyasi, migratsiyalar
va production fayllari o'zgartirilmadi. Keyingi qadam — HTTP worker logidagi
xatoni aktiv DB sxemasi va worker kaliti bilan solishtirish; keyin bir xil
backend revisionni joylashtirib smoke va test akkaunti bilan haqiqiy login /
exchange / verify / refresh / WebView tiklashni tekshirish. Yangi AAB yig'ilmadi.
