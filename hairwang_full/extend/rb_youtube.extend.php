<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

function getYouTubeVideoId($input) {
    // 입력이 정확히 11자리의 유튜브 동영상 ID 형식인지 확인
    if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $input)) {
        return $input;
    }

    // 유튜브 URL에서 동영상 ID를 추출하는 정규식
    preg_match('/(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/(?:watch\?v=|embed\/|v\/|.+\?v=)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $input, $matches);

    // 매칭된 결과가 있으면 동영상 ID 반환
    return isset($matches[1]) ? $matches[1] : null;
}