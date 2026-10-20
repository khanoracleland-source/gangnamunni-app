<?php
/**
 * 검색 오버레이 (Claude 디자인 App03_07: 최근 검색어 + 인기 검색어)
 * header.php 의 검색바(.header-search-wrap) 입력창을 누르면 열린다.
 * - 최근 검색어: 브라우저 localStorage('sb_recent_searches')
 * - 인기 검색어: gangnamunni_settings.popular_search_keywords (줄바꿈/쉼표 구분) → 없으면 기본 목록
 */
$_pk_default = ['리쥬란','텐써마','울쎄라','보톡스','슈링크','쥬베룩','아이 필러','물광주사'];
$_pk = [];
if (isset($connect) && $connect) {
    $_pkq = @mysqli_query($connect, "SELECT setting_value FROM gangnamunni_settings WHERE setting_key='popular_search_keywords' LIMIT 1");
    if ($_pkq && ($_pkr = mysqli_fetch_assoc($_pkq))) {
        foreach (preg_split('/[\r\n,]+/', (string)$_pkr['setting_value']) as $w) {
            $w = trim($w);
            if ($w !== '') $_pk[] = $w;
        }
    }
}
if (!$_pk) $_pk = $_pk_default;
$_pk = array_slice($_pk, 0, 10);
?>
<style>
#searchOverlay{display:none;position:fixed;inset:0;z-index:100000;background:#fff;overflow-y:auto;font-family:'Noto Sans KR',sans-serif;color:#1A1718;letter-spacing:-0.01em}
#searchOverlay.open{display:block}
#searchOverlay .so-inner{max-width:640px;margin:0 auto}
#searchOverlay .so-bar{height:52px;padding:0 16px 0 8px;display:flex;align-items:center;gap:4px;margin-top:12px}
#searchOverlay .so-back{width:40px;height:44px;display:flex;align-items:center;justify-content:center;background:none;border:0;color:#1A1718;padding:0;cursor:pointer}
#searchOverlay .so-field{flex:1;min-width:0;height:42px;box-sizing:border-box;border-bottom:1.5px solid #1A1718;display:flex;align-items:center;gap:8px}
#searchOverlay .so-input{flex:1;min-width:0;border:0;outline:0;background:transparent;font-size:15px;color:#1A1718;font-family:inherit;padding:0}
#searchOverlay .so-input::placeholder{color:#9A9395}
#searchOverlay .so-region{height:36px;padding:0 14px 0 10px;margin-bottom:-1px;display:inline-flex;align-items:center;gap:6px;background:#2A1416;color:#fff;border:0;border-radius:18px;font-size:14px;font-weight:700;white-space:nowrap;flex-shrink:0;cursor:pointer;font-family:inherit}
#searchOverlay .so-region svg{width:18px;height:18px;display:block;flex-shrink:0}
#searchOverlay .so-head{padding:18px 20px 10px;display:flex;align-items:center}
#searchOverlay .so-head .t{flex:1;font-size:16px;font-weight:700}
#searchOverlay .so-head .clear{font-size:13px;color:#6B6466;background:none;border:0;padding:0;cursor:pointer;font-family:inherit}
#searchOverlay .so-chips{padding:0 20px 22px;display:flex;flex-wrap:wrap;gap:8px}
#searchOverlay .so-chip{height:32px;padding:0 12px;display:inline-flex;align-items:center;gap:6px;border:1px solid #DCD6D8;border-radius:16px;font-size:14px;background:#fff;color:#1A1718;font-family:inherit;cursor:pointer}
#searchOverlay .so-chip svg{width:12px;height:12px;display:block;flex-shrink:0}
#searchOverlay .so-empty{padding:0 20px 22px;font-size:13px;color:#9A9395}
#searchOverlay .so-gap{height:8px;background:#F4F2F3}
#searchOverlay .so-head2{padding:20px 20px 6px;display:flex;align-items:baseline}
#searchOverlay .so-head2 .t{flex:1;font-size:16px;font-weight:700}
#searchOverlay .so-head2 .d{font-size:12px;color:#9A9395}
#searchOverlay .so-rank{padding:0 20px 40px}
#searchOverlay .so-rank button{width:100%;height:44px;display:flex;align-items:center;gap:14px;border:0;border-bottom:1px solid #ECE9EA;background:none;text-align:left;font-size:15px;color:#1A1718;font-family:inherit;padding:0;cursor:pointer}
#searchOverlay .so-rank .n{width:18px;font-weight:700;color:#6B6466}
#searchOverlay .so-rank .top .n{color:#AC1830}
#searchOverlay .so-clr{width:18px;height:18px;flex:0 0 18px;border-radius:9px;background:#C9C3C5;border:0;padding:0;display:none;align-items:center;justify-content:center;cursor:pointer}
#searchOverlay .so-clr svg{width:11px;height:11px}
#searchOverlay.typing .so-clr{display:flex}
#searchOverlay .so-input::-webkit-search-cancel-button{display:none}
#so-home{display:block}#so-suggest{display:none}
#searchOverlay.typing #so-home{display:none}#searchOverlay.typing #so-suggest{display:block}
#so-suggest .sg-t{padding:16px 20px 4px;font-size:13px;font-weight:700;color:#6B6466}
#so-suggest a{margin:0 20px;display:flex;align-items:center;gap:12px;border-bottom:1px solid #ECE9EA;text-decoration:none;color:#1A1718;font-size:15px}
#so-suggest a.tr{height:48px}
#so-suggest a.hp{padding:10px 0}
#so-suggest a b{color:#AC1830;font-weight:700}
#so-suggest .sg-ico{width:18px;height:18px;flex:0 0 18px}
#so-suggest .sg-hint{margin-left:auto;font-size:12px;color:#9A9395;flex-shrink:0}
#so-suggest .sg-logo{width:36px;height:36px;flex:0 0 36px;border:1px solid #ECE9EA;display:flex;align-items:center;justify-content:center}
#so-suggest .sg-sub{display:block;font-size:12px;color:#6B6466;margin-top:2px}
#so-suggest .sg-note{padding:14px 20px;font-size:12px;line-height:1.6;color:#9A9395}
#so-suggest .sg-empty{padding:30px 20px;font-size:14px;color:#6B6466;text-align:center}
#so-suggest .sg-none{display:flex;flex-direction:column;align-items:center;text-align:center;padding:90px 32px 30px}
#so-suggest .sg-none-ico{width:72px;height:72px;border-radius:36px;background:#FBEFF1;display:flex;align-items:center;justify-content:center;margin-bottom:18px}
#so-suggest .sg-none-t{font-size:18px;font-weight:700}
#so-suggest .sg-none-s{font-size:14px;line-height:1.6;color:#6B6466;margin-top:8px}
#so-suggest .sg-sugg{padding:0 20px}
#so-suggest .sg-sugg-t{font-size:15px;font-weight:700;margin-bottom:12px}
#so-suggest .sg-sugg-c{display:flex;flex-wrap:wrap;gap:8px}
#so-suggest .sg-sugg-c a{margin:0;height:32px;padding:0 12px;border:1px solid #DCD6D8;border-radius:16px;font-size:14px;display:inline-flex;align-items:center;border-bottom:1px solid #DCD6D8}
</style>
<div id="searchOverlay" role="dialog" aria-label="검색">
  <div class="so-inner">
    <div class="so-bar">
      <button type="button" class="so-back" aria-label="뒤로 가기" onclick="closeSearchOverlay()"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7"/></svg></button>
      <div class="so-field">
        <input type="search" id="so-keyword" class="so-input" enterkeyhint="search" autocomplete="off" placeholder="시술, 병원 이름을 검색해보세요">
        <button type="button" class="so-clr" id="so-clr" aria-label="지우기"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
        <button type="button" class="so-region" id="so-region" onclick="soOpenRegion()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21.5s7-6.1 7-11.8A7 7 0 0 0 5 9.7c0 5.7 7 11.8 7 11.8z"/><circle cx="12" cy="9.7" r="2.6"/></svg><span id="so-region-label">지역 선택</span></button>
      </div>
    </div>
    <div id="so-home">
    <div class="so-head"><span class="t">최근 검색어</span><button type="button" class="clear" id="so-clear" onclick="soClearAll()">전체 삭제</button></div>
    <div class="so-chips" id="so-chips"></div>
    <div class="so-gap"></div>
    <div class="so-head2"><span class="t">인기 검색어</span><span class="d"><?php echo date('m.d'); ?> 기준</span></div>
    <div class="so-rank">
      <?php foreach ($_pk as $i => $w): ?>
      <button type="button" class="<?php echo $i < 3 ? 'top' : ''; ?>" onclick="soSubmit(<?php echo htmlspecialchars(json_encode($w, JSON_UNESCAPED_UNICODE), ENT_QUOTES); ?>)"><span class="n"><?php echo $i + 1; ?></span><span><?php echo htmlspecialchars($w); ?></span></button>
      <?php endforeach; ?>
    </div>
    </div>
    <div id="so-suggest"></div>
  </div>
</div>
<script>
(function(){
  var KEY='sb_recent_searches', MAX=10;
  function load(){ try{ var a=JSON.parse(localStorage.getItem(KEY)||'[]'); return Array.isArray(a)?a:[]; }catch(e){ return []; } }
  function save(a){ try{ localStorage.setItem(KEY, JSON.stringify(a.slice(0,MAX))); }catch(e){} }
  window.sbAddRecentSearch=function(w){
    w=(w||'').trim(); if(!w) return;
    var a=load().filter(function(x){return x!==w;}); a.unshift(w); save(a);
  };
  var X='<svg viewBox="0 0 24 24" fill="none" stroke="#9A9395" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6l12 12M18 6L6 18"/></svg>';
  function render(){
    var a=load(), box=document.getElementById('so-chips'), clr=document.getElementById('so-clear');
    box.innerHTML='';
    if(!a.length){ box.innerHTML='<div class="so-empty">최근 검색어가 없습니다</div>'; clr.style.display='none'; return; }
    clr.style.display='';
    a.forEach(function(w){
      var c=document.createElement('span'); c.className='so-chip'; c.setAttribute('role','button');
      var t=document.createElement('span'); t.textContent=w; t.onclick=function(){ window.soSubmit(w); };
      var x=document.createElement('span'); x.innerHTML=X; x.style.display='inline-flex'; x.setAttribute('aria-label','삭제');
      x.onclick=function(e){ e.stopPropagation(); save(load().filter(function(v){return v!==w;})); render(); };
      c.appendChild(t); c.appendChild(x); box.appendChild(c);
    });
  }
  function regionLabel(){
    var r=[]; document.querySelectorAll('.region-cb:checked').forEach(function(cb){ r.push(cb.value); });
    return r.length ? (r.length>1 ? r[0]+' 외 '+(r.length-1) : r[0]) : '지역 선택';
  }
  window.openSearchOverlay=function(){
    var o=document.getElementById('searchOverlay'); if(!o) return;
    var hdr=document.getElementById('csb-keyword'), inp=document.getElementById('so-keyword');
    inp.value=hdr?hdr.value:'';
    document.getElementById('so-region-label').textContent=regionLabel();
    render(); o.classList.remove('typing'); o.classList.add('open'); if(inp.value.trim()) setTimeout(function(){ inp.dispatchEvent(new Event('input')); },0); document.documentElement.style.overflow='hidden';
    try { inp.focus({preventScroll:true}); } catch(e) { inp.focus(); }
    setTimeout(function(){ inp.focus(); },60);
  };
  window.closeSearchOverlay=function(){
    document.getElementById('searchOverlay').classList.remove('open'); document.documentElement.style.overflow='';
  };
  window.soClearAll=function(){ save([]); render(); };
  window.soSubmit=function(w){
    w=(w||'').trim(); if(!w) return;
    window.sbAddRecentSearch(w);
    document.getElementById('csb-keyword').value=w;
    closeSearchOverlay(); applyRegionFilter();
  };
  window.soOpenRegion=function(){
    var hdr=document.getElementById('csb-keyword'); hdr.value=document.getElementById('so-keyword').value;
    closeSearchOverlay(); toggleRegionPanel();
  };
  document.getElementById('so-keyword').addEventListener('keydown',function(e){
    if(e.key==='Enter'){ e.preventDefault(); var v=this.value.trim(); if(v){ window.soSubmit(v); } else { closeSearchOverlay(); applyRegionFilter(); } }
  });

  // ===== 자동완성 (03-08) =====
  var SUGG=<?php echo json_encode(array_slice($_pk, 0, 4), JSON_UNESCAPED_UNICODE); ?>;
  var sgTimer=null, sgSeq=0;
  function esc(t){ return String(t).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }
  function hl(name,q){ var i=name.toLowerCase().indexOf(q.toLowerCase()); if(i<0) return esc(name); return esc(name.slice(0,i))+'<b>'+esc(name.slice(i,i+q.length))+'</b>'+esc(name.slice(i+q.length)); }
  var ICO_T='<svg class="sg-ico" viewBox="0 0 24 24" fill="none" stroke="#AC1830" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/></svg>';
  var ICO_H='<span class="sg-logo"><svg class="sg-ico" viewBox="0 0 24 24" fill="none" stroke="#6B6466" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 21V7l8-4 8 4v14M9 21v-5h6v5M9 10h.01M15 10h.01"/></svg></span>';
  var ICO_C='<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9A9395" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="flex:0 0 16px"><path d="M9 5l7 7-7 7"/></svg>';
  function renderSuggest(d,q){
    var box=document.getElementById('so-suggest'), h='';
    if(d.treatments.length){ h+='<div class="sg-t">시술</div>'; d.treatments.forEach(function(t){
      h+='<a class="tr" href="'+esc(t.href)+'" data-w="'+esc(q)+'">'+ICO_T+'<span>'+hl(t.name,q)+'</span>'+(t.hint?'<span class="sg-hint">'+esc(t.hint)+'</span>':'')+'</a>'; }); }
    if(d.hospitals.length){ h+='<div class="sg-t">병원</div>'; d.hospitals.forEach(function(x){
      h+='<a class="hp" href="'+esc(x.href)+'" data-w="'+esc(x.name)+'">'+ICO_H+'<span style="flex:1;min-width:0"><span style="display:block">'+hl(x.name,q)+'</span><span class="sg-sub">'+esc((x.district?x.district+' · ':'')+x.address)+'</span></span>'+ICO_C+'</a>'; }); }
    if(d.offered && d.offered.items && d.offered.items.length){ h+='<div class="sg-t">'+esc(d.offered.treatment)+' 시술 병원</div>'; d.offered.items.forEach(function(x){
      h+='<a class="hp" href="'+esc(x.href)+'" data-w="'+esc(d.offered.treatment)+'">'+ICO_H+'<span style="flex:1;min-width:0"><span style="display:block">'+esc(x.name)+'</span><span class="sg-sub">'+esc((x.district?x.district+' · ':'')+x.address)+'</span></span><span style="font-size:14px;font-weight:700;color:#AC1830;flex:0 0 auto">'+Number(x.price).toLocaleString()+'<small style="font-size:11px;font-weight:400;color:#1A1718">원~</small></span></a>'; }); }
    var none = !d.treatments.length && !d.hospitals.length && !(d.offered && d.offered.items && d.offered.items.length);
    if(none){
      h='<div class="sg-none"><div class="sg-none-ico"><svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#AC1830" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/></svg></div>'
        +'<div class="sg-none-t">\''+esc(q)+'\' 검색 결과가 없어요</div><div class="sg-none-s">철자를 확인하거나 다른 단어로 검색해보세요</div></div>'
        +'<div class="sg-sugg"><div class="sg-sugg-t">이런 시술은 어떠세요?</div><div class="sg-sugg-c">'
        +SUGG.map(function(w){ return '<a href="hospitals.php?q='+encodeURIComponent(w)+'" data-w="'+esc(w)+'">'+esc(w)+'</a>'; }).join('')+'</div></div>';
    } else h+='<div class="sg-note">시술을 고르면 병원별 가격이, 병원을 고르면 그 병원의 시술이 보여요</div>';
    box.innerHTML=h;
    box.querySelectorAll('a').forEach(function(a){ a.addEventListener('click',function(){ window.sbAddRecentSearch(a.getAttribute('data-w')); }); });
  }
  function onType(){
    var inp=document.getElementById('so-keyword'), o=document.getElementById('searchOverlay'), q=inp.value.trim();
    if(!q){ o.classList.remove('typing'); return; }
    o.classList.add('typing');
    clearTimeout(sgTimer);
    sgTimer=setTimeout(function(){
      var my=++sgSeq;
      fetch('search_suggest.php?q='+encodeURIComponent(q),{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){ if(my===sgSeq) renderSuggest(d,q); }).catch(function(){});
    },160);
  }
  document.getElementById('so-keyword').addEventListener('input',onType);
  document.getElementById('so-clr').addEventListener('click',function(){ var i=document.getElementById('so-keyword'); i.value=''; onType(); i.focus(); });
  var h=document.getElementById('csb-keyword');
  if(h){
    h.setAttribute('readonly','readonly');
    h.addEventListener('click',openSearchOverlay);
    h.addEventListener('focus',function(){ openSearchOverlay(); });
  }
})();
</script>
