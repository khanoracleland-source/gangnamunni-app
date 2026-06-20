        <!-- 페이지 컨텐츠 끝 -->

        <?php
        // 페이지별 추가 스크립트 로드
        if (isset($page_scripts) && is_array($page_scripts)) {
            foreach ($page_scripts as $script_file) {
                echo '<script src="' . htmlspecialchars($script_file) . '"></script>' . "\n";
            }
        }
        ?>
    </div>
</body>
</html>
