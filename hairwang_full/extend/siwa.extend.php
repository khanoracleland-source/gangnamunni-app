<?php
/***
 * SIWA (Sign in with Apple 구현 관련 코드)
 * @author 야금야금
 *
 * 본 파일은 그누보드용으로 제작 된 파일입니다.
 * 빌더 사용시 본 파일이 적용되지 않을 수 있습니다.
 * 최대한 PHP 5.x 호환성을 고려하였습니다.
*/
if (!defined('_GNUBOARD_')) exit;

function siwa_stylesheet()
{
    if (!isset($_SERVER['SCRIPT_NAME'])) 
        $_SERVER['SCRIPT_NAME'] = __FILE__;

    $currentFile = $_SERVER['SCRIPT_NAME'];
    $executeFile = array(
        '/adm/member_form.php',
        '/adm/member_list.php'
    );

    foreach ($executeFile as $fileName) 
        if (false !== strpos($currentFile, $fileName))
            add_stylesheet('<link rel="stylesheet" href="' . G5_ADMIN_URL . '/css/siwa.css">', 0);

}

siwa_stylesheet();