const $22=s=>document.querySelector(s), $$22=s=>[...document.querySelectorAll(s)];
let M22=null;

const STEM_HAN={갑:'甲',을:'乙',병:'丙',정:'丁',무:'戊',기:'己',경:'庚',신:'辛',임:'壬',계:'癸'};
const BRANCH_HAN={자:'子',축:'丑',인:'寅',묘:'卯',진:'辰',사:'巳',오:'午',미:'未',신:'申',유:'酉',술:'戌',해:'亥'};
const STEM_EL={갑:'목',을:'목',병:'화',정:'화',무:'토',기:'토',경:'금',신:'금',임:'수',계:'수'};
const BRANCH_EL={자:'수',축:'토',인:'목',묘:'목',진:'토',사:'화',오:'화',미:'토',신:'금',유:'금',술:'토',해:'수'};
const ELEMENT_HAN={목:'木',화:'火',토:'土',금:'金',수:'水'};
const HIDDEN={
  자:['계'], 축:['기','계','신'], 인:['갑','병','무'], 묘:['을'], 진:['무','을','계'], 사:['병','무','경'],
  오:['정','기'], 미:['기','정','을'], 신:['경','임','무'], 유:['신'], 술:['무','신','정'], 해:['임','갑']
};

function input22(){
  const unknown=$22('#unknown')?.checked;
  return {
    year:+($22('#year')?.value||0), month:+($22('#month')?.value||0), day:+($22('#day')?.value||0),
    hour:unknown?12:+($22('#hour')?.value||12), minute:unknown?0:+($22('#minute')?.value||0),
    gender:$22('.sex.on')?.dataset.sex||'female', isLunar:$22('#calendar')?.value==='lunar',
    isLeapMonth:$22('#calendar')?.value==='lunar'&&$22('#leap')?.checked, unknown,
    city:$22('#city')?.value||'서울'
  };
}
function ganji22(p){return p?.heavenlyStem&&p?.earthlyBranch?`${p.heavenlyStem}${p.earthlyBranch}`:''}
function hanja22(p){return p?.heavenlyStem&&p?.earthlyBranch?`${STEM_HAN[p.heavenlyStem]||p.heavenlyStem}${BRANCH_HAN[p.earthlyBranch]||p.earthlyBranch}`:''}
function tg22(dayStem,stem){try{return M22.getTenGod(dayStem,stem)||''}catch{return''}}
function btg22(dayStem,branch){try{return M22.getBranchTenGod(dayStem,branch)||''}catch{return''}}
function pillar22(label,p,dayStem,isDay=false,unknown=false){
  if(unknown)return `<div class="mw22Pillar muted"><span>${label}</span><b>時柱 未詳</b><strong>태어난 시간 미상</strong><small>시간 기둥은 세부 판단에서 제외합니다</small></div>`;
  const stemGod=isDay?'나':tg22(dayStem,p.heavenlyStem), branchGod=btg22(dayStem,p.earthlyBranch);
  return `<div class="mw22Pillar"><span>${label}</span><b>${hanja22(p)}</b><strong>${ganji22(p)}</strong><div class="mw22God"><i>${stemGod||'—'}</i><i>${branchGod||'—'}</i></div><small>${ELEMENT_HAN[STEM_EL[p.heavenlyStem]]}${ELEMENT_HAN[BRANCH_EL[p.earthlyBranch]]}</small></div>`;
}
function elementCounts22(natal,v){
  const c={목:0,화:0,토:0,금:0,수:0};
  const pillars=[natal.year,natal.month,natal.day,...(v.unknown?[]:[natal.hour])];
  pillars.forEach(p=>{if(STEM_EL[p?.heavenlyStem])c[STEM_EL[p.heavenlyStem]]++;if(BRANCH_EL[p?.earthlyBranch])c[BRANCH_EL[p.earthlyBranch]]++});
  return c;
}
function elementHtml22(natal,v){
  const c=elementCounts22(natal,v),total=Object.values(c).reduce((a,b)=>a+b,0)||1,max=Math.max(...Object.values(c),1);
  return Object.entries(c).map(([k,n])=>`<div class="mw22El"><div><b>${ELEMENT_HAN[k]}</b><span>${k}</span></div><em>${n}</em><i><u style="width:${Math.round((n/max)*100)}%"></u></i></div>`).join('')+
  `<p class="mw22Note">오행 분포는 화면에 드러난 ${v.unknown?'6':'8'}글자를 기준으로 먼저 보여드립니다. 지장간과 계절의 힘은 실제 풀이에서 따로 겹쳐 봅니다.</p>`;
}
function hiddenHtml22(natal,v){
  const rows=[['년주',natal.year],['월주',natal.month],['일주',natal.day],...(v.unknown?[]:[['시주',natal.hour]])];
  return rows.map(([label,p])=>{
    const hs=HIDDEN[p?.earthlyBranch]||[];
    return `<div class="mw22HiddenRow"><b>${label} · ${p?.earthlyBranch||''}${BRANCH_HAN[p?.earthlyBranch]||''}</b><span>${hs.map(s=>`${s}${STEM_HAN[s]||''} <small>${tg22(natal.day.heavenlyStem,s)}</small>`).join(' · ')||'—'}</span></div>`;
  }).join('');
}
function luckData22(natal,v){
  const arr=natal?.luckPillars?.pillars||[];
  const age=Math.max(0,new Date().getFullYear()-v.year);
  return {age,arr:arr.slice(0,10)};
}
function luckHtml22(natal,v){
  const {age,arr}=luckData22(natal,v);if(!arr.length)return '<p class="mw22Note">대운 정보를 불러오지 못했습니다.</p>';
  return arr.map(x=>{
    const k=x.korean||x.ganji||`${x.heavenlyStem||''}${x.earthlyBranch||''}`;
    const start=Number(x.age||0),on=age>=start&&age<start+10,next=age<start&&start-age<=10;
    const stem=x.heavenlyStem||k[0],branch=x.earthlyBranch||k[1];
    const gods=stem&&branch?`${tg22(natal.day.heavenlyStem,stem)} · ${btg22(natal.day.heavenlyStem,branch)}`:'';
    return `<div class="mw22Luck ${on?'on':''} ${next?'next':''}"><span>${start}세~${start+9}세</span><b>${k}</b><small>${gods}</small>${on?'<em>현재 대운</em>':next?'<em>다음 대운</em>':''}</div>`;
  }).join('');
}
function currentLuckCopy22(natal,v){
  const {age,arr}=luckData22(natal,v);const cur=arr.find(x=>age>=Number(x.age||0)&&age<Number(x.age||0)+10);
  const next=arr.find(x=>Number(x.age||0)>age);
  const fmt=x=>x?(x.korean||x.ganji||`${x.heavenlyStem||''}${x.earthlyBranch||''}`):'';
  if(!cur)return '지금의 10년 흐름은 아래 대운표에서 이어서 확인할 수 있습니다.';
  return `현재는 ${fmt(cur)} 대운에 들어와 있습니다.${next?` 다음 큰 흐름은 ${fmt(next)} 대운으로 이어집니다.`:''} 아래 첫 풀이와 심층 풀이는 이 대운 위에 해마다 들어오는 흐름을 겹쳐서 봅니다.`;
}
function render22(){
  const result=$22('#result'),mount=$22('#readingMount');
  if(!M22||!result||result.classList.contains('hide')||!mount||$22('#manseryeok22'))return;
  const v=input22();if(!v.year||!v.month||!v.day)return;
  let natal;try{natal=M22.calculateFourPillars({...v,dayBoundary:'midnight'})}catch(e){console.error(e);return}
  const dayStem=natal.day?.heavenlyStem;
  const sec=document.createElement('section');sec.id='manseryeok22';sec.className='mw22Paper';
  sec.innerHTML=`
    <div class="mw22Intro"><span>四柱命式 · 萬歲曆</span><h2>당신의 사주팔자는 이렇게 놓입니다.</h2><p>아래 네 기둥과 대운의 흐름을 바탕으로 지나온 변곡과 앞으로의 순서를 이어서 봅니다.</p></div>
    <div class="mw22Meta"><b>${v.isLunar?'음력':'양력'} ${v.year}.${String(v.month).padStart(2,'0')}.${String(v.day).padStart(2,'0')}</b><span>${v.unknown?'출생시간 미상':`${String(v.hour).padStart(2,'0')}:${String(v.minute).padStart(2,'0')}`} · ${v.city}</span></div>
    <div class="mw22Pillars">
      ${pillar22('년주',natal.year,dayStem,false,false)}
      ${pillar22('월주',natal.month,dayStem,false,false)}
      ${pillar22('일주',natal.day,dayStem,true,false)}
      ${pillar22('시주',natal.hour,dayStem,false,v.unknown)}
    </div>
    <div class="mw22Legend"><span>큰 한자: 천간·지지</span><span>작은 표기: 십신</span><span>木火土金水: 오행</span></div>
    <section class="mw22Block"><div class="mw22BlockHead"><span>五行</span><h3>오행 분포</h3></div><div class="mw22Elements">${elementHtml22(natal,v)}</div></section>
    <section class="mw22Block"><div class="mw22BlockHead"><span>藏干</span><h3>지지 안에 숨어 있는 기운</h3></div><div class="mw22Hidden">${hiddenHtml22(natal,v)}</div><p class="mw22Note">겉으로 보이는 글자만 같아도 지장간 구성과 태어난 계절에 따라 실제 해석은 달라질 수 있습니다.</p></section>
    <section class="mw22Block"><div class="mw22BlockHead"><span>大運</span><h3>10년 단위의 큰 흐름</h3></div><p class="mw22LuckLead">${currentLuckCopy22(natal,v)}</p><div class="mw22LuckRow">${luckHtml22(natal,v)}</div></section>
    <div class="mw22Bridge"><b>이제 이 명식을 실제 삶의 흐름으로 풀어봅니다.</b><span>타고난 결 → 지나온 변곡 → 지금의 중심 → 앞으로 3년 순으로 이어집니다.</span></div>`;
  mount.parentNode.insertBefore(sec,mount);
}
function styles22(){
  const s=document.createElement('style');s.textContent=`
  .mw22Paper{max-width:820px;margin:0 auto 14px;background:linear-gradient(180deg,#f1e8d7,#e6dac5);color:#241914;border-top:2px solid #8b3027;border-bottom:14px solid #cfc1a9;box-sizing:border-box;overflow:hidden}.mw22Intro{padding:30px 26px 20px}.mw22Intro>span,.mw22BlockHead span{font-size:10px;letter-spacing:.16em;font-weight:900;color:#8b3027}.mw22Intro h2{font-family:Georgia,'Noto Serif KR',serif;font-size:28px;line-height:1.5;margin:7px 0 10px}.mw22Intro p{font-size:13px;line-height:1.85;color:#6c5c50;margin:0}.mw22Meta{display:flex;justify-content:space-between;gap:10px;padding:12px 26px;background:rgba(67,42,31,.06);border-top:1px solid #c8b598;border-bottom:1px solid #c8b598;font-size:11px}.mw22Meta span{color:#78695b}
  .mw22Pillars{display:grid;grid-template-columns:repeat(4,1fr);margin:22px 26px 8px;border:1px solid #b6a184;background:rgba(255,255,255,.18)}.mw22Pillar{min-height:184px;text-align:center;padding:15px 5px;border-right:1px solid #c3b092;box-sizing:border-box}.mw22Pillar:last-child{border-right:0}.mw22Pillar>span{display:block;font-size:10px;color:#8b3027;font-weight:900}.mw22Pillar>b{display:block;font-family:Georgia,serif;font-size:35px;letter-spacing:.07em;margin:14px 0 4px}.mw22Pillar>strong{display:block;font-size:13px}.mw22Pillar>small{display:block;margin-top:8px;font-family:Georgia,serif;font-size:11px;color:#816f61}.mw22Pillar.muted b{font-size:16px;margin-top:24px}.mw22Pillar.muted strong{font-size:10px;color:#78695b}.mw22God{display:grid;grid-template-columns:1fr 1fr;gap:3px;margin-top:13px}.mw22God i{font-style:normal;font-size:9px;padding:5px 2px;background:rgba(126,45,36,.07);color:#625348}.mw22Legend{display:flex;justify-content:center;gap:12px;flex-wrap:wrap;padding:2px 20px 20px;font-size:9px;color:#806f60}
  .mw22Block{margin:0 26px;padding:22px 0;border-top:1px solid #c6b397}.mw22BlockHead{display:flex;align-items:baseline;gap:10px}.mw22BlockHead h3{font-family:Georgia,'Noto Serif KR',serif;font-size:19px;margin:0}.mw22Elements{margin-top:16px}.mw22El{display:grid;grid-template-columns:80px 28px 1fr;gap:10px;align-items:center;margin:9px 0}.mw22El>div b{font-family:Georgia,serif;font-size:18px;margin-right:5px}.mw22El>div span{font-size:10px;color:#78695b}.mw22El em{font-style:normal;font-weight:900;font-size:12px}.mw22El i{height:7px;background:#d6c8b3;display:block;overflow:hidden}.mw22El u{display:block;height:100%;background:#783028;text-decoration:none}.mw22Note{font-size:10px;line-height:1.8;color:#7d6d60;margin:12px 0 0}
  .mw22Hidden{margin-top:14px}.mw22HiddenRow{display:grid;grid-template-columns:120px 1fr;gap:12px;padding:10px 0;border-bottom:1px solid rgba(118,90,67,.16);font-size:11px}.mw22HiddenRow b{color:#4d3c31}.mw22HiddenRow span{color:#6b5a4d}.mw22HiddenRow small{font-size:9px;color:#8b3027}.mw22LuckLead{font-size:12px;line-height:1.85;color:#655448;margin:14px 0 4px}.mw22LuckRow{display:flex;gap:7px;overflow-x:auto;padding:12px 0 4px}.mw22Luck{position:relative;flex:0 0 88px;min-height:76px;text-align:center;border:1px solid #b5a083;padding:9px 5px;background:rgba(255,255,255,.17);box-sizing:border-box}.mw22Luck.on{background:#762a23;color:#f3e6d4;border-color:#762a23}.mw22Luck.next{border-color:#8b3027}.mw22Luck span,.mw22Luck b,.mw22Luck small,.mw22Luck em{display:block}.mw22Luck span{font-size:8px}.mw22Luck b{font-family:Georgia,serif;font-size:16px;margin:4px 0}.mw22Luck small{font-size:8px;line-height:1.5}.mw22Luck em{font-style:normal;font-size:8px;font-weight:900;margin-top:5px}.mw22Bridge{margin-top:4px;padding:20px 26px 24px;background:#20130f;color:#eadccb}.mw22Bridge b,.mw22Bridge span{display:block}.mw22Bridge b{font-family:Georgia,'Noto Serif KR',serif;font-size:17px}.mw22Bridge span{font-size:10px;color:#b9a795;margin-top:7px;line-height:1.7}
  @media(max-width:560px){.mw22Intro{padding:26px 18px 18px}.mw22Intro h2{font-size:25px}.mw22Meta{padding:11px 18px;display:block}.mw22Meta span{display:block;margin-top:4px}.mw22Pillars{margin:18px 12px 8px}.mw22Pillar{min-height:158px;padding:12px 2px}.mw22Pillar>b{font-size:27px}.mw22Pillar>strong{font-size:10px}.mw22God i{font-size:7px}.mw22Block{margin:0 18px}.mw22HiddenRow{grid-template-columns:90px 1fr}.mw22Bridge{padding:18px}.mw22El{grid-template-columns:67px 22px 1fr}}
  `;document.head.appendChild(s)
}
async function init22(){styles22();try{M22=await import('https://cdn.jsdelivr.net/npm/manseryeok@2.0.0/+esm')}catch(e){console.error(e);return}const obs=new MutationObserver(render22);obs.observe(document.body,{subtree:true,childList:true,attributes:true,attributeFilter:['class']});setInterval(render22,450)}
init22();