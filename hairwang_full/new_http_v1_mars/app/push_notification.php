<?php
require_once dirname(__DIR__) . '/auth.php';
require_login();
$push_csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>푸시 알림 시스템</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Noto Sans KR', sans-serif;
        }
        .container {
            max-width: 1200px;
            margin: 30px auto;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            border: none;
            overflow: hidden;
        }
        .card-header {
            background-color: #4e73df;
            color: white;
            font-weight: bold;
            padding: 15px 20px;
            border-bottom: none;
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
        .form-control {
            border-radius: 8px;
            padding: 12px 15px;
            border: 1px solid #d1d3e2;
        }
        .form-control:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
        }
        .nav-tabs {
            border-bottom: none;
            margin-bottom: 20px;
        }
        .nav-tabs .nav-link {
            border: none;
            border-radius: 8px;
            padding: 12px 20px;
            margin-right: 10px;
            font-weight: 600;
            color: #6c757d;
        }
        .nav-tabs .nav-link.active {
            background-color: #4e73df;
            color: white;
        }
        .tab-content {
            padding: 20px 0;
        }
        .alert {
            border-radius: 8px;
            padding: 15px 20px;
        }
        .device-icon {
            font-size: 24px;
            margin-right: 10px;
        }
        .preview-box {
            border: 1px solid #d1d3e2;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
            background-color: white;
        }
        .preview-title {
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 10px;
        }
        .preview-body {
            color: #6c757d;
        }
        .preview-device {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 600;
            margin-right: 5px;
        }
        .preview-device.android {
            background-color: #a4c639;
            color: white;
        }
        .preview-device.ios {
            background-color: #a2aaad;
            color: white;
        }
        .advanced-toggle {
            cursor: pointer;
            color: #4e73df;
            margin-bottom: 15px;
            font-weight: 500;
        }
        .advanced-options {
            display: none;
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #e3e6f0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="m-0">푸시 알림 시스템</h3>
                    </div>
                    <div class="card-body">
                        <ul class="nav nav-tabs" id="pushTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="individual-tab" data-bs-toggle="tab" data-bs-target="#individual" type="button" role="tab" aria-controls="individual" aria-selected="true">
                                    <i class="fas fa-user"></i> 개별 푸시
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="group-tab" data-bs-toggle="tab" data-bs-target="#group" type="button" role="tab" aria-controls="group" aria-selected="false">
                                    <i class="fas fa-users"></i> 전체 푸시
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="topic-tab" data-bs-toggle="tab" data-bs-target="#topic" type="button" role="tab" aria-controls="topic" aria-selected="false">
                                    <i class="fas fa-bullhorn"></i> 토픽 푸시
                                </button>
                            </li>
                        </ul>
                        
                        <div class="tab-content" id="pushTabsContent">
                            <!-- 개별 푸시 탭 -->
                            <div class="tab-pane fade show active" id="individual" role="tabpanel" aria-labelledby="individual-tab">
                                <form id="individualPushForm" action="push_api.php" method="post">
                                    <input type="hidden" name="push_type" value="individual">
                                    
                                    <div class="mb-3">
                                        <label for="device_type" class="form-label">기기 유형</label>
                                        <div class="d-flex">
                                            <div class="form-check me-4">
                                                <input class="form-check-input" type="radio" name="app" id="android" value="android" checked>
                                                <label class="form-check-label" for="android">
                                                    <i class="fab fa-android device-icon text-success"></i>안드로이드
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="app" id="ios" value="ios">
                                                <label class="form-check-label" for="ios">
                                                    <i class="fab fa-apple device-icon text-dark"></i>iOS
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="user_token" class="form-label">사용자 토큰</label>
                                        <textarea class="form-control" id="user_token" name="user_token" rows="2" required placeholder="FCM 토큰을 입력하세요"></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="title" class="form-label">제목</label>
                                        <input type="text" class="form-control" id="title" name="title" required placeholder="알림 제목을 입력하세요">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="message" class="form-label">내용</label>
                                        <textarea class="form-control" id="message" name="message" rows="3" required placeholder="알림 내용을 입력하세요"></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="url" class="form-label">URL (선택사항)</label>
                                        <input type="url" class="form-control" id="url" name="url" placeholder="https://example.com">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="file_url" class="form-label">이미지 URL (선택사항)</label>
                                        <input type="url" class="form-control" id="file_url" name="file_url" placeholder="https://example.com/image.jpg">
                                    </div>
                                    
                                    <div class="advanced-toggle" onclick="toggleAdvancedOptions('individual')">
                                        <i class="fas fa-cog"></i> 고급 옵션 표시
                                    </div>
                                    
                                    <div id="individual-advanced" class="advanced-options">
                                        <div class="mb-3">
                                            <label for="order_id" class="form-label">주문 ID (선택사항)</label>
                                            <input type="text" class="form-control" id="order_id" name="order_id" placeholder="주문 ID를 입력하세요">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="arg1" class="form-label">추가 파라미터 1 (선택사항)</label>
                                            <input type="text" class="form-control" id="arg1" name="arg1" placeholder="기본값: $arg1">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="arg2" class="form-label">추가 파라미터 2 (선택사항)</label>
                                            <select class="form-control" id="arg2" name="arg2">
                                                <option value="1" selected>1 (팝업 표시)</option>
                                                <option value="0">0 (팝업 미표시)</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="arg3" class="form-label">추가 파라미터 3 (선택사항)</label>
                                            <input type="text" class="form-control" id="arg3" name="arg3" placeholder="기본값: MARS">
                                        </div>
                                    </div>
                                    
                                    <div class="preview-box">
                                        <h5>미리보기</h5>
                                        <div class="d-flex align-items-center mb-2">
                                            <span class="preview-device android" id="preview-device">안드로이드</span>
                                            <span class="preview-title" id="preview-title">알림 제목</span>
                                        </div>
                                        <div class="preview-body" id="preview-body">알림 내용이 여기에 표시됩니다.</div>
                                    </div>
                                    
                                    <div class="d-grid gap-2 mt-4">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-paper-plane me-2"></i>푸시 알림 보내기
                                        </button>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- 전체 푸시 탭 -->
                            <div class="tab-pane fade" id="group" role="tabpanel" aria-labelledby="group-tab">
                                <form id="groupPushForm" action="push_api.php" method="post">
                                    <input type="hidden" name="push_type" value="group">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">대상 기기</label>
                                        <div class="d-flex">
                                            <div class="form-check me-4">
                                                <input class="form-check-input" type="checkbox" name="target_devices[]" id="target_android" value="android" checked>
                                                <label class="form-check-label" for="target_android">
                                                    <i class="fab fa-android device-icon text-success"></i>안드로이드
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="target_devices[]" id="target_ios" value="ios" checked>
                                                <label class="form-check-label" for="target_ios">
                                                    <i class="fab fa-apple device-icon text-dark"></i>iOS
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="group_title" class="form-label">제목</label>
                                        <input type="text" class="form-control" id="group_title" name="title" required placeholder="알림 제목을 입력하세요">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="group_message" class="form-label">내용</label>
                                        <textarea class="form-control" id="group_message" name="message" rows="3" required placeholder="알림 내용을 입력하세요"></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="group_url" class="form-label">URL (선택사항)</label>
                                        <input type="url" class="form-control" id="group_url" name="url" placeholder="https://example.com">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="group_file_url" class="form-label">이미지 URL (선택사항)</label>
                                        <input type="url" class="form-control" id="group_file_url" name="file_url" placeholder="https://example.com/image.jpg">
                                    </div>
                                    
                                    <div class="advanced-toggle" onclick="toggleAdvancedOptions('group')">
                                        <i class="fas fa-cog"></i> 고급 옵션 표시
                                    </div>
                                    
                                    <div id="group-advanced" class="advanced-options">
                                        <div class="mb-3">
                                            <label for="group_order_id" class="form-label">주문 ID (선택사항)</label>
                                            <input type="text" class="form-control" id="group_order_id" name="order_id" placeholder="주문 ID를 입력하세요">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="group_arg1" class="form-label">추가 파라미터 1 (선택사항)</label>
                                            <input type="text" class="form-control" id="group_arg1" name="arg1" placeholder="기본값: $arg1">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="group_arg2" class="form-label">추가 파라미터 2 (선택사항)</label>
                                            <select class="form-control" id="group_arg2" name="arg2">
                                                <option value="1" selected>1 (팝업 표시)</option>
                                                <option value="0">0 (팝업 미표시)</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="group_arg3" class="form-label">추가 파라미터 3 (선택사항)</label>
                                            <input type="text" class="form-control" id="group_arg3" name="arg3" placeholder="기본값: MARS">
                                        </div>
                                    </div>
                                    
                                    <div class="alert alert-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <strong>주의:</strong> 전체 푸시는 모든 사용자에게 알림을 보냅니다. 신중하게 사용하세요.
                                    </div>
                                    
                                    <div class="d-grid gap-2 mt-4">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-paper-plane me-2"></i>전체 푸시 알림 보내기
                                        </button>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- 토픽 푸시 탭 -->
                            <div class="tab-pane fade" id="topic" role="tabpanel" aria-labelledby="topic-tab">
                                <form id="topicPushForm" action="push_api.php" method="post">
                                    <input type="hidden" name="push_type" value="topic">
                                    
                                    <div class="mb-3">
                                        <label for="topic_name" class="form-label">토픽 이름</label>
                                        <input type="text" class="form-control" id="topic_name" name="topic" required placeholder="토픽 이름을 입력하세요 (예: news, updates, all_users)">
                                        <div class="form-text">토픽 이름은 영문자, 숫자, 밑줄(_), 하이픈(-), 마침표(.)만 포함할 수 있습니다.</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="topic_title" class="form-label">제목</label>
                                        <input type="text" class="form-control" id="topic_title" name="title" required placeholder="알림 제목을 입력하세요">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="topic_message" class="form-label">내용</label>
                                        <textarea class="form-control" id="topic_message" name="message" rows="3" required placeholder="알림 내용을 입력하세요"></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="topic_url" class="form-label">URL (선택사항)</label>
                                        <input type="url" class="form-control" id="topic_url" name="url" placeholder="https://example.com">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="topic_file_url" class="form-label">이미지 URL (선택사항)</label>
                                        <input type="url" class="form-control" id="topic_file_url" name="file_url" placeholder="https://example.com/image.jpg">
                                    </div>
                                    
                                    <div class="advanced-toggle" onclick="toggleAdvancedOptions('topic')">
                                        <i class="fas fa-cog"></i> 고급 옵션 표시
                                    </div>
                                    
                                    <div id="topic-advanced" class="advanced-options">
                                        <div class="mb-3">
                                            <label for="topic_order_id" class="form-label">주문 ID (선택사항)</label>
                                            <input type="text" class="form-control" id="topic_order_id" name="order_id" placeholder="주문 ID를 입력하세요">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="topic_arg1" class="form-label">추가 파라미터 1 (선택사항)</label>
                                            <input type="text" class="form-control" id="topic_arg1" name="arg1" placeholder="기본값: $arg1">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="topic_arg2" class="form-label">추가 파라미터 2 (선택사항)</label>
                                            <select class="form-control" id="topic_arg2" name="arg2">
                                                <option value="1" selected>1 (팝업 표시)</option>
                                                <option value="0">0 (팝업 미표시)</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="topic_arg3" class="form-label">추가 파라미터 3 (선택사항)</label>
                                            <input type="text" class="form-control" id="topic_arg3" name="arg3" placeholder="기본값: MARS">
                                        </div>
                                    </div>
                                    
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>정보:</strong> 토픽 푸시는 해당 토픽을 구독한 모든 기기에 알림을 보냅니다.
                                    </div>
                                    
                                    <div class="d-grid gap-2 mt-4">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-paper-plane me-2"></i>토픽 푸시 알림 보내기
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 고급 옵션 토글 함수
        function toggleAdvancedOptions(formType) {
            const advancedSection = document.getElementById(formType + '-advanced');
            const toggle = document.querySelector(`#${formType} .advanced-toggle`);
            
            if (advancedSection.style.display === 'block') {
                advancedSection.style.display = 'none';
                toggle.innerHTML = '<i class="fas fa-cog"></i> 고급 옵션 표시';
            } else {
                advancedSection.style.display = 'block';
                toggle.innerHTML = '<i class="fas fa-cog"></i> 고급 옵션 숨기기';
            }
        }
        
        // 미리보기 업데이트 함수
        function updatePreview() {
            const title = document.getElementById('title').value || '알림 제목';
            const message = document.getElementById('message').value || '알림 내용이 여기에 표시됩니다.';
            const appType = document.querySelector('input[name="app"]:checked').value;
            
            document.getElementById('preview-title').textContent = title;
            document.getElementById('preview-body').textContent = message;
            
            const previewDevice = document.getElementById('preview-device');
            if (appType === 'android') {
                previewDevice.textContent = '안드로이드';
                previewDevice.className = 'preview-device android';
            } else {
                previewDevice.textContent = 'iOS';
                previewDevice.className = 'preview-device ios';
            }
        }
        
        // 이벤트 리스너 등록
        document.getElementById('title').addEventListener('input', updatePreview);
        document.getElementById('message').addEventListener('input', updatePreview);
        document.querySelectorAll('input[name="app"]').forEach(radio => {
            radio.addEventListener('change', updatePreview);
        });
        
        // 폼 제출 이벤트
        document.getElementById('individualPushForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('csrf_token', <?php echo json_encode($push_csrf_token); ?>);
            
            fetch('push_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('푸시 알림이 성공적으로 전송되었습니다.');
                } else {
                    alert('푸시 알림 전송에 실패했습니다: ' + data.message);
                }
            })
            .catch(error => {
                alert('오류가 발생했습니다: ' + error.message);
            });
        });
        
        document.getElementById('groupPushForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (confirm('정말로 모든 사용자에게 푸시 알림을 보내시겠습니까?')) {
                const formData = new FormData(this);
                formData.append('csrf_token', <?php echo json_encode($push_csrf_token); ?>);
                
                fetch('push_api.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('전체 푸시 알림이 성공적으로 전송되었습니다.');
                    } else {
                        alert('전체 푸시 알림 전송에 실패했습니다: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('오류가 발생했습니다: ' + error.message);
                });
            }
        });
        
        document.getElementById('topicPushForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('csrf_token', <?php echo json_encode($push_csrf_token); ?>);
            
            fetch('push_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('토픽 푸시 알림이 성공적으로 전송되었습니다.');
                } else {
                    alert('토픽 푸시 알림 전송에 실패했습니다: ' + data.message);
                }
            })
            .catch(error => {
                alert('오류가 발생했습니다: ' + error.message);
            });
        });
    </script>
</body>
</html>
