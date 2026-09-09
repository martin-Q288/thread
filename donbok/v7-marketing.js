const $=s=>document.querySelector(s),$$=s=>[...document.querySelectorAll(s)];
window.dataLayer=window.dataLayer||[];
const track=(event,extra={})=>window.dataLayer.push({event,...extra,hyunwoldang_version:'7.3.0'});

let pastViewed=false,answerViewed=false;
const readConcern=()=>({concern:$('#concern')?.value||'',label:$('#concernLabel')?.value||'',custom:$('#customQuestion')?.value.trim()||''});

/* 질문 분류 보정: 이사·부동산은 재물운과 분리 */
$$('.qbtn').forEach(b=>{if(b.dataset.label==='이사·부동산') b.dataset.concern='realestate';});

$('#f')?.addEventListener('submit',()=>{const q=readConcern();track('reading_start',{concern:q.concern,concern_label:q.label,has_custom_question:Boolean(q.custom)});});

const locked=$('.locked');
if(locked){
  const title=locked.querySelector('.lockBody h2');
  const desc=locked.querySelector('.lockBody p');
  const list=locked.querySelector('.lockedList');
  const buy=$('#buy');
  if(title) title.innerHTML='이제 앞으로의 흐름을<br>이어보겠습니다.';
  if(desc) desc.textContent='같은 사주를 바탕으로 언제 움직이고, 언제 기다리는 편이 나은지 이어서 풉니다.';
  if(list) list.innerHTML=`
    <div><b>직장에 더 있어야 할지, 나가야 할지</b><span>움직여도 되는 때와 기다리는 편이 나은 때</span></div>
    <div><b>돈이 들어오는 해와 지켜야 하는 해</b><span>수입·지출·계약의 변화가 큰 때</span></div>
    <div><b>사업을 키워도 되는 시기</b><span>시작·확인·확장 중 지금 어느 단계인지</span></div>
    <div><b>결혼을 결정하기 좋은 흐름</b><span>관계가 실제 결정으로 이어지기 쉬운 때</span></div>
    <div><b>계약·동업을 특히 조심할 시기</b><span>사람과 돈이 함께 얽히기 쉬운 때</span></div>
    <div><b>앞으로 5년 중 가장 크게 움직이는 해</b><span>해마다 무엇이 달라지는지 쉽게 풀이</span></div>`;
  if(buy) buy.innerHTML='앞으로의 흐름 이어서 보기 <span>→</span>';
}

const css=document.createElement('style');
css.textContent=`
.fb,.feedbackBox{display:none!important}.reason{display:none!important}
.lockedList div{display:flex;flex-direction:column;gap:2px;text-align:left;padding:12px 0!important}.lockedList div b{font-size:13px;color:#efe3d5}.lockedList div span{font-size:10px;color:#9e9084}.proofStrip{margin:0;padding:18px 20px;background:#120c0a;color:#e9ddcf;border-top:1px solid #3a2720;border-bottom:1px solid #3a2720}.proofStrip strong{display:block;font-family:Georgia,serif;font-size:20px;line-height:1.35}.proofStrip p{margin:6px 0 0;color:#a99b8d;font-size:11px}.plainNote{margin-top:14px;padding:12px 14px;border-left:3px solid #8a2d25;background:rgba(138,45,37,.06);font-size:12px;line-height:1.7}.answerBody h3{font-size:17px!important}.answerBody p{font-size:14px!important;line-height:1.8!important}
`;
document.head.appendChild(css);

const insertProofStrip=()=>{
  if($('#proofStrip')||!$('#pastYears')) return;
  const el=document.createElement('section');
  el.id='proofStrip';el.className='proofStrip';
  el.innerHTML='<strong>지난 흐름부터 짚습니다.</strong><p>어려운 사주 용어 대신, 실제 생활에서 어떤 일로 나타날 수 있는지 풀어서 설명합니다.</p>';
  $('#pastYears').before(el);
};
insertProofStrip();

const godPlain={
  편관:'해야 할 일과 책임이 갑자기 늘어나는 때',정관:'직장·직책·평가가 중요해지는 때',
  편재:'새로운 거래나 큰돈의 움직임이 생기기 쉬운 때',정재:'월급·생활비·저축처럼 현실적인 돈을 챙기는 때',
  겁재:'사람 때문에 돈이나 계획이 흔들리기 쉬운 때',비견:'내 생각대로 밀고 나가려는 힘이 강해지는 때',
  상관:'참던 말을 꺼내거나 기존 방식에서 벗어나고 싶은 때',식신:'내가 만든 결과로 성과를 내기 쉬운 때',
  편인:'생각이 많아지고 계획을 다시 짜기 쉬운 때',정인:'도움·문서·배움·보호를 받기 쉬운 때'
};
const pairPlain=(txt='')=>{
  const parts=txt.split('·').map(x=>x.trim()).filter(Boolean);
  const arr=[...new Set(parts.map(x=>godPlain[x]).filter(Boolean))];
  return arr.join(' 그리고 ')||txt;
};
function simplifyPast(){
  $$('.yearCard').forEach(card=>{
    const blocks=card.querySelectorAll('.deepBlock');
    if(blocks[1]){
      const b=blocks[1].querySelector('b'); const p=blocks[1].querySelector('p');
      if(b && /편관|정관|편재|정재|겁재|비견|상관|식신|편인|정인/.test(b.textContent)){
        const old=b.textContent.trim(); b.textContent=pairPlain(old);
        if(p){
          let t=p.textContent;
          t=t.replace(/그해의 십신은\s*([^입니다]+)입니다\.\s*그래서\s*/,'이 시기에는 ');
          Object.entries(godPlain).forEach(([k,v])=>{t=t.replaceAll(k,v)});
          t=t.replace(/원국|세운|대운|일간|십신/g,'사주 흐름');
          p.textContent=t;
        }
      }
    }
    const h=card.querySelector('h2');
    if(h){
      h.textContent=h.textContent
       .replace('기존 질서가 깨지거나 방향을 바꾸기 쉬웠던 해','생활이나 관계에 큰 변화가 생기기 쉬웠던 해')
       .replace('사람과 돈이 같이 흔들리기 쉬웠던 해','사람 문제와 돈 문제가 함께 커지기 쉬웠던 해')
       .replace('일과 돈의 판이 커지기 쉬웠던 해','일이나 돈의 규모가 커지기 쉬웠던 해')
       .replace('책임과 압박이 몰리기 쉬웠던 해','해야 할 일과 부담이 갑자기 늘기 쉬웠던 해')
       .replace('참고 있던 걸 더는 그대로 두기 어려웠던 해','더는 그대로 버티기 어렵다고 느끼기 쉬웠던 해');
    }
    const timing=card.querySelector('.timing');
    if(timing) timing.textContent=timing.textContent.replace('양력 기준으로는','특히').replace('상대적으로 더 강하게 잡힙니다. 월운은 절기 기준으로 바뀌기 때문에 실제 체감은 앞뒤로 약 1~2주 차이가 날 수 있습니다.','전후에 변화가 더 크게 느껴질 수 있습니다. 시기는 앞뒤로 조금 달라질 수 있습니다.');
  });
}

let manseryeokPromise;
const getLib=()=>manseryeokPromise||(manseryeokPromise=import('https://cdn.jsdelivr.net/npm/manseryeok@2.0.0/+esm'));
const clash=['자오','축미','인신','묘유','진술','사해'];
const combine=['자축','인해','묘술','진유','사신','오미'];
const pair=(a,b,list)=>list.includes(a+b)||list.includes(b+a);

async function renderRealestate(){
  const q=readConcern(); if(q.label!=='이사·부동산') return;
  const lead=$('#answerLead'),body=$('#answerBody'),why=$('#whyBox'); if(!lead||!body) return;
  try{
    const lib=await getLib();
    const unknown=$('#unknown')?.checked;
    const input={year:Number($('#year').value),month:Number($('#month').value),day:Number($('#day').value),hour:unknown?12:Number($('#hour').value),minute:unknown?0:Number($('#minute').value),gender:document.querySelector('.sex.on')?.dataset.sex||'female',isLunar:$('#calendar')?.value==='lunar',isLeapMonth:$('#calendar')?.value==='lunar'&&$('#leap')?.checked};
    const r=lib.calculateFourPillars(input); const dayStem=r.day.heavenlyStem;
    const natal=[r.year.earthlyBranch,r.month.earthlyBranch,r.day.earthlyBranch]; if(!unknown)natal.push(r.hour.earthlyBranch);
    const y0=new Date().getFullYear(); const years=[];
    for(let y=y0;y<=y0+5;y++){
      const yr=lib.calculateFourPillars({year:y,month:7,day:1,hour:12,minute:0,gender:'male'}).year;
      const sg=lib.getTenGod(dayStem,yr.heavenlyStem),bg=lib.getBranchTenGod(dayStem,yr.earthlyBranch);
      let move=0,contract=0;
      natal.forEach(z=>{if(pair(z,yr.earthlyBranch,clash))move+=4;if(pair(z,yr.earthlyBranch,combine))move+=1.5;if(z===yr.earthlyBranch)move+=1});
      if(['정인','편인'].includes(sg)||['정인','편인'].includes(bg))contract+=2.5;
      if(['정재','편재'].includes(sg)||['정재','편재'].includes(bg))contract+=2;
      if(['정관','편관'].includes(sg)||['정관','편관'].includes(bg))contract+=1.2;
      years.push({y,move,contract,total:move+contract,sg,bg});
    }
    const move=[...years].sort((a,b)=>b.move-a.move||b.total-a.total)[0];
    const contract=[...years].sort((a,b)=>b.contract-a.contract||b.total-a.total)[0];
    const caution=[...years].sort((a,b)=>(b.move+b.contract)-(a.move+a.contract))[0];
    lead.textContent=`이사·부동산만 놓고 보면, ${move.y}년 전후에 생활 터전이나 집과 관련된 움직임이 가장 크게 잡힙니다.`;
    body.innerHTML=`
      <div class="consultBlock"><h3>이사를 생각한다면</h3><p>${move.y}년은 지금 사는 곳을 바꾸거나 생활 환경을 크게 손보려는 마음이 강해지기 쉬운 때로 봅니다. 실제 이사는 물론, 독립·합가·직장 이동 때문에 거주지가 바뀌는 경우도 여기에 포함됩니다.</p></div>
      <div class="consultBlock"><h3>집을 사거나 계약한다면</h3><p>${contract.y}년은 집 자체보다 <b>계약, 돈의 조건, 서류</b>를 꼼꼼히 보는 것이 중요한 때입니다. 좋은 집을 찾는 것보다 대출 조건, 잔금 일정, 계약 상대방을 먼저 확인하는 편이 좋습니다.</p></div>
      <div class="consultBlock"><h3>특히 조심할 점</h3><p>${caution.y}년 전후에는 마음이 급해져 “지금 아니면 안 된다”는 생각으로 결정하기 쉽습니다. 이 시기에는 사주 풀이보다 실제 시세와 대출 상환액, 등기·계약 조건을 먼저 확인해야 합니다.</p></div>
      <div class="plainNote">사주에서는 집 문제를 단순히 ‘돈이 들어오는가’만 보지 않습니다. <b>사는 곳이 바뀌는 흐름</b>, <b>계약과 문서가 움직이는 흐름</b>, <b>돈이 크게 오가는 흐름</b>을 따로 보고 함께 판단합니다.</div>`;
    if(why){why.innerHTML=`<p>어려운 용어 대신 쉽게 말씀드리면, 태어날 때의 사주와 해마다 달라지는 흐름을 비교해 ‘이동이 커지는 때’, ‘계약과 문서가 중요해지는 때’, ‘돈의 움직임이 커지는 때’를 따로 살폈습니다.</p>`;}
  }catch(e){console.error(e)}
}

$('#buy')?.addEventListener('click',()=>track('paid_cta_click',readConcern()));
$('#pay')?.addEventListener('click',()=>track('checkout_start',readConcern()));

const observer=new MutationObserver(()=>{
  if(!$('#result')?.classList.contains('hide')){
    simplifyPast();
    renderRealestate();
    if(!pastViewed && $$('.yearCard').length){pastViewed=true;track('past_result_view',{count:$$('.yearCard').length});}
    if(!answerViewed && $('#answerLead')?.textContent.trim()){answerViewed=true;track('concern_answer_view',readConcern());}
    const q=readConcern();
    if(q.custom && $('#questionTitle') && !$('#questionTitle').dataset.v7){$('#questionTitle').dataset.v7='1';$('#questionTitle').textContent=q.custom;}
    if($('#whyBtn')) $('#whyBtn').textContent='왜 이렇게 풀이했는지 보기';
  }
});
observer.observe(document.body,{subtree:true,attributes:true,childList:true,characterData:true});
