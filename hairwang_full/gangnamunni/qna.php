<?php
require_once __DIR__ . '/includes/init.php';

// 테이블 자동 생성
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_qna (
    id INT AUTO_INCREMENT PRIMARY KEY, member_id VARCHAR(100), user_name VARCHAR(100),
    hospital_id INT DEFAULT NULL, title VARCHAR(300) NOT NULL, content TEXT NOT NULL,
    answer TEXT DEFAULT NULL, answered_by VARCHAR(100) DEFAULT NULL,
    answered_at DATETIME DEFAULT NULL, is_public TINYINT(1) DEFAULT 1,
    view_count INT DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20; $offset = ($page-1)*$limit;
$total = 0;
$res = mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_qna WHERE is_public=1");
if ($res) $total = mysqli_fetch_assoc($res)['c'];

$questions = [];
$res2 = mysqli_query($connect, "SELECT q.*, h.name as hospital_name FROM gangnamunni_qna q LEFT JOIN gangnamunni_hospitals h ON q.hospital_id=h.id WHERE q.is_public=1 ORDER BY q.created_at DESC LIMIT $limit OFFSET $offset");
if ($res2) while ($r = mysqli_fetch_assoc($res2)) $questions[] = $r;
?>
<!DOCTYPE html><html lang="ko" data-bs-theme="light"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<title>Q&A - 서울미녀</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>(function(){var t=localStorage.getItem('theme');if(t==='dark')document.documentElement.setAttribute('data-bs-theme','dark');})();</script>
<style>
:root{--pri:#AE1932;}*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;max-width:640px;margin:0 auto;background:var(--bs-body-bg,#f8f9fa);color:var(--bs-body-color);}
.header{padding:14px 16px;display:flex;align-items:center;gap:12px;border-bottom:1px solid var(--bs-border-color,#eee);background:var(--bs-body-bg,#fff);}
.header .back{width:36px;height:36px;border:none;background:none;font-size:calc(20px * var(--fs-scale,1) * var(--fs-page,1));cursor:pointer;color:inherit;}
.header h1{font-size:calc(17px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;flex:1;}
.qa-item{padding:14px 16px;border-bottom:1px solid var(--bs-border-color,#eee);background:var(--bs-body-bg,#fff);}
.qa-badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:calc(10px * var(--fs-scale,1) * var(--fs-page,1));font-weight:600;}
.qa-badge.answered{background:#dcfce7;color:#16a34a;}
.qa-badge.waiting{background:#fef3c7;color:#d97706;}
.qa-title{font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1));font-weight:600;margin:6px 0 4px;}
.qa-meta{font-size:calc(11px * var(--fs-scale,1) * var(--fs-page,1));color:var(--bs-secondary-color,#888);}
.qa-answer{margin-top:8px;padding:10px 12px;background:var(--bs-tertiary-bg,#f5f5f5);border-radius:8px;font-size:calc(13px * var(--fs-scale,1) * var(--fs-page,1));line-height:1.6;}
.empty{text-align:center;padding:60px 20px;color:var(--bs-secondary-color);}
</style><?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head><body>
<div class="header"><button class="back" onclick="history.back()"><i class="bi bi-chevron-left"></i></button><h1>Q&A</h1></div>
<?php if (empty($questions)): ?>
<div class="empty"><i class="bi bi-question-circle" style="font-size:48px;display:block;margin-bottom:12px;"></i>등록된 질문이 없습니다</div>
<?php else: foreach ($questions as $q): ?>
<div class="qa-item">
    <span class="qa-badge <?php echo $q['answer'] ? 'answered' : 'waiting'; ?>"><?php echo $q['answer'] ? '답변완료' : '대기중'; ?></span>
    <div class="qa-title"><?php echo htmlspecialchars($q['title']); ?></div>
    <div class="qa-meta"><?php echo htmlspecialchars($q['user_name'] ?? '익명'); ?> · <?php echo $q['created_at']; ?> · 조회 <?php echo $q['view_count']; ?></div>
    <div style="font-size:13px;margin-top:6px;color:var(--bs-body-color);line-height:1.6;"><?php echo nl2br(htmlspecialchars(mb_substr($q['content'],0,200))); ?></div>
    <?php if ($q['answer']): ?><div class="qa-answer"><strong style="color:var(--pri);">A.</strong> <?php echo nl2br(htmlspecialchars($q['answer'])); ?></div><?php endif; ?>
</div>
<?php endforeach; endif; ?>
<?php include_once __DIR__ . '/includes/bottom_nav.php'; ?>
</body></html>
