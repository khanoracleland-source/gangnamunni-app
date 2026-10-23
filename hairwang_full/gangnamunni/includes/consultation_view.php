<?php
/**
 * 마이페이지 상담 내역(07-40/07-41) 공용 헬퍼.
 */
function consult_status_view(string $st): array {
    switch ($st) {
        case 'contact_failed': return ['연락 실패', '#AC1830', '#FFFFFF'];
        case 'completed':      return ['상담 완료', '#EFECED', '#4A4446'];
        case 'rejected':       return ['상담 불가', '#EFECED', '#4A4446'];
        default:               return ['확인 중', '#2A1416', '#FFFFFF'];
    }
}
function consult_type_label(?string $type): string {
    $map = ['general' => '전화 상담', 'kakao' => '카카오톡 채널 상담', 'doctor' => '방문 상담'];
    return $map[(string)$type] ?? '상담';
}
function consult_slot_labels(): array {
    return ['am' => '오전', 'pm' => '오후', 'eve' => '저녁', 'any' => '언제든 괜찮아요'];
}
/** 10.20 (화) 오후 2:02 */
function consult_dt(?string $dt): string {
    $t = strtotime((string)$dt);
    if (!$t) return '';
    $w = ['일', '월', '화', '수', '목', '금', '토'][(int)date('w', $t)];
    $h = (int)date('G', $t);
    return date('m.d', $t) . " ({$w}) " . ($h < 12 ? '오전' : '오후') . ' ' . (($h % 12) ?: 12) . date(':i', $t);
}
function consult_hospital_contact(array $row): array {
    return [
        'phone' => trim((string)($row['h_phone'] ?? '')),
        'kakao' => trim((string)($row['h_kakao_channel_url'] ?? $row['h_kakao_url'] ?? '')),
    ];
}
function consult_safe_url(string $u): string {
    return preg_match('#^https?://#i', $u) ? $u : '';
}
