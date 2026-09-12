<?php
// Shared post actions for community list and detail pages.
if (defined('COMMUNITY_ACTION_SHEET_RENDERED')) return;
define('COMMUNITY_ACTION_SHEET_RENDERED', true);
?>
<style>
.community-more { border: 0; background: transparent; color: inherit; padding: 4px; min-width: 28px; min-height: 36px; cursor: pointer; font-size: 22px; flex-shrink: 0; }
.community-action-sheet { position: fixed; inset: 0; width: 100%; max-width: none; max-height: none; margin: 0; padding: 0; border: 0; background: transparent; overflow: clip; color: var(--bs-body-color, #000); }
.community-action-sheet::backdrop { background: transparent; }
.community-sheet-backdrop { position: absolute; inset: 0 0 var(--community-nav-height, 77px); background: #000; opacity: 0; touch-action: none; }
.community-sheet-stage { position: absolute; inset: 0 0 var(--community-nav-height, 77px); overflow: clip; pointer-events: none; }
.community-sheet-panel { pointer-events: auto; position: absolute; bottom: 0; left: 0; right: 0; width: 100%; max-width: 640px; margin: 0 auto; border-radius: 0; background: var(--bs-body-bg, #fff); overflow: hidden; touch-action: none; will-change: transform; box-shadow: none; }
.community-sheet-handle { display: block; position: relative; width: 100%; height: 38px; margin: 0; padding: 0; border: 0; background: transparent; cursor: grab; touch-action: none; user-select: none; }
.community-sheet-handle::before { content: ""; position: absolute; top: 14px; left: 50%; transform: translateX(-50%); width: 50px; height: 5px; border-radius: 4px; background: #b81739; }
.community-sheet-handle:active { cursor: grabbing; }
.community-sheet-body { box-sizing: border-box; overflow-y: auto; overscroll-behavior: contain; padding: 0 max(20px, env(safe-area-inset-right)) max(20px, env(safe-area-inset-bottom)) max(20px, env(safe-area-inset-left)); }
.community-sheet-options { padding: 0; }
.community-sheet-options [hidden] { display: none !important; }
a.community-sheet-action { text-decoration: none; }
.community-sheet-action { display: flex; align-items: center; gap: 17px; width: 100%; min-height: 70px; padding: 10px 0; border: 0; background: none; color: inherit; font: inherit; font-size: 18px; text-align: left; cursor: pointer; }
.community-sheet-action i { width: 28px; text-align: center; font-size: 28px; font-weight: 400; }
.community-sheet-action.report { color: #c31336; }
.community-sheet-footer { position: absolute; bottom: 0; left: 0; right: 0; box-sizing: border-box; width: 100%; max-width: 640px; margin: 0 auto; padding: 14px 20px max(20px, env(safe-area-inset-bottom)); background: var(--bs-body-bg, #fff); pointer-events: auto; will-change: transform; }
.community-sheet-footer .community-sheet-cancel { margin-top: 0; }
.community-sheet-cancel { width: 100%; height: 50px; margin-top: 24px; border: 1px solid #d7183d; background: transparent; color: #c31336; font: inherit; font-size: 18px; cursor: pointer; }
.community-action-sheet button:focus-visible, .community-more:focus-visible { outline: 2px solid #b81739; outline-offset: 3px; }
.community-copy-toast { position: fixed; left: 50%; bottom: calc(var(--copy-nav-height, 77px) + 14px); transform: translateX(-50%); box-sizing: border-box; width: calc(100% - 40px); max-width: 600px; min-height: 50px; display: flex; align-items: center; gap: 12px; padding: 4px 12px 4px 20px; border: 1px solid #d7183d; border-radius: 20px; background: #fff; color: #000; font-size: 14px; line-height: 1.5; z-index: 10000; }
.community-copy-toast[hidden] { display: none; }
.community-copy-toast-close { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; width: 40px; height: 40px; margin-left: auto; padding: 0; border: 0; background: transparent; color: inherit; font-size: 20px; cursor: pointer; }
.community-copy-toast-close:focus-visible { outline: 2px solid #b81739; outline-offset: -2px; border-radius: 10px; }
@media(max-width:480px) { .post-stat-group { gap: 12px; } .community-sheet-action { min-height: 60px; font-size: 18px; } }
</style>
<div class="community-copy-toast" id="communityCopyToast" hidden>
    <span role="status" aria-live="polite" aria-atomic="true" id="communityCopyToastMessage"></span>
    <button type="button" class="community-copy-toast-close" aria-label="알림 닫기"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
</div>
<dialog class="community-action-sheet" id="communityActionSheet" autofocus aria-label="게시글 메뉴" aria-modal="true">
    <div class="community-sheet-backdrop" aria-hidden="true"></div>
    <div class="community-sheet-stage">
    <div class="community-sheet-panel">
    <button type="button" class="community-sheet-handle" aria-label="아래로 드래그하거나 아래 방향키로 닫기" title="아래로 드래그하여 닫기"></button>
    <div class="community-sheet-body">
    <div class="community-sheet-options">
    <button type="button" class="community-sheet-action" id="communityCopyLink"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>링크 복사</button>
    <button type="button" class="community-sheet-action" id="communityBlockUser"><i class="bi bi-ban" aria-hidden="true"></i>차단</button>
    <button type="button" class="community-sheet-action report" id="communityReportPost"><i class="bi bi-bell" aria-hidden="true"></i>신고</button>
    <a class="community-sheet-action" id="communityEditPost" hidden><i class="bi bi-pencil" aria-hidden="true"></i>수정</a>
    <form id="communityDeletePost" method="post" hidden onsubmit="return confirm('정말 삭제하시겠습니까?');">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="post_id" id="communityDeleteId">
        <input type="hidden" name="csrf_token" id="communityDeleteCsrf">
        <button type="submit" class="community-sheet-action report"><i class="bi bi-trash" aria-hidden="true"></i>삭제</button>
    </form>
    </div>
    </div>
    </div>
    <div class="community-sheet-footer">
        <button type="button" class="community-sheet-cancel" onclick="closeCommunityActions()">취소</button>
    </div>
    </div>
</dialog>
<script>
const communitySheetUserId = <?php echo json_encode(function_exists('get_gangnamunni_member_id') ? (get_gangnamunni_member_id() ?? '') : '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
function communitySheetLoginPrompt() {
    if (confirm('로그인이 필요합니다. 로그인 페이지로 이동하시겠습니까?')) location.href = 'auth/login.php?url=' + encodeURIComponent(location.href);
}
const communityActionSheet = document.getElementById('communityActionSheet');
const communitySheetPanel = communityActionSheet.querySelector('.community-sheet-panel');
const communitySheetBackdrop = communityActionSheet.querySelector('.community-sheet-backdrop');
const communitySheetBody = communityActionSheet.querySelector('.community-sheet-body');
const communitySheetFooter = communityActionSheet.querySelector('.community-sheet-footer');
let communityActionTarget = null;
const communityMotion = (() => {
    const backdropOpacity = .15;
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    let screenHeight = 0, maxHeight = 0, y = 0;
    let panelAnimation, backdropAnimation, footerAnimation, closing = null, savedPage = null, pointer = null;
    let suppressClick = false;
    const translate = value => 'translate3d(0,' + value + 'px,0)';
    function freezePage() {
        const body = document.body;
        savedPage = { x: window.scrollX, y: window.scrollY, body: body.getAttribute('style'), root: document.documentElement.style.overflow };
        Object.assign(body.style, {position:'fixed', top:-savedPage.y+'px', left:-savedPage.x+'px', width:'100%', overflow:'hidden'});
        document.documentElement.style.overflow = 'hidden';
    }
    function restorePage() {
        if (!savedPage) return;
        const saved = savedPage;
        savedPage = null;
        if (saved.body === null) document.body.removeAttribute('style');
        else document.body.setAttribute('style', saved.body);
        document.documentElement.style.overflow = saved.root;
        const old = document.documentElement.style.scrollBehavior;
        document.documentElement.style.scrollBehavior = 'auto';
        window.scrollTo(saved.x, saved.y);
        document.documentElement.style.scrollBehavior = old;
    }
    function stopAnimation() {
        if (footerAnimation) {
            const transform = getComputedStyle(communitySheetFooter).transform;
            footerAnimation.cancel(); footerAnimation = null;
            communitySheetFooter.style.transform = transform;
        }
        if (panelAnimation) {
            const matrix = new DOMMatrixReadOnly(getComputedStyle(communitySheetPanel).transform);
            y = matrix.m42;
            panelAnimation.cancel(); panelAnimation = null;
            communitySheetPanel.style.transform = translate(y);
        }
        if (backdropAnimation) {
            const opacity = getComputedStyle(communitySheetBackdrop).opacity;
            backdropAnimation.cancel(); backdropAnimation = null;
            communitySheetBackdrop.style.opacity = opacity;
        }
    }
    function sizeBody(visibleHeight) {
        const handleHeight = communityActionSheet.querySelector('.community-sheet-handle').offsetHeight;
        communitySheetBody.style.maxHeight = Math.max(0, visibleHeight - handleHeight - communitySheetFooter.offsetHeight) + 'px';
    }
    function geometry() {
        const viewport = window.visualViewport;
        const viewportHeight = viewport ? viewport.height : window.innerHeight;
        const viewportTop = viewport ? viewport.offsetTop : 0;
        const nav = document.getElementById('bottomNav');
        const navRect = nav ? nav.getBoundingClientRect() : null;
        const navHeight = navRect ? Math.max(0, Math.min(navRect.bottom, viewportTop + viewportHeight) - Math.max(navRect.top, viewportTop)) : 0;
        screenHeight = Math.max(1, viewportHeight - navHeight);
        communityActionSheet.style.setProperty('--community-nav-height', navHeight + 'px');
        communityActionSheet.style.top = viewportTop + 'px';
        communityActionSheet.style.height = viewportHeight + 'px';
        // Use actual content height, never a large panel translated mostly offscreen.
        communitySheetBody.style.maxHeight = 'none';
        const contentHeight = communityActionSheet.querySelector('.community-sheet-handle').offsetHeight
            + communitySheetBody.scrollHeight + communitySheetFooter.offsetHeight;
        maxHeight = Math.min(contentHeight || 340, Math.max(1, screenHeight - 24));
        communitySheetPanel.style.height = maxHeight + 'px';
    }
    async function animateTo(target, duration, opening = false, dismiss = false) {
        stopAnimation();
        const from = y;
        const frames = opening && !reduced.matches
            ? [{transform:translate(from),offset:0,easing:'cubic-bezier(.16,1,.3,1)'},
               {transform:translate(target-3),offset:.86,easing:'ease-out'}, {transform:translate(target),offset:1}]
            : [{transform:translate(from)}, {transform:translate(target)}];
        const ms = reduced.matches ? 0 : duration;
        const animation = communitySheetPanel.animate(frames, {duration:ms, easing:opening?'linear':dismiss?'cubic-bezier(.4,0,1,1)':'cubic-bezier(.22,1,.36,1)', fill:'forwards'});
        panelAnimation = animation;
        const footerHeight = communitySheetFooter.offsetHeight;
        if (dismiss) {
            // The footer waits until the moving sheet top reaches its upper edge.
            const contact = maxHeight - footerHeight;
            const hold = Math.max(0, Math.min(.99, (contact - from) / Math.max(1, target - from)));
            const initial = Math.max(0, from - contact);
            footerAnimation = communitySheetFooter.animate([
                {transform:translate(initial), offset:0},
                {transform:translate(initial), offset:hold},
                {transform:translate(footerHeight + 32), offset:1}
            ], {duration:ms, easing:'cubic-bezier(.4,0,1,1)', fill:'forwards'});
        } else {
            footerAnimation = communitySheetFooter.animate([
                {transform:opening ? translate(footerHeight + 32) : getComputedStyle(communitySheetFooter).transform},
                {transform:translate(0)}
            ], {duration:ms, easing:'cubic-bezier(.22,1,.36,1)', fill:'forwards'});
        }
        backdropAnimation = communitySheetBackdrop.animate(
            [{opacity:getComputedStyle(communitySheetBackdrop).opacity}, {opacity:dismiss?0:backdropOpacity}],
            {duration:ms, easing:dismiss?'ease-in':'ease-out', fill:'forwards'});
        try { await animation.finished; } catch (_) { return false; }
        if (panelAnimation !== animation) return false;
        communitySheetFooter.style.transform = translate(dismiss ? footerHeight + 32 : 0);
        if (footerAnimation) {footerAnimation.cancel(); footerAnimation = null;}
        y = target;
        communitySheetPanel.style.transform = translate(y);
        animation.cancel(); panelAnimation = null;
        communitySheetBackdrop.style.opacity = dismiss ? '0' : String(backdropOpacity);
        if (backdropAnimation) { backdropAnimation.cancel(); backdropAnimation = null; }
        return true;
    }
    function open(button) {
        if (communityActionSheet.open || closing) return;
        communityActionTarget = {...button.dataset};
        const isComment = communityActionTarget.type === 'comment';
        communityActionSheet.setAttribute('aria-label', isComment ? '댓글 메뉴' : '게시글 메뉴');
        document.getElementById('communityCopyLink').hidden = isComment;
        const ownPost = !isComment && communityActionTarget.owner === 'true' && communityActionTarget.user === String(communitySheetUserId);
        document.getElementById('communityBlockUser').hidden = ownPost;
        document.getElementById('communityReportPost').hidden = ownPost;
        const edit = document.getElementById('communityEditPost');
        const deletion = document.getElementById('communityDeletePost');
        edit.hidden = deletion.hidden = !ownPost;
        if (ownPost) {
            edit.href = 'community_write.php?id=' + encodeURIComponent(communityActionTarget.id);
            deletion.action = communityActionTarget.url;
            document.getElementById('communityDeleteId').value = communityActionTarget.id;
            document.getElementById('communityDeleteCsrf').value = communityActionTarget.csrf || '';
        }
        geometry(); freezePage();
        y = maxHeight + 32;
        communitySheetPanel.style.transform = translate(y);
        communitySheetBackdrop.style.opacity = '0';
        communitySheetBody.scrollTop = 0;
        sizeBody(maxHeight);
        // Focus must not scroll the off-screen animated panel into view.
        communityActionSheet.showModal();
        communityActionSheet.focus({preventScroll:true});
        communityActionSheet.scrollTop = 0;
        communityActionSheet.querySelector('.community-sheet-stage').scrollTop = 0;
        geometry();
        sizeBody(maxHeight);
        animateTo(0, 320, true);
    }
    function releasePointer() {
        if (!pointer) return;
        const id = pointer.id; pointer = null;
        if (communitySheetPanel.hasPointerCapture(id)) communitySheetPanel.releasePointerCapture(id);
    }
    function close() {
        if (closing) return closing;
        if (!communityActionSheet.open) return Promise.resolve();
        releasePointer();
        closing = (async () => {
            await animateTo(maxHeight + 32, 240, false, true);
            communityActionSheet.close();
            restorePage();
            closing = null;
        })();
        return closing;
    }
    communityActionSheet.addEventListener('cancel', event => {event.preventDefault(); close();});
    communityActionSheet.addEventListener('click', event => {
        if (event.target !== communitySheetBackdrop && event.target !== communityActionSheet) return;
        event.preventDefault();
        event.stopPropagation();
        close();
    });
    communityActionSheet.addEventListener('close', () => {releasePointer(); restorePage();});
    communitySheetPanel.addEventListener('pointerdown', event => {
        if (closing || pointer || !event.isPrimary || event.button !== 0) return;
        stopAnimation();
        pointer = {id:event.pointerId, start:event.clientY, last:event.clientY, y, height:maxHeight-y,
            samples:[{y:event.clientY,t:event.timeStamp}], moved:false,
            inBody:communitySheetBody.contains(event.target)};
        suppressClick = false;
        // Capture immediately: transferring capture from the handle on the first
        // move emits a bubbling lostpointercapture event in mobile browsers.
        if (event.target.closest('.community-sheet-handle')) {
            communitySheetPanel.setPointerCapture(event.pointerId);
            event.preventDefault();
        }
    });
    communitySheetPanel.addEventListener('pointermove', event => {
        if (!pointer || pointer.id !== event.pointerId) return;
        const delta = event.clientY-pointer.start;
        if (!pointer.moved && Math.abs(delta)<4) return;
        if (!pointer.moved) {
            pointer.moved=true;
            communitySheetPanel.setPointerCapture(event.pointerId);
        }
        suppressClick = true;
        event.preventDefault();
        const step = event.clientY-pointer.last;
        pointer.last = event.clientY;
        // At the upper snap, scroll overflowing content before moving the sheet.
        if (pointer.inBody && (communitySheetBody.scrollTop>0 || (y<=.5 && step<0)) && communitySheetBody.scrollHeight>communitySheetBody.clientHeight) {
            const before = communitySheetBody.scrollTop;
            communitySheetBody.scrollTop -= step;
            if (communitySheetBody.scrollTop !== before) {
                pointer.start=event.clientY; pointer.y=y; pointer.height=maxHeight-y;
                pointer.samples=[{y:event.clientY,t:event.timeStamp}];
                return;
            }
        }
        const raw = pointer.y + event.clientY-pointer.start;
        y = raw<0 ? -Math.min(48, -raw*.18) : raw;
        communitySheetPanel.style.transform = translate(y);
        communitySheetFooter.style.transform = translate(Math.max(0, y - (maxHeight - communitySheetFooter.offsetHeight)));
        communitySheetBackdrop.style.opacity = String(backdropOpacity*Math.min(1,Math.max(0,(maxHeight-y)/pointer.height)));
        pointer.samples.push({y:event.clientY,t:event.timeStamp});
        while (pointer.samples.length > 2 && event.timeStamp-pointer.samples[1].t>100) pointer.samples.shift();
    }, {passive:false});
    function finish(event) {
        if (!pointer || pointer.id!==event.pointerId) return;
        const drag = pointer;
        releasePointer();
        if (!drag.moved) {
            animateTo(0, 180);
            return;
        }
        const sample=drag.samples[0];
        const velocity=event.timeStamp-drag.samples[drag.samples.length-1].t>100 ? 0
            : (event.clientY-sample.y)/Math.max(1,event.timeStamp-sample.t);
        const downward=event.clientY-drag.start;
        if (event.type==='pointerup' && (downward>drag.height*.25 || (downward>8 && velocity>.8))) {close(); return;}

        sizeBody(maxHeight);
        animateTo(0, 240);
    }
    communitySheetPanel.addEventListener('pointerup', finish);
    communitySheetPanel.addEventListener('pointercancel', finish);
    communitySheetPanel.addEventListener('lostpointercapture', event => {
        // Ignore capture changes bubbling from the handle or an action button.
        if (event.target !== communitySheetPanel) return;
        if (pointer && !communitySheetPanel.hasPointerCapture(pointer.id)) finish(event);
    });
    communitySheetPanel.addEventListener('click', event => {
        if (suppressClick) {event.preventDefault();event.stopImmediatePropagation();suppressClick=false;}
    }, true);
    communityActionSheet.querySelector('.community-sheet-handle').addEventListener('keydown', event => {
        if (!closing && event.key === 'ArrowDown') {event.preventDefault(); close();}
    });
    function resize() {
        if (!communityActionSheet.open || closing || pointer) return;
        stopAnimation(); geometry();
        y=0;
        communitySheetPanel.style.transform=translate(y);
        communitySheetFooter.style.transform=translate(0);
        sizeBody(maxHeight);
    }
    window.addEventListener('resize',resize);
    if(window.visualViewport) {window.visualViewport.addEventListener('resize',resize); window.visualViewport.addEventListener('scroll',resize);}
    return {open,close};
})();
function openCommunityActions(button) { communityMotion.open(button); }
function closeCommunityActions() { return communityMotion.close(); }
const communityCopyToast = document.getElementById('communityCopyToast');
let communityCopyToastTimer;
function hideCommunityCopyToast() {
    clearTimeout(communityCopyToastTimer);
    communityCopyToast.hidden = true;
    document.getElementById('communityCopyToastMessage').textContent = '';
}
function positionCommunityCopyToast() {
    const nav = document.getElementById('bottomNav');
    if (nav) communityCopyToast.style.setProperty('--copy-nav-height', nav.getBoundingClientRect().height + 'px');
}
function showCommunityCopyToast(message = '링크가 복사되었습니다.') {
    clearTimeout(communityCopyToastTimer);
    positionCommunityCopyToast();
    communityCopyToast.hidden = false;
    document.getElementById('communityCopyToastMessage').textContent = message;
    communityCopyToastTimer = setTimeout(hideCommunityCopyToast, 4000);
}
communityCopyToast.querySelector('button').addEventListener('click', hideCommunityCopyToast);
window.addEventListener('resize', positionCommunityCopyToast);
window.addEventListener('load', function() {
    const nav = document.getElementById('bottomNav');
    if (nav && window.ResizeObserver) new ResizeObserver(positionCommunityCopyToast).observe(nav);
    try {
        if (sessionStorage.getItem('communityBlockCompleted') === '1') {
            sessionStorage.removeItem('communityBlockCompleted');
            showCommunityCopyToast('차단이 완료되었어요.');
        }
    } catch (error) { /* Storage may be unavailable in private browsing. */ }
});
function completeCommunityBlock() {
    // Refresh the filtered feed first, then display the confirmation above its navigation.
    try {
        sessionStorage.setItem('communityBlockCompleted', '1');
        location.replace('community.php');
    } catch (error) {
        showCommunityCopyToast('차단이 완료되었어요.');
        setTimeout(function() { location.replace('community.php'); }, 4000);
    }
}
document.getElementById('communityCopyLink').addEventListener('click', async function() {
    const url = new URL(communityActionTarget.url, location.href).href;
    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(url);
        } else {
            const field = document.createElement('textarea');
            field.value = url;
            field.style.position = 'absolute';
            field.style.opacity = '0';
            communityActionSheet.appendChild(field);
            field.select();
            const copied = document.execCommand('copy');
            field.remove();
            if (!copied) throw new Error('Clipboard unavailable');
        }
        await closeCommunityActions();
        showCommunityCopyToast();
    } catch (error) { await closeCommunityActions(); showToast('링크 복사에 실패했습니다. 다시 시도해주세요.', 'error'); }
});
document.getElementById('communityBlockUser').addEventListener('click', async function() {
    const target = communityActionTarget;
    await closeCommunityActions();
    if (!communitySheetUserId) { communitySheetLoginPrompt(); return; }
    if (!target.user) { showToast('차단할 사용자 정보가 없습니다.', 'error'); return; }
    if (target.user === String(communitySheetUserId)) { showToast('자기 자신을 차단할 수 없습니다.', 'warning'); return; }
    openBlockModal(target.user, target.author);
});
document.getElementById('communityReportPost').addEventListener('click', async function() {
    const target = communityActionTarget;
    await closeCommunityActions();
    if (!communitySheetUserId) { communitySheetLoginPrompt(); return; }
    openReportModal(target.type, target.id, target.user, target.author, target.url);
});
</script>
