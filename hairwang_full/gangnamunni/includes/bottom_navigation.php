<?php
/**
 * 하단 고정 네비게이션 바 (5탭)
 * 강남언니/바비톡/여기어때 스타일
 * 홈 / 병원 / 이벤트 / 후기 / 마이
 */
$current_page = basename($_SERVER['PHP_SELF']);
$is_home = ($current_page === 'index.php');
$is_hospital = in_array($current_page, ['hospitals.php', 'detail.php', 'doctor_detail.php']);
$is_event = in_array($current_page, ['events.php', 'event_detail.php']);
$is_review = in_array($current_page, ['reviews.php', 'review_write.php']);
$is_my = in_array($current_page, ['mypage.php', 'my_bookings.php', 'my_notifications.php', 'my_events.php']);

$nav_items = [
    ['url' => 'index.php', 'icon' => 'bi-house', 'icon_active' => 'bi-house-fill', 'label' => '홈', 'active' => $is_home],
    ['url' => 'hospitals.php', 'icon' => 'bi-building', 'icon_active' => 'bi-building-fill', 'label' => '병원', 'active' => $is_hospital],
    ['url' => 'events.php', 'icon' => 'bi-calendar-event', 'icon_active' => 'bi-calendar-event-fill', 'label' => '이벤트', 'active' => $is_event],
    ['url' => 'reviews.php', 'icon' => 'bi-star', 'icon_active' => 'bi-star-fill', 'label' => '후기', 'active' => $is_review],
    ['url' => 'mypage.php', 'icon' => 'bi-person', 'icon_active' => 'bi-person-fill', 'label' => '마이', 'active' => $is_my],
];
?>
<style>
.bottom-nav {
    position: fixed;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 100%;
    max-width: 640px;
    height: 60px;
    background: var(--bs-body-bg, #fff);
    border-top: 1px solid var(--bs-border-color, #eee);
    display: flex;
    align-items: stretch;
    z-index: 999;
    box-shadow: 0 -2px 8px rgba(0,0,0,0.06);
}
.bottom-nav-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    color: #999;
    font-size: 10px;
    font-weight: 500;
    gap: 2px;
    transition: color 0.2s;
    position: relative;
}
.bottom-nav-item i {
    font-size: 20px;
    line-height: 1;
}
.bottom-nav-item.active {
    color: var(--primary-color, #AE1932);
    font-weight: 700;
}
.bottom-nav-item:hover {
    color: var(--primary-color, #AE1932);
}
.bottom-nav-item .nav-badge {
    position: absolute;
    top: 6px;
    right: calc(50% - 16px);
    background: #ef4444;
    color: #fff;
    font-size: 9px;
    font-weight: 700;
    padding: 0 4px;
    border-radius: 6px;
    min-width: 14px;
    text-align: center;
    line-height: 14px;
}
</style>

<nav class="bottom-nav">
<?php foreach ($nav_items as $item): ?>
    <a href="<?php echo $item['url']; ?>" class="bottom-nav-item<?php echo $item['active'] ? ' active' : ''; ?>">
        <i class="bi <?php echo $item['active'] ? $item['icon_active'] : $item['icon']; ?>"></i>
        <span><?php echo $item['label']; ?></span>
    </a>
<?php endforeach; ?>
</nav>
