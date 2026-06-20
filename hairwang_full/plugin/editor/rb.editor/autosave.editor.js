var AUTOSAVE_INTERVAL = 600000;

function autosave() {
    //console.log("자동 저장 실행");

    $("form#fwrite").each(function () {
        let contentToSave = "";
        const formElement = this;  // 현재 폼 요소를 formElement로 지정
        const uid = $(formElement).find("input[name='uid']").val(); // ✅ uid 가져오기

        if (typeof g5_editor !== "undefined") {
            if (g5_editor.indexOf("ckeditor4") !== -1 && typeof CKEDITOR.instances.wr_content !== "undefined") {
                contentToSave = CKEDITOR.instances.wr_content.getData();
            } else if (g5_editor.indexOf("cheditor5") !== -1 && typeof ed_wr_content !== "undefined") {
                contentToSave = ed_wr_content.outputBodyHTML();
            } else if (g5_editor.indexOf("rb.editor") !== -1) {
                //console.log("RB 에디터 감지됨");

                const editorIframe = document.querySelector("iframe[data-editor-id]");
                if (editorIframe) {
                    //console.log("iframe 메시지 전송 시작");

                    // RB 에디터에서 데이터 요청
                    editorIframe.contentWindow.postMessage({
                        type: "rbeditor-get-content"
                    }, "*");

                    // 메시지 이벤트 리스너 추가 (한 번만 실행되도록 변경)
                    const messageHandler = function (event) {
                        if (event.data.type === "rbeditor-content") {
                            //console.log("RB 에디터에서 데이터 응답 받음", event.data);
                            contentToSave = event.data.content;

                            saveContentToServer(formElement, contentToSave); // formElement를 올바르게 전달
                            window.removeEventListener("message", messageHandler);
                        }
                    };

                    // 기존 중복 등록 방지 후 추가
                    window.removeEventListener("message", messageHandler);
                    window.addEventListener("message", messageHandler);
                } else {
                    console.error("에디터를 찾을 수 없습니다.");
                }
            }
        }

        // ✅ 기존 CKEditor, cheditor 데이터 저장
        if (contentToSave) {
            saveContentToServer(formElement, contentToSave); // ✅ formElement를 올바르게 전달
        }
    });
}

function saveContentToServer(formElement, content) {
    const uid = $(formElement).find("input[name='uid']").val(); // uid 값을 가져옴
    const subject = $(formElement).find("input[name='wr_subject']").val(); // 제목 가져오기

    //if (save_wr_subject !== subject || save_wr_content !== content) { 클릭 > 저장 기존데이터 덮어쓰지 않음
        $.ajax({
            url: g5_bbs_url + "/ajax.autosave.php",
            data: {
                "uid": uid,  // 아이프레임 부모에서 uid 값을 올바르게 가져옴
                "subject": subject,
                "content": content
            },
            type: "POST",
            success: function (data) {
                if (data) {
                    $("#autosave_count").html(data);
                    //console.log("자동저장 완료", data);
                    alert('임시저장이 완료 되었습니다.');
                } else { 
                    alert('저장될 내용과 이전 내용이 동일합니다.\n최초 저장시 제목을 입력해주셔야 합니다.');
                }
            },
            error: function (xhr, status, error) {
                console.error("AJAX 오류:", error); // 에러 로그 추가
            }
        });

        save_wr_subject = subject;
        save_wr_content = content;
    //}
}


// 아이프레임 내부 버튼클릭, 정보받기
window.addEventListener("message", function (event) {
    if (event.data.type === "autosave-trigger") {
        //console.log("RB 에디터에서 autosave-trigger 요청 수신 → autosave 실행");
        autosave();
    }
    
    if (event.data.type === "trigger-autosave-popup") {
        //console.log("팝업 실행");
        $("#btn_autosave").click(); // 기존 버튼 클릭 이벤트 실행
    }
});


$(document).on('click', '.autosave_load', function () {
    var $li = $(this).parents('li');
    var as_id = $li.data('as_id');
    var as_uid = $li.data('uid');

    $('#fwrite input[name=\"uid\"]').val(as_uid);

    $.get(g5_bbs_url + '/ajax.autosaveload.php', {
        'as_id': as_id
    }, function (data) {
        var subject = $(data).find('item').find('subject').text();
        var content = $(data).find('item').find('content').text();

        $('#wr_subject').val(subject);

        // RB 에디터가 존재하는 경우 iframe으로 데이터 전달
        const editorIframe = document.querySelector('iframe[data-editor-id]');
        if (editorIframe) {
            editorIframe.contentWindow.postMessage({
                type: 'rbeditor-insert-content',
                content: content
            }, '*');
        } else {
            console.error('에디터를 찾을 수 없습니다.');
        }
    }, 'xml');

    $('#autosave_pop').hide();
});
