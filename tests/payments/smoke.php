<?php
/** Native app-bootstrap checks. Run INSIDE the local gangnamunni container.
 * No credentials, booking IDs or payment data are submitted.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$cases = [
    ['booking_payment.php','GET',null,302],
    ['booking_payment_success.php','GET',null,302],
    ['booking_payment_mock_complete.php','GET',null,405],
    ['booking_payment_request.php','GET',null,405],
    ['booking_payment_verify.php','GET',null,405],
    ['booking_payment_mock_complete.php','POST','',403],
    ['booking_payment_request.php','POST','',403],
    ['booking_payment_verify.php','POST','',403],
    ['api_wallet.php','GET',null,401],
    ['admin_booking_payments.php','GET',null,403],
];
$count=0;
foreach ($cases as [$path,$method,$payload,$expected]) {
    $ch=curl_init('http://127.0.0.1/gangnamunni/'.$path);
    curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10]);
    if($payload!==null) curl_setopt($ch,CURLOPT_POSTFIELDS,$payload);
    if($payload==='{}') curl_setopt($ch,CURLOPT_HTTPHEADER,['Content-Type: application/json']);
    $body=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    if($code!==$expected) { fwrite(STDERR,"FAIL: $method $path returned $code; expected $expected\n");exit(1); }
    if($expected>=400 && str_ends_with($path,'.php') && $path!=='admin_booking_payments.php' && !is_array(json_decode((string)$body,true))) {
        fwrite(STDERR,"FAIL: $path response is not JSON\n");exit(1);
    }
    echo "PASS: $method $path -> $code\n";$count++;
}
echo "All $count native HTTP smoke checks passed.\n";
