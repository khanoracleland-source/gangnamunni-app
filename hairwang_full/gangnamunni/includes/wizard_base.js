/**
 * 공통 마법사 JS - 모든 마법사 페이지 재사용
 * 사용법: const wz = new WizardBase(totalSteps, options);
 */
class WizardBase {
    constructor(totalSteps, opts = {}) {
        this.totalSteps = totalSteps;
        this.currentStep = 1;
        this.validators = {};   // { stepNum: () => true/false }
        this.onStepChange = opts.onStepChange || null;
        this.onSubmit = opts.onSubmit || null;
        this.storageKey = opts.storageKey || null;
        this._initEvents();
    }

    _initEvents() {
        // 스텝 클릭
        document.querySelectorAll('.wz-step').forEach(s => {
            s.addEventListener('click', () => {
                const n = parseInt(s.dataset.step);
                if (n <= this.currentStep + 1 && n >= 1) this.goTo(n);
            });
        });
    }

    goTo(step) {
        if (step < 1 || step > this.totalSteps) return;
        // 앞으로 갈 때 현재 단계 검증
        if (step > this.currentStep && this.validators[this.currentStep]) {
            if (!this.validators[this.currentStep]()) return;
        }
        this.currentStep = step;
        // 패널 전환
        document.querySelectorAll('.wz-panel').forEach(p => p.classList.remove('active'));
        const panel = document.getElementById('wzPanel' + step);
        if (panel) panel.classList.add('active');
        // 스텝 헤더 갱신
        document.querySelectorAll('.wz-step').forEach(s => {
            const sn = parseInt(s.dataset.step);
            s.classList.remove('active', 'done');
            if (sn === step) s.classList.add('active');
            else if (sn < step) s.classList.add('done');
        });
        document.querySelectorAll('.wz-line').forEach((l, i) => {
            l.classList.toggle('done', i + 1 < step);
        });
        // 콜백
        if (this.onStepChange) this.onStepChange(step);
        // 스크롤 맨 위
        window.scrollTo({ top: 0, behavior: 'smooth' });
        // 임시 저장
        this._autoSave();
    }

    next() {
        if (this.currentStep < this.totalSteps) this.goTo(this.currentStep + 1);
    }

    prev() {
        if (this.currentStep > 1) this.goTo(this.currentStep - 1);
    }

    setValidator(step, fn) {
        this.validators[step] = fn;
    }

    // 임시 저장
    _autoSave() {
        if (!this.storageKey) return;
        try {
            const data = { step: this.currentStep, ts: Date.now() };
            localStorage.setItem(this.storageKey, JSON.stringify(data));
        } catch (e) {}
    }

    restore() {
        if (!this.storageKey) return null;
        try {
            const raw = localStorage.getItem(this.storageKey);
            if (!raw) return null;
            const data = JSON.parse(raw);
            // 24시간 이내만 복원
            if (Date.now() - data.ts > 86400000) {
                localStorage.removeItem(this.storageKey);
                return null;
            }
            return data;
        } catch (e) { return null; }
    }

    clearStorage() {
        if (this.storageKey) localStorage.removeItem(this.storageKey);
    }
}

/* ── 별점 컴포넌트 ── */
function initStarRating(container, callback) {
    const stars = container.querySelectorAll('.star');
    let value = 0;
    stars.forEach((star, i) => {
        star.addEventListener('click', () => {
            value = i + 1;
            stars.forEach((s, j) => s.classList.toggle('active', j < value));
            if (callback) callback(value);
        });
        star.addEventListener('mouseenter', () => {
            stars.forEach((s, j) => s.classList.toggle('active', j <= i));
        });
    });
    container.addEventListener('mouseleave', () => {
        stars.forEach((s, j) => s.classList.toggle('active', j < value));
    });
    return { getValue: () => value, setValue: (v) => { value = v; stars.forEach((s, j) => s.classList.toggle('active', j < v)); } };
}

/* ── 카드 선택 (단일/복수) ── */
function initCardSelect(container, opts = {}) {
    const cards = container.querySelectorAll('.wz-card');
    const multi = opts.multi || false;
    const selected = new Set();

    cards.forEach(card => {
        card.addEventListener('click', () => {
            const val = card.dataset.value;
            if (multi) {
                if (selected.has(val)) { selected.delete(val); card.classList.remove('selected'); }
                else { selected.add(val); card.classList.add('selected'); }
            } else {
                cards.forEach(c => c.classList.remove('selected'));
                selected.clear();
                selected.add(val);
                card.classList.add('selected');
            }
            if (opts.onChange) opts.onChange([...selected]);
        });
    });

    return {
        getSelected: () => [...selected],
        setSelected: (vals) => {
            selected.clear();
            cards.forEach(c => {
                const v = c.dataset.value;
                if (vals.includes(v)) { selected.add(v); c.classList.add('selected'); }
                else c.classList.remove('selected');
            });
        }
    };
}

/* ── 검색 드롭다운 ── */
function initSearchDropdown(input, dropdown, opts = {}) {
    const items = dropdown.querySelectorAll('.wz-dropdown-item');

    input.addEventListener('focus', () => dropdown.classList.add('show'));
    input.addEventListener('input', () => {
        const q = input.value.toLowerCase().trim();
        dropdown.classList.add('show');
        items.forEach(item => {
            const text = (item.dataset.name || item.textContent || '').toLowerCase();
            item.style.display = (!q || text.includes(q)) ? '' : 'none';
        });
    });

    document.addEventListener('click', (e) => {
        if (!input.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.remove('show');
        }
    });

    items.forEach(item => {
        item.addEventListener('click', () => {
            dropdown.classList.remove('show');
            if (opts.onSelect) opts.onSelect(item);
        });
    });

    // 키보드
    input.addEventListener('keydown', (e) => {
        if (!dropdown.classList.contains('show')) return;
        const visible = [...items].filter(i => i.style.display !== 'none');
        const cur = dropdown.querySelector('.highlighted');
        let idx = cur ? visible.indexOf(cur) : -1;
        if (e.key === 'ArrowDown') { e.preventDefault(); if (cur) cur.classList.remove('highlighted'); idx = (idx+1)%visible.length; visible[idx]?.classList.add('highlighted'); visible[idx]?.scrollIntoView({block:'nearest'}); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); if (cur) cur.classList.remove('highlighted'); idx = idx<=0?visible.length-1:idx-1; visible[idx]?.classList.add('highlighted'); visible[idx]?.scrollIntoView({block:'nearest'}); }
        else if (e.key === 'Enter') { e.preventDefault(); if (cur) cur.click(); }
        else if (e.key === 'Escape') { dropdown.classList.remove('show'); }
    });
}

/* ── 태그 선택 ── */
function initTagSelect(container, opts = {}) {
    const tags = container.querySelectorAll('.wz-tag');
    const max = opts.max || Infinity;
    const selected = new Set();

    tags.forEach(tag => {
        tag.addEventListener('click', () => {
            const val = tag.dataset.value;
            if (selected.has(val)) { selected.delete(val); tag.classList.remove('selected'); }
            else if (selected.size < max) { selected.add(val); tag.classList.add('selected'); }
            if (opts.onChange) opts.onChange([...selected]);
        });
    });

    return { getSelected: () => [...selected] };
}

/* ── 유틸리티 ── */
function wzEsc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }
function wzAlert(msg) { alert(msg); }
