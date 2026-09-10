const {test} = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const {JSDOM} = require('jsdom');
const root = path.resolve(__dirname, '../../hairwang_full/gangnamunni');
function fixture(reduced = false) {
    const dom = new JSDOM(execFileSync('php', ['-n', path.join(root, 'about_project.php')], {encoding:'utf8'}), {runScripts:'outside-only'});
    const w = dom.window;
    let callbacks = new Map(), id = 0;
    w.matchMedia = () => ({matches:reduced, addEventListener(){}});
    w.requestAnimationFrame = callback => {callbacks.set(++id,callback);return id;};
    w.cancelAnimationFrame = id => callbacks.delete(id);
    // CSS/JS now ship inline in about_project.php; pull the behavior script straight from the rendered markup.
    w.eval(w.document.querySelector('script').textContent);
    return {dom, w, frame(now){const batch=[...callbacks.values()];callbacks.clear();batch.forEach(fn=>fn(now));},
        swipe(){const stage=w.document.querySelector('.intro-stage');for(const [type,y] of [['touchstart',300],['touchend',100]]) {const e=new w.Event(type);Object.defineProperty(e,type==='touchstart'?'touches':'changedTouches',{value:[{clientX:150,clientY:y}]});stage.dispatchEvent(e);}},
        };
}
test('prices animate fully before a further swipe opens hospital details',()=>{
    const f=fixture();try {
        const panel=f.w.document.getElementById('price-scene');
        assert.equal(panel.hidden,true);
        assert.equal(f.w.document.getElementById('intro-hero').hidden,false);
        assert.equal(f.w.document.querySelector('.intro-stage').dataset.playing,'false');
        f.swipe();
        const treatments=f.w.document.getElementById('treatments-scene');
        assert.equal(treatments.hidden,false);
        assert.equal(panel.hidden,true);
        treatments.dispatchEvent(new f.w.Event('animationend'));
        f.swipe();
        const filtering=f.w.document.getElementById('filtering-scene');
        assert.equal(filtering.hidden,false);
        assert.equal(panel.hidden,true);
        filtering.dispatchEvent(new f.w.Event('animationend'));
        assert.equal(treatments.hidden,true);
        f.swipe();
        assert.equal(panel.hidden,false);
        assert.equal(f.w.document.querySelector('video'),null);
        assert.equal(f.w.document.querySelector('.intro-stage').dataset.playing,'true');
        const values=()=>[...panel.querySelectorAll('.price-value')].map(e=>e.textContent);
        const widths=()=>[...panel.querySelectorAll('.price-fill')].map(e=>parseFloat(e.style.width));
        assert.deepEqual(values(),['0원','0원','0원']);
        f.frame(0);f.frame(1200);
        assert.deepEqual(values(),['0원','0원','0원']);
        f.frame(2200);
        f.swipe();
        assert.equal(f.w.document.getElementById('hospital-scene').hidden,true);
        widths().forEach((v,i)=>assert.ok(v>0 && v<[27,54,81][i]));
        assert.notDeepEqual(values(),['39,000원','55,000원','72,000원']);
        f.frame(3200);
        assert.deepEqual(values(),['39,000원','55,000원','72,000원']);
        assert.deepEqual(widths(),[27,54,81]);
        f.swipe();assert.deepEqual(widths(),[27,54,81]);
        const hospital=f.w.document.getElementById('hospital-scene');
        assert.equal(hospital.hidden,false);
        assert.equal(panel.hidden,false);
        assert.equal(f.w.document.querySelector('.intro-stage').dataset.scene,'5');
        const phone=f.w.document.getElementById('hospital-phone');
        assert.equal(phone.hidden,false);
        assert.equal(phone.querySelectorAll('.phone-shell').length,1);
        phone.dispatchEvent(new f.w.Event('animationend'));
        assert.equal(panel.hidden,true);
        assert.equal(f.w.document.querySelector('.intro-stage').dataset.playing,'false');
        f.swipe();
        const team=f.w.document.getElementById('hospital-team-scene');
        assert.equal(team.hidden,false);
        assert.equal(phone.classList.contains('show-booking'),false);
        assert.equal(phone.querySelector('.booking-heading').getAttribute('aria-hidden'),'true');
        assert.equal(phone.querySelector('.phone-heading').getAttribute('aria-hidden'),'false');
        assert.equal(team.closest('.phone-shell'),hospital.closest('.phone-shell'));
        assert.equal(hospital.hidden,false);
        assert.equal(f.w.document.querySelector('.intro-stage').dataset.scene,'6');
        team.dispatchEvent(new f.w.Event('animationend'));
        assert.equal(hospital.hidden,true);
        assert.equal(f.w.document.querySelector('.intro-stage').dataset.playing,'false');
        f.swipe();
        const booking=f.w.document.getElementById('hospital-booking-scene');
        assert.equal(booking.hidden,false);
        assert.equal(phone.classList.contains('show-booking'),true);
        assert.equal(phone.querySelector('.booking-heading').getAttribute('aria-hidden'),'false');
        assert.equal(phone.querySelector('.phone-heading').getAttribute('aria-hidden'),'true');
        assert.equal(team.hidden,false);
        assert.equal(booking.closest('.phone-shell'),hospital.closest('.phone-shell'));
        assert.equal(f.w.document.querySelector('.intro-stage').dataset.scene,'7');
        booking.dispatchEvent(new f.w.Event('animationend'));
        assert.equal(team.hidden,true);
        assert.equal(f.w.document.getElementById('booking-ending').hidden,false);
        assert.equal(f.w.document.querySelector('.introduction').classList.contains('scroll-mode'),true);
        const wheel=new f.w.WheelEvent('wheel',{deltaY:100,cancelable:true});
        f.w.document.querySelector('.intro-stage').dispatchEvent(wheel);
        assert.equal(wheel.defaultPrevented,false);
        const key=new f.w.KeyboardEvent('keydown',{key:'PageDown',cancelable:true});
        f.w.document.dispatchEvent(key);
        assert.equal(key.defaultPrevented,false);
        assert.equal(f.w.document.querySelector('.intro-stage').dataset.playing,'false');
        f.swipe();
        assert.equal(f.w.document.querySelector('.intro-stage').dataset.scene,'7');
    } finally {f.dom.window.close();}
});
test('reduced motion shows the final values immediately',()=>{
    const f=fixture(true);try {
        f.swipe();
        f.swipe();
        f.swipe();
        const panel=f.w.document.getElementById('price-scene');assert.equal(panel.hidden,false);
        assert.deepEqual([...panel.querySelectorAll('.price-value')].map(e=>e.textContent),['39,000원','55,000원','72,000원']);
        f.swipe();
        assert.equal(f.w.document.getElementById('hospital-scene').hidden,false);
        assert.equal(panel.hidden,true);
        assert.equal(f.w.document.querySelector('.intro-stage').dataset.playing,'false');
        f.swipe();
        assert.equal(f.w.document.getElementById('hospital-team-scene').hidden,false);
        assert.equal(f.w.document.getElementById('hospital-scene').hidden,true);
        assert.equal(f.w.document.querySelector('.intro-stage').dataset.playing,'false');
        f.swipe();
        assert.equal(f.w.document.getElementById('hospital-booking-scene').hidden,false);
        assert.equal(f.w.document.getElementById('hospital-team-scene').hidden,true);
        assert.equal(f.w.document.querySelector('.intro-stage').dataset.playing,'false');
    } finally {f.dom.window.close();}
});
test('third image scrolls before allowing the next scene',()=>{
    const f=fixture(true);try {
        f.swipe();f.swipe();
        const panel=f.w.document.getElementById('filtering-scene');
        Object.defineProperties(panel,{clientHeight:{value:600},scrollHeight:{value:1400}});
        const stage=f.w.document.querySelector('.intro-stage');
        assert.equal(stage.classList.contains('filtering-scroll'),true);
        f.swipe();assert.equal(stage.dataset.scene,'3');
        const wheel=new f.w.WheelEvent('wheel',{deltaY:120,cancelable:true});
        stage.dispatchEvent(wheel);assert.equal(wheel.defaultPrevented,false);
        panel.scrollTop=800;
        f.swipe();assert.equal(stage.dataset.scene,'4');
    } finally {f.dom.window.close();}
});
test('upward scrolling stays native and previous sections can be reopened',()=>{
    const f=fixture(true);try {
        f.swipe();f.swipe();
        const panel=f.w.document.getElementById('filtering-scene');
        const stage=f.w.document.querySelector('.intro-stage');
        Object.defineProperties(panel,{clientHeight:{value:600},scrollHeight:{value:1400}});
        panel.scrollTop=300;
        const wheel=new f.w.WheelEvent('wheel',{deltaY:-100,cancelable:true});
        stage.dispatchEvent(wheel);
        assert.equal(wheel.defaultPrevented,false);
        assert.equal(stage.dataset.scene,'3');
        panel.scrollTop=0;
        f.w.document.dispatchEvent(new f.w.KeyboardEvent('keydown',{key:'PageUp',cancelable:true}));
        assert.equal(stage.dataset.scene,'2');
        assert.equal(panel.hidden,true);
        assert.equal(f.w.document.getElementById('treatments-scene').hidden,false);
        f.w.document.dispatchEvent(new f.w.KeyboardEvent('keydown',{key:'PageUp',cancelable:true}));
        assert.equal(stage.dataset.scene,'1');
        assert.equal(f.w.document.getElementById('intro-hero').hidden,false);
    } finally {f.dom.window.close();}
});
test('return animation keeps the outgoing image visible until the previous image arrives',()=>{
    const f=fixture();try {
        const hero=f.w.document.getElementById('intro-hero');
        const treatments=f.w.document.getElementById('treatments-scene');
        f.swipe();treatments.dispatchEvent(new f.w.Event('animationend'));
        f.w.document.dispatchEvent(new f.w.KeyboardEvent('keydown',{key:'PageUp',cancelable:true}));
        assert.equal(hero.hidden,false);
        assert.equal(treatments.hidden,false);
        assert.equal(hero.classList.contains('scene-return'),true);
        assert.equal(f.w.document.querySelector('.intro-stage').dataset.playing,'true');
        hero.dispatchEvent(new f.w.Event('animationend'));
        assert.equal(treatments.hidden,true);
        assert.equal(hero.hidden,false);
        assert.equal(f.w.document.querySelector('.intro-stage').dataset.playing,'false');
    } finally {f.dom.window.close();}
});
test('return from third to second clears animation without restarting it',()=>{
    const f=fixture();try {
        const treatments=f.w.document.getElementById('treatments-scene');
        const filtering=f.w.document.getElementById('filtering-scene');
        f.swipe();treatments.dispatchEvent(new f.w.Event('animationend'));
        f.swipe();filtering.dispatchEvent(new f.w.Event('animationend'));
        f.w.document.dispatchEvent(new f.w.KeyboardEvent('keydown',{key:'PageUp',cancelable:true}));
        treatments.dispatchEvent(new f.w.Event('animationend'));
        assert.equal(treatments.classList.contains('scene-return'),false);
        assert.equal(treatments.classList.contains('scene-enter'),false);
        assert.equal(treatments.hidden,false);
        assert.equal(filtering.hidden,true);
        assert.equal(f.w.document.querySelector('.intro-stage').dataset.playing,'false');
        f.w.document.dispatchEvent(new f.w.KeyboardEvent('keydown',{key:'PageUp',cancelable:true}));
        f.w.document.getElementById('intro-hero').dispatchEvent(new f.w.Event('animationend'));
        f.swipe();
        assert.equal(treatments.classList.contains('scene-return'),false);
        assert.equal(treatments.hidden,false);
    } finally {f.dom.window.close();}
});
