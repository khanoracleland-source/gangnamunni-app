<?php
include_once('./_common.php');

if (ob_get_length()) ob_clean();
header('Content-Type: application/json; charset=UTF-8');

$userPrompt = trim(isset($_POST['prompt']) ? $_POST['prompt'] : '');

$apiKey = ""; //https://aistudio.google.com/ 에서 발급한 api키를 넣어주세요.
$url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-preview-image-generation:generateContent?key=" . $apiKey;

// 프롬프트 후처리 원하면 아래에 추가
$prompt = $userPrompt . " 대답은 반드시 한글로 해줘.";

$data = [
    "contents" => [
        [
            "role" => "user",
            "parts" => [
                ["text" => $prompt]
            ]
        ]
    ],
    "generationConfig" => [
        "responseModalities" => ["TEXT", "IMAGE"],
        "temperature" => 0.7,
        "topK" => 40,
        "topP" => 0.85,
        "maxOutputTokens" => 512
    ]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

//file_put_contents('gemini_response.json', $response); 응답디버깅

if ($httpCode !== 200) {
    $errorMsg = "잠시 후 다시 시도해주세요.";
    $errArr = json_decode($response, true);
    if ($httpCode == 429) {
        $errorMsg = "요청 한도를 초과했습니다. 익일 다시 시도해주세요.";
        if (!empty($errArr['error']['message'])) {
            $errorMsg .= " (" . $errArr['error']['message'] . ")";
        }
    }
    echo json_encode(["error" => $errorMsg]);
    exit;
}

$responseArray = json_decode($response, true);
$textResult = null;
$imageResult = null;



if (!empty($responseArray["candidates"][0]["content"]["parts"])) {
    foreach ($responseArray["candidates"][0]["content"]["parts"] as $part) {
        if (isset($part["text"]) && !$textResult) {
            $textResult = $part["text"];
        }
        if (
            isset($part["inlineData"]["mimeType"]) &&
            strpos($part["inlineData"]["mimeType"], "image/") === 0 &&
            isset($part["inlineData"]["data"]) &&
            !$imageResult
        ) {
            $imageResult = $part["inlineData"]["data"]; // base64
        }
    }
}


function markdownTableToHtml($markdown) {
    $lines = preg_split('/\r\n|\r|\n/', trim($markdown));
    $table = [];
    $headerCount = 0;
    $html = '';
    $buffer = [];

    foreach ($lines as $row) {
        // 구분선 무시
        $trimmed = trim($row, '| ');
        if (preg_match('/^[-\s|]+$/', $trimmed)) continue;
        if (strpos($row, '|') === false) {
            // 표 구조 끝, 지금까지 쌓인 표가 있으면 변환
            if ($buffer) {
                $html .= bufferToTableHtml($buffer);
                $buffer = [];
            }
            $html .= $row . "\n";
            continue;
        }
        $buffer[] = $row;
    }
    // 남은 표 버퍼 처리
    if ($buffer) $html .= bufferToTableHtml($buffer);

    return $html;
}

// 버퍼 -> 표 변환
function bufferToTableHtml($lines) {
    $table = [];
    foreach ($lines as $row) {
        $cols = array_map('trim', explode('|', trim($row, '|')));
        $table[] = $cols;
    }
    $headerCount = count($table[0] ?? []);
    $html = '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse;width:100%;">';
    foreach ($table as $i => $cols) {
        // 열 개수 맞추기
        if (count($cols) < $headerCount) $cols = array_merge($cols, array_fill(0, $headerCount - count($cols), ''));
        if (count($cols) > $headerCount) $cols = array_slice($cols, 0, $headerCount);

        $html .= '<tr>';
        foreach ($cols as $col) {
            $tag = ($i === 0) ? 'th' : 'td';
            $html .= "<$tag>$col</$tag>";
        }
        $html .= '</tr>';
    }
    $html .= '</table>';
    return $html;
}


// 텍스트 후처리
if ($textResult) {
    // 1. 시스템 안내 메시지 제거
    $textResult = preg_replace('/Image Generation:.*(\r?\n)?/i', '', $textResult);

    // 2. 마크다운 표를 HTML 표로 변환
    // (마크다운 표가 여러 개 있을 수도 있으니, 정규식으로 잡아 교체)
    $textResult = preg_replace_callback(
        '/((?:\|.+\|(?:\r?\n))+)/',
        function($matches) {
            return markdownTableToHtml($matches[1]);
        },
        $textResult
    );

    // 3. 텍스트 스타일링(기존)
    $textResult = preg_replace('/[\x{1F600}-\x{1F64F}]|[\x{1F300}-\x{1F5FF}]|[\x{1F680}-\x{1F6FF}]|[\x{1F700}-\x{1F77F}]|[\x{1F780}-\x{1F7FF}]|[\x{1F800}-\x{1F8FF}]|[\x{1F900}-\x{1F9FF}]|[\x{1FA00}-\x{1FA6F}]|[\x{2600}-\x{26FF}]|[\x{2700}-\x{27BF}]|[\x{FE0F}]|[\x{200D}]|[\x{E0020}-\x{E007F}]|[\x{E000}-\x{F8FF}]|[\x{1F1E6}-\x{1F1FF}]|[\x{1F004}-\x{1F0CF}]|[\x{1F18E}-\x{1F251}]/u', '', $textResult);
    $textResult = preg_replace('/\*\*(.*?)\*\*/', '<b>$1</b>', $textResult);
    $textResult = preg_replace('/<p>(.*?)<\/p>/s', '<div>$1</div>', $textResult);
    $textResult = "<div>" . preg_replace('/\n+/', "</div><div>", $textResult) . "</div>";
}


echo json_encode([
    "text" => $textResult,
    "image" => $imageResult,
    "model" => "Gemini 2.0 Flash Preview"
]);
exit;
?>
