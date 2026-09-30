(() => {
  const $=s=>document.querySelector(s), $$=s=>[...document.querySelectorAll(s)];
  const form=$('#f'), submitBtn=$('#submitBtn');
  let ready=false, booting=false;

  function shellSetup(){
    const hour=$('#hour');
    if(hour && !hour.options.length){
      for(let i=0;i<24;i++) hour.insertAdjacentHTML('beforeend', `<option value="${i}" ${i===12?'selected':''}>${String(i).padStart(2,'0')}시</option>`);
    }
    $$('.sex').forEach(b=>b.onclick=()=>{$$('.sex').forEach(x=>x.classList.remove('on'));b.classList.add('on')});
    $$('.cal').forEach(b=>b.onclick=()=>{$$('.cal').forEach(x=>x.classList.remove('on'));b.classList.add('on');$('#calendar').value=b.dataset.cal;$('#leapWrap').classList.toggle('hide',b.dataset.cal!=='lunar')});
    if($('#unknown')) $('#unknown').onchange=e=>{if($('#hour'))$('#hour').disabled=e.target.checked;if($('#minute'))$('#minute').disabled=e.target.checked};
    const enter=$('#enterBtn');
    if(enter) enter.onclick=e=>{e.preventDefault();$('#consult')?.scrollIntoView({behavior:'smooth',block:'start'});history.replaceState(null,'','#consult')};
  }

  function clearDailyQuota(){
    if(new URLSearchParams(location.search).get('test')!=='1') return;
    for(let i=localStorage.length-1;i>=0;i--){
      const k=localStorage.key(i);
      if(/^hyun14-\d{4}-\d{2}-\d{2}$/.test(k||'')) localStorage.removeItem(k);
    }
  }

  function installBuildBadge(){
    if(new URLSearchParams(location.search).get('test')!=='1') return;
    let b=$('#tm23Badge');
    if(!b){b=document.createElement('div');b.id='tm23Badge';document.body.appendChild(b)}
    b.textContent='TEST MODE · V32';
    b.style.cssText='position:fixed;right:10px;top:calc(env(safe-area-inset-top) + 8px);z-index:99999;background:#8b2f27;color:#fff7eb;border:1px solid #d28a7d;padding:7px 10px;font:800 10px/1 sans-serif;letter-spacing:.06em;border-radius:999px;box-shadow:0 3px 14px rgba(0,0,0,.35)';
  }

  function status(text,bad=false){
    let el=$('#boot32');
    if(!el){
      el=document.createElement('div');el.id='boot32';
      el.style.cssText='position:fixed;left:50%;bottom:calc(env(safe-area-inset-bottom) + 18px);transform:translateX(-50%);z-index:100000;max-width:88vw;padding:9px 13px;border:1px solid #5b4037;background:#100b09;color:#d8c8b8;font:700 11px/1.35 sans-serif;border-radius:9px;pointer-events:none;text-align:center';
      document.body.appendChild(el);
    }
    el.textContent=text;el.style.color=bad?'#ffb3a8':'#d8c8b8';
  }

  function showBootScreen(){
    ['landing','result','sales','checkout','paid'].forEach(id=>$('#'+id)?.classList.add('hide'));
    $('#loading')?.classList.remove('hide');
    scrollTo(0,0);
  }

  function promiseTimeout(p,ms,label){
    return Promise.race([p,new Promise((_,reject)=>setTimeout(()=>reject(new Error(label+' 시간 초과')),ms))]);
  }

  function loadModule(src,label){
    status('V32 · '+label);
    return new Promise((resolve,reject)=>{
      const s=document.createElement('script');s.type='module';s.src=src;
      const timer=setTimeout(()=>{s.remove();reject(new Error(src+' 로딩 시간 초과'))},7000);
      s.onload=()=>{clearTimeout(timer);resolve()};
      s.onerror=()=>{clearTimeout(timer);reject(new Error(src+' 로딩 실패'))};
      document.body.appendChild(s);
    });
  }

  function waitEngine(ms=5000){
    return new Promise((resolve,reject)=>{
      const st=Date.now();
      const t=setInterval(()=>{
        if(window.__HYUN_V32_ENGINE_READY){clearInterval(t);resolve();return}
        if(window.__HYUN_V32_ENGINE_ERROR){clearInterval(t);reject(new Error(window.__HYUN_V32_ENGINE_ERROR));return}
        if(Date.now()-st>ms){clearInterval(t);reject(new Error('핵심 엔진 초기화 시간 초과'))}
      },50);
    });
  }

  async function selfTest(){
    status('V32 · 로컬 만세력 확인 중');
    const M=await promiseTimeout(import('./v32-manseryeok-adapter.js'),5000,'로컬 만세력');
    const r=M.calculateFourPillars({year:1992,month:10,day:24,hour:5,minute:30,gender:'female'});
    const got=[r.year.heavenlyStem+r.year.earthlyBranch,r.month.heavenlyStem+r.month.earthlyBranch,r.day.heavenlyStem+r.day.earthlyBranch,r.hour.heavenlyStem+r.hour.earthlyBranch].join('/');
    if(got!=='임신/경술/계유/을묘') throw new Error('만세력 자체검증 불일치: '+got);
  }

  function fail(err){
    console.error('[V32]',err);
    booting=false;
    if(submitBtn){submitBtn.disabled=false;submitBtn.innerHTML='다시 시도하기 <span>→</span>'}
    $('#loading')?.classList.add('hide');
    $('#landing')?.classList.remove('hide');
    $('#consult')?.scrollIntoView({block:'start'});
    status('V32 · '+(err?.message||'풀이 준비 실패'),true);
  }

  async function boot(){
    if(booting||ready) return;
    booting=true;clearDailyQuota();showBootScreen();
    if(submitBtn){submitBtn.disabled=true;submitBtn.textContent='풀이 준비 중'}
    const hour=$('#hour'), chosenHour=hour?.value||'12';

    try{
      await selfTest();
      if(hour) hour.innerHTML='';
      await loadModule('./v32-engine.js','핵심 풀이 준비 1/8');
      await waitEngine();
      if(hour) hour.value=chosenHour;

      const modules=[
        ['./v15-consumer.js','문장 정리 2/8'],
        ['./v32-report.js','무료 풀이 준비 3/8'],
        ['./v32-paid.js','심층 풀이 준비 4/8'],
        ['./v32-fix.js','시기 풀이 준비 5/8'],
        ['./v20-sales.js','상세페이지 준비 6/8'],
        ['./v32-manseryeok.js','만세력 화면 준비 7/8'],
        ['./v32-testmode.js','테스트 도구 준비 8/8']
      ];
      for(const [src,label] of modules) await loadModule(src,label);

      ready=true;booting=false;
      if(submitBtn){submitBtn.disabled=false;submitBtn.innerHTML='현월당 첫 풀이 보기 <span>→</span>'}
      $('#boot32')?.remove();
      form.requestSubmit();
    }catch(e){ fail(e); }
  }

  function gateSubmit(e){
    if(ready) return;
    e.preventDefault();e.stopImmediatePropagation();boot();
  }

  shellSetup();clearDailyQuota();installBuildBadge();
  if(form) form.addEventListener('submit',gateSubmit,true);
})();