        </div><!-- .admin-content -->
    </div><!-- .admin-layout -->

<script>
// ============================================
// 페이지별 디버그 정보 콘솔 출력
// ============================================
<?php if (isset($GLOBALS['page_debug'])): ?>
console.log('%c========================================', 'color: #00bcd4; font-weight: bold; font-size: 16px;');
console.log('%c📄 <?php echo isset($g5['title']) ? $g5['title'] : '페이지'; ?> 디버그 정보', 'color: #00bcd4; font-weight: bold; font-size: 14px;');
console.log('%c========================================', 'color: #00bcd4; font-weight: bold; font-size: 16px;');

var pageDebug = <?php echo json_encode($GLOBALS['page_debug'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?>;
console.log('%c📌 페이지 정보:', 'color: #00bcd4; font-weight: bold;');
console.table(pageDebug);

<?php if (isset($GLOBALS['page_debug']['errors']) && !empty($GLOBALS['page_debug']['errors'])): ?>
console.error('%c❌ 에러 발생:', 'color: #f44336; font-weight: bold;');
console.table(<?php echo json_encode($GLOBALS['page_debug']['errors'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?>);
<?php endif; ?>

<?php if (isset($GLOBALS['page_debug']['sql_query'])): ?>
console.log('%c🔍 실행된 쿼리:', 'color: #4caf50; font-weight: bold;');
console.log(<?php echo json_encode($GLOBALS['page_debug']['sql_query'], JSON_UNESCAPED_UNICODE); ?>);
<?php endif; ?>

console.log('%c========================================', 'color: #00bcd4; font-weight: bold; font-size: 16px;');
<?php endif; ?>
</script>
</body>
</html>
