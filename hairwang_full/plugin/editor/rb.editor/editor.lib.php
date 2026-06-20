<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

function editor_html($id, $content, $is_dhtml_editor = true)
{
    global $g5, $config;
    static $js = true;

    $editor_url = G5_EDITOR_URL.'/'.$config['cf_editor'];

    // ✅ Nonce 생성 및 세션에 저장
    if (!isset($_SESSION['token_' . FT_NONCE_SESSION_KEY])) {
        $_SESSION['token_' . FT_NONCE_SESSION_KEY] = ft_nonce_create('rbeditor');
    }
    $nonce = $_SESSION['token_' . FT_NONCE_SESSION_KEY];

    $html = "<span class=\"sound_only\">웹에디터 시작</span>";

    if ($is_dhtml_editor) {
        if ($js) {
            $js = false;
        }

        $html .= "<script>
            var g5_editor_url = '{$editor_url}';
            var ed_nonce = '{$nonce}';

            window.addEventListener('message', function(event) {
                if (event.data.type === 'request-nonce') {
                    event.source.postMessage({ type: 'rbeditor-nonce', nonce: ed_nonce }, '*');
                }
                if (event.data.type === 'rbeditor-ready') {
                    const editorId = event.data.editorId;
                    const iframe = document.getElementById('rb-editor-frame-' + editorId);
                    const hiddenInput = document.getElementById('rb-' + editorId + '-hidden');
                    if (iframe && hiddenInput) {
                        setTimeout(() => {
                            iframe.contentWindow.postMessage({
                                type: 'rbeditor-set-content',
                                content: hiddenInput.value,
                                editorId: editorId
                            }, '*');
                        }, 100);
                    }
                }
            });

            document.addEventListener('DOMContentLoaded', function() {
                document.querySelectorAll('.rb-editor-frames').forEach((iframe) => {
                    iframe.addEventListener('load', function() {
                        var messageData = {
                            type: 'rbeditor-config',
                            g5_editor_url: g5_editor_url || '',
                            g5_bo_table: typeof g5_bo_table !== 'undefined' ? g5_bo_table : '',
                            g5_is_member: typeof g5_is_member !== 'undefined' ? g5_is_member : '',
                            g5_is_mobile: typeof g5_is_mobile !== 'undefined' ? g5_is_mobile : '',
                            g5_url: typeof g5_url !== 'undefined' ? g5_url : '',
                            g5_bbs_url: typeof g5_bbs_url !== 'undefined' ? g5_bbs_url : '',
                            g5_editor: typeof g5_editor !== 'undefined' ? g5_editor : '',
                            g5_is_admin: typeof g5_is_admin !== 'undefined' ? g5_is_admin : ''
                        };
                        if (typeof g5_admin_url !== 'undefined') {
                            messageData.g5_admin_url = g5_admin_url;
                        }
                        iframe.contentWindow.postMessage(messageData, '*');
                    });
                });

                
            });
        </script>";

        $html .= "
            <iframe id='rb-editor-frame-{$id}' src='{$editor_url}/rb.editor.html?editorId={$id}' data-editor-id='{$id}' class='rb-editor-frames' width='100%' height='670px' frameborder='0'></iframe>
            <input type='hidden' name='{$id}' id='rb-{$id}-hidden' value='".htmlspecialchars($content, ENT_QUOTES, 'UTF-8')."'>";

        $html .= "<script>
        window.addEventListener('message', function(event) {
            if (event.data.type === 'rbeditor-content') {
                const editorId = event.data.editorId;
                const hiddenInput = document.getElementById('rb-' + editorId + '-hidden');

                if (hiddenInput) {
                    let content = event.data.content;
                    const iframe = document.getElementById('rb-editor-frame-' + editorId);
                    if (!iframe) {
                        hiddenInput.value = ensureEditorDataWrapper(content);
                        return;
                    }
                    try {
                        const editorElem = iframe.contentWindow.document.querySelector('#editor');
                        let editorStyle = editorElem ? editorElem.style.cssText.trim() : '';
                        content = ensureEditorDataWrapper(content, editorStyle);
                        hiddenInput.value = content;
                    } catch (e) {
                        hiddenInput.value = ensureEditorDataWrapper(content);
                    }
                }
            }
        });

        function ensureEditorDataWrapper(content, editorStyle = '') {
            let parser = new DOMParser();
            let doc = parser.parseFromString(content, 'text/html');

            // 내용이 텍스트도 없이 완전히 비어있거나 빈 div만 존재하면 빈 값 리턴
            if (!doc.body.textContent.trim() && !doc.body.querySelector('img, video, iframe')) {
                return '';
            }

            let existingEditorData = doc.querySelector('.rb_editor_data');

            if (!existingEditorData) {
                let wrapper = doc.createElement('div');
                wrapper.className = 'rb_editor_data';
                if (editorStyle) wrapper.style.cssText = editorStyle;
                wrapper.innerHTML = doc.body.innerHTML.trim();
                doc.body.innerHTML = '';
                doc.body.appendChild(wrapper);
            } else {
                let allElements = [...doc.body.children];
                allElements.forEach(el => {
                    if (!el.classList.contains('rb_editor_data')) {
                        existingEditorData.appendChild(el);
                    }
                });
                if (editorStyle) {
                    existingEditorData.style.cssText = editorStyle;
                }
            }


            return doc.body.innerHTML;
        }
        </script>";
    } else {
        $html .= "<textarea id='$id' name='$id' style='width:100%;height:300px;'>$content</textarea>\n";
    }

    $html .= "<span class=\"sound_only\">웹 에디터 끝</span>";
    return $html;
}

// 에디터 내용 저장 (TEXTAREA → DIV 대응)
function get_editor_js($id, $is_dhtml_editor = true)
{
    if ($is_dhtml_editor) {
        return "
            const iframe_{$id} = document.getElementById('rb-editor-frame-{$id}').querySelector('iframe');
            if (iframe_{$id} && iframe_{$id}.contentWindow) {
                iframe_{$id}.contentWindow.postMessage({
                    type: 'rbeditor-get-content',
                    editorId: '{$id}'
                }, '*');
            }
        ";
    } else {
        return "var {$id}_editor = document.getElementById('{$id}');\n";
    }
}

// 에디터 값이 비어 있는지 검사 (TEXTAREA → DIV 대응)
function chk_editor_js($id, $is_dhtml_editor = true)
{
    if ($is_dhtml_editor) {
        return "
            var content = document.getElementById('rb-{$id}-hidden').value;
            if (!content || content.trim() === '') {
                alert('내용을 입력해 주십시오.');
                return false;
            }
        ";
    } else {
        return "if (!{$id}_editor.value) { alert(\"내용을 입력해 주십시오.\"); {$id}_editor.focus(); return false; }\n";
    }
}

// Nonce 관련 상수 및 함수 정의
if (!defined('FT_NONCE_UNIQUE_KEY'))
    define('FT_NONCE_UNIQUE_KEY', sha1($_SERVER['SERVER_SOFTWARE'] . G5_MYSQL_USER . session_id() . G5_TABLE_PREFIX));

if (!defined('FT_NONCE_SESSION_KEY'))
    define('FT_NONCE_SESSION_KEY', substr(md5(FT_NONCE_UNIQUE_KEY), 5));

if (!defined('FT_NONCE_DURATION'))
    define('FT_NONCE_DURATION', 60 * 60);

if (!defined('FT_NONCE_KEY'))
    define('FT_NONCE_KEY', '_nonce');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function ft_nonce_create($action = '', $user = '', $timeoutSeconds = FT_NONCE_DURATION)
{
    $secret = ft_get_secret_key($action . $user);
    set_session('token_' . FT_NONCE_SESSION_KEY, $secret);

    $salt = ft_nonce_generate_hash();
    $time = time();
    $maxTime = $time + $timeoutSeconds;
    $nonce = $salt . '|' . $maxTime . '|' . sha1($salt . $secret . $maxTime);

    return $nonce;
}

function ft_nonce_is_valid($nonce, $action = '', $user = '')
{
    $secret = ft_get_secret_key($action.$user);
    $token = get_session('token_'.FT_NONCE_SESSION_KEY);

    if ($secret != $token) return false;
    if (!is_string($nonce)) return false;

    $a = explode('|', $nonce);
    if (count($a) != 3) return false;

    $salt = $a[0];
    $maxTime = intval($a[1]);
    $hash = $a[2];
    $back = sha1($salt . $secret . $maxTime);

    if ($back != $hash || time() > $maxTime) return false;

    return true;
}

function ft_get_secret_key($secret)
{
    return md5(FT_NONCE_UNIQUE_KEY . $secret);
}

function ft_nonce_generate_hash()
{
    $length = 10;
    $chars = '1234567890qwertyuiopasdfghjklzxcvbnmQWERTYUIOPASDFGHJKLZXCVBNM';
    $ll = strlen($chars) - 1;
    $o = '';

    while (strlen($o) < $length) {
        $o .= $chars[rand(0, $ll)];
    }
    return $o;
}