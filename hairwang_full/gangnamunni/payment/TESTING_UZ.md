# Booking va PortOne to‘lovlarini test qilish

2026-09-16 tekshiruvi: **57 ta regression, 26 ta HTTP, 11 ta lokal ilova va
5 ta konfiguratsiya tekshiruvi — jami 99 ta tekshiruv o‘tdi.** Haqiqiy PortOne so‘rovi va pul yechish
bajarilmadi. Lokal bazaga joriy migratsiya qo‘llandi.

## 1. Hozir nimani tekshirish mumkin?

| Qism | Lokal holat | Ma’nosi |
| --- | --- | --- |
| To‘lovsiz davom etish | Yoqilgan | Bron uchun alohida preview yozuvi yaratiladi; moliyaviy buyurtma `pending` qoladi. |
| PortOne muhiti | Sandbox | Test kanalidan foydalanish kerak. |
| Haqiqiy checkout | Tayyor emas | Store ID, API key/secret, public HTTPS URL va kanal kiritilmagan. |
| Baza jadvallari va migratsiya | Tayyor | Preview, refund so‘rovlari va settlement ustunlari mavjud. |
| Ball sarflash | O‘chirilgan | To‘lovdan ball ayirish va refundda qaytarish hali yakunlanmagan. |

## 2. Brauzerda to‘lovsiz oqimni tekshirish

Oddiy foydalanuvchi va admin uchun alohida brauzer profili yoki inkognito oynadan
foydalaning. O‘zingiz yaratgan test bronini ishlating.

| Qadam | Amal | Kutiladigan natija |
| --- | --- | --- |
| 1 | [Lokal sayt](http://localhost:8002/gangnamunni/)ga foydalanuvchi sifatida kiring, klinika/xizmat orqali bron yarating. | Bron [Mening bronlarim](http://localhost:8002/gangnamunni/my_bookings.php)da ko‘rinadi. |
| 2 | [Admin bronlar](http://localhost:8002/gangnamunni/admin_bookings.php)da bronni tekshirish bosqichiga o‘tkazing, klinikaga tegishli shifokor tayinlang va `예약확정` bilan tasdiqlang. | Bron holati `confirmed` bo‘ladi. |
| 3 | Foydalanuvchi oynasida bronlarni yangilang, `결제하기`ni bosing. | `booking_payment.php` ochiladi. Summa serverdagi buyurtmadan olinadi. Sozlamalarsiz to‘lov usullari o‘chiriladi va yonida izoh chiqadi. |
| 4 | `위 내용을 모두 확인했어요`ni belgilang, pastki paneldagi `결제 없이 계속하기`ni bosing. To‘lov usulini tanlash shart emas. | Natija: `결제 없이 계속했어요`. Haqiqiy to‘lov qilingan deb yozilmaydi. Shartlar belgilanmasa, tugma rozilik katagiga olib boradi. |
| 5 | Bronlar va admin sahifasini yangilang. | `결제 없이 진행` belgisi ko‘rinadi; real tushum/settlement summasi oshmaydi. |
| 6 | Shu bronning checkout sahifasiga qayting va to‘lovsiz davom etishni takrorlang. | Ikkinchi moliyaviy buyurtma yoki takroriy preview yaratilmaydi. |
| 7 | Shu preview bronni bekor qilish sahifasini oching. | Undirilgan pul yo‘qligi sabab qaytariladigan to‘lov summasi bo‘lmaydi. |

Qo‘shimcha tekshiruvlar:

- Boshqa foydalanuvchi hisobidan shu bron URLini oching: unga kirish rad etilishi kerak.
- Hali to‘lanmagan, preview ham qilinmagan boshqa bron uchun success URLini oching:
  to‘lov tugadi degan xabar chiqmasligi kerak.
- `BOOKING_PAYMENT_PREVIEW_ENABLED=0` qilib ilovani qayta yaratsangiz, preview
  tugmasi va uning server endpointi yopilishi kerak. Keyin kerakli sozlamaga qaytaring.
- To‘liq refund qilingan bron checkoutida qayta to‘lash yoki preview tugmasi chiqmasligi kerak.

## 3. Avtomatik testlarni qayta ishga tushirish

Loyiha papkasidagi terminalda, lokal Docker xizmatlari ishlab turganda bajaring:

```sh
docker exec hms-saas-gangnamunni-1 php /var/www/html/hairwang_full/gangnamunni/payment/check.php
docker exec hms-saas-gangnamunni-1 php /var/www/html/tests/payments/regression.php
docker exec hms-saas-gangnamunni-1 php /var/www/html/tests/payments/http.php
docker exec hms-saas-gangnamunni-1 php /var/www/html/tests/payments/smoke.php
docker exec hms-saas-gangnamunni-1 php /var/www/html/tests/payments/config.php
```

| Skript | Nimani tekshiradi | Hozirgi natija |
| --- | --- | --- |
| `check.php` | Sozlamalar mavjudligi va baza sxemasi. Kalit qiymatlarini chiqarmaydi. | `Real checkout: NOT READY`, preview `ON`, migratsiya `OK`. |
| `regression.php` | Summa/valyuta/tranzaksiya mosligi, takroriy callback, refund, timeout, tranzaksiya rollback, asl to‘lov sanasi, test tushumini chiqarib tashlash va oylik hisob-kitob. | 57 PASS |
| `http.php` | Haqiqiy endpoint kodida bron egasi, CSRF, noto‘g‘ri HTTP usuli, preview, UI holatlari va wallet ruxsatlari; preview jadvali yo‘qligi va tiklangandan keyingi oqim. | 26 PASS |
| `smoke.php` | Ishlayotgan lokal ilovada login va endpoint himoyalari. | 11 PASS |
| `config.php` | Veb-ilova DB sozlamasining ustuvorligi, fallback, test bazasining ajratilishi va buzilgan konfiguratsiyada to‘xtash. | 5 PASS |

Birinchi ikki test to‘plami tasodifiy nomdagi alohida test bazasini yaratadi va
tugagach o‘chiradi; MySQL foydalanuvchisiga test bazasini yaratish/o‘chirish huquqi
kerak. Regression PortOne javoblarini simulyatsiya qiladi. HTTP testidagi
foydalanuvchilar vaqtinchalik lokal serverga tegishli: bu haqiqiy login jarayonining
to‘liq testi emas. Smoke testi login qilinmagan so‘rovlarni tekshiradi.

`check.php`dagi `READY` faqat kerakli sozlamalar borligini bildiradi; API kalitlari
PortOne tomonidan qabul qilinishini isbotlamaydi. Uning exit kodi baza/sxema
holatini bildiradi, checkout holatini chiqish matnidan alohida o‘qing.

Agar migratsiya talab qilinsa, lokal muhitda bir marta bajaring:

```sh
docker exec hms-saas-gangnamunni-1 php /var/www/html/hairwang_full/gangnamunni/payment/migrate.php --local-only
```

Serverda Docker bo‘lmasa, loyiha ildizidan bajaring:

```sh
php hairwang_full/gangnamunni/payment/migrate.php
php hairwang_full/gangnamunni/payment/check.php
```

2026-09-16 kuni serverdagi `임시 결제 완료 처리에 실패했습니다` xabari tekshirildi:
preview va refund jadvallari yo‘q edi. Migratsiya saytning haqiqiy bazasiga
qo‘llandi. Preview INSERT tranzaksiyada sinalib, darhol rollback qilindi;
foydalanuvchining broni yoki to‘lov holati o‘zgartirilmadi.

CLI vositalari va webhook endi sayt kabi `hairwang_full/data/dbconfig.php`ni
ishlatadi. U yo‘q muhitdagina `hairwang_full/dbconfig.php`ga o‘tadi.
Fayllarni yangilash bilan birga bazaga migratsiya qo‘llash ham kerak;
faqat PHP fayllarini nusxalash yangi jadval yaratmaydi.

## 4. PortOne sandbox sinovi

Loyiha ildizidagi `.env`ga o‘z PortOne test rekvizitlaringizni kiriting.
Quyidagi qiymatlar namuna; kalitlar o‘rniga shaxsiy qiymatlaringiz yoziladi.

```dotenv
G5_PG_PAYMENTS_ENABLED=1
BOOKING_PAYMENT_PREVIEW_ENABLED=1
PORTONE_SANDBOX=1
BOOKING_PAYMENT_BASE_URL=https://YOUR-TEST-DOMAIN/gangnamunni
PORTONE_IMP_CODE=YOUR-STORE-ID
PORTONE_API_KEY=YOUR-API-KEY
PORTONE_API_SECRET=YOUR-API-SECRET
PORTONE_CHANNEL_CARD=YOUR-TEST-CHANNEL-KEY
```

Test kanalini PortOne konsolidan tanlang. `PORTONE_SANDBOX=1`ning o‘zi jonli kanalni
test kanaliga aylantirmaydi. Avval karta kanalini tekshiring; boshqa usullarni faqat
mos kanal bilan qo‘shing. Hozirgi easy-pay maydonlari KG Inicis hub oqimiga moslangan;
bevosita KakaoPay/NaverPay shartnomalari uchun qo‘shimcha integratsiya kerak.

Muhit o‘zgaruvchilari qo‘llanishi uchun xizmatni qayta yarating va holatni tekshiring:

```sh
docker compose up -d --no-deps --force-recreate gangnamunni
docker exec hms-saas-gangnamunni-1 php /var/www/html/hairwang_full/gangnamunni/payment/check.php
```

Test domeni shu ilovaga tashqaridan HTTPS orqali yetib kelishi kerak. PortOne V1
webhook manzili: `https://YOUR-TEST-DOMAIN/gangnamunni/payment/portone_webhook.php`.
`localhost` tashqi webhook uchun yetarli emas. Brauzerdagi mobil qaytish manzili
ham `BOOKING_PAYMENT_BASE_URL` asosida tuziladi. Testni shu public domen orqali
login qilib boshlang: localhostdagi sessiya boshqa domenga ko‘chmaydi.

| Sinov | Amal | Kutiladigan natija |
| --- | --- | --- |
| Muvaffaqiyatli to‘lov | Yangi tasdiqlangan test bronida `결제하기` orqali PortOne test oynasini yakunlang. | Server tasdiqlagach `paid`, provider `portone_test`, ekranda test to‘lovi belgisi. |
| Oynani bekor qilish | Boshqa yangi bronda PG oynasini to‘lovni yakunlamasdan yoping. | Buyurtma to‘langan deb belgilanmaydi; preview avtomatik ishga tushmaydi. |
| Natija yo‘qolishi | Test to‘lovi PGda tugagach natija sahifasiga yetmasdan brauzer oynasini yoping. | Webhook yoki qayta tekshirish to‘lov holatini tiklaydi. |
| Takroriy xabar | PortOne konsolida mavjud bo‘lsa webhookni qayta yuboring yoki adminda holatni qayta tekshiring. | To‘lov hodisasi va tushum takrorlanmaydi. |
| Qisman refund | 100 000 KRW test buyurtmasida super admin orqali 30 000 KRW qaytaring. | `partially_refunded`, qaytarilgan 30 000, qolgan 70 000. |
| To‘liq refund | Qolgan 70 000 KRWni qaytaring. | `refunded`, jami qaytarilgan 100 000; qayta checkout tugmalari ko‘rinmaydi. |
| Test tushumi | [Daromad sahifasi](http://localhost:8002/gangnamunni/admin_booking_revenue.php)ni ko‘ring. | Test to‘lovi real daromadga kirmaydi. |
| Mobil brauzer | Public domenda yangi test bronini telefon orqali to‘lang. | Qaytish sahifasi sessiya/bron egasini va PortOne natijasini tekshiradi. |

Refund va qayta tekshirish sahifasi:
[admin_booking_payments.php](http://localhost:8002/gangnamunni/admin_booking_payments.php).
Unga `super_admin` roli kerak. Public domenda tekshirayotgan bo‘lsangiz yuqoridagi
lokal havolalar o‘rniga o‘sha domenni ishlating. Test summasini tanlangan PG
kanalining cheklovlariga moslang.

Callback yetib kelmagan holatlarni CLI orqali qayta tekshirish ham mumkin:

```sh
docker exec hms-saas-gangnamunni-1 php /var/www/html/hairwang_full/gangnamunni/cron_reconcile_booking_payments.php 0
```

Bir chaqiriq 100 tagacha buyurtmani ko‘radi. Keyingi sahifa uchun chiqishdagi
`Next cursor`ni argument qiling; keyingi to‘liq tekshiruvni yana `0`dan boshlang.
U barcha tegishli buyurtmalarni PortOne bilan solishtiradi, shu sabab faqat sozlangan
test muhitida ishga tushiring. PGga yetib bormagan pending buyurtmalarda
`reconciliation unavailable` chiqishi mumkin; bu to‘lov muvaffaqiyatli bo‘lganini
anglatmaydi.

## 5. Tuzatilgan xatolar va qolgan ishlar

| Topilgan xato | Tuzatish |
| --- | --- |
| To‘lov sanasi callback kelgan vaqtdan yozilardi. | PortOne bergan asl `paid_at` Seoul vaqtida saqlanadi. |
| Muhit livega almashtirilganda eski sandbox to‘lovini tekshirish buzilardi. | Oldin tasdiqlangan to‘lovning saqlangan provider muhiti ishlatiladi. |
| Birinchi xabar to‘liq refund bo‘lsa, bazadagi xato buyurtmani `paid`da qoldirishi mumkin edi. | To‘lov va refund bitta atomik tranzaksiyada qo‘llanadi. |
| PortOne aniq rad etgan refund cheksiz `pending` qolardi. | Aniq API rad javobi `failed`; natijasi noma’lum timeout esa `pending` qoladi. |
| Refund qilingan test to‘lovi preview yoki qayta to‘lanadigan buyurtma bo‘lib ko‘rinardi. | Yakuniy holatlar birinchi tekshiriladi, qayta to‘lash tugmalari yashiriladi. |
| Sozlamalar to‘liq bo‘lmasa ham checkout yoqilishi mumkin edi. | Store, secret, API key, HTTPS URL va kanal borligi birga tekshiriladi. |
| Noto‘g‘ri tranzaksiya javobi qabul qilinishi mumkin edi. | Javobdagi `imp_uid` aynan so‘ralgan tranzaksiyaga mos bo‘lishi shart. |
| Ixtiyoriy ball tizimidagi xato checkoutni to‘xtatishi mumkin edi. | Ball balansini o‘qish xatosi to‘lov sahifasini yiqitmaydi. |

Jonli ishga tushirishdan oldin PortOne sandbox orqali yuqoridagi to‘liq oqimni
tekshirish, callback/cronlarni ishchi muhitda sozlash va yopilgan oylarga kech
kelgan moliyaviy hodisalarni qayta hisoblash tartibini yakunlash kerak.
Avtomatik bank payouti, reklama walletini PG orqali to‘ldirish va ball sarflash
hali yakunlanmagan. Oylik settlement skripti bankka pul o‘tkazmaydi.

PortOne rasmiy manbalari:

- [V1 to‘lov integratsiyasi](https://developers.portone.io/opi/ko/integration/start/v1/auth)
- [V1 webhook](https://developers.portone.io/opi/ko/integration/webhook/readme-v1)
- [V1 REST to‘lov API](https://developers.portone.io/api/rest-v1/payment)
