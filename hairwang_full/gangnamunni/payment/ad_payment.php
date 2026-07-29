<?php
/**
 * Retired legacy ad-package checkout.
 *
 * Advertising is funded exclusively through the hospital advertising wallet.
 * A slot may be assigned only when that wallet covers three months of the
 * selected slot, and active slots are charged daily.
 */
http_response_code(410);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
?>
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>광고 결제 방식 변경</title>
</head>
<body>
    <main style="max-width:640px;margin:80px auto;padding:24px;font-family:sans-serif">
        <h1 style="font-size:22px">광고 패키지 결제가 종료되었습니다.</h1>
        <p>광고는 병원 광고 지갑을 충전한 뒤 광고 슬롯을 배정하는 방식으로 운영됩니다.</p>
        <p>선택 슬롯 월 요금의 3개월분 잔액이 필요하며, 실제 이용 금액은 매일 지갑에서 차감됩니다.</p>
        <p>
            <a href="../partner_wallet.php">광고 지갑 충전</a>
            ·
            <a href="../admin_ad_slots.php">광고 슬롯 관리</a>
        </p>
    </main>
</body>
</html>
