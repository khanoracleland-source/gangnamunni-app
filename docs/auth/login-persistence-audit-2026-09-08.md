# Login saqlanishi tekshiruvi — 2026-09-08

Holat: lokal kod tuzatildi va avtomatik testlardan o'tkazildi. Production
serverdagi exchange endpoint hali 503 qaytaryapti. Shu sababli telefon uchun
“120 kun login saqlanishi to'liq tasdiqlandi” degan xulosa chiqarilmaydi.

## Aniqlangan sabablar va tuzatishlar

| Holat | Avvalgi muammo | Tuzatish |
|---|---|---|
| Ilovani qayta ochish | Native token yo'qligi bekor qilingan login bilan bir xil baholanib, barcha WebView cookielari o'chirilgan. | `noStoredSession` alohida holat; cookie saqlanadi. Tasdiqlangan token rad etilishi yoki logout esa cookielarni tozalaydi. |
| Gnuboard avtomatik login | `common.php` `$connect_db`ni yaratgan, remember/SSO yordamchilari esa `$connect` globalini kutgan. Sahifa initidagi keyingi ulanish dastlabki login tekshiruvi uchun kech bo'lgan. | Bitta ulanish login tekshiruvi va extend hooklardan oldin ikkala nom bilan bog'landi. |
| Eski WebView akkaunti | Native token yangi akkauntniki bo'lsa ham, eski PHPSESSID tekshiruvi bridge tokenidan oldin ishlashi mumkin. | Native akkaunt tasdiqlangach eski cookielar tozalanib, bridge birinchi requestdan oldin qo'yiladi. Retry va cookie-only holatlarda bu tozalash bajarilmaydi. |
| Vaqt zonasi | Web PHP `Asia/Seoul`; alohida native API va MySQL sessiyasi unga moslanmagan. Qisqa token yozilishi va tekshirilishi orasida 9 soat tafovut bo'lishi mumkin. | Web SQL sessiyasi va native API PHP/SQL soati bir xil Seoul vaqtiga moslandi. UTCdan boshlangan haqiqiy HTTP server bilan tekshirildi. |
| Login ortidan sahifalar almashishi | Har sahifa boshqa exchange kodini chiqargan; kechikkan javoblar raqobat qilgan. | PHP sessiyasida bir login oynasi uchun bitta kod; Flutterda takroriy kod bitta operatsiya, boshqa kodlar ketma-ket bajariladi. |
| Exchange javobidan oldin ilovani o'ldirish | Server native javobni chiqarishdan oldin shu qurilmaning mavjud remember tokenlarini, jumladan cookie tokenini o'chirgan. Telefon javobni saqlashga ulgurmasa ikkala yo'l yo'qolgan. | Exchange yaroqli cookie tokenini bekor qilmaydi. Muddati o'tgan tokenlar tozalanadi; explicit logout shu qurilmadagi barcha tokenlarni bekor qiladi. |
| Diskka saqlash | 5 alohida secure-storage yozuvi yarim holatda qolishi mumkin; Android `apply()` diskka yozilishidan oldin javob bergan. | Bitta shifrlangan JSON yozuvi; Android worker threadda `commit()` natijasini kutadi. Eski kalitlardan migratsiya bor. |
| Logout bilan poyga | Epoch yozuvdan oldin tekshirilgan, lekin asinxron yozuv davomida logout aralashishi mumkin. Kechikkan server logout yangi loginni ham bekor qilishi mumkin. | Mutatsiya va epoch tekshiruvi bir navbatda; lokal logout tarmoqdan oldin saqlanadi. Keyingi exchange server logout tugashini kutadi. |
| Noto'g'ri API javobi | To'liq bo'lmagan 200 javob tokenlarni yo'qotishi mumkin; har qanday 401 logout deb qabul qilingan. | To'liq token to'plami yozishdan oldin tekshiriladi. Faqat auth API'ning tanilgan 401 xatolari loginni bekor qiladi. Proxy/HTML/5xx/timeout tokenni saqlaydi. |
| Eski versiyadan yangilash | Yangi native device ID eski cookie tokeni bog'langan IDni almashtirishi mumkin. | Cookie-only holatda ID ustiga yozilmaydi; autentifikatsiyalangan exchange eski qurilma ID'sini secure storagega ko'chiradi. |
| O'tkazib yuborilgan exchange | Faqat login ortidan 60 soniya davomida ishlagan; outage, redirect yoki kechikkan sahifadan so'ng qayta urinish yo'q. | Authenticated WebView orqali `app_auth_bootstrap.php` faqat qisqa kod beradi; keyingi sahifa tugaganda tiklashga uriniladi. Guest yoki cross-origin oddiy so'rovdan token olinmaydi. |
| WebView ishga tushishi | JS channel ro'yxatdan o'tishi kutilmagan; cookie/setup xatosi foydalanuvchini osilib qolgan ekranda qoldirishi mumkin. | Channel, cookie va navigation delegate birinchi requestdan oldin tayyorlanadi. Setup/main-frame xatolari retry ko'rsatadi. |
| Android backup | Shifrlangan preferences boshqa qurilmaga Keystore kalitisiz qaytishi mumkin. | Secure-storage fayllari cloud backup va device-transferdan chiqarildi. Oddiy ilova yangilanishi ma'lumotlarni saqlaydi. |

## Tekshiruv natijalari

- Flutter: **101 test o'tdi** — 47 native auth, 10 auth-gate/WebView startup,
  44 mavjud OAuth testi.
- Haqiqiy PHP HTTP server + alohida vaqtinchalik MySQL bazasi: **40 tekshiruv
  o'tdi**. Exchange, bir xil javobni qayta olish, access expiry, refresh,
  120 kunlik refresh expiry, haqiqiy web-session promotion, bir martalik bridge,
  ikkita qurilma, logout, bloklangan akkaunt, yo'q migratsiya va DB xatosi.
- MySQL tranzaksiya/shifrlash: **32 tekshiruv o'tdi**. Qulflash, rollback,
  logout bilan parallel requestlar, kod takrori va UTC/Seoul farqi.
- Mavjud `patient_app_remember.php` regressiyasi o'tdi: 120 kun, renewal,
  qurilma bog'lanishi va logout.
- Eski native-auth regressiyasining **60 tekshiruvi** alohida vaqtinchalik
  bazada o'tdi; ikkala SSO session-promoter ham tekshirildi.
- Dart analyzer: xato yo'q. O'zgargan/yangi **18 PHP fayl** sintaksisi toza.
- Secure-storage Java kodi alohida kompilyatsiyadan o'tdi. Bu Android
  Keystore/migratsiyani haqiqiy qurilmada sinashning o'rnini bosmaydi.
- Avvalgi debug APK va release AAB yig'ilgan, lekin ushbu yakuniy refaktoringdan
  oldingi kodni o'z ichiga oladi. Foydalanuvchi ko'rsatmasi bo'yicha yangi AAB
  yig'ilmadi; bu eski artefaktlar relizga tayyor natija hisoblanmaydi.

Flutter testidagi “cold start” yangi service instance va o'sha saqlangan
ma'lumot bilan tekshirilgan. Bu Android/iOS processini haqiqiy o'ldirish testi
bilan teng emas. HTTP test haqiqiy endpointlar va session-promoter kodini
ishlatadi; butun production Gnuboard UI va Kakao/Apple tashqi xizmatlarini
emulyatsiya qilmaydi.

## Productionda tasdiqlangan to'siq

`scripts/auth/check_native_auth.py` natijasi:

| Endpoint | Olingan javob | Diagnostika uchun kutilgan javob |
|---|---|---|
| `app_auth_exchange.php` | **503** | `401 exchange_invalid` |
| `app_auth_verify.php` | 401 | `401 access_token_invalid` |
| `app_auth_refresh.php` | 401 | `401 refresh_invalid` |

So'rovlar ataylab yaroqsiz test ma'lumotlari bilan yuborildi; mijoz
akkaunti ishlatilmadi. Exchange 503ning ichki sababini server logisiz
aniq ajratib bo'lmaydi. Birinchi tekshiriladiganlar:

1. PHP worker muhitidagi `APP_NATIVE_AUTH_KEY`: base64 shaklidagi 32 bayt.
   Faqat Laravel `.env`iga yozish bu mustaqil PHP endpointga yetib borishini
   kafolatlamaydi. Kalitni logga yoki chatga chiqarmaslik kerak.
2. `2026_09_07_000001` token type migratsiyasi va `2026_09_08_000001`
   `payload` ustuni migratsiyasi. `sso_tokens` InnoDB bo'lishi kerak.
3. Exchange endpointining PHP error logi va SQL xatosi; verify/refreshning
   javob berishi exchange shifrlash va payload sxemasi tayyorligini isbotlamaydi.

Serverga SSH kirish berilmagani uchun production konfiguratsiyasi o'zgartirilmadi.

## Relizdan oldin bajariladigan qabul tekshiruvi

1. Avval backend tuzatishlarini, jumladan yangi bootstrap endpointni,
   ikkala migratsiyani va worker kalitini joylash. Kalit barcha workerlarda
   bir xil bo'lsin. Smoke skripti muvaffaqiyatli tugamaguncha relizni to'xtatish:
   `python3 scripts/auth/check_native_auth.py https://hms.seoulbeauty.app`.
2. Ichki test orqali eski versiya ustiga yangisini o'rnatish. O'chirib-qayta
   o'rnatish yoki app data clear qilish yangilanish testini almashtirmaydi.
   Application ID va signing identity bir xil qolishi kerak.
3. Email, Kakao va Apple orqali alohida login: bir necha sahifaga tez o'tish,
   ilovani force-stop qilish, qayta ochish, aynan o'sha akkauntni ko'rish.
4. Login tugashi atrofida turli paytda yopish: birinchi sahifa ko'ringanda,
   exchange ketayotganida va token saqlangach. Kamida Android va iOSda sinash.
5. Internetni uzib ochish: token yo'qolmasligi, internet qaytgach retry bilan
   tiklanishi. Access muddati tugagandan keyin ham shu holatni tekshirish.
6. Ikki qurilmada bir akkaunt: birida logout ikkinchisiga ta'sir qilmasligi.
   Logoutdan keyin appni o'ldirib qayta ochish loginni qaytarmasligi.
7. Qurilma qayta yuklanishi va ilova ustidan update holatlarini alohida sinash.

Hozir ulangan telefon/emulator topilmadi; ushbu qurilma qabul sinovlari hali
bajarilmagan. O'zgargan saqlash formati v2: eski buildga downgrade qilish
loginni saqlash bo'yicha qo'llab-quvvatlangan rollback yo'li emas. 120 kunlik
sliding muddat serverdagi valid token bilan davom etadi; explicit logout,
akkaunt bloklanishi, app data clear va muddat tugashi qayta login talab qiladi.


## Keyingi faylma-fayl tekshiruv va tozalash

Ushbu tekshiruv bemor ilovasining native login saqlanishi, unga xizmat qiluvchi
PHP API, ikki WebView SSO-promoter va logout zanjiriga qaratildi. Bu hisobot
butun repodagi barcha eski login/OAuth/payment kodlari auditi tugaganini
bildirmaydi. Alohida legacy `api/app_login.php` faol Flutter native oqimidan
chaqirilmaydi; tashqi eski mijozlar yo'qligi isbotlanmagani uchun ommaviy
endpoint dead code deb o'chirilmadi.

| Qism | Mas'uliyat / tekshirilgan qoida |
|---|---|
| `app_native_auth_http.php` | POST/OPTIONS, no-store, request maydonlari, ulanish va akkaunt javobi. Ulanish endpointning try/catch ichida: DB xatosi JSON 503. |
| `app_native_auth.php` | Kod/token yaratish, shifrlangan idempotent payload, tranzaksiya, qurilma bog'lanishi va revokatsiya. Ishlatilmaydigan `app_discard_stale_refresh_token` o'chirildi. |
| `sso_session_token.php` | Ikki promoter uchun yagona token tekshiruvi. Faqat raw SSO turlari va bir martalik native bridge qabul qilinadi. Access/refresh/exchange xeshini raw SSO sifatida ishlatish yopildi. |
| API endpointlar | Har biri faqat exchange, verify, refresh yoki logout amalini boshqaradi. Takroriy member query va HTTP/DB bootstrap kodi olib tashlandi. |
| `NativeAuthService` | Shifrlangan sessiya yozuvi, eski kalit migratsiyasi, operatsiyalar ketma-ketligi, tekshirish/tiklash/logout. Corrupt/read-error sessiya explicit logout yozuvini to'xtatmaydi; yozish xatosi muvaffaqiyat deb yashirilmaydi. |
| `WebViewBridge` | Ishonchli origin, tur tekshiruvi, native xizmatga uzatish. Noto'g'ri JSON action/code/device turlari credentialni o'zgartirmaydi. Gate bilan aynan bir auth service ishlatiladi. |
| Gate va StartupWebView | Native holatdan keyin cookie/channel/delegate tayyorlash; saqlangan akkaunt bilan server javobidagi akkaunt mos bo'lishi shart. |
| Android secure storage | `commit()` orqali tasdiqlangan yozuvlar. Migratsiya butun destinationni yozgach source'ni tozalaydi, retry yangi target qiymatini eski nusxa bilan almashtirmaydi. Encrypted storage xatosida boshqa store'ga jim o'tish olib tashlandi. |
| Web logout | SSO revokatsiyasi bitta hookda. Ikkinchi DB ulanishi va takroriy DELETE olib tashlandi. Revokatsiya xatosi Flutterga logout xabarini yetkazuvchi redirectni to'xtatmaydi. |

Qo'shimcha tuzatishlar: member ID boshqa qiymatga sanitizatsiya qilinmaydi;
bo'sh site config email-certification tekshiruvini chetlab o'tmaydi; logout
DB xatosida success qaytarmaydi; xeshlarni legacy logout bearer sifatida
ishlatib native tokenlarni o'chirib bo'lmaydi. Eski izohlar amaldagi
idempotent exchange, session shutdown va refresh-renewal tartibiga moslandi.

Yangi HTTP sinovlari DB ulanishi yo'qolishi, yo'q config, noto'g'ri request
turlari, barcha endpointlarda GET rad etilishi, aniq member ID, native hash
bilan SSO login/logout urinishlarini qamrab oladi. Flutter sinovlari corrupt
storage logout, read/write xatolari, noto'g'ri yoki yetishmaydigan server
akkaunti va noto'g'ri bridge xabarlarini qamrab oladi.
