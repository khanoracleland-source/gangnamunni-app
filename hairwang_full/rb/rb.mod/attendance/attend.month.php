<?php
include_once(dirname(__FILE__).'/../../../common.php');
include_once(G5_PATH.'/rb/rb.mod/attendance/attend.lib.php');
header('Content-Type: application/json; charset=utf-8');
if (!defined('_GNUBOARD_')) exit;

$year  = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
if ($year < 1970) $year = (int)date('Y');
if ($month < 1 || $month > 12) $month = (int)date('n');

$first = DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-01', $year, $month));
$start_w = (int)$first->format('w');
$days = (int)$first->format('t');
$range_from = $first->format('Ymd');
$last = DateTime::createFromFormat('Y-m-d', sprintf('%04d-%02d-%02d', $year, $month, $days));
$range_to = $last->format('Ymd');

$mine = array();
if (isset($is_member) && $is_member) {
    $rs = sql_query("
        SELECT REPLACE(ymd,'-','') AS ymd8
        FROM rb_attendance
        WHERE mb_id='".sql_real_escape_string($member['mb_id'])."'
          AND REPLACE(ymd,'-','') BETWEEN '{$range_from}' AND '{$range_to}'
    ");
    while ($r = sql_fetch_array($rs)) { $mine[$r['ymd8']] = 1; }
}

$today_ymd8 = rb_attend_norm_ymd8(defined('G5_TIME_YMD') ? G5_TIME_YMD : date('Ymd'));

echo json_encode(array(
  'ok'=>1,'year'=>$year,'month'=>$month,'start_w'=>$start_w,'days'=>$days,'today'=>$today_ymd8,'mine'=>$mine
));
