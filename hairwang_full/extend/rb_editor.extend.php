<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

if(isset($config['cf_editor']) && $config['cf_editor'] == "rb.editor") { 
    
$rb_editor_url = G5_EDITOR_URL.'/'.$config['cf_editor'];
add_stylesheet('<link rel="stylesheet" href="'.G5_EDITOR_URL.'/'.$config['cf_editor'].'/css/inc.skin.css">', 0);
add_stylesheet('<link rel="stylesheet" href="'.G5_EDITOR_URL.'/'.$config['cf_editor'].'/css/inc.font.css">', 0);
add_javascript('<script src="'.G5_EDITOR_URL.'/'.$config['cf_editor'].'/js/skin.js"></script>', 1);

    //수정시 이미지 스타일 손실을 막기위함
    add_event('html_purifier_config', function($config) {
        $add_html_tag = $config->getHTMLDefinition(true);

        //$add_html_tag -> addElement('img', 'Inline', 'Empty', 'Common', ['src' => 'URI', 'alt' => 'Text','style' => 'Text']);
        $add_html_tag -> addElement('label', 'Block', 'Flow', 'Common', ['style' => 'Text', 'id' => 'Text']);
        $add_html_tag -> addElement('img', 'Block', 'Flow', 'Common', ['style' => 'Text']);
        $add_html_tag -> addElement('button', 'Block', 'Flow', 'Common', ['style' => 'Text']);
        $add_html_tag -> addElement('ul', 'Block', 'Flow', 'Common', ['style' => 'Text']);
        $add_html_tag -> addElement('mark', 'Block', 'Flow', 'Common', ['style' => 'Text']);
        $add_html_tag -> addElement('li', 'Block', 'Flow', 'Common', ['style' => 'Text']);
        $add_html_tag -> addElement('div', 'Block', 'Flow', 'Common', ['style' => 'Text', 'data-original-width' => 'Text', 'data-original-height' => 'Text']);

    }, 1, 1);
    

    // get_view_thumbnail() 함수를 수정하지 않기위해 원본 내용을 저장함
    // 각 스킨에 $original_content = isset($view['content']) ? $view['content'] : ''; 를 추가하고
    // 아래 add_replace 로 복원함
    add_replace('get_view_thumbnail', function($contents) {
        global $original_content;

        return preg_replace_callback(
            '/(<a[^>]+href=["\'][^"\']+view_image\.php\?fn=([^"\']+)["\'][^>]*>)?<img([^>]*)src="([^"]+thumb-[^"]+)"([^>]*)>(<\/a>)?/i', 
            function($matches) use ($original_content) {

            $a_open = isset($matches[1]) ? $matches[1] : '';
            $encoded_original_src = isset($matches[2]) ? urldecode($matches[2]) : '';
            $img_attrs_before = trim($matches[3]);
            $thumbnail_src = $matches[4];
            $img_attrs_after = trim($matches[5]);
            $a_close = isset($matches[6]) ? $matches[6] : '';

            // 썸네일에서 원본이미지 유추 (만약 a태그가 있다면 여기서 추출한 URL을 우선 사용)
            if ($encoded_original_src) {
                $original_src = $encoded_original_src;
            } else {
                $original_src = preg_replace('/\/thumb-/', '/', $thumbnail_src);
                $original_src = preg_replace('/_(\d+)x(\d+)(\.\w+)$/i', '$3', $original_src);
                $original_src = preg_replace('/^https?:\/\/[^\/]+/', '', $original_src);
            }

            // 원본 컨텐츠에서 정확한 이미지 스타일 추출
            $original_style = "";
            $escaped_original_src = preg_quote($original_src, '/');
            if (preg_match('/<img[^>]*src=["\'][^"\']*'.$escaped_original_src.'["\'][^>]*style=["\']([^"\']+)["\']/i', $original_content, $m)) {
                $original_style = $m[1];
            }

            $style_attr = $original_style ? ' style="'.$original_style.'"' : '';

            $new_img_tag = "<img src=\"{$thumbnail_src}\"{$style_attr} {$img_attrs_before} {$img_attrs_after}>";

            return $a_open . $new_img_tag . $a_close;

        }, $contents);
    });
}