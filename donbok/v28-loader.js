(() => {
  const $ = s => document.querySelector(s);
  const $$ = s => [...document.querySelectorAll(s)];
  const form = $('#f');
  const submitBtn = $('#submitBtn');
  let ready = false;
  let booting = false;
  let engineSubmitReady = false;

  function shellSetup(){
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

  function clearDailyQuota(){
    if(new URLSearchParams(location.search).get('test')!=='1') return;
    for(let i=localStorage.length-1;i>=0;i--){const k=localStorage.key(i);if(/^hyun14-\d{4}-\d{2}-\d{2}$/.test(k||''))localStorage.removeItem(k)}
  }

  function testBadge(){
    if(new URLSearchParams(location.search).get('test')!=='1') return;
    const b=document.createElement('div');b.textContent='TEST MODE';b.style.cssText='position:fixed;right:10px;top:10px;z-index:99999;background:#8b2f27;color:#fff7eb;border:1px solid #d28a7d;padding:6px 9px;font:800 10px/1 sans-serif;letter-spacing:.08em;border-radius:999px';document.body.appendChild(b);
  }

  function status(text,bad=false){
    let el=$('#boot28');
    if(!el){el=document.createElement('div');el.id='boot28';el.style.cssText='position:fixed;left:50%;bottom:18px;transform:translateX(-50%);z-index:100000;padding:8px 12px;border:1px solid #5b4037;background:#100b09;color:#d8c8b8;font:700 11px/1.3 sans-serif;border-radius:8px;pointer-events:none';document.body.appendChild(el)}
    el.textContent=text;el.style.color=bad?'#ffb3a8':'#d8c8b8';
  }

  function showBootScreen(){
    ['landing','result','sales','checkout','paid'].forEach(id=>$('#'+id)?.classList.add('hide'));
    $('#loading')?.classList.remove('hide');
    scrollTo(0,0);
  }

  function loadModule(src){
    return new Promise((resolve,reject)=>{const s=document.createElement('script');s.type='module';s.src=src;s.onload=resolve;s.onerror=()=>reject(new Error(src+' load failed'));document.body.appendChild(s)});
  }
  function waitEngine(ms=15000){return new Promise((resolve,reject)=>{const st=Date.now();const t=setInterval(()=>{if(engineSubmitReady){clearInterval(t);resolve()}else if(Date.now()-st>ms){clearInterval(t);reject(new Error('engine init timeout'))}},60)})}

  async function boot(){
    if(booting||ready)return;
    booting=true;clearDailyQuota();showBootScreen();status('풀이를 준비하고 있습니다');
    if(submitBtn){submitBtn.disabled=true;submitBtn.textContent='풀이 준비 중'}
    const hour=$('#hour');const chosenHour=hour?.value||'12';if(hour)hour.innerHTML='';

    const originalAdd=form.addEventListener.bind(form);
    form.addEventListener=function(type,listener,options){if(type==='submit'&&listener!==gateSubmit)engineSubmitReady=true;return originalAdd(type,listener,options)};
    try{
      await loadModule('./v14-engine.js');
      await waitEngine();
      if(hour){hour.value=chosenHour}
      for(const src of ['./v15-consumer.js','./v16-report.js','./v17-paid.js','./v18-fix.js','./v20-sales.js','./v22-manseryeok.js','./v23-testmode.js']) await loadModule(src);
      ready=true;booting=false;
      if(submitBtn){submitBtn.disabled=false;submitBtn.innerHTML='현월당 첫 풀이 보기 <span>→</span>'}
      $('#boot28')?.remove();
      form.requestSubmit();
    }catch(err){
      console.error('[V28]',err);booting=false;
      if(submitBtn){submitBtn.disabled=false;submitBtn.innerHTML='다시 시도하기 <span>→</span>'}
      ['loading'].forEach(id=>$('#'+id)?.classList.add('hide'));$('#landing')?.classList.remove('hide');$('#consult')?.scrollIntoView({block:'start'});
      status('풀이 기능을 불러오지 못했습니다. 다시 눌러주세요',true);
    }
  }

  function gateSubmit(e){
    if(ready)return;
    e.preventDefault();e.stopImmediatePropagation();boot();
  }

  shellSetup();clearDailyQuota();testBadge();
  if(form) form.addEventListener('submit',gateSubmit,true);
})();