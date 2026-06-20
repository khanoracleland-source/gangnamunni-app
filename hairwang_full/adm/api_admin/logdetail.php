<?php
/**
 * API 로그 상세 페이지 (AJAX)
 * API 요청 로그 상세 내용을 반환하는 페이지
 * 작성일: 2026-01-19
 * 버전: 1.0
 */

// API 관리자 공통 파일 포함
include_once './_common.php';

// 로그 ID
$al_id = isset($_GET['al_id']) ? (int)$_GET['al_id'] : 0;

if ($al_id === 0) {
    echo '<p style="color:red;">로그 ID가 유효하지 않습니다.</p>';
    exit;
}

// 로그 정보 조회
$sql = "
    SELECT
        al.*,
        ak.ak_name,
        ak.ak_key
    FROM {$g5['api_log_table']} al
    LEFT JOIN {$g5['api_key_table']} ak ON al.ak_id = ak.ak_id
    WHERE al.al_id = '" . sql_real_escape_string($al_id) . "'
";

$log = sql_fetch($sql);

if (!$log) {
    echo '<p style="color:red;">로그를 찾을 수 없습니다.</p>';
    exit;
}
?>

<table class="table table-bordered">
    <thead>
        <tr>
            <th style="width:30%;">항목</th>
            <th>내용</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <th>로그 ID</th>
            <td><?php echo $log['al_id']; ?></td>
        </tr>
        <tr>
            <th>API 키</th>
            <td>
                <?php echo htmlspecialchars($log['ak_name']); ?>
                <?php if ($log['ak_key']) { ?>
                    <br><small><code><?php echo htmlspecialchars($log['ak_key']); ?></code></small>
                <?php } ?>
            </td>
        </tr>
        <tr>
            <th>엔드포인트</th>
            <td><?php echo htmlspecialchars($log['al_endpoint']); ?></td>
        </tr>
        <tr>
            <th>메서드</th>
            <td><?php echo htmlspecialchars($log['al_method']); ?></td>
        </tr>
        <tr>
            <th>요청 IP</th>
            <td><?php echo htmlspecialchars($log['al_request_ip']); ?></td>
        </tr>
        <tr>
            <th>요청 데이터</th>
            <td>
                <pre style="background:#f5f5f5; padding:10px; border:1px solid #ddd; max-height:300px; overflow:auto;"><?php echo htmlspecialchars($log['al_request_data']); ?></pre>
            </td>
        </tr>
        <tr>
            <th>응답 코드</th>
            <td>
                <?php if ($log['al_response_code'] == 200) { ?>
                    <span style="color:green;"><?php echo $log['al_response_code']; ?></span>
                <?php } else { ?>
                    <span style="color:red;"><?php echo $log['al_response_code']; ?></span>
                <?php } ?>
            </td>
        </tr>
        <tr>
            <th>응답 데이터</th>
            <td>
                <pre style="background:#f5f5f5; padding:10px; border:1px solid #ddd; max-height:300px; overflow:auto;"><?php echo htmlspecialchars($log['al_response_data']); ?></pre>
            </td>
        </tr>
        <tr>
            <th>에러</th>
            <td>
                <?php if ($log['al_error']) { ?>
                    <span style="color:red;"><?php echo htmlspecialchars($log['al_error']); ?></span>
                <?php } else { ?>
                    -
                <?php } ?>
            </td>
        </tr>
        <tr>
            <th>실행 시간</th>
            <td><?php echo $log['al_execution_time'] ? number_format($log['al_execution_time'], 4) . '초' : '-'; ?></td>
        </tr>
        <tr>
            <th>생성일</th>
            <td><?php echo $log['al_created_at']; ?></td>
        </tr>
    </tbody>
</table>
