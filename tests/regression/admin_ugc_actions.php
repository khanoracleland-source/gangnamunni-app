<?php
/** Run in local Docker: docker exec -i hms-saas-gangnamunni-1 php < tests/regression/admin_ugc_actions.php
 * All writes are to connection-local TEMPORARY tables; no real records are changed.
 */
ini_set('zend.exception_ignore_args','1');
$root = is_dir('/var/www/html/hairwang_full') ? '/var/www/html' : dirname(__DIR__,2);
require $root.'/hairwang_full/gangnamunni/dbconfig.php';
require $root.'/hairwang_full/gangnamunni/includes/admin_ugc_actions.php';
if (!in_array($_DB_HOST,['mysql','localhost','127.0.0.1'],true)) exit('Local DB only');
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$db=new mysqli($_DB_HOST,$_DB_USER,$_DB_PASSWORD,$_DB_NAME,(int)$_DB_PORT);
$db->set_charset('utf8mb4');
$_SESSION=['ad_admin_role'=>'admin','ss_mb_id'=>'moderator'];
function check($value,$message){if(!$value)throw new RuntimeException($message);}
$schemas=[
'g5_member'=>"mb_no INT PRIMARY KEY,mb_id VARCHAR(100),mb_email VARCHAR(100),mb_nick VARCHAR(100),mb_level INT,mb_intercept_date VARCHAR(8),mb_memo TEXT",
'gangnamunni_admins'=>"id INT PRIMARY KEY,username VARCHAR(100),email VARCHAR(100),is_active INT",
'gangnamunni_admin_restrictions'=>"mb_id VARCHAR(100) PRIMARY KEY,previous_intercept VARCHAR(8),applied_intercept VARCHAR(8),restriction_type VARCHAR(20),admin_id INT",
'gangnamunni_blocked_users'=>"id INT AUTO_INCREMENT PRIMARY KEY,blocker_id VARCHAR(100),blocked_id VARCHAR(100),blocked_name VARCHAR(200),reason VARCHAR(500)",
'gangnamunni_content_reports'=>"id INT PRIMARY KEY,content_type VARCHAR(20),content_id INT,status VARCHAR(20),action_taken VARCHAR(30),action_note TEXT,admin_id INT,resolved_at DATETIME,reported_user_id INT,reported_user_name VARCHAR(100)",
'gangnamunni_community_posts'=>"id INT PRIMARY KEY,mb_id VARCHAR(100),status VARCHAR(20)",
'gangnamunni_community_comments'=>"id INT PRIMARY KEY,mb_id VARCHAR(100),content TEXT",
'hospital_reviews'=>"id INT PRIMARY KEY,user_id VARCHAR(100),is_active INT",
'gangnamunni_qna'=>"id INT PRIMARY KEY,member_id VARCHAR(100),is_public INT",
'gangnamunni_chat_messages'=>"id INT PRIMARY KEY,sender_id INT,sender_type VARCHAR(20),message TEXT",
'gangnamunni_tips'=>"id INT PRIMARY KEY,is_active INT",
'gangnamunni_events'=>"id INT PRIMARY KEY,is_active INT"
];
foreach($schemas as $table=>$schema)$db->query("CREATE TEMPORARY TABLE $table ($schema) ENGINE=InnoDB");
$db->query("INSERT INTO g5_member VALUES (1,'writer','writer@example.test','Writer',2,'',''),(2,'protected','admin@example.test','Admin',10,'',''),(3,'previous','previous@example.test','Previous',2,'20200101','')");
$db->query("INSERT INTO gangnamunni_community_posts VALUES(1,'writer','active')");
$db->query("INSERT INTO gangnamunni_community_comments VALUES(1,'writer','original')");
$db->query("INSERT INTO hospital_reviews VALUES(1,'writer',1)");
$db->query("INSERT INTO gangnamunni_qna VALUES(1,'writer',1)");
$db->query("INSERT INTO gangnamunni_chat_messages VALUES(1,1,'patient','original')");
$db->query("INSERT INTO gangnamunni_tips VALUES(1,1)");
$db->query("INSERT INTO gangnamunni_events VALUES(1,1)");
function report($db,$id,$type='community',$content=1){ugc_query($db,"INSERT INTO gangnamunni_content_reports(id,content_type,content_id,status) VALUES(?,?,?,'pending')",'isi',[$id,$type,$content]);}
report($db,1);$db->begin_transaction();ugc_resolve($db,1,'warning','QA warning',9);$db->commit();
check(str_contains($db->query("SELECT mb_memo FROM g5_member WHERE mb_no=1")->fetch_row()[0],'QA warning'),'Warning was not recorded');
check($db->query('SELECT reported_user_id FROM gangnamunni_content_reports WHERE id=1')->fetch_row()[0]==1,'Author resolution failed');
report($db,2);$db->begin_transaction();ugc_resolve($db,2,'user_suspended','QA suspension',9);$db->commit();
check($db->query("SELECT mb_intercept_date FROM g5_member WHERE mb_no=1")->fetch_row()[0]===date('Ymd'),'Suspension not enforced');
$id=(int)$db->query("SELECT id FROM gangnamunni_blocked_users WHERE blocked_id='writer'")->fetch_row()[0];
$db->begin_transaction();ugc_unblock($db,$id);$db->commit();
check($db->query("SELECT mb_intercept_date FROM g5_member WHERE mb_no=1")->fetch_row()[0]==='','Unblock did not restore account');
$db->begin_transaction();ugc_restrict($db,'previous','user_banned','QA',9);$id=(int)$db->query("SELECT id FROM gangnamunni_blocked_users WHERE blocked_id='previous'")->fetch_row()[0];ugc_unblock($db,$id);$db->commit();
check($db->query("SELECT mb_intercept_date FROM g5_member WHERE mb_no=3")->fetch_row()[0]==='20200101','Existing restriction lost');
foreach(['protected','missing'] as $user){$db->begin_transaction();try{ugc_restrict($db,$user,'user_banned','QA',9);throw new RuntimeException('Invalid target accepted');}catch(DomainException $e){$db->rollback();}}
report($db,3);report($db,4,'community',999);
$db->begin_transaction();try{ugc_resolve($db,3,'content_removed','',9);ugc_resolve($db,4,'content_removed','',9);throw new RuntimeException('Missing content accepted');}catch(DomainException $e){$db->rollback();}
check($db->query('SELECT status FROM gangnamunni_content_reports WHERE id=3')->fetch_row()[0]==='pending','Bulk partial commit');
check($db->query('SELECT status FROM gangnamunni_community_posts WHERE id=1')->fetch_row()[0]==='active','Content rollback failed');
$db->begin_transaction();ugc_resolve($db,4,'dismiss','',9);$db->commit();
check($db->query('SELECT status FROM gangnamunni_content_reports WHERE id=4')->fetch_row()[0]==='dismissed','Dismiss failed');
foreach (['community','comment','review','qna','chat'] as $type) check(ugc_author($db,$type,ugc_content($db,$type,1))['mb_id']==='writer','Author ID mismatch: '.$type);
$i=10;foreach(['community','comment','review','qna','chat','tip','event'] as $type){report($db,$i,$type);$db->begin_transaction();ugc_resolve($db,$i++,'content_removed','QA removal',9);$db->commit();}
check($db->query('SELECT status FROM gangnamunni_community_posts')->fetch_row()[0]==='deleted','Community not removed');
check(str_contains($db->query('SELECT content FROM gangnamunni_community_comments')->fetch_row()[0],'관리자'),'Comment not removed');
foreach(['hospital_reviews','gangnamunni_tips','gangnamunni_events'] as $t)check((int)$db->query("SELECT is_active FROM $t")->fetch_row()[0]===0,$t.' still active');
check((int)$db->query('SELECT is_public FROM gangnamunni_qna')->fetch_row()[0]===0,'Q&A public');
check(str_contains($db->query('SELECT message FROM gangnamunni_chat_messages')->fetch_row()[0],'관리자'),'Chat not removed');
try{ugc_resolve($db,1,'warning','again',9);throw new RuntimeException('Resolved report accepted twice');}catch(DomainException $e){}
report($db,30);
$db->begin_transaction();ugc_resolve($db,30,'user_banned','ban check',9);$db->commit();
check($db->query("SELECT restriction_type FROM gangnamunni_admin_restrictions WHERE mb_id='writer'")->fetch_row()[0]==='user_banned','Report ban missing');
ugc_query($db,"INSERT INTO gangnamunni_blocked_users(blocker_id,blocked_id) VALUES('other','writer')");
$userBlock=$db->insert_id;
$db->begin_transaction();ugc_unblock($db,$userBlock);$db->commit();
check($db->query("SELECT mb_intercept_date FROM g5_member WHERE mb_id='writer'")->fetch_row()[0]===date('Ymd'),'Removing user block lifted admin restriction');
$db->query("INSERT INTO gangnamunni_admins VALUES(99,'previous','previous@example.test',1)");
$db->begin_transaction();try{ugc_restrict($db,'previous','user_banned','QA',9);throw new RuntimeException('Platform admin accepted');}catch(DomainException $e){$db->rollback();}
echo "PASS: warning, suspension, unblock, previous restriction preservation, protected/missing targets, atomic bulk rollback, dismissal, seven content removals, duplicate resolution.\n";
