<?php
header("Access-Control-Allow-Origin: *");
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Credentials: true');
header('Set-Cookie: my_cookie=value; SameSite=None; Secure');

function isFunctionAvailable($function) {
    return function_exists($function);
}

function curl_get_contents($url) {
    if (!isFunctionAvailable('curl_init')) {
        return ['error' => 'CURL 기능이 서버에서 지원되지 않습니다.'];
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
        'Referer: https://www.google.com/',
        'Upgrade-Insecure-Requests: 1'
    ]);

    $data = curl_exec($ch);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        return ['error' => 'CURL 에러: ' . $error_msg];
    }

    curl_close($ch);
    return ['html' => $data, 'content_type' => $contentType];
}

if (isset($_GET['url'])) {
    $url = $_GET['url'];

    if (preg_match('/\.(jpg|jpeg|png|gif|webp|svg)$/i', $url)) {
        echo json_encode(['title' => 'Image', 'meta' => ['og:image' => $url]], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $result = curl_get_contents($url);
    if (isset($result['error'])) {
        echo json_encode(['error' => $result['error']], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $html = $result['html'];
    $contentType = $result['content_type'];

    if (!$html) {
        echo json_encode(['error' => '데이터를 가져오지 못했습니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    preg_match('/<meta.*?charset=["\']?([^"\'>]+)/i', $html, $matches);
    $htmlCharset = isset($matches[1]) ? strtolower($matches[1]) : 'utf-8';

    if (isFunctionAvailable('mb_detect_encoding')) {
        $detectedEncoding = mb_detect_encoding($html, ['UTF-8', 'EUC-KR', 'SJIS', 'ISO-8859-1', 'CP949'], true);
    } else {
        $detectedEncoding = false;
    }

    if (isFunctionAvailable('mb_convert_encoding')) {
        if ($detectedEncoding && $detectedEncoding !== 'UTF-8') {
            $html = mb_convert_encoding($html, 'UTF-8', $detectedEncoding);
        } elseif ($htmlCharset !== 'utf-8' && $htmlCharset !== '') {
            $html = mb_convert_encoding($html, 'UTF-8', strtoupper($htmlCharset));
        }
    }

    if (!class_exists('DOMDocument')) {
        echo json_encode(['error' => 'DOMDocument 확장이 필요합니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $dom = new DOMDocument();
    @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    $metas = $dom->getElementsByTagName('meta');
    $metaData = [];

    foreach ($metas as $meta) {
        $name = $meta->getAttribute('name');
        if (!$name) {
            $name = $meta->getAttribute('property');
        }
        $content = $meta->getAttribute('content');
        if ($name && $content) {
            $metaData[$name] = $content;
        }
    }

    // ✅ og:image가 없으면 첫 번째 <img> 태그의 src를 가져옴
    if (!isset($metaData['og:image'])) {
        $imgTags = $dom->getElementsByTagName('img');
        $firstImage = null;

        foreach ($imgTags as $img) {
            $src = $img->getAttribute('src');
            if (!$src) continue;

            if (!preg_match('/^https?:\/\//', $src)) {
                $parsedUrl = parse_url($url);
                $base = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
                if (isset($parsedUrl['port'])) {
                    $base .= ':' . $parsedUrl['port'];
                }
                $src = $base . (substr($src, 0, 1) === '/' ? '' : '/') . $src;
            }

            $firstImage = $src;
            break;
        }

        if ($firstImage) {
            $metaData['og:image'] = $firstImage;
        }
    }

    // ✅ <title> 태그 가져오기
    $titleElement = $dom->getElementsByTagName('title')->item(0);
    $title = $titleElement ? trim($titleElement->nodeValue) : '';

    $response = [
        'title' => $title,
        'meta' => $metaData
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(['error' => 'URL 파라미터가 필요합니다.'], JSON_UNESCAPED_UNICODE);
}
?>
