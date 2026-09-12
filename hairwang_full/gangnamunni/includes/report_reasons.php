<?php
function ugc_report_reason_options(): array {
    return [
        'spam'=>['영리성 홍보 목적','spam'], 'sexual'=>['음란성/선정성','sexual'],
        'harassment'=>['욕설/인신공격','harassment'], 'privacy'=>['개인정보노출','other'],
        'repeat'=>['같은 내용 반복게시','spam'], 'other'=>['기타','other']
    ];
}
function ugc_encode_report_reasons($reasons, $detail): array {
    $options=ugc_report_reason_options();
    if (!is_array($reasons) || !$reasons || count($reasons)>6 || !is_string($detail) || mb_strlen($detail)>1000) throw new InvalidArgumentException('신고 사유를 확인해주세요.');
    foreach($reasons as $reason) if (!is_string($reason) || !isset($options[$reason])) throw new InvalidArgumentException('올바른 신고 사유를 선택해주세요.');
    $reasons=array_values(array_unique($reasons));
    return [$options[$reasons[0]][1], json_encode(['version'=>1,'reasons'=>$reasons,'detail'=>in_array('other',$reasons,true)?trim($detail):''],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)];
}
function ugc_display_report_reasons(array $report, array $legacyLabels): array {
    $data=json_decode($report['reason_detail'] ?? '',true);
    $options=ugc_report_reason_options();
    if (is_array($data) && ($data['version'] ?? null)===1 && is_array($data['reasons'] ?? null)) {
        $labels=[];
        foreach($data['reasons'] as $id) if (is_string($id) && isset($options[$id])) $labels[]=$options[$id][0];
        if ($labels) return [array_values(array_unique($labels)),is_string($data['detail'] ?? null)?$data['detail']:''];
    }
    return [[$legacyLabels[$report['reason']] ?? $report['reason']],$report['reason_detail'] ?? ''];
}
