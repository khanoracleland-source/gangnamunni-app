<?php
// Figma Before/After Report viewer
$file = __DIR__ . '/figma_before_after_report.html';
if (file_exists($file)) {
    readfile($file);
} else {
    echo 'Report not found';
}
