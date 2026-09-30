(() => {
  const $=s=>document.querySelector(s), $$=s=>[...document.querySelectorAll(s)];
  const form=$('#f'), submitBtn=$('#submitBtn');
  const TEST=new URLSearchParams(location.search).get('test')==='1';
  let ready=false, booting=false, enhancing=false;

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
    if(!TEST)return;
    for(let i=localStorage.length-1;i>=0;i--){
      const k=localStorage.key(i);
      if(/^hyun14-\d{4}-\d{2}-\d{2}$/.test(k||'')) localStorage.removeItem(k);
    }
  }

  function badge(){
    if(!TEST)return;
    let b=$('#tm23Badge');
    if(!b){b=document.createElement('div');b.id='tm23Badge';document.body.appendChild(b)}
    b.textContent='TEST MODE · V34';
    b.style.cssText='position:fixed;right:10px;top:calc(env(safe-area-inset-top) + 8px);z-index:99999;background:#8b2f27;color:#fff7eb;border:1px solid #d28a7d;padding:7px 10px;font:800 10px/1 sans-serif;letter-spacing:.05em;border-radius:999px';
  }

  function status(text,bad=false){
    let el=$('#boot34');
    if(!el){el=document.createElement('div');el.id='boot34';el.style.cssText='position:fixed;left:50%;bottom:calc(env(safe-area-inset-bottom) + 18px);transform:translateX(-50%);z-index:100000;max-width:88vw;padding:9px 13px;border:1px solid #5b4037;background:#100b09;color:#d8c8b8;font:700 11px/1.35 sans-serif;border-radius:9px;pointer-events:none;text-align:center';document.body.appendChild(el)}
    el.textContent=text;el.style.color=bad?'#ffb3a8':'#d8c8b8';
  }

  function showLoading(){
    ['landing','result','sales','checkout','paid'].forEach(id=>$('#'+id)?.classList.add('hide'));
    $('#loading')?.classList.remove('hide');scrollTo(0,0);
  }

  function loadModule(src,ms=7000){
    return new Promise((resolve,reject)=>{
      const s=document.createElement('script');s.type='module';s.src=src;
      const timer=setTimeout(()=>{s.remove();reject(new Error(src+' 로딩 시간 초과'))},ms);
      s.onload=()=>{clearTimeout(timer);resolve()};
      s.onerror=()=>{clearTimeout(timer);reject(new Error(src+' 로딩 실패'))};
      document.body.appendChild(s);
    });
  }

  function waitFlag(okKey,errKey,ms=5000){
    return new Promise((resolve,reject)=>{
      const st=Date.now(),t=setInterval(()=>{
        if(window[okKey]){clearInterval(t);resolve()}
        else if(window[errKey]){clearInterval(t);reject(new Error(window[errKey]))}
        else if(Date.now()-st>ms){clearInterval(t);reject(new Error('핵심 엔진 초기화 시간 초과'))}
      },50);
    });
  }

  function waitResult(ms=20000){
    return new Promise((resolve,reject)=>{
      const st=Date.now(),t=setInterval(()=>{
        if(!$('#result')?.classList.contains('hide')){clearInterval(t);resolve()}
        else if(Date.now()-st>ms){clearInterval(t);reject(new Error('첫 풀이 계산 시간 초과'))}
      },80);
    });
  }

  async function selfTest(){
    status('V34 · 로컬 만세력 확인 중');
    const M=await import('./v32-manseryeok-adapter.js');
    const r=M.calculateFourPillars({year:1992,month:10,day:24,hour:5,minute:30,gender:'female'});
    const got=[r.year.heavenlyStem+r.year.earthlyBranch,r.month.heavenlyStem+r.month.earthlyBranch,r.day.heavenlyStem+r.day.earthlyBranch,r.hour.heavenlyStem+r.hour.earthlyBranch].join('/');
    if(got!=='임신/경술/계유/을묘')throw new Error('만세력 자체검증 불일치: '+got);
  }

  function fail(e){
    console.error('[V34]',e);booting=false;
    $('#loading')?.classList.add('hide');$('#landing')?.classList.remove('hide');$('#consult')?.scrollIntoView({block:'start'});
    if(submitBtn){submitBtn.disabled=false;submitBtn.innerHTML='다시 시도하기 <span>→</span>'}
    status('V34 · '+(e?.message||'풀이 준비 실패'),true);
  }

  async function enhance(){
    if(enhancing)return;enhancing=true;
    const modules=[
      ['./v15-consumer.js','문장 정리'],
      ['./v32-report.js','무료 풀이'],
      ['./v32-paid.js','심층 풀이'],
      ['./v32-fix.js','시기 풀이'],
      ['./v20-sales.js','상세페이지'],
      ['./v32-manseryeok.js','만세력 화면'],
      ['./v34-testmode.js','테스트 도구']
    ];
    try{
      for(let i=0;i<modules.length;i++){
        const [src,label]=modules[i];
        if(TEST)status(`V34 · ${label} ${i+1}/${modules.length}`);
        await loadModule(src);
        await new Promise(r=>setTimeout(r,20));
      }
      if(TEST)setTimeout(()=>$('#boot34')?.remove(),300);
    }catch(e){
      console.error('[V34 enhance]',e);
      if(TEST){status('V34 · 기본 풀이는 정상 · 부가 화면 일부 실패',true);setTimeout(()=>$('#boot34')?.remove(),3500)}
    }
  }

  async function boot(){
    if(booting||ready)return;
    booting=true;clearQuota();showLoading();status('V34 · 핵심 풀이 준비 중');
    if(submitBtn){submitBtn.disabled=true;submitBtn.textContent='풀이 준비 중'}
    const hour=$('#hour'),chosen=hour?.value||'12';
    try{
      await selfTest();
      if(hour)hour.innerHTML='';
      await loadModule('./v34-engine.js');
      await waitFlag('__HYUN_V34_ENGINE_READY','__HYUN_V34_ENGINE_ERROR');
      if(hour)hour.value=chosen;
      ready=true;booting=false;
      if(submitBtn){submitBtn.disabled=false;submitBtn.innerHTML='현월당 첫 풀이 보기 <span>→</span>'}
      status('V34 · 첫 풀이 계산 중');
      form.requestSubmit();
      await waitResult();
      $('#boot34')?.remove();
      setTimeout(enhance,80);
    }catch(e){fail(e)}
  }

  function gate(e){if(ready)return;e.preventDefault();e.stopImmediatePropagation();boot()}
  setup();clearQuota();badge();form?.addEventListener('submit',gate,true);
})();