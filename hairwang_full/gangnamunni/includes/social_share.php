<?php
/**
 * 소셜 공유 버튼 공통 컴포넌트
 * 카카오톡, 네이버 블로그, 페이스북, 트위터(X), URL 복사
 * 사용: renderShareButtons($title, $description, $url, $imageUrl)
 */

function renderShareButtons($title = '', $description = '', $url = '', $imageUrl = '') {
    if (!$url) {
        $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
             . '://' . ($_SERVER['HTTP_HOST'] ?? 'hms.seoulbeauty.app')
             . ($_SERVER['REQUEST_URI'] ?? '');
    }
    $title = htmlspecialchars($title, ENT_QUOTES);
    $description = htmlspecialchars(mb_substr(strip_tags($description), 0, 100), ENT_QUOTES);
    $encodedUrl = urlencode($url);
    $encodedTitle = urlencode($title);
    ?>
<div class="share-container" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
    <span style="font-size:13px;font-weight:600;color:var(--bs-secondary,#888);">공유</span>
    <!-- 카카오톡 -->
    <button type="button" class="share-btn" onclick="shareKakao('<?php echo addslashes($title); ?>', '<?php echo addslashes($description); ?>', '<?php echo $url; ?>', '<?php echo $imageUrl; ?>')" style="background:#FEE500;border:none;width:36px;height:36px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;" title="카카오톡">
        <svg width="20" height="20" viewBox="0 0 24 24"><path d="M12 3C6.5 3 2 6.58 2 11c0 2.84 1.87 5.33 4.69 6.75-.15.54-.97 3.47-.99 3.67 0 0-.02.17.09.24.11.06.24.01.24.01.32-.04 3.68-2.42 4.26-2.84.56.08 1.13.12 1.71.12 5.5 0 10-3.58 10-8s-4.5-8-10-8" fill="#3C1E1E"/></svg>
    </button>
    <!-- 네이버 블로그 -->
    <a href="https://share.naver.com/web/shareView?url=<?php echo $encodedUrl; ?>&title=<?php echo $encodedTitle; ?>" target="_blank" rel="noopener" class="share-btn" style="background:#03C75A;border:none;width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;text-decoration:none;" title="네이버">
        <span style="color:#fff;font-weight:900;font-size:16px;">N</span>
    </a>
    <!-- 페이스북 -->
    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $encodedUrl; ?>" target="_blank" rel="noopener" class="share-btn" style="background:#1877F2;border:none;width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;text-decoration:none;" title="페이스북">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="#fff"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
    </a>
    <!-- X (트위터) -->
    <a href="https://twitter.com/intent/tweet?text=<?php echo $encodedTitle; ?>&url=<?php echo $encodedUrl; ?>" target="_blank" rel="noopener" class="share-btn" style="background:#000;border:none;width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;text-decoration:none;" title="X">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="#fff"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
    </a>
    <!-- URL 복사 -->
    <button type="button" class="share-btn" onclick="copyShareUrl('<?php echo $url; ?>')" style="background:var(--bs-tertiary-bg,#e9ecef);border:none;width:36px;height:36px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;" title="URL 복사">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
    </button>
</div>

    <?php
}

function renderShareScript() {
    static $loaded = false;
    if ($loaded) return;
    $loaded = true;
    ?>
<script src="https://t1.kakaocdn.net/kakao_js_sdk/2.7.4/kakao.min.js" crossorigin="anonymous"></script>
<script>
// 카카오 SDK 초기화 (JavaScript 키)
if (typeof Kakao !== 'undefined' && !Kakao.isInitialized()) {
    Kakao.init('a26f846dea6a4855a67a7cfb55e89e89');
}

function shareKakao(title, desc, url, img) {
    if (typeof Kakao === 'undefined' || !Kakao.isInitialized()) {
        alert('카카오톡 공유 준비 중입니다.');
        return;
    }
    var params = {
        objectType: 'feed',
        content: {
            title: title,
            description: desc || '',
            imageUrl: img || '<?php echo (defined("SITE_FULL_URL") ? SITE_FULL_URL : "https://hms.seoulbeauty.app/hairwang/gangnamunni/"); ?>assets/images/seoul_beauty_logo.png',
            link: { mobileWebUrl: url, webUrl: url }
        },
        buttons: [
            { title: '자세히 보기', link: { mobileWebUrl: url, webUrl: url } }
        ]
    };
    Kakao.Share.sendDefault(params);
}

function copyShareUrl(url) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(function() {
            showCopyToast();
        });
    } else {
        var t = document.createElement('textarea');
        t.value = url;
        t.style.position = 'fixed';
        t.style.left = '-9999px';
        document.body.appendChild(t);
        t.select();
        document.execCommand('copy');
        document.body.removeChild(t);
        showCopyToast();
    }
}

function showCopyToast() {
    var toast = document.createElement('div');
    toast.textContent = 'URL이 복사되었습니다';
    toast.style.cssText = 'position:fixed;bottom:80px;left:50%;transform:translateX(-50%);background:#333;color:#fff;padding:10px 24px;border-radius:8px;font-size:14px;z-index:9999;animation:fadeInUp 0.3s ease';
    document.body.appendChild(toast);
    setTimeout(function() { toast.remove(); }, 2000);
}
</script>
<style>
.share-btn { transition: transform 0.15s, opacity 0.15s; }
.share-btn:hover { transform: scale(1.1); opacity: 0.9; }
@keyframes fadeInUp { from{opacity:0;transform:translate(-50%,10px)} to{opacity:1;transform:translate(-50%,0)} }
</style>
    <?php
}
