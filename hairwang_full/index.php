<?php


/**
 * 루트 접근 시 app(서울미녀, 실제 폴더명 app) 사이트로 리다이렉트
 * 그누보드 관리자 페이지는 /adm 으로 직접 접근
 */
header('Location: /app/');
exit;