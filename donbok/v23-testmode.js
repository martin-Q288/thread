const TEST23=new URLSearchParams(location.search).get('test')==='1';
if(TEST23){
  const $=s=>document.querySelector(s), $$=s=>[...document.querySelectorAll(s)];
  let autoPaid=false;
  function clearDailyQuota(){for(let i=localStorage.length-1;i>=0;i--){const k=localStorage.key(i);if(/^hyun14-\d{4}-\d{2}-\d{2}$/.test(k||''))localStorage.removeItem(k)}}
  function toast(msg){let t=$('#tm23Toast');if(!t){t=document.createElement('div');t.id='tm23Toast';document.body.appendChild(t)}t.textContent=msg;t.classList.add('on');clearTimeout(t._x);t._x=setTimeout(()=>t.classList.remove('on'),2200)}
  function topic(){return $('.hwTopic.on')?.textContent?.trim()||'선택한 주제'}
  function isCompatibility(){return topic()==='궁합'}
  function visible(id){const el=$(id);return el&&!el.classList.contains('hide')}
  function waitFor(fn,ms=3500){return new Promise((resolve,reject)=>{const st=Date.now();const x=setInterval(()=>{let v=null;try{v=fn()}catch{}if(v){clearInterval(x);resolve(v)}else if(Date.now()-st>ms){clearInterval(x);reject(new Error('timeout'))}},60)})}
  function patch(){
    $$('.hwBlurContent').forEach(el=>{el.style.filter='none';el.style.opacity='1';el.style.transform='none';el.style.pointerEvents='auto'});
    $$('.hwBlur').forEach(el=>{el.style.maxHeight='none';el.style.overflow='visible'});
    $$('.hwPaywall').forEach(el=>{const b=el.querySelector('button');if(b)b.textContent='테스트 · 상세페이지 보기 →';const sp=el.querySelector('span');if(sp)sp.textContent='TEST MODE'});
    $$('.hwEy').forEach(el=>{if(el.textContent.includes('무료 공개'))el.textContent=el.textContent.replace('무료 공개','테스트 보기')});
    const engine=$('#checkout .engine');if(engine)engine.textContent='TEST MODE · 실제 결제 없이 전체 풀이를 확인합니다.';
    const pay=$('#pay');if(pay)pay.textContent='테스트 · 32장 전체 풀이 열기';
    const buy=$('#salesBuy20');if(buy)buy.textContent='테스트 · 결제 없이 32장 보기';
    const toolbar=$('#tm23Bar');if(toolbar)toolbar.classList.toggle('show',visible('#result')||visible('#sales')||visible('#checkout')||visible('#paid'));
  }
  function showResult(){['landing','loading','sales','checkout','paid'].forEach(id=>$('#'+id)?.classList.add('hide'));$('#result')?.classList.remove('hide');scrollTo(0,0)}
  async function showSales(){if(isCompatibility()){toast('궁합은 상대방 정보 입력 기능을 먼저 구현해야 합니다.');return}if(visible('#sales')){scrollTo(0,0);return}if(!visible('#result'))showResult();const btn=$('.v16Unlock,#unlock');if(!btn){toast('먼저 보고 싶은 주제를 선택하세요.');return}btn.click();try{await waitFor(()=>visible('#sales')&&$('#salesBuy20'));scrollTo(0,0)}catch{toast('상세페이지를 열지 못했습니다.')}}
  async function showPaid(){if(isCompatibility()){toast('궁합은 아직 두 명식 비교 기능이 미구현입니다.');return}try{
      if(visible('#paid')){scrollTo(0,0);return}
      if(visible('#result')){const u=$('.v16Unlock,#unlock');if(!u){toast('먼저 보고 싶은 주제를 선택하세요.');return}u.click();await waitFor(()=>visible('#sales')&&$('#salesBuy20'))}
      if(visible('#sales')){$('#salesBuy20')?.click();await waitFor(()=>visible('#checkout')&&$('#pay'))}
      if(visible('#checkout')){$('#pay')?.click();await waitFor(()=>visible('#paid')&&$('#paidMount')?.children.length)}
      scrollTo(0,0);
    }catch(e){console.error(e);toast('전체 풀이 진입 중 오류가 났습니다.')}
  }
  function resetTest(){clearDailyQuota();location.href=location.pathname+'?test=1'}
  function install(){
    clearDailyQuota();document.documentElement.dataset.testMode='1';
    const style=document.createElement('style');style.textContent=`html[data-test-mode="1"] .hwBlurContent{filter:none!important;opacity:1!important;transform:none!important;pointer-events:auto!important}html[data-test-mode="1"] .hwBlur{max-height:none!important;overflow:visible!important}#tm23Badge{position:fixed;right:10px;top:calc(env(safe-area-inset-top) + 8px);z-index:99999;background:#8b2f27;color:#fff7eb;border:1px solid #d28a7d;padding:6px 9px;font:800 10px/1 sans-serif;letter-spacing:.08em;border-radius:999px;box-shadow:0 3px 14px rgba(0,0,0,.35)}#tm23Bar{position:fixed;left:50%;bottom:calc(env(safe-area-inset-bottom) + 12px);transform:translateX(-50%) translateY(120px);z-index:99998;width:min(94vw,620px);display:grid;grid-template-columns:repeat(4,1fr);gap:5px;background:rgba(12,8,7,.96);border:1px solid #624239;padding:7px;border-radius:13px;box-shadow:0 10px 30px rgba(0,0,0,.45);transition:.2s}#tm23Bar.show{transform:translateX(-50%) translateY(0)}#tm23Bar button{border:1px solid #5b4037;background:#211511;color:#eadbca;padding:10px 4px;border-radius:8px;font:800 11px/1.25 sans-serif}#tm23Bar button.hot{background:#7d2d25;border-color:#a34a3d;color:#fff}#tm23Toast{position:fixed;left:50%;bottom:90px;z-index:100000;transform:translate(-50%,20px);opacity:0;pointer-events:none;background:#1b110e;color:#f1e2d2;border:1px solid #71483d;padding:10px 14px;border-radius:9px;font:700 12px/1.5 sans-serif;transition:.2s;max-width:86vw;text-align:center}#tm23Toast.on{opacity:1;transform:translate(-50%,0)}@media(max-width:520px){#tm23Bar button{font-size:10px;padding:9px 2px}}`;
    document.head.appendChild(style);
    const badge=document.createElement('div');badge.id='tm23Badge';badge.textContent='TEST MODE';document.body.appendChild(badge);
    const bar=document.createElement('div');bar.id='tm23Bar';bar.innerHTML='<button id="tm23Free">무료 결과</button><button id="tm23Sales">상세페이지</button><button id="tm23Paid" class="hot">32장 전체</button><button id="tm23Reset">새 명식</button>';document.body.appendChild(bar);
    $('#tm23Free').onclick=showResult;$('#tm23Sales').onclick=showSales;$('#tm23Paid').onclick=showPaid;$('#tm23Reset').onclick=resetTest;
    document.addEventListener('submit',e=>{if(e.target?.id==='f')clearDailyQuota()},true);
    document.addEventListener('click',e=>{if(e.target.closest('#salesBuy20'))autoPaid=true},true);
    const obs=new MutationObserver(()=>{patch();if(autoPaid&&visible('#checkout')&&$('#pay')){autoPaid=false;setTimeout(()=>$('#pay')?.click(),80)}});obs.observe(document.body,{subtree:true,childList:true,attributes:true,attributeFilter:['class','style']});
    setInterval(()=>{clearDailyQuota();patch()},500);patch();toast('테스트 모드: 무료 제한과 블러가 해제됐습니다.');
  }
  install();
}
