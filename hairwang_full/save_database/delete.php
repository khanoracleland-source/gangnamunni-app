<?php
/**
 * 백업 파일 삭제
 */

if (isset($_GET['file'])) {
    $file = basename($_GET['file']);
    $file_path = 'dbbackup/' . $file;
    
    // 파일 존재 확인
    if (file_exists($file_path)) {
        // 파일 확장자 확인
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        if ($extension === 'sql') {
            // 파일 삭제
            if (unlink($file_path)) {
                header('Location: index.php?deleted=success');
                exit;
            } else {
                header('Location: index.php?deleted=error');
                exit;
            }
        } else {
            header('Location: index.php?deleted=invalid');
            exit;
        }
    } else {
        header('Location: index.php?deleted=notfound');
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}
?>
