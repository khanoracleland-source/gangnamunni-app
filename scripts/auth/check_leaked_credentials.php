<?php
/**
 * Sizib chiqqan parollar hali amaldami? — faqat O'QISH, hech narsa o'zgartirmaydi.
 *
 * Nega kerak: `admin123`, `hospital123`, `123456` parollari web orqali ochiq
 * fayllarda (auto_manual_engine konfiglari, manual/*.html, qa skriptlari) va git
 * tarixida turgan. Kodda ularni *o'rnatadigan* joy yo'q, shuning uchun ular hali
 * ishlaydimi — faqat DB dagi hash'ni tekshirib bilish mumkin.
 *
 * Serverda:
 *   php scripts/auth/check_leaked_credentials.php /yo-l/gangnamunni/dbconfig.php
 * yoki:
 *   G5_MYSQL_HOST=... G5_MYSQL_USER=... G5_MYSQL_PASSWORD=... G5_MYSQL_DB=... \
 *   php scripts/auth/check_leaked_credentials.php
 *
 * Chiqishda parollar ko'rsatilmaydi — faqat "AMALDA" / "o'zgargan".
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

// ── DB ulanishi ────────────────────────────────────────────────────────────
$dbconfig = $argv[1] ?? '';
if ($dbconfig !== '') {
    if (!is_file($dbconfig)) { fwrite(STDERR, "dbconfig topilmadi: {$dbconfig}\n"); exit(1); }
    require_once $dbconfig;
}
foreach (['G5_MYSQL_HOST', 'G5_MYSQL_USER', 'G5_MYSQL_DB'] as $c) {
    if (!defined($c) && getenv($c) === false) {
        fwrite(STDERR, "Sozlama yo'q: {$c}. dbconfig.php yo'lini argument qilib bering yoki env o'zgaruvchilarini qo'ying.\n");
        exit(1);
    }
}
$cfg = function (string $name, $default = null) {
    if (defined($name)) return constant($name);
    $v = getenv($name);
    return $v === false ? $default : $v;
};

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @mysqli_connect(
    $cfg('G5_MYSQL_HOST'), $cfg('G5_MYSQL_USER'), $cfg('G5_MYSQL_PASSWORD', ''),
    $cfg('G5_MYSQL_DB'), (int) $cfg('G5_MYSQL_PORT', 3306)
);
if (!$conn) { fwrite(STDERR, 'DB ulanmadi: ' . mysqli_connect_error() . "\n"); exit(1); }
mysqli_set_charset($conn, 'utf8mb4');

// ── Tekshiriladigan ro'yxat ────────────────────────────────────────────────
// [jadval, login ustuni, parol ustuni, login, sizib chiqqan parol, manba]
$targets = [
    ['gangnamunni_admins', 'username', 'password', 'admin',          'admin123',    'manual_hospital_admin_20260528.html, auto_manual_engine/*-book-config.json, manual/*.html'],
    ['gangnamunni_admins', 'username', 'password', 'hospital_test',  'hospital123', 'e2e/e2e-config.json, manual_hospital_admin_20260528.html'],
    ['users',              'email',    'password', 'admin@hms.com',  '123456',      'auto_manual_engine/hms-*-book-config.json'],
];

function table_exists($conn, string $t): bool {
    $res = @mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $t) . "'");
    return $res && mysqli_num_rows($res) > 0;
}

$printf = "%-22s %-16s %-11s %s\n";
printf($printf, 'JADVAL', 'LOGIN', 'HOLAT', 'IZOH');
echo str_repeat('-', 100) . "\n";

$live = 0;
$unknown = 0;

foreach ($targets as [$table, $loginCol, $pwCol, $login, $leaked, $source]) {
    if (!table_exists($conn, $table)) {
        printf($printf, $table, $login, 'JADVAL YO\'Q', 'bu o\'rnatishda mavjud emas');
        continue;
    }

    $stmt = @mysqli_prepare($conn, "SELECT `{$pwCol}` AS pw FROM `{$table}` WHERE `{$loginCol}` = ? LIMIT 1");
    if (!$stmt) {
        printf($printf, $table, $login, 'O\'QILMADI', 'ustunlar boshqacha: ' . mysqli_error($conn));
        $unknown++;
        continue;
    }
    mysqli_stmt_bind_param($stmt, 's', $login);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) {
        printf($printf, $table, $login, 'AKKAUNT YO\'Q', 'o\'chirilgan yoki qayta nomlangan — xavf yo\'q');
        continue;
    }

    $hash = (string) $row['pw'];
    $verified = false;
    $note = '';

    if ($hash === '') {
        $note = 'parol bo\'sh — alohida tekshirish kerak';
        $unknown++;
    } elseif (preg_match('/^\$(2[aby]|argon2)/', $hash)) {
        $verified = password_verify($leaked, $hash);
        $note = $verified ? 'SIZIB CHIQQAN PAROL HALI ISHLAYDI — DARHOL ALMASHTIRING' : 'parol almashtirilgan';
    } else {
        // gnuboard pbkdf2 (create_hash) yoki boshqa format
        $note = 'hash formati bcrypt/argon2 emas (pbkdf2?) — qo\'lda tekshirish kerak';
        $unknown++;
    }

    if ($verified) $live++;
    printf($printf, $table, $login, $verified ? '*** AMALDA ***' : ($note === 'parol almashtirilgan' ? 'xavfsiz' : 'noaniq'), $note);
    if ($verified) echo str_repeat(' ', 51) . "manba: {$source}\n";
}

echo "\n";
if ($live > 0) {
    echo "NATIJA: {$live} ta sizib chiqqan parol HALI AMALDA. Darhol almashtirish kerak.\n";
    exit(2);
}
echo $unknown > 0
    ? "NATIJA: amalda ishlayotgani topilmadi, lekin {$unknown} ta holat noaniq (yuqoridagi izohlarga qarang).\n"
    : "NATIJA: sizib chiqqan parollarning hech biri endi ishlamaydi. Fayllardagi literal parollar eskirgan.\n";
