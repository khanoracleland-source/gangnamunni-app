// Booking uses the same pointer physics and delayed footer motion as community_action_sheet.php.
// Loaded after the sheet markup so gesture handlers are always attached.
(function () {
'use strict';
function createBookingSheet(overlay, onClose) {
    const panel = overlay.querySelector('.booking-motion-panel');
    if (!panel) return {open:function(){}, close:function(){return Promise.resolve();}};
    const body = panel.querySelector('.booking-motion-body');
    const footer = panel.querySelector('.booking-motion-footer') || overlay.querySelector('.booking-motion-footer');
    if (!body || !footer) return {open:function(){}, close:function(){return Promise.resolve();}};
    const backdrop = document.createElement('div');
    backdrop.className = 'booking-motion-backdrop';
    backdrop.setAttribute('aria-hidden', 'true');
    const stage = document.createElement('div');
    stage.className = 'booking-motion-stage';
    ['role', 'aria-modal', 'aria-labelledby'].forEach(attribute => {
        if (panel.hasAttribute(attribute)) {
            stage.setAttribute(attribute, panel.getAttribute(attribute));
            panel.removeAttribute(attribute);
        }
    });
    overlay.prepend(backdrop);
    overlay.append(stage);
    stage.append(panel, footer);
    let previousFocus = null;
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
            const transform = getComputedStyle(footer).transform;
            footerAnimation.cancel(); footerAnimation = null;
            footer.style.transform = transform;
        }
        if (panelAnimation) {
            const matrix = new DOMMatrixReadOnly(getComputedStyle(panel).transform);
            y = matrix.m42;
            panelAnimation.cancel(); panelAnimation = null;
            panel.style.transform = translate(y);
        }
        if (backdropAnimation) {
            const opacity = getComputedStyle(backdrop).opacity;
            backdropAnimation.cancel(); backdropAnimation = null;
            backdrop.style.opacity = opacity;
        }
    }
    function sizeBody(visibleHeight) {
        const handleHeight = overlay.querySelector('.booking-motion-handle').offsetHeight;
        body.style.maxHeight = Math.max(0, visibleHeight - handleHeight - footer.offsetHeight) + 'px';
    }
    function geometry() {
        const viewport = window.visualViewport;
        const viewportHeight = viewport ? viewport.height : window.innerHeight;
        const viewportTop = viewport ? viewport.offsetTop : 0;
        const nav = document.getElementById('bottomNav');
        const navRect = nav ? nav.getBoundingClientRect() : null;
        const navHeight = navRect ? Math.max(0, Math.min(navRect.bottom, viewportTop + viewportHeight) - Math.max(navRect.top, viewportTop)) : 0;
        screenHeight = Math.max(1, viewportHeight - navHeight);
        overlay.style.setProperty('--booking-nav-height', navHeight + 'px');
        overlay.style.top = viewportTop + 'px';
        overlay.style.height = viewportHeight + 'px';
        // Use actual content height, never a large panel translated mostly offscreen.
        body.style.maxHeight = 'none';
        const contentHeight = overlay.querySelector('.booking-motion-handle').offsetHeight
            + body.scrollHeight + footer.offsetHeight;
        maxHeight = Math.min(contentHeight || 340, Math.max(1, screenHeight - 24));
        panel.style.height = maxHeight + 'px';
    }
    async function animateTo(target, duration, opening = false, dismiss = false) {
        stopAnimation();
        const from = y;
        const frames = opening && !reduced.matches
            ? [{transform:translate(from),offset:0,easing:'cubic-bezier(.16,1,.3,1)'},
               {transform:translate(target-3),offset:.86,easing:'ease-out'}, {transform:translate(target),offset:1}]
            : [{transform:translate(from)}, {transform:translate(target)}];
        const ms = reduced.matches ? 0 : duration;
        const animation = panel.animate(frames, {duration:ms, easing:opening?'linear':dismiss?'cubic-bezier(.4,0,1,1)':'cubic-bezier(.22,1,.36,1)', fill:'forwards'});
        panelAnimation = animation;
        const footerHeight = footer.offsetHeight;
        if (dismiss) {
            // The footer waits until the moving sheet top reaches its upper edge.
            const contact = maxHeight - footerHeight;
            const hold = Math.max(0, Math.min(.99, (contact - from) / Math.max(1, target - from)));
            const initial = Math.max(0, from - contact);
            footerAnimation = footer.animate([
                {transform:translate(initial), offset:0},
                {transform:translate(initial), offset:hold},
                {transform:translate(footerHeight + 32), offset:1}
            ], {duration:ms, easing:'cubic-bezier(.4,0,1,1)', fill:'forwards'});
        } else {
            footerAnimation = footer.animate([
                {transform:opening ? translate(footerHeight + 32) : getComputedStyle(footer).transform},
                {transform:translate(0)}
            ], {duration:ms, easing:'cubic-bezier(.22,1,.36,1)', fill:'forwards'});
        }
        backdropAnimation = backdrop.animate(
            [{opacity:getComputedStyle(backdrop).opacity}, {opacity:dismiss?0:backdropOpacity}],
            {duration:ms, easing:dismiss?'ease-in':'ease-out', fill:'forwards'});
        try { await animation.finished; } catch (_) { return false; }
        if (panelAnimation !== animation) return false;
        footer.style.transform = translate(dismiss ? footerHeight + 32 : 0);
        if (footerAnimation) {footerAnimation.cancel(); footerAnimation = null;}
        y = target;
        panel.style.transform = translate(y);
        animation.cancel(); panelAnimation = null;
        backdrop.style.opacity = dismiss ? '0' : String(backdropOpacity);
        if (backdropAnimation) { backdropAnimation.cancel(); backdropAnimation = null; }
        return true;
    }
    function open() {
        if (overlay.classList.contains('open') || closing) return;
        previousFocus = document.activeElement;
        overlay.classList.add('open');
        overlay.setAttribute('aria-hidden', 'false');
        geometry(); freezePage();
        y = maxHeight + 32;
        panel.style.transform = translate(y);
        backdrop.style.opacity = '0';
        body.scrollTop = 0;
        sizeBody(maxHeight);
        // Focus must not scroll the off-screen animated panel into view.
        overlay.focus({preventScroll:true});
        overlay.scrollTop = 0;
        overlay.querySelector('.booking-motion-stage').scrollTop = 0;
        geometry();
        sizeBody(maxHeight);
        animateTo(0, 320, true);
    }
    function releasePointer() {
        if (!pointer) return;
        const id = pointer.id; pointer = null;
        if (panel.hasPointerCapture(id)) panel.releasePointerCapture(id);
    }
    function close() {
        if (closing) return closing;
        if (!overlay.classList.contains('open')) return Promise.resolve();
        releasePointer();
        closing = (async () => {
            await animateTo(maxHeight + 32, 240, false, true);
            overlay.classList.remove('open');
            overlay.setAttribute('aria-hidden', 'true');
            restorePage();
            if (onClose) onClose();
            else if (previousFocus && previousFocus.isConnected) previousFocus.focus({preventScroll:true});
            closing = null;
        })();
        return closing;
    }
    overlay.addEventListener('keydown', event => {
        if (event.key === 'Escape') {event.preventDefault(); close();}
    });
    overlay.addEventListener('click', event => {
        if (event.target !== backdrop && event.target !== overlay) return;
        event.preventDefault();
        event.stopPropagation();
        close();
    });
    const onPointerDown = event => {
        if (closing || pointer || !event.isPrimary || event.button !== 0) return;
        stopAnimation();
        pointer = {id:event.pointerId, start:event.clientY, last:event.clientY, y, height:maxHeight-y,
            samples:[{y:event.clientY,t:event.timeStamp}], moved:false,
            inBody:body.contains(event.target)};
        suppressClick = false;
        // Capture immediately: transferring capture from the handle on the first
        // move emits a bubbling lostpointercapture event in mobile browsers.
        if (event.target.closest('.booking-motion-handle')) {
            panel.setPointerCapture(event.pointerId);
            event.preventDefault();
        }
    };
    // The footer lives outside the panel (moved into the stage), so it needs the same listeners to be a drag surface.
    panel.addEventListener('pointerdown', onPointerDown);
    footer.addEventListener('pointerdown', onPointerDown);
    const onPointerMove = event => {
        if (!pointer || pointer.id !== event.pointerId) return;
        const delta = event.clientY-pointer.start;
        if (!pointer.moved && Math.abs(delta)<4) return;
        if (!pointer.moved) {
            pointer.moved=true;
            panel.setPointerCapture(event.pointerId);
        }
        suppressClick = true;
        event.preventDefault();
        const step = event.clientY-pointer.last;
        pointer.last = event.clientY;
        // At the upper snap, scroll overflowing content before moving the sheet.
        if (pointer.inBody && (body.scrollTop>0 || (y<=.5 && step<0)) && body.scrollHeight>body.clientHeight) {
            const before = body.scrollTop;
            body.scrollTop -= step;
            if (body.scrollTop !== before) {
                pointer.start=event.clientY; pointer.y=y; pointer.height=maxHeight-y;
                pointer.samples=[{y:event.clientY,t:event.timeStamp}];
                return;
            }
        }
        const raw = pointer.y + event.clientY-pointer.start;
        y = raw<0 ? -Math.min(48, -raw*.18) : raw;
        panel.style.transform = translate(y);
        footer.style.transform = translate(Math.max(0, y - (maxHeight - footer.offsetHeight)));
        backdrop.style.opacity = String(backdropOpacity*Math.min(1,Math.max(0,(maxHeight-y)/pointer.height)));
        pointer.samples.push({y:event.clientY,t:event.timeStamp});
        while (pointer.samples.length > 2 && event.timeStamp-pointer.samples[1].t>100) pointer.samples.shift();
    };
    panel.addEventListener('pointermove', onPointerMove, {passive:false});
    footer.addEventListener('pointermove', onPointerMove, {passive:false});
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
    panel.addEventListener('pointerup', finish);
    panel.addEventListener('pointercancel', finish);
    footer.addEventListener('pointerup', finish);
    footer.addEventListener('pointercancel', finish);
    panel.addEventListener('lostpointercapture', event => {
        // Ignore capture changes bubbling from the handle or an action button.
        if (event.target !== panel) return;
        if (pointer && !panel.hasPointerCapture(pointer.id)) finish(event);
    });
    const onClickCapture = event => {
        if (suppressClick) {event.preventDefault();event.stopImmediatePropagation();suppressClick=false;}
    };
    panel.addEventListener('click', onClickCapture, true);
    footer.addEventListener('click', onClickCapture, true);
    overlay.querySelector('.booking-motion-handle').addEventListener('keydown', event => {
        if (!closing && event.key === 'ArrowDown') {event.preventDefault(); close();}
    });
    function resize() {
        if (!overlay.classList.contains('open') || closing || pointer) return;
        stopAnimation(); geometry();
        y=0;
        panel.style.transform=translate(y);
        footer.style.transform=translate(0);
        sizeBody(maxHeight);
    }
    window.addEventListener('resize',resize);
    if(window.visualViewport) {window.visualViewport.addEventListener('resize',resize); window.visualViewport.addEventListener('scroll',resize);}
    return {open,close};
}
function bindBookingMotionSheet(overlay, name, onClose) {
    if (!overlay || overlay.__bookingMotion) return overlay ? overlay.__bookingMotion : null;
    const motion = createBookingSheet(overlay, onClose);
    overlay.__bookingMotion = motion;
    if (name) window[name] = motion;
    return motion;
}
const treatment = document.getElementById('treatmentSheet');
bindBookingMotionSheet(treatment, 'bookingTreatmentMotion');
const success = document.getElementById('bookingSuccessOverlay');
bindBookingMotionSheet(success, 'bookingSuccessMotion', function () {
    if (window.unlockSuccessScreen) window.unlockSuccessScreen();
});
document.querySelectorAll('[data-booking-motion-sheet]').forEach(overlay => {
    const name = overlay.getAttribute('data-booking-motion-name') || '';
    bindBookingMotionSheet(overlay, name);
});
window.bindBookingMotionSheet = bindBookingMotionSheet;
})();
