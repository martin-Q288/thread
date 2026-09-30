(() => {
  const $=s=>document.querySelector(s), $$=s=>[...document.querySelectorAll(s)];
  const form=$('#f'), submitBtn=$('#submitBtn');
  let ready=false, booting=false;

  function setup(){
    const hour=$('#hour');
    if(hour && !hour.options.length){
      for(let i=0;i<24;i++) hour.insertAdjacentHTML('beforeend',`<option value="${i}" ${i===12?'selected':''}>${String(i).padStart(2,'0')}시</option>`);
    }
    $$('.sex').forEach(b=>b.onclick=()=>{$$('.sex').forEach(x=>x.classList.remove('on'));b.classList.add('on')});
    $$('.cal').forEach(b=>b.onclick=()=>{$$('.cal').forEach(x=>x.classList.remove('on'));b.classList.add('on');$('#calendar').value=b.dataset.cal;$('#leapWrap').classList.toggle('hide',b.dataset.cal!=='lunar')});
    if($('#unknown')) $('#unknown').onchange=e=>{if($('#hour'))$('#hour').disabled=e.target.checked;if($('#minute'))$('#minute').disabled=e.target.checked};
    const enter=$('#enterBtn');
    if(enter) enter.onclick=e=>{e.preventDefault();$('#consult')?.scrollIntoView({behavior:'smooth',block:'start'});history.replaceState(null,'','#consult')};
  }

  function clearQuota(){
    if(new URLSearchParams(location.search).get('test')!=='1') return;
    for(let i=localStorage.length-1;i>=0;i--){
      const k=localStorage.key(i);
      if(/^hyun14-\d{4}-\d{2}-\d{2}$/.test(k||'')) localStorage.removeItem(k);
    }
  }

  function badge(){
    if(new URLSearchParams(location.search).get('test')!=='1') return;
    const b=document.createElement('div');b.id='v33Badge';b.textContent='TEST MODE · V33 CORE';
    b.style.cssText='position:fixed;right:10px;top:calc(env(safe-area-inset-top) + 8px);z-index:99999;background:#8b2f27;color:#fff7eb;border:1px solid #d28a7d;padding:7px 10px;font:800 10px/1 sans-serif;letter-spacing:.05em;border-radius:999px';
    document.body.appendChild(b);
  }

  function status(text,bad=false){
    let el=$('#boot33');
    if(!el){el=document.createElement('div');el.id='boot33';el.style.cssText='position:fixed;left:50%;bottom:calc(env(safe-area-inset-bottom) + 18px);transform:translateX(-50%);z-index:100000;max-width:88vw;padding:9px 13px;border:1px solid #5b4037;background:#100b09;color:#d8c8b8;font:700 11px/1.35 sans-serif;border-radius:9px;pointer-events:none;text-align:center';document.body.appendChild(el)}
    el.textContent=text;el.style.color=bad?'#ffb3a8':'#d8c8b8';
  }

  function showLoading(){
    ['landing','result','sales','checkout','paid'].forEach(id=>$('#'+id)?.classList.add('hide'));
    $('#loading')?.classList.remove('hide');scrollTo(0,0);
  }

  function loadModule(src){
    return new Promise((resolve,reject)=>{
      const s=document.createElement('script');s.type='module';s.src=src;
      const timer=setTimeout(()=>{s.remove();reject(new Error('핵심 엔진 로딩 시간 초과'))},7000);
      s.onload=()=>{clearTimeout(timer);resolve()};
      s.onerror=()=>{clearTimeout(timer);reject(new Error('핵심 엔진 로딩 실패'))};
      document.body.appendChild(s);
    });
  }

  function waitReady(ms=5000){
    return new Promise((resolve,reject)=>{
      const st=Date.now(), t=setInterval(()=>{
        if(window.__HYUN_V33_ENGINE_READY){clearInterval(t);resolve()}
        else if(window.__HYUN_V33_ENGINE_ERROR){clearInterval(t);reject(new Error(window.__HYUN_V33_ENGINE_ERROR))}
        else if(Date.now()-st>ms){clearInterval(t);reject(new Error('핵심 엔진 초기화 시간 초과'))}
      },50);
    });
  }

  async function selfTest(){
    status('V33 · 로컬 만세력 확인 중');
    const M=await import('./v32-manseryeok-adapter.js');
    const r=M.calculateFourPillars({year:1992,month:10,day:24,hour:5,minute:30,gender:'female'});
    const got=[r.year.heavenlyStem+r.year.earthlyBranch,r.month.heavenlyStem+r.month.earthlyBranch,r.day.heavenlyStem+r.day.earthlyBranch,r.hour.heavenlyStem+r.hour.earthlyBranch].join('/');
    if(got!=='임신/경술/계유/을묘') throw new Error('만세력 자체검증 불일치: '+got);
  }

  function fail(e){
    console.error('[V33 CORE]',e);
    booting=false;
    $('#loading')?.classList.add('hide');$('#landing')?.classList.remove('hide');
    $('#consult')?.scrollIntoView({block:'start'});
    if(submitBtn){submitBtn.disabled=false;submitBtn.innerHTML='다시 시도하기 <span>→</span>'}
    status('V33 · '+(e?.message||'풀이 준비 실패'),true);
  }

  async function boot(){
    if(booting||ready)return;
    booting=true;clearQuota();showLoading();status('V33 · 핵심 풀이 준비 중');
    if(submitBtn){submitBtn.disabled=true;submitBtn.textContent='풀이 준비 중'}
    const hour=$('#hour'), chosen=hour?.value||'12';
    try{
      await selfTest();
      if(hour)hour.innerHTML='';
      await loadModule('./v33-engine.js');
      await waitReady();
      if(hour)hour.value=chosen;
      ready=true;booting=false;
      if(submitBtn){submitBtn.disabled=false;submitBtn.innerHTML='현월당 첫 풀이 보기 <span>→</span>'}
      status('V33 · 첫 풀이 계산 중');
      form.requestSubmit();
      const watch=setInterval(()=>{
        if(!$('#result')?.classList.contains('hide')){
          clearInterval(watch);$('#boot33')?.remove();
        }
      },100);
      setTimeout(()=>clearInterval(watch),30000);
    }catch(e){fail(e)}
  }

  function gate(e){if(ready)return;e.preventDefault();e.stopImmediatePropagation();boot()}
  setup();clearQuota();badge();
  form?.addEventListener('submit',gate,true);
})();