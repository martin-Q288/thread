const $21=s=>document.querySelector(s), $$21=s=>[...document.querySelectorAll(s)];
let M21=null;

const STEM_KO={갑:'甲',을:'乙',병:'丙',정:'丁',무:'戊',기:'己',경:'庚',신:'辛',임:'壬',계:'癸'};
const BRANCH_KO={자:'子',축:'丑',인:'寅',묘:'卯',진:'辰',사:'巳',오:'午',미:'未',신:'申',유:'酉',술:'戌',해:'亥'};
const GOD_EASY={비견:'비견',겁재:'겁재',식신:'식신',상관:'상관',편재:'편재',정재:'정재',편관:'편관',정관:'정관',편인:'편인',정인:'정인'};

function input21(){
  const unknown=$21('#unknown')?.checked;
  return {
    year:+($21('#year')?.value||0), month:+($21('#month')?.value||0), day:+($21('#day')?.value||0),
    hour:unknown?12:+($21('#hour')?.value||12), minute:unknown?0:+($21('#minute')?.value||0),
    gender:$21('.sex.on')?.dataset.sex||'female',
    isLunar:$21('#calendar')?.value==='lunar',
    isLeapMonth:$21('#calendar')?.value==='lunar'&&$21('#leap')?.checked,
    unknown,
    city:$21('#city')?.value||'서울'
  };
}
function ganji21(p){return p?.heavenlyStem&&p?.earthlyBranch?`${p.heavenlyStem}${p.earthlyBranch}`:''}
function hanja21(p){return p?.heavenlyStem&&p?.earthlyBranch?`${STEM_KO[p.heavenlyStem]||p.heavenlyStem}${BRANCH_KO[p.earthlyBranch]||p.earthlyBranch}`:''}
function god21(dayStem,p,isDay=false){
  if(!p||!dayStem)return{stem:'',branch:''};
  if(isDay)return {stem:'나',branch:GOD_EASY[M21.getBranchTenGod(dayStem,p.earthlyBranch)]||M21.getBranchTenGod(dayStem,p.earthlyBranch)||''};
  const a=M21.getTenGod(dayStem,p.heavenlyStem),b=M21.getBranchTenGod(dayStem,p.earthlyBranch);
  return{stem:GOD_EASY[a]||a||'',branch:GOD_EASY[b]||b||''};
}
function pillarCard21(label,p,dayStem,isDay=false,unknown=false){
  if(unknown)return `<div class="mwPillar muted"><span>${label}</span><b>時柱 未詳</b><strong>태어난 시간 미상</strong><small>시간 기둥은 풀이에서 제외합니다</small></div>`;
  const g=god21(dayStem,p,isDay);
  return `<div class="mwPillar"><span>${label}</span><b>${hanja21(p)}</b><strong>${ganji21(p)}</strong><div class="mwGod"><i>${g.stem}</i><i>${g.branch}</i></div></div>`;
}
function luck21(natal,v){
  const arr=natal?.luckPillars?.pillars||[];
  if(!arr.length)return '';
  const age=Math.max(0,new Date().getFullYear()-v.year);
  return arr.slice(0,10).map(x=>{
    const k=x.korean||x.ganji||`${x.heavenlyStem||''}${x.earthlyBranch||''}`;
    const start=Number(x.age||0),on=age>=start&&age<start+10;
    return `<div class="mwLuck ${on?'on':''}"><span>${start}세~</span><b>${k}</b>${on?'<small>현재</small>':''}</div>`;
  }).join('');
}
function render21(){
  const result=$21('#result'),mount=$21('#readingMount');
  if(!M21||!result||result.classList.contains('hide')||!mount||$21('#manseryeok21'))return;
  const v=input21(); if(!v.year||!v.month||!v.day)return;
  let natal;try{natal=M21.calculateFourPillars({...v,dayBoundary:'midnight'})}catch(e){console.error(e);return}
  const dayStem=natal.day?.heavenlyStem;
  const section=document.createElement('section');
  section.id='manseryeok21';section.className='mwPaper';
  section.innerHTML=`
    <div class="mwHead"><div><span>四柱命式</span><h2>내 만세력</h2></div><small>${v.isLunar?'음력':'양력'} ${v.year}.${String(v.month).padStart(2,'0')}.${String(v.day).padStart(2,'0')} · ${v.city}</small></div>
    <div class="mwPillars">
      ${pillarCard21('년주',natal.year,dayStem,false,false)}
      ${pillarCard21('월주',natal.month,dayStem,false,false)}
      ${pillarCard21('일주',natal.day,dayStem,true,false)}
      ${pillarCard21('시주',natal.hour,dayStem,false,v.unknown)}
    </div>
    <div class="mwLegend"><span>위 글자: 천간</span><span>아래 글자: 지지</span><span>작은 표기: 십신</span></div>
    <details class="mwDetails"><summary>대운 흐름 보기</summary><div class="mwLuckRow">${luck21(natal,v)}</div><p>대운은 약 10년 단위로 바뀌는 큰 흐름입니다. 첫 풀이와 심층 풀이는 이 큰 흐름 위에 해마다 들어오는 흐름을 함께 겹쳐 봅니다.</p></details>`;
  mount.parentNode.insertBefore(section,mount);
}
function styles21(){
  const s=document.createElement('style');s.textContent=`
  .mwPaper{max-width:820px;margin:0 auto 14px;background:linear-gradient(180deg,#f1e8d7,#e8ddc9);color:#241914;border-top:2px solid #8d3027;border-bottom:14px solid #d0c4ae;padding:28px 24px;box-sizing:border-box}
  .mwHead{display:flex;justify-content:space-between;gap:16px;align-items:flex-end;margin-bottom:22px}.mwHead span{font-size:10px;letter-spacing:.16em;font-weight:900;color:#8b3027}.mwHead h2{font-family:Georgia,'Noto Serif KR',serif;font-size:25px;margin:5px 0 0}.mwHead small{font-size:11px;color:#78695b;text-align:right}
  .mwPillars{display:grid;grid-template-columns:repeat(4,1fr);border:1px solid #b9a78c;background:rgba(255,255,255,.18)}.mwPillar{min-height:170px;text-align:center;padding:15px 6px;border-right:1px solid #c6b59a;box-sizing:border-box}.mwPillar:last-child{border-right:0}.mwPillar>span{display:block;font-size:10px;color:#8b3027;font-weight:900}.mwPillar>b{display:block;font-family:Georgia,serif;font-size:34px;letter-spacing:.08em;margin:14px 0 4px}.mwPillar>strong{display:block;font-size:13px}.mwPillar.muted b{font-size:17px;margin-top:22px}.mwPillar.muted strong{font-size:11px;color:#786b5e}.mwPillar.muted small{display:block;margin-top:12px;font-size:9px;color:#8f8174}.mwGod{display:grid;grid-template-columns:1fr 1fr;gap:3px;margin-top:14px}.mwGod i{font-style:normal;font-size:9px;padding:5px 2px;background:rgba(126,45,36,.07);color:#65564b}
  .mwLegend{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin:12px 0 0;font-size:9px;color:#817264}.mwDetails{margin-top:18px;border-top:1px solid #c2b096;padding-top:14px}.mwDetails summary{cursor:pointer;font-size:12px;font-weight:900;color:#6f2b24}.mwLuckRow{display:flex;gap:7px;overflow-x:auto;padding:15px 0 8px}.mwLuck{flex:0 0 70px;text-align:center;border:1px solid #b9a58a;padding:9px 5px;background:rgba(255,255,255,.17)}.mwLuck.on{background:#762a23;color:#f3e6d4;border-color:#762a23}.mwLuck span,.mwLuck b,.mwLuck small{display:block}.mwLuck span{font-size:8px}.mwLuck b{font-family:Georgia,serif;font-size:15px;margin:5px 0}.mwLuck small{font-size:8px}.mwDetails p{font-size:11px;line-height:1.8;color:#75675b;margin:8px 0 0}
  @media(max-width:560px){.mwPaper{padding:24px 14px}.mwHead{align-items:flex-start}.mwHead h2{font-size:23px}.mwPillar{min-height:150px;padding:12px 3px}.mwPillar>b{font-size:28px}.mwPillar>strong{font-size:11px}.mwGod i{font-size:8px}.mwHead small{font-size:9px}}
  `;document.head.appendChild(s)
}
async function init21(){styles21();try{M21=await import('https://cdn.jsdelivr.net/npm/manseryeok@2.0.0/+esm')}catch(e){console.error(e);return}const obs=new MutationObserver(render21);obs.observe(document.body,{subtree:true,childList:true,attributes:true,attributeFilter:['class']});setInterval(render21,500)}
init21();