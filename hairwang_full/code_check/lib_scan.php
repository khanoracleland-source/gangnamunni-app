<?php
/**
 * PHP 문법 스캔 라이브러리
 * php -l 을 사용해 파일 목록을 검사하고 오류를 반환
 */

function cc_scan_directory(string $dir, array $excludePatterns = []): array {
    $errors = [];
    $ok = 0;
    $total = 0;

    $rit = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($rit as $file) {
        if ($file->getExtension() !== 'php') continue;
        $path = str_replace('\\', '/', $file->getPathname());

        // 제외 패턴 체크
        $skip = false;
        foreach ($excludePatterns as $pat) {
            if (fnmatch($pat, $path)) { $skip = true; break; }
        }
        if ($skip) continue;

        $total++;
        $result = cc_check_file($path);
        if ($result['ok']) {
            $ok++;
        } else {
            $errors[] = $result;
        }
    }

    return [
        'total'  => $total,
        'ok'     => $ok,
        'errors' => $errors,
        'error_count' => count($errors),
        'scanned_at' => date('Y-m-d H:i:s'),
        'scan_path' => $dir,
        'php_version' => PHP_VERSION,
    ];
}

function cc_check_file(string $path): array {
    $cmd = escapeshellarg(CC_PHP_BIN) . ' -l ' . escapeshellarg($path) . ' 2>&1';
    $output = shell_exec($cmd);
    $ok = (strpos($output, 'No syntax errors') !== false);

    if ($ok) {
        return ['ok' => true, 'file' => $path];
    }

    // 오류 파싱: "Parse error: ... in /path/to/file.php on line 42"
    $line_no = null;
    $error_msg = trim($output);
    if (preg_match('/on line (\d+)/i', $output, $m)) {
        $line_no = (int)$m[1];
    }
    // 오류 메시지 정리 (파일경로 제거해 짧게)
    $short_msg = preg_replace('/\s+in\s+.+\.php\s+on\s+line\s+\d+/i', '', $error_msg);
    $short_msg = preg_replace('/^(Parse error|Fatal error|Warning):\s*/i', '', $short_msg);
    $short_msg = trim($short_msg);

    // 오류 컨텍스트 (앞뒤 5줄)
    $context = cc_get_context($path, $line_no);

    return [
        'ok'        => false,
        'file'      => $path,
        'line_no'   => $line_no,
        'error_msg' => $short_msg,
        'raw_output'=> $error_msg,
        'context'   => $context,
    ];
}

function cc_get_context(string $path, ?int $line_no, int $radius = 5): string {
    if (!$line_no || !file_exists($path)) return '';
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if (!$lines) return '';

    $start = max(0, $line_no - 1 - $radius);
    $end   = min(count($lines) - 1, $line_no - 1 + $radius);

    $out = [];
    for ($i = $start; $i <= $end; $i++) {
        $marker = ($i === $line_no - 1) ? '>>>' : '   ';
        $out[] = sprintf('%s %4d: %s', $marker, $i + 1, $lines[$i]);
    }
    return implode("\n", $out);
}
