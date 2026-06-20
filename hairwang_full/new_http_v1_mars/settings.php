<?php
/**
 * ========================================
 * 푸시 알림 시스템 설정 관리 페이지
 * ========================================
 *
 * Firebase JSON 파일과 토픽 설정을 관리하는 관리자 페이지입니다.
 *
 * @file    settings.php
 * @created 2025-12-18
 * @version 1.1.0
 * @changes
 *   - 로그인 인증 시스템 추가
 */

// 인증 시스템 로드 및 로그인 체크
require_once __DIR__ . '/auth.php';
require_login();  // 로그인하지 않은 사용자는 login.php로 리다이렉트

// 현재 사용자 정보 가져오기
$current_user = get_current_user();

// config.php 로드
require_once __DIR__ . '/config.php';

// 설정 로드
$config = get_push_config();
$firebase_json_path = get_firebase_json_path();

// 처리 결과 메시지
$message = '';
$message_type = '';

// POST 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    // ==========================================
    // 액션 0: 테스트 알림 설정 저장
    // ==========================================
    if ($action === 'save_test_notification') {
        $test_config = [
            'title' => trim($_POST['test_title']),
            'message' => trim($_POST['test_message']),
            'domain' => trim($_POST['test_domain']),
            'image' => isset($_POST['test_image']) ? trim($_POST['test_image']) : ''
        ];

        if (save_test_notification_config($test_config)) {
            $message = '테스트 알림 설정이 성공적으로 저장되었습니다.';
            $message_type = 'success';
        } else {
            $message = '테스트 알림 설정 저장에 실패했습니다.';
            $message_type = 'danger';
        }
    }

    // ==========================================
    // 액션 1: 푸시 설정 변경 (project_id, arg3, default_url)
    // ==========================================
    if ($action === 'update_push_settings') {
        $project_id = trim($_POST['project_id']);
        $arg3_value = trim($_POST['arg3_value']);
        $default_url = trim($_POST['default_url']);

        $config['project_id'] = $project_id;
        $config['arg3_value'] = $arg3_value;
        $config['default_url'] = $default_url;

        if (save_push_config($config)) {
            $message = '푸시 설정이 성공적으로 변경되었습니다.';
            $message_type = 'success';
        } else {
            $message = '푸시 설정 저장에 실패했습니다.';
            $message_type = 'danger';
        }
    }

    // ==========================================
    // 액션 2: 토픽 이름 변경
    // ==========================================
    if ($action === 'update_topic') {
        $new_topic = trim($_POST['topic_name']);

        // 토픽 이름 유효성 검사
        if (validate_topic_name($new_topic)) {
            $config['topic_name'] = $new_topic;
            if (save_push_config($config)) {
                $message = '토픽 이름이 성공적으로 변경되었습니다: ' . htmlspecialchars($new_topic);
                $message_type = 'success';
            } else {
                $message = '설정 저장에 실패했습니다.';
                $message_type = 'danger';
            }
        } else {
            $message = '토픽 이름이 올바르지 않습니다. 영문자, 숫자, 밑줄(_), 하이픈(-)만 사용 가능합니다.';
            $message_type = 'warning';
        }
    }

    // ==========================================
    // 액션 2: Firebase JSON 파일 내용 수정
    // ==========================================
    elseif ($action === 'update_firebase_json') {
        $new_json_content = $_POST['firebase_json_content'];

        // JSON 유효성 검사
        $test_decode = json_decode($new_json_content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            // 임시 파일에 저장 후 검증
            $temp_file = $firebase_json_path . '.tmp';
            file_put_contents($temp_file, $new_json_content);

            $validation = validate_firebase_json($temp_file);

            if ($validation['valid']) {
                // 원본 파일 백업
                $backup_file = $firebase_json_path . '.backup.' . date('YmdHis');
                copy($firebase_json_path, $backup_file);

                // 새 내용으로 덮어쓰기
                rename($temp_file, $firebase_json_path);

                $message = 'Firebase JSON 파일이 성공적으로 수정되었습니다. (백업: ' . basename($backup_file) . ')';
                $message_type = 'success';
            } else {
                unlink($temp_file);
                $message = 'Firebase JSON 유효성 검사 실패: ' . $validation['message'];
                $message_type = 'danger';
            }
        } else {
            $message = 'JSON 형식이 올바르지 않습니다: ' . json_last_error_msg();
            $message_type = 'danger';
        }
    }

    // ==========================================
    // 액션 3: Firebase JSON 파일 변경 (파일명 변경)
    // ==========================================
    elseif ($action === 'change_firebase_file') {
        $new_filename = trim($_POST['firebase_json_file']);

        // 파일 존재 확인
        $new_file_path = __DIR__ . '/' . $new_filename;
        if (file_exists($new_file_path)) {
            // 유효성 검사
            $validation = validate_firebase_json($new_file_path);

            if ($validation['valid']) {
                $config['firebase_json_file'] = $new_filename;
                if (save_push_config($config)) {
                    $message = 'Firebase JSON 파일이 성공적으로 변경되었습니다: ' . htmlspecialchars($new_filename);
                    $message_type = 'success';
                    $firebase_json_path = $new_file_path; // 경로 업데이트
                } else {
                    $message = '설정 저장에 실패했습니다.';
                    $message_type = 'danger';
                }
            } else {
                $message = 'Firebase JSON 유효성 검사 실패: ' . $validation['message'];
                $message_type = 'danger';
            }
        } else {
            $message = '파일이 존재하지 않습니다: ' . htmlspecialchars($new_filename);
            $message_type = 'danger';
        }
    }

    // 설정 다시 로드
    $config = get_push_config();
}

// Firebase JSON 파일 내용 읽기
$firebase_json_content = '';
$firebase_json_exists = false;
$firebase_info = null;

if (file_exists($firebase_json_path)) {
    $firebase_json_content = file_get_contents($firebase_json_path);
    $firebase_json_exists = true;

    // 파일 정보 가져오기
    $validation = validate_firebase_json($firebase_json_path);
    if ($validation['valid']) {
        $firebase_info = $validation['data'];
    }
}

// 폴더 내 JSON 파일 목록
$json_files = glob(__DIR__ . '/*.json');
$json_file_list = array_map('basename', $json_files);

// 테스트 알림 설정 로드
$test_notification_config = get_test_notification_config();

// 이미지 파일 목록 가져오기
$image_files = get_image_files();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>푸시 알림 설정 관리</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Noto Sans KR', sans-serif;
        }
        .container {
            max-width: 1000px;
            margin: 30px auto;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            border: none;
        }
        .card-header {
            background-color: #4e73df;
            color: white;
            font-weight: bold;
            padding: 15px 20px;
            border-bottom: none;
            border-radius: 15px 15px 0 0 !important;
        }
        .card-body {
            padding: 30px;
        }
        .btn-primary {
            background-color: #4e73df;
            border-color: #4e73df;
            padding: 10px 20px;
            font-weight: 600;
            border-radius: 8px;
        }
        .btn-primary:hover {
            background-color: #2e59d9;
            border-color: #2e59d9;
        }
        .btn-secondary {
            padding: 10px 20px;
            font-weight: 600;
            border-radius: 8px;
        }
        .form-control {
            border-radius: 8px;
            padding: 12px 15px;
            border: 1px solid #d1d3e2;
        }
        .form-control:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
        }
        .info-box {
            background-color: #e8f4fd;
            border-left: 4px solid #4e73df;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .info-box i {
            color: #4e73df;
            margin-right: 8px;
        }
        .json-editor {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            background-color: #2d3748;
            color: #68d391;
            border-radius: 8px;
            padding: 15px;
            max-height: 400px;
            overflow-y: auto;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        .status-badge.success {
            background-color: #d4edda;
            color: #155724;
        }
        .status-badge.error {
            background-color: #f8d7da;
            color: #721c24;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #4e73df;
            text-decoration: none;
            font-weight: 600;
        }
        .back-link:hover {
            color: #2e59d9;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- 뒤로가기 링크 -->
        <a href="index.php" class="back-link">
            <i class="fas fa-arrow-left"></i> 푸시 알림 전송 페이지로 돌아가기
        </a>

        <!-- 페이지 제목 -->
        <h1 class="mb-4">
            <i class="fas fa-cog"></i> 푸시 알림 설정 관리
        </h1>

        <!-- 메시지 표시 -->
        <?php if (!empty($message)): ?>
        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- ==========================================
             섹션 0: 테스트 알림 설정
             ========================================== -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-flask"></i> 테스트 알림 설정
            </div>
            <div class="card-body">
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <strong>테스트 알림 설정</strong>은 푸시 알림 전송 페이지에서 빠르게 테스트할 수 있는 기본값을 저장합니다.
                </div>

                <form method="post">
                    <input type="hidden" name="action" value="save_test_notification">

                    <div class="mb-3">
                        <label for="test_title" class="form-label">제목</label>
                        <input type="text" class="form-control" id="test_title" name="test_title"
                               value="<?= htmlspecialchars($test_notification_config['title']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="test_message" class="form-label">내용</label>
                        <textarea class="form-control" id="test_message" name="test_message" rows="3" required><?= htmlspecialchars($test_notification_config['message']) ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="test_domain" class="form-label">도메인 (URL)</label>
                        <input type="url" class="form-control" id="test_domain" name="test_domain"
                               value="<?= htmlspecialchars($test_notification_config['domain']) ?>" required
                               placeholder="https://hairwang.com">
                        <div class="form-text">
                            현재 설정: <strong><?= htmlspecialchars($test_notification_config['domain']) ?></strong>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="test_image" class="form-label">
                            <i class="fas fa-image"></i> 이미지 파일 선택
                        </label>
                        <select class="form-control" id="test_image" name="test_image">
                            <option value="">이미지 없음</option>
                            <?php foreach ($image_files as $image): ?>
                            <option value="<?= htmlspecialchars($image) ?>"
                                    <?= $image === $test_notification_config['image'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($image) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">
                            현재 폴더에 있는 이미지 파일 목록입니다.
                            <?php if (!empty($test_notification_config['image'])): ?>
                            <br>현재 선택: <strong><?= htmlspecialchars($test_notification_config['image']) ?></strong>
                            <?php endif; ?>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> 테스트 설정 저장
                    </button>
                </form>
            </div>
        </div>

        <!-- ==========================================
             섹션 1: 푸시 알림 기본 설정
             ========================================== -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-cogs"></i> 푸시 알림 기본 설정
            </div>
            <div class="card-body">
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <strong>푸시 알림 기본 설정</strong>은 모든 푸시 알림에서 사용되는 공통 값입니다.
                </div>

                <form method="post">
                    <input type="hidden" name="action" value="update_push_settings">

                    <div class="mb-3">
                        <label for="project_id" class="form-label">
                            <i class="fab fa-google"></i> Firebase 프로젝트 ID
                        </label>
                        <input type="text" class="form-control" id="project_id" name="project_id"
                               value="<?= htmlspecialchars($config['project_id'] ?? 'hairwang-web-app') ?>" required>
                        <div class="form-text">
                            현재 설정: <strong><?= htmlspecialchars($config['project_id'] ?? 'hairwang-web-app') ?></strong>
                            <br><small class="text-muted">Firebase Console에서 확인 가능 (예: hairwang-web-app)</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="arg3_value" class="form-label">arg3 값 (보낸 사람 식별자)</label>
                        <input type="text" class="form-control" id="arg3_value" name="arg3_value"
                               value="<?= htmlspecialchars($config['arg3_value'] ?? 'HAIRWANG') ?>" required>
                        <div class="form-text">
                            현재 설정: <strong><?= htmlspecialchars($config['arg3_value'] ?? 'HAIRWANG') ?></strong>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="default_url" class="form-label">기본 URL</label>
                        <input type="url" class="form-control" id="default_url" name="default_url"
                               value="<?= htmlspecialchars($config['default_url'] ?? 'https://hairwang.com') ?>" required
                               placeholder="https://hairwang.com">
                        <div class="form-text">
                            현재 설정: <strong><?= htmlspecialchars($config['default_url'] ?? 'https://hairwang.com') ?></strong>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> 푸시 설정 저장
                    </button>
                </form>
            </div>
        </div>

        <!-- ==========================================
             섹션 2: 토픽 설정
             ========================================== -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-bullhorn"></i> 토픽 설정
            </div>
            <div class="card-body">
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <strong>토픽(Topic)</strong>은 전체 푸시 알림을 보낼 때 사용됩니다.
                    토픽 이름은 영문자, 숫자, 밑줄(_), 하이픈(-)만 사용 가능합니다.
                </div>

                <form method="post">
                    <input type="hidden" name="action" value="update_topic">

                    <div class="mb-3">
                        <label for="topic_name" class="form-label">현재 토픽 이름</label>
                        <input type="text" class="form-control" id="topic_name" name="topic_name"
                               value="<?= htmlspecialchars($config['topic_name']) ?>" required>
                        <div class="form-text">
                            현재 설정: <strong><?= htmlspecialchars($config['topic_name']) ?></strong>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> 토픽 이름 저장
                    </button>
                </form>
            </div>
        </div>

        <!-- ==========================================
             섹션 2: Firebase JSON 파일 설정
             ========================================== -->
        <div class="card">
            <div class="card-header">
                <i class="fab fa-google"></i> Firebase 서비스 계정 설정
            </div>
            <div class="card-body">
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <strong>Firebase 서비스 계정 JSON 파일</strong>은 FCM API 인증에 사용됩니다.
                    Firebase Console에서 다운로드한 JSON 파일을 사용하세요.
                </div>

                <!-- 현재 파일 정보 -->
                <div class="mb-4">
                    <h5><i class="fas fa-file-alt"></i> 현재 파일 정보</h5>
                    <p>
                        <strong>파일명:</strong> <?= htmlspecialchars($config['firebase_json_file']) ?>
                        <?php if ($firebase_json_exists): ?>
                        <span class="status-badge success">
                            <i class="fas fa-check-circle"></i> 존재함
                        </span>
                        <?php else: ?>
                        <span class="status-badge error">
                            <i class="fas fa-exclamation-circle"></i> 파일 없음
                        </span>
                        <?php endif; ?>
                    </p>

                    <?php if ($firebase_info): ?>
                    <p>
                        <strong>프로젝트 ID:</strong> <?= htmlspecialchars($firebase_info['project_id']) ?><br>
                        <strong>클라이언트 이메일:</strong> <?= htmlspecialchars($firebase_info['client_email']) ?>
                    </p>
                    <?php endif; ?>
                </div>

                <!-- 파일명 변경 -->
                <form method="post" class="mb-4">
                    <input type="hidden" name="action" value="change_firebase_file">

                    <div class="mb-3">
                        <label for="firebase_json_file" class="form-label">
                            <i class="fas fa-folder-open"></i> 다른 JSON 파일 선택
                        </label>
                        <select class="form-control" id="firebase_json_file" name="firebase_json_file" required>
                            <?php foreach ($json_file_list as $file): ?>
                            <option value="<?= htmlspecialchars($file) ?>"
                                    <?= $file === $config['firebase_json_file'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($file) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">
                            현재 폴더에 있는 JSON 파일 목록입니다.
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-exchange-alt"></i> 파일 변경
                    </button>
                </form>

                <hr>

                <!-- JSON 파일 내용 편집 -->
                <?php if ($firebase_json_exists): ?>
                <h5 class="mt-4"><i class="fas fa-edit"></i> JSON 파일 내용 편집</h5>
                <form method="post">
                    <input type="hidden" name="action" value="update_firebase_json">

                    <div class="mb-3">
                        <label for="firebase_json_content" class="form-label">
                            Firebase 서비스 계정 JSON 내용
                        </label>
                        <textarea class="form-control json-editor" id="firebase_json_content"
                                  name="firebase_json_content" rows="15" required><?= htmlspecialchars($firebase_json_content) ?></textarea>
                        <div class="form-text">
                            <i class="fas fa-exclamation-triangle text-warning"></i>
                            주의: JSON 형식이 올바르지 않으면 푸시 알림이 작동하지 않습니다.
                            수정 전 원본 파일이 자동으로 백업됩니다.
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> JSON 파일 저장
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="formatJSON()">
                        <i class="fas fa-magic"></i> 자동 포맷팅
                    </button>
                </form>
                <?php else: ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    Firebase JSON 파일이 존재하지 않습니다. 파일을 업로드하거나 다른 파일을 선택하세요.
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ==========================================
             섹션 3: 백업 파일 관리
             ========================================== -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-archive"></i> 백업 파일 관리
            </div>
            <div class="card-body">
                <?php
                // 백업 파일 목록
                $backup_files = glob(__DIR__ . '/*.backup.*');
                if (!empty($backup_files)):
                    // 최신순 정렬
                    usort($backup_files, function($a, $b) {
                        return filemtime($b) - filemtime($a);
                    });
                ?>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>파일명</th>
                            <th>크기</th>
                            <th>생성 시간</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backup_files as $backup): ?>
                        <tr>
                            <td><?= htmlspecialchars(basename($backup)) ?></td>
                            <td><?= number_format(filesize($backup)) ?> bytes</td>
                            <td><?= date('Y-m-d H:i:s', filemtime($backup)) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p class="text-muted">백업 파일이 없습니다.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        /**
         * JSON 자동 포맷팅 함수
         *
         * textarea의 JSON 내용을 파싱하여 보기 좋게 포맷팅합니다.
         */
        function formatJSON() {
            const textarea = document.getElementById('firebase_json_content');
            const content = textarea.value;

            try {
                // JSON 파싱
                const jsonObj = JSON.parse(content);

                // 포맷팅 (들여쓰기 4칸)
                const formatted = JSON.stringify(jsonObj, null, 4);

                // textarea에 적용
                textarea.value = formatted;

                alert('JSON 포맷팅이 완료되었습니다.');
            } catch (e) {
                alert('JSON 형식이 올바르지 않습니다:\n' + e.message);
            }
        }

        // 폼 제출 전 확인
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const action = this.querySelector('input[name="action"]').value;

                if (action === 'update_firebase_json') {
                    if (!confirm('Firebase JSON 파일을 수정하시겠습니까?\n\n이전 파일은 자동으로 백업됩니다.')) {
                        e.preventDefault();
                    }
                }
            });
        });
    </script>
</body>
</html>
