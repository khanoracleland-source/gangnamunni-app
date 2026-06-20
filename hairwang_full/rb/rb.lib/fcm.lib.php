<?php
// /rb/lib/fcm.lib.php
// FCM 메시지 전송을 위한 라이브러리

class FCMService {
    private $serverKey;
    private $projectId;
    
    public function __construct($serverKey, $projectId) {
        $this->serverKey = $serverKey;
        $this->projectId = $projectId;
    }
    
    /**
     * 단일 기기에 메시지 전송
     */
    public function sendToDevice($token, $title, $body, $data = [], $options = []) {
        $message = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body
                ]
            ]
        ];
        
        if (!empty($data)) {
            $message['message']['data'] = $data;
        }
        
        if (!empty($options['icon'])) {
            $message['message']['notification']['icon'] = $options['icon'];
        }
        
        if (!empty($options['click_action'])) {
            $message['message']['webpush'] = [
                'fcm_options' => [
                    'link' => $options['click_action']
                ]
            ];
        }
        
        return $this->sendMessage($message);
    }
    
    /**
     * 여러 기기에 메시지 전송
     */
    public function sendToMultipleDevices($tokens, $title, $body, $data = [], $options = []) {
        $results = [];
        
        // 최대 500개씩 나누어 전송
        $chunks = array_chunk($tokens, 500);
        
        foreach ($chunks as $chunk) {
            foreach ($chunk as $token) {
                $result = $this->sendToDevice($token, $title, $body, $data, $options);
                $results[] = $result;
            }
        }
        
        return $results;
    }
    
    /**
     * 주제로 메시지 전송
     */
    public function sendToTopic($topic, $title, $body, $data = [], $options = []) {
        $message = [
            'message' => [
                'topic' => $topic,
                'notification' => [
                    'title' => $title,
                    'body' => $body
                ]
            ]
        ];
        
        if (!empty($data)) {
            $message['message']['data'] = $data;
        }
        
        if (!empty($options['icon'])) {
            $message['message']['notification']['icon'] = $options['icon'];
        }
        
        return $this->sendMessage($message);
    }
    
    /**
     * 조건부 메시지 전송
     */
    public function sendToCondition($condition, $title, $body, $data = [], $options = []) {
        $message = [
            'message' => [
                'condition' => $condition,
                'notification' => [
                    'title' => $title,
                    'body' => $body
                ]
            ]
        ];
        
        if (!empty($data)) {
            $message['message']['data'] = $data;
        }
        
        return $this->sendMessage($message);
    }
    
    /**
     * 실제 메시지 전송
     */
    private function sendMessage($message) {
        $url = 'https://fcm.googleapis.com/v1/projects/' . $this->projectId . '/messages:send';
        
        $headers = [
            'Authorization: Bearer ' . $this->getAccessToken(),
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'success' => $httpCode === 200,
            'response' => json_decode($response, true),
            'http_code' => $httpCode
        ];
    }
    
    /**
     * 액세스 토큰 가져오기 (서비스 계정 사용)
     * 실제 구현시 Google API Client 라이브러리 사용 권장
     */
    private function getAccessToken() {
        // 이 부분은 실제로는 서비스 계정 JSON 파일을 사용하여
        // OAuth 2.0 액세스 토큰을 생성해야 합니다.
        // 여기서는 예시로 서버 키를 반환합니다.
        return $this->serverKey;
    }
}

/**
 * 회원에게 FCM 알림 전송
 */
function send_fcm_to_member($mb_id, $title, $body, $data = [], $options = []) {
    global $config;
    
    if (!$mb_id) return false;
    
    // 회원의 활성 토큰 가져오기
    $sql = "SELECT ft_token FROM g5_fcm_tokens 
            WHERE mb_id = '".sql_real_escape_string($mb_id)."' 
            AND ft_is_active = 1 
            ORDER BY ft_updated_at DESC";
    $result = sql_query($sql);
    
    $tokens = [];
    while ($row = sql_fetch_array($result)) {
        $tokens[] = $row['ft_token'];
    }
    
    if (empty($tokens)) {
        return false;
    }
    
    // FCM 서비스 초기화
    $fcm = new FCMService($config['cf_fcm_server_key'], $config['cf_fcm_project_id']);
    
    // 메시지 전송
    $results = $fcm->sendToMultipleDevices($tokens, $title, $body, $data, $options);
    
    // 전송 결과 로깅
    foreach ($results as $i => $result) {
        if (!$result['success']) {
            // 실패한 토큰 비활성화
            $failed_token = $tokens[$i];
            sql_query("UPDATE g5_fcm_tokens SET ft_is_active = 0 
                      WHERE ft_token = '".sql_real_escape_string($failed_token)."'");
        }
    }
    
    return $results;
}

/**
 * 전체 회원에게 FCM 알림 전송
 */
function send_fcm_to_all($title, $body, $data = [], $options = []) {
    global $config;
    
    // 모든 활성 토큰 가져오기
    $sql = "SELECT ft_token FROM g5_fcm_tokens 
            WHERE ft_is_active = 1 
            ORDER BY ft_updated_at DESC";
    $result = sql_query($sql);
    
    $tokens = [];
    while ($row = sql_fetch_array($result)) {
        $tokens[] = $row['ft_token'];
    }
    
    if (empty($tokens)) {
        return false;
    }
    
    // FCM 서비스 초기화
    $fcm = new FCMService($config['cf_fcm_server_key'], $config['cf_fcm_project_id']);
    
    // 메시지 전송
    return $fcm->sendToMultipleDevices($tokens, $title, $body, $data, $options);
}

/**
 * 주제 구독자에게 FCM 알림 전송
 */
function send_fcm_to_topic($topic, $title, $body, $data = [], $options = []) {
    global $config;
    
    // FCM 서비스 초기화
    $fcm = new FCMService($config['cf_fcm_server_key'], $config['cf_fcm_project_id']);
    
    // 메시지 전송
    return $fcm->sendToTopic($topic, $title, $body, $data, $options);
}

/**
 * 게시글 알림 전송 예시
 */
function send_fcm_new_post($bo_table, $wr_id, $wr_subject) {
    // 새 게시글 알림
    $title = "새 게시글이 등록되었습니다";
    $body = mb_substr($wr_subject, 0, 50);
    $data = [
        'type' => 'new_post',
        'bo_table' => $bo_table,
        'wr_id' => $wr_id
    ];
    $options = [
        'click_action' => G5_BBS_URL."/board.php?bo_table={$bo_table}&wr_id={$wr_id}"
    ];
    
    // 주제 구독자에게 전송
    return send_fcm_to_topic('new_posts', $title, $body, $data, $options);
}

/**
 * 댓글 알림 전송 예시
 */
function send_fcm_new_comment($mb_id, $bo_table, $wr_id, $comment) {
    // 댓글 알림
    $title = "내 글에 새 댓글이 달렸습니다";
    $body = mb_substr($comment, 0, 50);
    $data = [
        'type' => 'new_comment',
        'bo_table' => $bo_table,
        'wr_id' => $wr_id
    ];
    $options = [
        'click_action' => G5_BBS_URL."/board.php?bo_table={$bo_table}&wr_id={$wr_id}"
    ];
    
    // 특정 회원에게 전송
    return send_fcm_to_member($mb_id, $title, $body, $data, $options);
}
?>