<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

add_stylesheet('<link rel="stylesheet" href="'.$member_skin_url.'/style.css">', 0);
add_stylesheet('<link rel="stylesheet" href="'.G5_URL.'/rb/rb.mod/chat/style.chat.css?ver='.G5_TIME_YMDHIS.'">', 0);

$recv_mb = get_member($me_recv_mb_id);
$p_icon = get_member_profile_img($me_recv_mb_id);

// 대화창을 열면 오래된 대화를 삭제
$days_old = $chat_set['ch_days_old'];
$max_file_size = $chat_set['ch_max_file_size']; // 1MB = 1048576 bytes

$ch_extension = $chat_set['ch_extension']; //확장자
$extensions_array = explode(',', $ch_extension); //배열
$allowed_extensions = $extensions_array;

if($days_old > 0) {
    $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_old} days"));
    $sql = "SELECT * FROM rb_chat WHERE me_send_datetime < '{$cutoff_date}' AND (me_send_mb_id = '{$member['mb_id']}' OR me_recv_mb_id = '{$member['mb_id']}')";
    $result = sql_query($sql);

    while ($row = sql_fetch_array($result)) {
        // 파일이 있는 경우 파일 삭제
        if (preg_match('/<a.*href="([^"]*)"/i', $row['me_memo'], $matches) || 
            preg_match('/<img.*src="([^"]*)"/i', $row['me_memo'], $matches) || 
            preg_match('/<video.*src="([^"]*)"/i', $row['me_memo'], $matches) || 
            preg_match('/<audio.*src="([^"]*)"/i', $row['me_memo'], $matches)) {

            foreach ($matches[1] as $file_url) {
                $file_path = parse_url($file_url, PHP_URL_PATH);
                $file_path = $_SERVER['DOCUMENT_ROOT'] . $file_path;
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
        }

        // 메시지 삭제
        $del_sql = "DELETE FROM rb_chat WHERE me_id = '{$row['me_id']}'";
        sql_query($del_sql);
    }
}

// 대화창을 열면 기존 읽지 않은 메세지를 업데이트 한다
$aaa = "SELECT * FROM rb_chat WHERE me_recv_mb_id ='".$member['mb_id']."' AND me_send_mb_id = '".$recv_mb['mb_id']."' AND me_read_datetime = '0000-00-00 00:00:00'";
$bbb = sql_query($aaa);

// 한 개씩 읽은 날짜를 업데이트 해준다
while ($ccc = sql_fetch_array($bbb)) {
    $up = "UPDATE rb_chat SET me_read_datetime = '".G5_TIME_YMDHIS."' WHERE me_id = '".$ccc['me_id']."'";
    sql_query($up);
}

// 가장 큰 결과 값을 구함
$que = "SELECT MAX(me_id) AS max FROM rb_chat WHERE (me_recv_mb_id ='".$recv_mb['mb_id']."' AND me_send_mb_id = '".$member['mb_id']."') OR (me_recv_mb_id = '".$member['mb_id']."' AND me_send_mb_id = '".$recv_mb['mb_id']."')";
$max = sql_fetch($que); 

// 전체 카운터 
$que = "SELECT COUNT(me_id) AS cnt FROM rb_chat WHERE (me_recv_mb_id ='".$recv_mb['mb_id']."' AND me_send_mb_id = '".$member['mb_id']."') OR (me_recv_mb_id = '".$member['mb_id']."' AND me_send_mb_id = '".$recv_mb['mb_id']."')";
$cnt = sql_fetch($que);
$total = $cnt['cnt']; // 총 갯수
$limit = 10; // 출력할 갯수
//$limit = $cnt['cnt']; // 출력할 갯수

$from_record = $total - $limit;
if ($from_record < 0) {
    $limit_record = $limit + $from_record;
    $from_record = 0;
} else {
    $limit_record = $limit;
}

$sql = "SELECT * FROM rb_chat WHERE (me_send_mb_id ='".$recv_mb['mb_id']."' AND me_recv_mb_id = '".$member['mb_id']."') OR (me_send_mb_id = '".$member['mb_id']."' AND me_recv_mb_id = '".$recv_mb['mb_id']."') ORDER BY me_send_datetime ASC LIMIT {$from_record}, {$limit}";
$res = sql_query($sql);

?>

<div id="drop-zone">
    <div id="chat-header">
        <div>
            <div class="mo_tits">
                <span class="badge3"><?php echo isset($recv_mb['mb_nick']) ? htmlspecialchars($recv_mb['mb_nick']) : ''; ?>님과의 대화</span>
            </div>
            <div class="mo_bdgs">
                <a href="javascript:void(0);" class="btn_close_r1" onclick="javascript:self.close();">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 -5 10 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-plus w-5 h-5 sm:w-6 sm:h-6"><line x1="13" y1="12" x2="3" y2="2"></line><line x1="13" y1="2" x2="3" y2="12"></line></svg>
                </a>
            </div>
            <div class="mo_bdgs mo_bdgs_back">
                <a href="javascript:void(0);" class="btn_close_r2" onclick="location.href='<?php echo G5_URL ?>/rb/chat.php';">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-arrow-left"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                </a>
            </div>
            <div style="clear:both"></div>
        </div>
    </div>
    <div id="chat-body">
        <?php if ($total > $limit) { // 총 갯수가 출력할 갯수보다 많으면 이전 버튼을 만들어 준다 ?>
            <div class="chat_history_div_wrap" id="chat_history_div_wrap">
                <div class="chat_history_div">
                    <button type="button" id="chat-history" style="">이전대화 10개 보기</button>
                </div>
            </div>
        <?php } ?>
        <ul id="chat-list">
            <?php if ($total <= 0) { ?>
                <div class="chktotal font-R"><?php echo isset($recv_mb['mb_nick']) ? htmlspecialchars($recv_mb['mb_nick']) : ''; ?>님과 대화를 시작해보세요!</div>
            <?php } ?>
            <?php
            
            $chat_exists = false; // 대화가 있는지 여부를 저장하는 변수
            $deleted_message_shown = false; // 상대방이 대화를 삭제했는지 여부를 저장하는 변수
            
            // 상대방이 대화를 삭제했는지 확인
            /*
            $deleted_sql = "SELECT COUNT(me_id) as cnt FROM rb_chat WHERE me_send_mb_id='".$recv_mb['mb_id']."' AND me_recv_mb_id='".$member['mb_id']."'";
            $deleted_res = sql_fetch($deleted_sql);
            if ($deleted_res['cnt'] == 0) {
                echo '<li class="chkdate">상대방이 대화내역을 삭제 했습니다</li>';
                $deleted_message_shown = true;
            }
            */
            
            while ($row = sql_fetch_array($res)) {
                $newDate = substr($row['me_send_datetime'], 0, 10);
                if ($row['me_send_mb_id'] == $member['mb_id']) {
                    $cls = "recv";
                    $cls_box = "bubble_recv";
                } else {
                    $cls = "send";
                    $cls_box = "bubble_send";
                }

                if (!isset($chkDate) || $newDate != $chkDate) {
                    echo '<li id="'.htmlspecialchars($newDate).'" class="chkdate">' . htmlspecialchars($newDate) . '</li>';
                    $chkDate = $newDate;
                }
                echo '<li id="chat_list_'.htmlspecialchars($row['me_id']).'" class="'.htmlspecialchars($cls).'">';
                if ($row['me_send_mb_id'] != $member['mb_id']) {
                    echo '<a href="'.G5_BBS_URL.'/profile.php?mb_id='.htmlspecialchars($recv_mb['mb_id']).'" target="_blank" class="p_icon win_profile">'.$p_icon.'</a>';
                    echo '<span class="p_nick"> '.htmlspecialchars($recv_mb['mb_nick']).'</span>';
                }
                echo '<div class="'.htmlspecialchars($cls_box).'">';
                echo htmlspecialchars_decode($row['me_memo']);
                echo '</div>';
                echo '<div class="chat_time_'.$cls.'">';

                // 수신 확인 부분
                if ($row['me_send_mb_id'] == $member['mb_id']) {
                    if (substr($row['me_read_datetime'], 0, 1) == '0') {
                        echo "<span id='am".htmlspecialchars($row['me_id'])."' style='color:#FF0000;' class='font-B'>1</span>";
                    } else {
                        echo "";
                    }
                }
                // 수신 확인
                echo substr($row['me_send_datetime'], 10, 6);
                echo '</div>';

                if ($row['me_send_mb_id'] == $member['mb_id']) {
                    echo '<div style="position: absolute; margin-top:-62px; margin-left:-20px;"><a href="#" class="chatroom-cnt2 badge3" data-me_id="'.$row['me_id'].'" data-del_id="'.$recv_mb['mb_id'].'"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="feather feather-trash w-4 h-4 mr-2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></a><div>';
                }
                echo '</li>';
                $pos_id = $row['me_id'];
                $chat_exists = true; // 대화가 있음을 표시
            }
            $toDate = date("Y-m-d");
            if (!isset($chkDate) || $chkDate != $toDate) {
                echo '<li id="'.htmlspecialchars($toDate).'" class="chkdate">' . htmlspecialchars($toDate) . '</li>';
            }
            ?>
        </ul>
    </div>


    <div id="chat-footer">
        <div class="chat_message_div">
            <textarea id="chat-message" name="cmt_text" placeholder="메세지를 입력하세요."></textarea>
            <input type="file" id="file-input" style="display: none;" accept="image/*,video/mp4,audio/mp3,audio/m4a">
            <!-- 프로그래스바 추가 -->
            <div id="progress-container" style="display: none;">
                <div id="progress-bar"></div>
            </div>
        </div>
        <div class="chat_message_btn_div">
            <div id="btn-ref" title="새로고침">
                <i class="fa fa-refresh" aria-hidden="true"></i>
            </div>
            <div id="btn-upload" title="파일전송">
                <i class="fa fa-folder" aria-hidden="true"></i>
            </div>
            <div id="btn-chat" title="보내기">
                <i class="fa fa-paper-plane" aria-hidden="true"></i>
            </div>
        </div>
        <div style="clear:both"></div>

    </div>
    
    <span id="drop-message" style="display: none;">
        <div class="drop-message_wrap">
            <ul>
                <img src="<?php echo G5_URL ?>/rb/rb.mod/chat/image/ico_file.svg">
            </ul>
        </div>
    </span>
</div>
<script>
    
var pos_id = "<?php echo isset($pos_id) ? htmlspecialchars($pos_id) : ''; ?>";
var chkDate = "<?php echo isset($chkDate) ? htmlspecialchars($chkDate) : ''; ?>";
var send_mb_id = "<?php echo htmlspecialchars($member['mb_id']); ?>";
var recv_mb_id = "<?php echo isset($recv_mb['mb_id']) ? htmlspecialchars($recv_mb['mb_id']) : ''; ?>";
var max_id = "<?php echo isset($max['max']) ? htmlspecialchars($max['max']) : 0; ?>";
var limit_record = "<?php echo htmlspecialchars($limit); ?>";
var from_record = "<?php echo htmlspecialchars($from_record); ?>";
var p_icon = '<?php echo addslashes($p_icon); ?>';
var p_nick = "<?php echo isset($recv_mb['mb_nick']) ? htmlspecialchars($recv_mb['mb_nick']) : ''; ?>";

    
// 파일 업로드 버튼 클릭 이벤트
$('#btn-upload').click(function() {
    $('#file-input').click();
});
    
var maxFileSize = <?php echo $max_file_size; ?>;
var allowedExtensions = <?php echo json_encode($allowed_extensions); ?>;
var maxFileSizeMsg = '파일 크기가 ' + (maxFileSize / 1048576) + 'Mb를 초과할 수 없습니다.';
var allowedExtensionsMsg = '허용된 파일 형식이 아닙니다.\n' + allowedExtensions.join(', ') + ' 파일만 가능합니다.';

$(document).ready(function() {
    
    // 페이지 로드 시 이미지를 감지하여 스크롤 이동
    $('#chat-list img, #chat-list video, #chat-list audio').on('load', function() {
        move_page();
    });
    
    // 파일 입력 변화 이벤트
    $('#file-input').change(function() {
        var file = this.files[0];
        if (file) {
            var fileSize = file.size;
            var fileExtension = file.name.split('.').pop().toLowerCase();

            if (fileSize > maxFileSize) {
                alert(maxFileSizeMsg);
                $(this).val(''); // 파일 입력 초기화
                return;
            }

            if ($.inArray(fileExtension, allowedExtensions) === -1) {
                alert(allowedExtensionsMsg);
                $(this).val(''); // 파일 입력 초기화
                return;
            }

            uploadFile(file);
        }
    });
    
    var dragCounter = 0;

    $('#drop-zone').on('dragenter', function(event) {
        event.preventDefault();
        event.stopPropagation();
        dragCounter++;
        $('#drop-message').show();
    });
    
    $('#drop-zone').on('dragleave', function(event) {
        event.preventDefault();
        event.stopPropagation();
        dragCounter--;
        if (dragCounter === 0) {
            $('#drop-message').hide();
        }
    });
    
    $('#drop-zone').on('dragover', function(event) {
        event.preventDefault();
        event.stopPropagation();
        event.originalEvent.dataTransfer.dropEffect = 'copy';
    });
    
    $('#drop-zone').on('drop', function(event) {
        event.preventDefault();
        event.stopPropagation();
        $('#drop-message').hide();
        dragCounter = 0;

        var files = event.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            var file = files[0];
            var fileSize = file.size;
            var fileExtension = file.name.split('.').pop().toLowerCase();

            if (fileSize > maxFileSize) {
                alert(maxFileSizeMsg);
                return;
            }

            if ($.inArray(fileExtension, allowedExtensions) === -1) {
                alert(allowedExtensionsMsg);
                return;
            }

            uploadFile(file);
        }
    });
    
    function uploadFile(file) {
        var formData = new FormData();
        formData.append('file', file);
        formData.append('act', 'upload_image'); // act 값을 'upload_image'로 유지
        formData.append('send_mb_id', send_mb_id);
        formData.append('recv_mb_id', recv_mb_id);

        // 프로그래스바 초기화
        var progressContainer = $('#progress-container');
        var progressBar = $('#progress-bar');
        progressContainer.show();
        progressBar.css('width', '0%');

        $.ajax({
            url: '<?php echo G5_URL; ?>/rb/rb.mod/chat/core/ajax.chat.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            xhr: function() {
                var xhr = $.ajaxSettings.xhr();
                xhr.upload.onprogress = function(event) {
                    if (event.lengthComputable) {
                        var percentComplete = (event.loaded / event.total) * 100;
                        progressBar.css('width', percentComplete + '%');
                    }
                };
                return xhr;
            },
            success: function(response) {
                console.log('Server response:', response);
                if (response.success) {
                    var fileHtml = '';
                    if (file.type.startsWith('image/')) {
                        fileHtml = '<a data-fslightbox="gallery" href="' + response.file_path + '" class="chat_list_image"><img src="' + response.file_path + '"></a>';
                    } else if (file.type === 'video/mp4') {
                        fileHtml = '<a data-fslightbox="gallery" href="' + response.file_path + '"><video src="' + response.file_path + '" width="230" height="auto" controls class="chat_list_video"></video></a>';
                    } else if (file.type === 'audio/mp3' || file.type === 'audio/mpeg' || file.type === 'audio/x-m4a' || file.type === 'audio/x-m4a') {
                        fileHtml = '<audio src="' + response.file_path + '" width="230" height="auto" controls class="chat_list_audio"></audio>';
                    } else {
                        fileHtml = '<a href="' + response.file_path + '" download><img src="<?php echo G5_URL ?>/rb/rb.mod/chat/image/ico_file.svg" class="chat_file_icos">' + file.name + '</a>';
                    }

                    send_message(fileHtml, true);
                    // 이미지 및 비디오 로드 이벤트 추가
                    $('#chat-list img, #chat-list video, #chat-list audio').on('load', function() {
                        move_page();
                    });
                    
                    // fslightbox 새로고침
                    refreshFsLightbox();
                    
                } else {
                    alert('파일 업로드에 실패했습니다.');
                    console.error('Upload error:', response.error);
                }
                // 프로그래스바 숨기기
                progressContainer.hide();
            },
            error: function(xhr, status, error) {
                alert('파일 업로드 중 오류가 발생했습니다.');
                progressContainer.hide();
            }
        });
    }
    
    
    $("#btn-chat").click(function() {
        $("#chat-message").focus();
        var message = $("#chat-message").val();
        if (message === "") {
            alert('메세지를 입력하세요');
            return false;
        }
        $("#chat-message").val('');
        send_message(message);
    });

    $("#btn-ref").click(function() {   
        window.location.reload();
    });
    
    function escapeHtml(text) {
        return text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function send_message(msg, isHtml = false) {
        var linkifiedMsg = msg.replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank">$1</a>');
        var escapedMsg = isHtml ? msg : linkifiedMsg;

        var data = {
            act: 'update',
            send_mb_id: send_mb_id,
            recv_mb_id: recv_mb_id,
            me_memo: isHtml ? escapedMsg : nl2br(escapedMsg),
            recv_mb_level: <?php echo htmlspecialchars($recv_mb['mb_level']); ?>
        };

        $.ajax({
            type: "POST",
            data: data,
            url: '<?php echo G5_URL; ?>/rb/rb.mod/chat/core/ajax.chat.php',
            success: function(data) {
                if (data['error_msg'].length > 0) {
                    var html = '';
                    html +='<li class="">이용하실 수 없습니다.('+data['error_msg']+')</li>';
                    $('#chat-list').append(html);
                    return false;
                }
                chat_refresh();
            }
        });
    }
    

    $(document).on('click', '.chatroom-cnt2', function(e) {
        e.preventDefault(); // 기본 링크 동작 방지

        var me_id = $(this).data('me_id');
        var del_id = $(this).data('del_id');

        if (confirm('대화를 삭제 하시겠습니까?\n상대방의 화면에서 즉시 삭제되지는 않습니다.')) {
            $.ajax({
                type: "POST",
                url: '<?php echo G5_URL; ?>/rb/rb.mod/chat/core/ajax.chat_del.php',
                data: {
                    me_id: me_id,
                    del_id: del_id
                },
                success: function(response) {
                    //alert('대화가 삭제 되었습니다.');
                    $('#chat_list_' + me_id).remove(); // 삭제한 항목을 UI에서 제거

                    // 상대방의 채팅 목록에서도 메시지를 제거하도록 요청
                    notifyReceiverAboutDeletion(me_id, del_id);
                },
                error: function(xhr, status, error) {
                    console.error('삭제 중 오류 발생:', status, error);
                    alert('삭제 중 오류가 발생했습니다.');
                }
            });
        }
    });
});
</script>

<script>
    
    function nl2br(str) {
        return str.replace(/\n/g, '<br>');
    }

    function linkify(text) {
        var urlPattern = /(https?:\/\/[^\s]+)/g;
        return text.replace(urlPattern, function(url) {
            return '<a href="' + url + '" target="_blank">' + url + '</a>';
        });
    }

    function processMessage(msg) {
        msg = nl2br(msg);
        msg = linkify(msg);
        return msg;
    }

function notifyReceiverAboutDeletion(me_id, del_id) {
    $.ajax({
        type: "POST",
        url: '<?php echo G5_URL; ?>/rb/rb.mod/chat/core/ajax.notify_del.php',
        data: {
            me_id: me_id,
            del_id: del_id
        },
        success: function(response) {
            recalculateMaxIdAndRefresh(); // 삭제 후 새로고침
        },
        error: function(xhr, status, error) {
            console.error('상대방에게 삭제 알림 전송 중 오류 발생:', status, error);
        }
    });
}
    
function recalculateMaxIdAndRefresh() {
    $.ajax({
        type: "POST",
        data: { act: 'recalculate_max_id', send_mb_id: send_mb_id, recv_mb_id: recv_mb_id },
        url: '<?php echo G5_URL; ?>/rb/rb.mod/chat/core/ajax.chat.php',
        success: function(data) {
            if (data.max_id) {
                max_id = data.max_id;
            }
            chat_refresh();
        },
        error: function(xhr, status, error) {
            console.error('Max ID 재계산 중 오류 발생:', status, error);
        }
    });
}
    
function decodeHtml(html) {
    var txt = document.createElement('textarea');
    txt.innerHTML = html;
    return txt.value;
}
    
function chat_refresh() {
    $.ajax({
        type: "POST",
        data: { act: 'refresh', send_mb_id: send_mb_id, recv_mb_id: recv_mb_id, max_id: max_id },
        url: '<?php echo G5_URL; ?>/rb/rb.mod/chat/core/ajax.chat.php',
        success: function(data) {
            if (data.data && data.data.length > 0) {
                var new_max_id = max_id;
                var html = '';

                $.each(data.data, function(i, $i) {
                    if ($i.me_id > max_id) {
                        var cls = ($i.me_send_mb_id == send_mb_id) ? "recv" : "send";
                        var cls_box = ($i.me_send_mb_id == send_mb_id) ? "bubble_recv" : "bubble_send";

                        html += '<li id="chat_list_' + $i.me_id + '" class="' + cls + '">';
                        if ($i.me_send_mb_id != send_mb_id) {
                            html += '<span class="p_icon">' + p_icon + '</span>';
                            html += '<span class="p_nick">' + p_nick + '</span>';
                        }
                        html += '<div class="' + cls_box + '">';
                        html += nl2br(decodeHtml($i.me_memo));
                        html += '</div>';
                        html += '<div class="chat_time2_' + cls + '">';
                        if ($i.me_send_mb_id == send_mb_id) {
                            if ($i.me_read_datetime.substr(0, 1) == '0') {
                                html += "<span id='bm" + $i.me_id + "' style='color:#FF0000; font-weight:bold;'>1</span>";
                            }
                        }
                        html += $i.me_send_datetime.substr(10, 6);
                        html += '</div>';
                        if ($i.me_send_mb_id == send_mb_id) {
                            html += '<div class="del_div"><a href="#" class="chatroom-cnt2 badge3" data-me_id="' + $i.me_id + '" data-del_id="' + recv_mb_id + '"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="feather feather-trash w-4 h-4 mr-2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></a></div>';
                        }
                        html += '</li>';

                        new_max_id = $i.me_id;

                        
                    }
                });

                if (html) {
                    $('#chat-list').append(html);
                    move_page();
                }

                max_id = new_max_id;

                // 이미지 로드 이벤트 추가
                $('#chat-list img, #chat-list video, #chat-list audio').on('load', function() {
                    move_page();
                });
                
                refreshFsLightbox();
            }
        }
    });
    return false;
}
    
$(document).on('click', '#chat-history', function() {
    //console.log("Chat history button clicked");

    $.ajax({
        type: "POST",
        data: {
            act: 'history',
            send_mb_id: send_mb_id,
            recv_mb_id: recv_mb_id,
            from_record: from_record,
            limit_record: limit_record
        },
        url: '<?php echo G5_URL; ?>/rb/rb.mod/chat/core/ajax.chat.php',
        success: function(data) {
            console.log("AJAX response:", data);
            if (data.data && data.data.length > 0) {
                var html = '';
                $.each(data.data, function(i, $i) {
                    pos_id = $i.me_id;
                    newDate = $i.me_send_datetime.substr(0, 10);
                    if ($i.me_send_mb_id == send_mb_id) {
                        var cls = "recv";
                        var cls_box = "bubble_recv";
                    } else {
                        var cls = "send";
                        var cls_box = "bubble_send";
                    }
                    if (newDate != chkDate) {
                        if ($('#' + newDate).length > 0) {
                            $('#' + newDate).remove();
                        }
                        html += '<li id="' + newDate + '" class="chkdate">' + newDate + '</li>';
                    } else {
                        if ($('#' + newDate).length > 0) {
                            $('#' + newDate).remove();
                            html += '<li id="' + newDate + '" class="chkdate">' + newDate + '</li>';
                        }
                    }
                    html += '<li id="chat_list_' + $i.me_id + '" class="' + cls + '">';
                    if ($i.me_send_mb_id != send_mb_id) {
                        html += '<span class="p_icon">' + p_icon + '</span>';
                        html += '<span class="p_nick">' + p_nick + '</span>';
                    }
                    html += '<div class="' + cls_box + '">';
                    html += nl2br($i.me_memo);
                    html += '</div>';
                    html += '<div class="chat_time2_' + cls + '">' + $i.me_send_datetime.substr(10, 6) + '</div>';
                    if ($i.me_send_mb_id == send_mb_id) {
                         html += '<div class="del_div"><a href="#" class="chatroom-cnt2 badge3" data-me_id="'+$i.me_id+'" data-del_id="'+recv_mb_id+'"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="feather feather-trash w-4 h-4 mr-2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></a></div>';
                    }
                    html += '</li>';
                    chkDate = newDate;
                });
                $('#chat-list').prepend(html);
                from_record = data.from_record;
                if (from_record == 0) {
                    $('#chat_history_div_wrap').hide();
                }
                movePos();
                refreshFsLightbox();
            } else {
                console.log("No chat history data received.");
            }
        },
        error: function(xhr, status, error) {
            console.log("AJAX error:", status, error);
        }
    });
    return false;
})
    
<?php if(!IS_MOBILE()) { ?>
$(function() {
    $('#chat-message').on('keydown', function(event) {
        if (event.keyCode == 13 && !event.shiftKey) {
            event.preventDefault();
            $('#btn-chat').trigger('click');
        }
    });
});
<?php } ?>

function movePos(){
    var pos = pos_id;
    document.getElementById('chat_list_'+pos).scrollIntoView();
}
    
function move_page() {
    $('#chat-body').animate({scrollTop: $('#chat-body')[0].scrollHeight}, 0);
}
    
window.onload = function() { 
    move_page();
};

function check_Read() {
    var sender = "<?php echo isset($recv_mb['mb_id']) ? htmlspecialchars($recv_mb['mb_id']) : ''; ?>";
    var receiver = "<?php echo htmlspecialchars($member['mb_id']); ?>";
    var fromRecord = "<?php echo htmlspecialchars($from_record); ?>";

    if (sender && receiver) {
        $.post("<?php echo G5_URL; ?>/rb/rb.mod/chat/core/ajax.chat_read.php", 
            {
                sender: sender, 
                receiver: receiver, 
                from_record: fromRecord
            }, 
            function(result) {
                var dd = result.split("--");
                for (var i = 0; i < dd.length; i++) {
                    var rd = dd[i].split("|");
                    if (rd[1] == "Y") {
                        $("#am" + rd[0]).html("");
                        $("#bm" + rd[0]).html("");
                    }
                }
            }
        );
    }
}


var defaultPollingInterval = <?php echo json_encode($chat_set['ch_ref_1']); ?> * 1000; // 기본 폴링 간격
var activePollingInterval = <?php echo json_encode($chat_set['ch_ref_2']); ?> * 1000; // 활동 중일 때 폴링 간격
var inactivePollingInterval = <?php echo json_encode($chat_set['ch_ref_4']); ?> * 1000; // 폴링 간격을 변경한다.
var inactivityTimeout = <?php echo json_encode($chat_set['ch_ref_3']); ?> * 1000; // N초 동안 아무것도 하지 않으면

var activityTimeout;
var pollingIntervalId;
var typingTimer;
var doneTypingInterval = 500; // 타이핑 후 0.5초 후에 실행

    // 폴링 인터벌 설정 함수
    function setPollingInterval(interval) {
        clearInterval(pollingIntervalId);
        pollingIntervalId = setInterval(function() {
            chat_refresh();
            check_Read();
        }, interval);
    }

    // 폴링 인터벌 초기화
    pollingIntervalId = setInterval(function() {
        chat_refresh();
        check_Read();
    }, defaultPollingInterval);

    // 키 입력 및 포커스 이벤트 처리
    $(document).on('keydown focus', '#chat-message', function() {
        setPollingInterval(activePollingInterval); // 활동 중일 때 폴링 간격
        clearTimeout(activityTimeout);
        activityTimeout = setTimeout(function() {
            setPollingInterval(inactivePollingInterval); // 폴링 간격을 변경한다.
        }, inactivityTimeout); // N초 동안 아무것도 하지 않으면
    });

    // 디바운싱 추가
    $('#chat-message').on('keyup', function () {
        clearTimeout(typingTimer);
        typingTimer = setTimeout(doneTyping, doneTypingInterval);
    });

    $('#chat-message').on('keydown', function () {
        clearTimeout(typingTimer);
    });



    function doneTyping() {
        // 타이핑이 끝난 후 실행할 코드
        // 예: console.log('타이핑이 끝났습니다.');
    }

</script>


<script src="<?php echo G5_URL ?>/rb/rb.mod/chat/chat_lightbox.js"></script>