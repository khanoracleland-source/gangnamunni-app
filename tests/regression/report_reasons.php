<?php
require __DIR__.'/../../hairwang_full/gangnamunni/includes/report_reasons.php';
function expect_reason($ok){if(!$ok)throw new RuntimeException('Reason regression failed');}
[$primary,$encoded]=ugc_encode_report_reasons(['spam','sexual','privacy','repeat','other','spam'],'Extra detail');
expect_reason($primary==='spam');
[$labels,$detail]=ugc_display_report_reasons(['reason'=>$primary,'reason_detail'=>$encoded],[]);
expect_reason(count($labels)===5 && in_array('개인정보노출',$labels,true) && in_array('같은 내용 반복게시',$labels,true) && $detail==='Extra detail');
[$primary,$encoded]=ugc_encode_report_reasons(['sexual','spam'],'stale detail');
expect_reason(json_decode($encoded,true)['detail']==='');
[$labels,$detail]=ugc_display_report_reasons(['reason'=>'spam','reason_detail'=>'Old report'],['spam'=>'스팸']);
expect_reason($labels===['스팸'] && $detail==='Old report');
foreach([[],['invalid'],['spam',[]],'spam'] as $invalid){
    try{ugc_encode_report_reasons($invalid,'');throw new RuntimeException('Invalid selection accepted');}
    catch(InvalidArgumentException $e){}
}
echo "PASS: multiple reasons, deduplication, distinct privacy/repeat, other detail, legacy records and invalid input.\n";
