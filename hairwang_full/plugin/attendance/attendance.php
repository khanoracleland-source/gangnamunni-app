<?php
include_once('./_common.php');
include_once('./_config.php');

$g5['title'] = '출석부';

$__att_is_guest        = !$is_member;
$__att_disabled_attr   = $__att_is_guest ? 'disabled' : '';
$__att_aria_attr       = $__att_is_guest ? 'aria-disabled="true" tabindex="-1"' : '';
$__att_mood_lock_class = $__att_is_guest ? 'is-disabled' : '';

include_once(G5_PATH.'/head.php');
?>
<link rel="stylesheet" href="./assets/style.css">
<div class="attendance-body">
  <div class="att-container">
    <div class="att-grid">
      <!-- 캘린더 카드 -->
      <section class="att-card">
        <h2 id="att-monthLabel"></h2>
        <div class="att-muted" id="att-todayLabel"></div>
        <div class="att-actions">
          <button id="att-prevBtn" class="att-btn">이전</button>
          <button id="att-todayBtn" class="att-btn">오늘</button>
          <button id="att-nextBtn" class="att-btn">다음</button>
        </div>

        <div class="att-summary">
          <div class="att-sum"><p class="att-sum-title">이번달 출석</p><p id="att-statMonthCount" class="att-sum-value">0일</p></div>
          <div class="att-sum"><p class="att-sum-title">연속 출석</p><p id="att-statStreak" class="att-sum-value">0일</p></div>
          <div class="att-sum"><p class="att-sum-title">최장 기록</p><p id="att-statBest" class="att-sum-value">0일</p></div>
        </div>

        <div class="att-weekhead"><div>일</div><div>월</div><div>화</div><div>수</div><div>목</div><div>금</div><div>토</div></div>
        <div id="att-calendarGrid" class="att-cal"></div>
      </section>

      <!-- 입력 & 리스트 -->
      <section class="att-form">
        <form id="att-greetingForm" class="att-card">
          <h3>오늘의 인사말</h3>
          <div class="att-muted">로그인한 회원만 출석 가능하며 포인트가 적립됩니다.</div>
          <input type="hidden" name="token" id="att-token" value="<?php echo get_token(); ?>">

          <?php if ($is_admin) { ?>
          <button id="att-adminBtn" class="att-fab" type="button" aria-label="출석 포인트 설정">
            <!-- gear svg -->
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 15C13.6569 15 15 13.6569 15 12C15 10.3431 13.6569 9 12 9C10.3431 9 9 10.3431 9 12C9 13.6569 10.3431 15 12 15Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M18.7273 14.7273C18.6063 15.0015 18.5702 15.3056 18.6236 15.6005C18.6771 15.8954 18.8177 16.1676 19.0273 16.3818L19.0818 16.4364C19.2509 16.6052 19.385 16.8057 19.4765 17.0265C19.568 17.2472 19.6151 17.4838 19.6151 17.7227C19.6151 17.9617 19.568 18.1983 19.4765 18.419C19.385 18.6397 19.2509 18.8402 19.0818 19.0091C18.913 19.1781 18.7124 19.3122 18.4917 19.4037C18.271 19.4952 18.0344 19.5423 17.7955 19.5423C17.5565 19.5423 17.3199 19.4952 17.0992 19.4037C16.8785 19.3122 16.678 19.1781 16.5091 19.0091L16.4545 18.9545C16.2403 18.745 15.9682 18.6044 15.6733 18.5509C15.3784 18.4974 15.0742 18.5335 14.8 18.6545C14.5311 18.7698 14.3018 18.9611 14.1403 19.205C13.9788 19.4489 13.8921 19.7347 13.8909 20.0273V20.1818C13.8909 20.664 13.6994 21.1265 13.3584 21.4675C13.0174 21.8084 12.5549 22 12.0727 22C11.5905 22 11.1281 21.8084 10.7871 21.4675C10.4461 21.1265 10.2545 20.664 10.2545 20.1818V20.1C10.2475 19.7991 10.1501 19.5073 9.97501 19.2625C9.79991 19.0176 9.55521 18.8312 9.27273 18.7273C8.99853 18.6063 8.69437 18.5702 8.39947 18.6236C8.10456 18.6771 7.83244 18.8177 7.61818 19.0273L7.56364 19.0818C7.39478 19.2509 7.19425 19.385 6.97353 19.4765C6.7528 19.568 6.51621 19.6151 6.27727 19.6151C6.03834 19.6151 5.80174 19.568 5.58102 19.4765C5.36029 19.385 5.15977 19.2509 4.99091 19.0818C4.82186 18.913 4.68775 18.7124 4.59626 18.4917C4.50476 18.271 4.45766 18.0344 4.45766 17.7955C4.45766 17.5565 4.50476 17.3199 4.59626 17.0992C4.68775 16.8785 4.82186 16.678 4.99091 16.5091L5.04545 16.4545C5.25503 16.2403 5.39562 15.9682 5.4491 15.6733C5.50257 15.3784 5.46647 15.0742 5.34545 14.8C5.23022 14.5311 5.03887 14.3018 4.79497 14.1403C4.55107 13.9788 4.26526 13.8921 3.97273 13.8909H3.81818C3.33597 13.8909 2.87351 13.6994 2.53253 13.3584C2.19156 13.0174 2 12.5549 2 12.0727C2 11.5905 2.19156 11.1281 2.53253 10.7871C2.87351 10.4461 3.33597 10.2545 3.81818 10.2545H3.9C4.2009 10.2475 4.49273 10.1501 4.73754 9.97501C4.98236 9.79991 5.16883 9.55521 5.27273 9.27273C5.39374 8.99853 5.42984 8.69437 5.37637 8.39947C5.3229 8.10456 5.18231 7.83244 4.97273 7.61818L4.91818 7.56364C4.74913 7.39478 4.61503 7.19425 4.52353 6.97353C4.43203 6.7528 4.38493 6.51621 4.38493 6.27727C4.38493 6.03834 4.43203 5.80174 4.52353 5.58102C4.61503 5.36029 4.74913 5.15977 4.91818 4.99091C5.08704 4.82186 5.28757 4.68775 5.50829 4.59626C5.72901 4.50476 5.96561 4.45766 6.20455 4.45766C6.44348 4.45766 6.68008 4.50476 6.9008 4.59626C7.12152 4.68775 7.32205 4.82186 7.49091 4.99091L7.54545 5.04545C7.75971 5.25503 8.03183 5.39562 8.32674 5.4491C8.62164 5.50257 8.9258 5.46647 9.2 5.34545H9.27273C9.54161 5.23022 9.77093 5.03887 9.93245 4.79497C10.094 4.55107 10.1807 4.26526 10.1818 3.97273V3.81818C10.1818 3.33597 10.3734 2.87351 10.7144 2.53253C11.0553 2.19156 11.5178 2 12 2C12.4822 2 12.9447 2.19156 13.2856 2.53253C13.6266 2.87351 13.8182 3.33597 13.8182 3.81818V3.9C13.8193 4.19253 13.906 4.47834 14.0676 4.72224C14.2291 4.96614 14.4584 5.15749 14.7273 5.27273C15.0015 5.39374 15.3056 5.42984 15.6005 5.37637C15.8954 5.3229 16.1676 5.18231 16.3818 4.97273L16.4364 4.91818C16.6052 4.74913 16.8057 4.61503 17.0265 4.52353C17.2472 4.43203 17.4838 4.38493 17.7227 4.38493C17.9617 4.38493 18.1983 4.43203 18.419 4.52353C18.6397 4.61503 18.8402 4.74913 19.0091 4.91818C19.1781 5.08704 19.3122 5.28757 19.4037 5.50829C19.4952 5.72901 19.5423 5.96561 19.5423 6.20455C19.5423 6.44348 19.4952 6.68008 19.4037 6.9008C19.3122 7.12152 19.1781 7.32205 19.0091 7.49091L18.9545 7.54545C18.745 7.75971 18.6044 8.03183 18.5509 8.32674C18.4974 8.62164 18.5335 8.9258 18.6545 9.2V9.27273C18.7698 9.54161 18.9611 9.77093 19.205 9.93245C19.4489 10.094 19.7347 10.1807 20.0273 10.1818H20.1818C20.664 10.1818 21.1265 10.3734 21.4675 10.7144C21.8084 11.0553 22 11.5178 22 12C22 12.4822 21.8084 12.9447 21.4675 13.2856C21.1265 13.6266 20.664 13.8182 20.1818 13.8182H20.1C19.8075 13.8193 19.5217 13.906 19.2778 14.0676C19.0339 14.2291 18.8425 14.4584 18.7273 14.7273Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>
          <?php } ?>

          <!-- Mood buttons -->
          <div class="mood-wrap" id="att-moods">
            <div class="mood-btn <?php echo $__att_mood_lock_class; ?>" <?php echo $__att_disabled_attr; ?> <?php echo $__att_aria_attr; ?> data-mood="🙂 보통">🙂 보통</div>
            <div class="mood-btn <?php echo $__att_mood_lock_class; ?>" <?php echo $__att_disabled_attr; ?> <?php echo $__att_aria_attr; ?> data-mood="😄 최고">😄 최고</div>
            <div class="mood-btn <?php echo $__att_mood_lock_class; ?>" <?php echo $__att_disabled_attr; ?> <?php echo $__att_aria_attr; ?> data-mood="😌 여유">😌 여유</div>
            <div class="mood-btn <?php echo $__att_mood_lock_class; ?>" <?php echo $__att_disabled_attr; ?> <?php echo $__att_aria_attr; ?> data-mood="😮 바쁨">😮 바쁨</div>
            <div class="mood-btn <?php echo $__att_mood_lock_class; ?>" <?php echo $__att_disabled_attr; ?> <?php echo $__att_aria_attr; ?> data-mood="😴 피곤">😴 피곤</div>
          </div>
          <input type="hidden" id="att-selectedMood" name="mood">

          <div style="margin-top:12px">
            <label for="att-message" class="att-label">인사말</label>
            <textarea id="att-message" name="message" rows="3" maxlength="120" placeholder="오늘의 한마디" class="att-textarea" <?php echo $__att_disabled_attr; ?>></textarea></textarea>
            <div class="att-count"><span id="att-charCount">0</span>/120</div>
          </div>

          <div class="att-quickwrap">
            <button type="button" class="att-chip" <?php echo $__att_disabled_attr; ?> <?php echo $__att_aria_attr; ?>>좋은 하루 되세요!</button>
            <button type="button" class="att-chip" <?php echo $__att_disabled_attr; ?> <?php echo $__att_aria_attr; ?>>비도 피해없이 ☔</button>
            <button type="button" class="att-chip" <?php echo $__att_disabled_attr; ?> <?php echo $__att_aria_attr; ?>>화이팅! 💪</button>
          </div>

          <?php
          // 로그인 후 돌아올 URL (현재 화면)
          $return_url = urlencode($_SERVER['REQUEST_URI']);
          $login_href = G5_BBS_URL . '/login.php?url=' . $return_url;
          ?>

          <?php if (!$is_member) { ?>
            <!-- 비회원: 로그인 버튼 (폼 submit 방지: type="button") -->
            <button type="button" class="att-submit" onclick="location.href='<?php echo $login_href; ?>'">
              로그인
            </button>
          <?php } else { ?>
            <!-- 회원: 기존 출석체크 버튼 -->
            <button class="att-submit">출석 체크</button>
          <?php } ?>
        </form>

        <div class="att-card">
          <div class="att-listhead">
            <h3 id="att-listTitle">오늘의 인사</h3>
            <select id="att-listScope" class="att-scope">
              <option value="day">선택한 날짜</option>
              <option value="month">이번 달 전체</option>
            </select>
          </div>
          <!-- 닉네임/내용 검색 input 제거됨 -->
          <ul id="att-greetingList" class="att-list"></ul>
        </div>
      </section>

      <?php if ($is_admin) { ?>
      <!-- 관리자 설정 모달 -->
      <div id="att-adminModal" class="att-modal" aria-hidden="true">
        <div class="att-modal-dlg" role="dialog" aria-modal="true" aria-labelledby="att-adminTitle">
          <div class="att-modal-head">
            <h3 id="att-adminTitle">출석 포인트 설정</h3>
            <button type="button" class="att-modal-close" aria-label="닫기">✕</button>
          </div>
          <div class="att-modal-body">
            <div class="att-row">
              <div>
                <label class="att-label">1회 출석 포인트</label>
                <input type="number" id="set-daily-pt" class="att-input" min="0" value="0">
              </div>
            </div>
            <div class="att-row">
              <div>
                <label class="att-label">누적 N일마다 지급 (N)</label>
                <input type="number" id="set-every-n" class="att-input" min="0" value="0">
              </div>
              <div>
                <label class="att-label">누적 N일 달성 포인트</label>
                <input type="number" id="set-every-pt" class="att-input" min="0" value="0">
              </div>
            </div>
            <div class="att-row">
              <div>
                <label class="att-label">연속 일수(기본 7)</label>
                <input type="number" id="set-streak-n" class="att-input" min="0" value="7">
              </div>
              <div>
                <label class="att-label">연속 달성 포인트</label>
                <input type="number" id="set-streak-pt" class="att-input" min="0" value="0">
              </div>
            </div>
            <p class="att-muted" style="margin-top:8px">0으로 설정하면 해당 항목은 비활성화됩니다.</p>
            <input type="hidden" id="att-adminToken" value="<?php echo get_token(); ?>">
          </div>
          <div class="att-modal-foot">
            <button type="button" id="att-adminSave" class="att-btn">저장</button>
          </div>
        </div>
      </div>
      <?php } ?>
    </div>
  </div>
</div>

<script>
  // Utils
  const pad = n => n.toString().padStart(2,'0');
  const toISO = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
  const fromISO = s => { const [y,m,day] = s.split('-').map(Number); return new Date(y, m-1, day); };
  const moodEmoji = mood => (mood||'').split(' ')[0] || '🙂';

  const state = { viewDate: new Date(), selectedDate: new Date(), isAdmin:false };
  let monthDays = new Set();

  function monthLabel(d){ return `${d.getFullYear()}년 ${d.getMonth()+1}월`; }
  function buildMonthDays(d){
    const y=d.getFullYear(), m=d.getMonth();
    const first=new Date(y,m,1), last=new Date(y,m+1,0);
    const startIdx=first.getDay(), total=last.getDate();
    const days=[];
    const prevLast=new Date(y,m,0).getDate();
    for(let i=startIdx-1;i>=0;i--) days.push({date:new Date(y,m-1, prevLast-i), inMonth:false});
    for(let i=1;i<=total;i++) days.push({date:new Date(y,m,i), inMonth:true});
    while(days.length<42){ const n=new Date(days[days.length-1].date); n.setDate(n.getDate()+1); days.push({date:n, inMonth:false}); }
    return days;
  }

  async function fetchStats(){
    const r = await fetch('./attendance.ajax.php?action=stats');
    const j = await r.json();
    if(j.ok){
      document.getElementById('att-statMonthCount').textContent = j.month + '일';
      document.getElementById('att-statBest').textContent = j.best + '일';
      document.getElementById('att-statStreak').textContent = j.streak + '일';
    }
  }

  async function fetchMonthDays(){
    const y = state.viewDate.getFullYear();
    const m = state.viewDate.getMonth() + 1;
    const ym = `${y}-${String(m).padStart(2,'0')}`;

    const url = new URL('./attendance.ajax.php', location.href);
    url.searchParams.set('action','days');
    url.searchParams.set('ym', ym);

    const res = await fetch(url);
    const j = await res.json();
    monthDays = new Set((j && j.ok && Array.isArray(j.days)) ? j.days : []);
  }  

  function renderCalendar(){
    const grid = document.getElementById('att-calendarGrid');
    grid.innerHTML='';
    const days = buildMonthDays(state.viewDate);
    const todayISO = toISO(new Date());
    const selectedISO = toISO(state.selectedDate);

    days.forEach(({date,inMonth})=>{
      const iso = toISO(date);
      const isToday = iso===todayISO;
      const isSelected = iso===selectedISO;
      const el = document.createElement('button');
      el.type='button';
      el.className = 'att-day' + (inMonth?'':' out') + (isSelected?' is-selected':'') + (isToday?' is-today':'');
      el.innerHTML = `<span class="att-date">${date.getDate()}</span>`;
      if (monthDays.has(iso)) {
        el.insertAdjacentHTML('beforeend','<span class="att-dot" aria-hidden="true"></span>');
      }
      el.addEventListener('click', ()=>{
        state.selectedDate = date;
        updateList();
        renderCalendar();
      });
      grid.appendChild(el);
    });

    document.getElementById('att-monthLabel').textContent = monthLabel(state.viewDate);
    const t = new Date();
    document.getElementById('att-todayLabel').textContent = `${t.getFullYear()}-${pad(t.getMonth()+1)}-${pad(t.getDate())} (${['일','월','화','수','목','금','토'][t.getDay()]})`;

    fetchStats();
  }

  async function updateList(){
    const ul = document.getElementById('att-greetingList');
    ul.innerHTML='';
    const scope = document.getElementById('att-listScope').value;
    // 닉네임/내용 검색 제거됨
    const kw = '';
    const selISO = toISO(state.selectedDate);

    const url = new URL('./attendance.ajax.php', location.href);
    url.searchParams.set('action','list');
    url.searchParams.set('scope', scope);
    url.searchParams.set('date', selISO);
    // if (kw) url.searchParams.set('kw', kw); // 검색 파라미터 제거

    const res = await fetch(url);
    const j = await res.json();

    state.isAdmin = !!j.is_admin;
    document.getElementById('att-listTitle').textContent = scope==='day' ? `${selISO} 인사` : '이번 달 인사';

    if(!j.ok || !j.rows || j.rows.length===0){
      ul.innerHTML = '<li class="att-muted">아직 인사말이 없습니다.</li>';
      return;
    }

    for(const g of j.rows){
      const li = document.createElement('li');
      li.className='att-item';
      const delBtn = state.isAdmin ? `<button class="att-del" data-id="${g.id}">삭제</button>` : '';
      li.innerHTML = `
        ${delBtn}
        <div class="att-itemrow">
          <div class="att-avatar">${moodEmoji(g.mood)}</div>
          <div style="flex:1">
            <div class="att-meta">
              <div class="att-name">${g.name || '회원'}</div>
              <div class="att-time">${g.date} · ${g.time}</div>
            </div>
            <div class="att-msg">${g.msg}</div>
          </div>
        </div>
      `;
      ul.appendChild(li);
    }

    if (state.isAdmin) {
      ul.querySelectorAll('.att-del').forEach(btn=>{
        btn.addEventListener('click', async ()=>{
          if(!confirm('삭제하시겠습니까?')) return;
          const form = new FormData();
          form.set('action','delete');
          form.set('id', btn.getAttribute('data-id'));
          form.set('token', document.getElementById('att-token').value);
          const r = await fetch('./attendance.ajax.php', { method:'POST', body: form });
          const jr = await r.json();
          if(jr.ok){ await fetchMonthDays(); renderCalendar(); updateList(); } else { alert(jr.msg || '삭제 실패'); }
        });
      });
    }
  }

  function bindEvents(){
    document.getElementById('att-prevBtn').addEventListener('click', async ()=>{
      state.viewDate.setMonth(state.viewDate.getMonth()-1);
      await fetchMonthDays();
      renderCalendar();
      updateList();
    });

    document.getElementById('att-nextBtn').addEventListener('click', async ()=>{
      state.viewDate.setMonth(state.viewDate.getMonth()+1);
      await fetchMonthDays();
      renderCalendar();
      updateList();
    });

    document.getElementById('att-todayBtn').addEventListener('click', async ()=>{
      const now = new Date();
      state.viewDate   = new Date(now.getFullYear(), now.getMonth(), 1);
      state.selectedDate = new Date();
      await fetchMonthDays();
      renderCalendar();
      updateList();
    });
    document.getElementById('att-listScope').addEventListener('change', updateList);
    document.getElementById('att-message').addEventListener('input', (e)=>{
      document.getElementById('att-charCount').textContent = e.target.value.length;
    });
    document.querySelectorAll('.att-chip').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const ta = document.getElementById('att-message');
        ta.value = btn.textContent.trim();
        document.getElementById('att-charCount').textContent = ta.value.length;
      });
    });

    const moods = document.getElementById('att-moods');
    const moodInput = document.getElementById('att-selectedMood');
    moods.querySelectorAll('.mood-btn').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        moods.querySelectorAll('.mood-btn').forEach(b=>b.classList.remove('active'));
        btn.classList.add('active');
        moodInput.value = btn.getAttribute('data-mood');
      });
    });

    document.getElementById('att-greetingForm').addEventListener('submit', async (e)=>{
      e.preventDefault();
      const mood = document.getElementById('att-selectedMood').value;
      const message = document.getElementById('att-message').value.trim();
      const token = document.getElementById('att-token').value;
      if(!mood){ alert('기분을 선택해주세요.'); return; }
      if(!message){ alert('인사말을 입력해주세요.'); return; }
      const form = new FormData();
      form.set('action','save'); form.set('mood', mood); form.set('message', message); form.set('token', token);
      const res = await fetch('./attendance.ajax.php', { method:'POST', body:form });
      const j = await res.json();
      if(j.ok){
        document.getElementById('att-message').value='';
        document.getElementById('att-charCount').textContent='0';
        // reset mood selection
        document.querySelectorAll('.mood-btn').forEach(b=>b.classList.remove('active'));
        document.getElementById('att-selectedMood').value='';
        state.selectedDate = new Date();
        await fetchMonthDays(); renderCalendar(); updateList();
      } else {
        alert(j.msg || '저장 오류');
      }
    });
  }

  async function adminOpen(){
    const r = await fetch('./attendance.ajax.php?action=get_settings');
    const j = await r.json();
    if(!j.ok){ alert(j.msg || '불러오기 실패'); return; }
    document.getElementById('set-daily-pt').value = j.daily_points ?? 0;
    document.getElementById('set-every-n').value  = j.every_days_n ?? 0;
    document.getElementById('set-every-pt').value = j.every_days_points ?? 0;
    document.getElementById('set-streak-n').value = j.streak_days ?? 7;
    document.getElementById('set-streak-pt').value= j.streak_points ?? 0;

    document.getElementById('att-adminModal').classList.add('show');
    document.getElementById('att-adminModal').setAttribute('aria-hidden','false');
  }

  async function adminSave(){
    const form = new FormData();
    form.set('action','save_settings');
    form.set('token', document.getElementById('att-adminToken').value);
    form.set('daily_points', parseInt(document.getElementById('set-daily-pt').value || '0', 10));
    form.set('every_days_n',  parseInt(document.getElementById('set-every-n').value || '0', 10));
    form.set('every_days_points', parseInt(document.getElementById('set-every-pt').value || '0', 10));
    form.set('streak_days',   parseInt(document.getElementById('set-streak-n').value || '0', 10));
    form.set('streak_points', parseInt(document.getElementById('set-streak-pt').value || '0', 10));

    const r = await fetch('./attendance.ajax.php', { method:'POST', body: form });
    const j = await r.json();
    if(!j.ok){ alert(j.msg || '저장 실패'); return; }
    alert('저장되었습니다.');
    adminClose();
  }

  function adminClose(){
    const m = document.getElementById('att-adminModal');
    m.classList.remove('show');
    m.setAttribute('aria-hidden','true');
  }

  // 이벤트 연결 (관리자만 버튼이 존재)
  document.getElementById('att-adminBtn')?.addEventListener('click', adminOpen);
  document.querySelector('.att-modal-close')?.addEventListener('click', adminClose);
  document.getElementById('att-adminSave')?.addEventListener('click', adminSave);
  document.getElementById('att-adminModal')?.addEventListener('click', (e)=>{
    if(e.target.id === 'att-adminModal') adminClose();
  });


  (function init(){
    bindEvents();
    fetchMonthDays().then(renderCalendar); // 먼저 가져오고 그리기
    updateList();
  })();
</script>
<?php
include_once(G5_PATH.'/tail.php');
