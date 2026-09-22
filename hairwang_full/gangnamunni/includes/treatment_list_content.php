        <?php
        // DB에서 대분류 + 중분류 동적 조회
        $treatment_categories = [];
        $tc_sql = "SELECT c.id, c.name AS cat_name, GROUP_CONCAT(m.name ORDER BY m.sort_order ASC, m.popularity_score DESC, m.id ASC SEPARATOR '||') AS treatments
            FROM gangnamunni_treatment_categories c
            LEFT JOIN gangnamunni_treatment_master m ON m.category_id = c.id AND m.is_active = 1
            WHERE c.is_active = 1
            GROUP BY c.id, c.name, c.sort_order
            ORDER BY c.sort_order ASC, c.id ASC";
        $tc_r = mysqli_query($connect, $tc_sql);
        if (!$tc_r) throw new RuntimeException('Treatment list query failed');
        if ($tc_r) {
            while ($tc = mysqli_fetch_assoc($tc_r)) {
                $subs = $tc['treatments'] ? explode('||', $tc['treatments']) : [];
                $treatment_categories[$tc['cat_name']] = $subs;
            }
        }
        ?>
        <div class="tc-table-wrap" style="overflow:hidden; width:100%; margin:0 auto; margin-bottom:15px;">
        <?php if ($tc_table_mode === 'row'): ?>
        <!-- 모드B: 행별 독립 스크롤 -->
        <div class="tc-table tc-mode-row" style="background:var(--bs-body-bg, #fff);border-radius:0;overflow:hidden;border:1px solid #D5D5D5;">
            <?php foreach ($treatment_categories as $major => $subs): ?>
            <div class="tc-row" style="display:flex;border-bottom:1px solid #D5D5D5;">
                <div class="tc-major"><?php echo htmlspecialchars($major); ?></div>
                <div class="tc-subs-wrap">
                    <button class="tc-arrow tc-arrow-l" onclick="tcRowScroll(this,-1)" aria-label="왼쪽">‹</button>
                    <div class="tc-subs">
                        <?php foreach ($subs as $sub): ?>
                        <a href="hospitals.php?treatment=<?php echo urlencode($sub); ?>" class="tc-chip"><?php echo htmlspecialchars($sub); ?></a>
                        <?php endforeach; ?>
                    </div>
                    <button class="tc-arrow tc-arrow-r" onclick="tcRowScroll(this,1)" aria-label="오른쪽">›</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php elseif ($tc_table_mode === 'hybrid'): ?>
        <!-- 모드C: 하이브리드 — 대분류 고정 + 각 행 독립 스크롤 -->
        <div class="tc-table tc-mode-hybrid" style="background:var(--bs-body-bg, #fff);border-radius:0;overflow:hidden;border:1px solid #D5D5D5;">
            <?php foreach ($treatment_categories as $major => $subs): ?>
            <div class="tc-row" style="display:flex;border-bottom:1px solid #D5D5D5;">
                <div class="tc-major tc-major-sticky"><?php echo htmlspecialchars($major); ?></div>
                <div class="tc-subs-wrap">
                    <button class="tc-arrow tc-arrow-l" onclick="tcRowScroll(this,-1)" aria-label="왼쪽">‹</button>
                    <div class="tc-subs">
                        <?php foreach ($subs as $sub): ?>
                        <a href="hospitals.php?treatment=<?php echo urlencode($sub); ?>" class="tc-chip"><?php echo htmlspecialchars($sub); ?></a>
                        <?php endforeach; ?>
                    </div>
                    <button class="tc-arrow tc-arrow-r" onclick="tcRowScroll(this,1)" aria-label="오른쪽">›</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <!-- 패널형: 좌측 1/3 대분류 + 우측 2/3 시술 2열 -->
        <div class="tc-panel-wrap">
            <div class="tc-nav-only" id="tcNavOnly">
                <?php $pi = 0; foreach ($treatment_categories as $major => $subs): ?>
                <div class="tc-nav-item<?php echo $pi === 0 ? ' active' : ''; ?>"
                     onclick="tcNavSelect(this, <?php echo $pi; ?>)"
                ><?php echo htmlspecialchars($major); ?></div>
                <?php $pi++; endforeach; ?>
            </div>
            <div class="tc-panel-right" id="tcPanelRight">
                <?php $pi = 0; foreach ($treatment_categories as $major => $subs): ?>
                <div class="tc-panel-body<?php echo $pi === 0 ? ' tc-panel-body-active' : ''; ?>" id="tcBody<?php echo $pi; ?>">
                    <?php foreach ($subs as $sub): ?>
                    <a href="hospitals.php?treatment=<?php echo urlencode($sub); ?>" class="tc-chip"><?php echo htmlspecialchars($sub); ?></a>
                    <?php endforeach; ?>
                </div>
                <?php $pi++; endforeach; ?>
                <div class="tc-below-divider" id="tcBelowDivider"></div>
                <div class="tc-below-wrap" id="tcBelowWrap">
                    <?php $pi = 0; foreach ($treatment_categories as $major => $subs): ?>
                    <div class="tc-below-group" id="tcBelow<?php echo $pi; ?>" style="display:none">
                        <?php foreach ($subs as $sub): ?>
                        <span class="tc-chip tc-chip-faded"><?php echo htmlspecialchars($sub); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php $pi++; endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        </div>
