<?php
// Full QA Report viewer
$file = __DIR__ . '/docs_html/full_qa_report_20260324.html';
if (file_exists($file)) {
    header('Content-Type: text/html; charset=UTF-8');
    readfile($file);
} else {
    http_response_code(404);
    echo 'Report not found';
}
