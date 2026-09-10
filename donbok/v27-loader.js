(() => {
  const MODULES = [
    './v14-engine.js',
    './v15-consumer.js',
    './v16-report.js',
    './v17-paid.js',
    './v18-fix.js',
    './v20-sales.js',
    './v22-manseryeok.js',
    './v23-testmode.js'
  ];

  const $ = s => document.querySelector(s);

  function jumpToForm(e){
    if(e) e.preventDefault();
    const el = $('#consult');
    if(el) el.scrollIntoView({behavior:'smooth', block:'start'});
    history.replaceState(null,'','#consult');
  }

  const enter = $('#enterBtn');
  if(enter){
    enter.addEventListener('click', jumpToForm, {capture:true});
  }

  function badge(text, bad=false){
    let el = $('#boot27');
    if(!el){
      el = document.createElement('div');
      el.id = 'boot27';
      el.style.cssText = 'position:fixed;left:10px;bottom:10px;z-index:100000;padding:6px 9px;border:1px solid #5b4037;background:#100b09;color:#b9a99a;font:700 10px/1.2 sans-serif;border-radius:8px;opacity:.9;pointer-events:none';
      document.body.appendChild(el);
    }
    el.textContent = text;
    el.style.color = bad ? '#ffb3a8' : '#b9a99a';
    if(text==='준비 완료') setTimeout(()=>el.remove(),1200);
  }

  function loadModule(src){
    return new Promise((resolve,reject)=>{
      const s=document.createElement('script');
      s.type='module';
      s.src=src;
      s.onload=()=>resolve();
      s.onerror=()=>reject(new Error(src+' load failed'));
      document.body.appendChild(s);
    });
  }

  async function boot(){
    badge('풀이 준비 중');
    for(const src of MODULES){
      try{
        await loadModule(src);
      }catch(err){
        console.error('[V27]',err);
        badge('일부 기능 로딩 실패',true);
        return;
      }
    }
    badge('준비 완료');
  }

  if(document.readyState==='complete'){
    setTimeout(boot,30);
  }else{
    window.addEventListener('load',()=>setTimeout(boot,30),{once:true});
  }
})();