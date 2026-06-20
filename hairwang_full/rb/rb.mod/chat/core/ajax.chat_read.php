<?php
include_once('../../../../common.php');

$sender = isset($_POST["sender"]) ? $_POST["sender"] : '';
$receiver = isset($_POST["receiver"]) ? $_POST["receiver"] : '';
$from_record = isset($_POST["from_record"]) ? $_POST["from_record"] : '20';

$escaped_sender = sql_escape_string($sender);
$escaped_receiver = sql_escape_string($receiver);

$sqla = "SELECT me_id, me_read_datetime FROM rb_chat 
         WHERE (me_send_mb_id = '{$escaped_sender}' AND me_recv_mb_id = '{$escaped_receiver}') 
            OR (me_send_mb_id = '{$escaped_receiver}' AND me_recv_mb_id = '{$escaped_sender}') 
         ORDER BY me_send_datetime ASC";

$res = sql_query($sqla);

$resa = array();
while ($row = sql_fetch_array($res)) {
    $ch = (substr($row['me_read_datetime'], 0, 1) != '0') ? "Y" : "N";
    $resa[] = $row["me_id"] . "|" . $ch;
}

echo implode("--", $resa);
?>