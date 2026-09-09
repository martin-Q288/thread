const $=s=>document.querySelector(s),$$=s=>[...document.querySelectorAll(s)];
const CLASH=['자오','축미','인신','묘유','진술','사해'];
const COMB=['자축','인해','묘술','진유','사신','오미'];
const HARM=['자미','축오','인사','묘진','신해','유술'];
const BREAK=['자유','묘오','진축','미술','인해','사신'];
const TRI=[['신','자','진'],['해','묘','미'],['인','오','술'],['사','유','축']];
const PUNISH=[['인','사','신'],['축','술','미']];
const SELF_PUNISH=['진','오','유','해'];
const HIDDEN={자:['계'],축:['기','계','신'],인:['갑','병','무'],묘:['을'],진:['무','을','계'],사:['병','무','경'],오:['정','기'],미:['기','정','을'],신:['경','임','무'],유:['신'],술:['무','신','정'],해:['임','갑']};
const LONG={서울:126.9780,인천:126.7052,부산:129.0756,대구:128.6014,대전:127.3845,광주:126.8526,울산:129.3114,세종:127.2890,제주:126.5312};
const DOMAIN_LABEL={work:'일·직장',money:'돈·수입',relationship:'가까운 관계',home:'집·이사·생활환경',family:'가족·자녀',study:'시험·배움·문서'};
const DOMAIN_DESC={
  work:'지금 하는 일을 계속 가져갈지, 역할을 바꿀지, 다른 길을 만들지에 마음이 많이 쓰이기 쉬운 흐름입니다.',
  money:'돈이 단순히 많고 적은 문제보다, 들어오고 나가는 규모나 수입 방식 자체가 달라지기 쉬운 흐름입니다.',
  relationship:'새로운 사람보다 이미 가까운 사람과의 거리, 약속, 관계의 모양을 다시 정하는 문제가 먼저 보입니다.',
  home:'사는 곳이나 생활 환경을 그대로 둘지 바꿀지, 이사·독립·합가·출퇴근 같은 현실적인 변화가 먼저 잡힙니다.',
  family:'내 일보다 가족 일에 신경을 더 쓰거나, 부모·형제·자녀 계획처럼 가족 안에서 역할이 달라지기 쉬운 흐름입니다.',
  study:'시험, 자격, 공부, 계약서나 중요한 서류처럼 ‘확인하고 준비해야 하는 일’이 커지기 쉬운 흐름입니다.'
};
const topicMap={business:'work',sidejob:'money',jobchange:'work',promotion:'work',money:'money',investment:'money',newlove:'relationship',reunion:'relationship',marriage:'relationship',children:'family',relations:'relationship',exam:'study',realestate:'home',family:'family',annual:'work'};
const topics=[['business','사업·창업'],['sidejob','부업·투잡'],['jobchange','이직·퇴사'],['promotion','직장·승진'],['money','재물·돈 흐름'],['investment','투자·계약'],['newlove','새 연애'],['reunion','재회'],['marriage','결혼 시기'],['compatibility','궁합'],['children','임신·자녀'],['relations','인간관계'],['exam','시험·합격'],['realestate','이사·부동산'],['family','가족 문제'],['annual','올해 전체운']];
let lib,readingInput,lastNatal,lastBlind;

const pair=(a,b,list)=>list.includes(a+b)||list.includes(b+a);
const esc=s=>String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
const num=v=>Number(v||0);
function getInput(){const unknown=$('#unknown')?.checked;return{year:num($('#year')?.value),month:num($('#month')?.value),day:num($('#day')?.value),hour:unknown?12:num($('#hour')?.value),minute:unknown?0:num($('#minute')?.value),gender:$('.sex.on')?.dataset.sex||'female',isLunar:$('#calendar')?.value==='lunar',isLeapMonth:$('#calendar')?.value==='lunar'&&$('#leap')?.checked,trueSolarTime:{longitude:LONG[$('#city')?.value]||126.978,applyEquationOfTime:true,applyHistoricalDst:true},dayBoundary:'midnight',unknown,name:$('#name')?.value.trim()||'당신',city:$('#city')?.value||'서울'};}
function parts(x){if(!x)return{};const k=x.korean||x.ganji||'';return{stem:x.heavenlyStem||k[0],branch:x.earthlyBranch||k[1]};}
function natalPositions(r,b){const a=[['year',r.year.earthlyBranch],['month',r.month.earthlyBranch],['day',r.day.earthlyBranch]];if(!b.unknown)a.push(['hour',r.hour.earthlyBranch]);return a;}
function currentDayun(r,b,year){const ps=r.luckPillars?.pillars||[];const age=year-b.year;return ps.find(x=>age>=x.age&&age<x.age+10)||null;}
function relationType(a,b,natalBranches){
  if(pair(a,b,CLASH))return['clash',6];
  if(pair(a,b,HARM))return['harm',3.4];
  if(pair(a,b,BREAK))return['break',2.8];
  if(pair(a,b,COMB))return['combine',2.7];
  if(a===b&&SELF_PUNISH.includes(a))return['self',3.2];
  if((a==='자'&&b==='묘')||(a==='묘'&&b==='자'))return['punish',3.8];
  for(const g of PUNISH){if(g.includes(a)&&g.includes(b)&&g.some(x=>natalBranches.includes(x)&&x!==a&&x!==b))return['punish',4.2];}
  for(const g of TRI){if(g.includes(b)){const rest=g.filter(x=>x!==b);if(rest.every(x=>natalBranches.includes(x)))return['triad',4.5];}}
  if(a===b)return['repeat',1.6];
  return null;
}
function addGod(scores,g,sex){
  const add=(k,v)=>scores[k]=(scores[k]||0)+v;
  if(['정관','편관'].includes(g)){add('work',4);add('study',1.8);if(sex==='female')add('relationship',2.6);}
  if(['정재','편재'].includes(g)){add('money',4);add('home',1.2);if(sex==='male')add('relationship',2.6);}
  if(['식신','상관'].includes(g)){add('work',2.5);add('money',2);add('family',1.6);}
  if(['정인','편인'].includes(g)){add('study',4);add('family',2.2);add('home',1.6);}
  if(['비견','겁재'].includes(g)){add('relationship',2.7);add('money',2.2);add('family',1.2);}
}
function yearInfo(r,b,year){
  const yr=lib.calculateFourPillars({year,month:7,day:1,hour:12,minute:0,gender:b.gender}).year;
  const stemGod=lib.getTenGod(r.day.heavenlyStem,yr.heavenlyStem),branchGod=lib.getBranchTenGod(r.day.heavenlyStem,yr.earthlyBranch);
  const scores={work:0,money:0,relationship:0,home:0,family:0,study:0},interactions=[];
  addGod(scores,stemGod,b.gender);addGod(scores,branchGod,b.gender);
  const nbs=natalPositions(r,b).map(x=>x[1]);
  for(const [pos,z] of natalPositions(r,b)){
    const rel=relationType(z,yr.earthlyBranch,nbs);if(!rel)continue;const [type,w]=rel;interactions.push({pos,type,w,natal:z,year:yr.earthlyBranch});
    if(pos==='month'){scores.work+=w*1.15;scores.home+=w*.45;}
    if(pos==='day'){scores.relationship+=w*1.2;scores.home+=w*.65;}
    if(pos==='year'){scores.family+=w*1.15;scores.relationship+=w*.35;}
    if(pos==='hour'){scores.family+=w;scores.work+=w*.35;}
  }
  const d=currentDayun(r,b,year),dp=parts(d);let dayunGod='';
  if(dp.stem){dayunGod=lib.getTenGod(r.day.heavenlyStem,dp.stem);const tmp={work:0,money:0,relationship:0,home:0,family:0,study:0};addGod(tmp,dayunGod,b.gender);Object.keys(scores).forEach(k=>scores[k]+=tmp[k]*.45);}
  if(dp.branch){const rel=relationType(dp.branch,yr.earthlyBranch,nbs);if(rel){const w=rel[1]*.55;Object.keys(scores).forEach(k=>scores[k]+=w*.25);interactions.push({pos:'dayun',type:rel[0],w,natal:dp.branch,year:yr.earthlyBranch});}}
  const intensity=interactions.reduce((s,x)=>s+x.w,0)+Math.max(...Object.values(scores))*.7+(['편관','상관','겁재','편재'].includes(stemGod)?1.8:0)+(['편관','상관','겁재','편재'].includes(branchGod)?1.4:0);
  return{year,pillar:yr.heavenlyStem+yr.earthlyBranch,stemGod,branchGod,dayunGod,scores,interactions,intensity};
}
function monthWindow(r,b,year,domain){
  const arr=[];const nbs=natalPositions(r,b).map(x=>x[1]);
  for(let m=1;m<=12;m++){
    const mo=lib.calculateFourPillars({year,month:m,day:15,hour:12,minute:0,gender:b.gender}).month;
    const sg=lib.getTenGod(r.day.heavenlyStem,mo.heavenlyStem),bg=lib.getBranchTenGod(r.day.heavenlyStem,mo.earthlyBranch);let s=0;
    const t={work:0,money:0,relationship:0,home:0,family:0,study:0};addGod(t,sg,b.gender);addGod(t,bg,b.gender);s+=t[domain]||0;
    for(const [pos,z] of natalPositions(r,b)){const rel=relationType(z,mo.earthlyBranch,nbs);if(!rel)continue;const w=rel[1];if(domain==='work'&&pos==='month')s+=w;if(domain==='relationship'&&pos==='day')s+=w;if(domain==='home'&&['day','month'].includes(pos))s+=w;if(domain==='family'&&['year','hour'].includes(pos))s+=w;if(domain==='money')s+=w*.45;if(domain==='study')s+=w*.35;}
    arr.push({month:m,score:s});
  }
  return arr.sort((a,b)=>b.score-a.score).slice(0,2).sort((a,b)=>a.month-b.month).map(x=>x.month);
}
function blendedNow(r,b){
  const y=new Date().getFullYear(),a=yearInfo(r,b,y),n=yearInfo(r,b,y+1),scores={};
  Object.keys(a.scores).forEach(k=>scores[k]=a.scores[k]+n.scores[k]*.42);
  const rank=Object.entries(scores).sort((x,y)=>y[1]-x[1]);return{year:y,current:a,next:n,scores,rank};
}
function blindTheme(r,b){
  const now=blendedNow(r,b),[first,second]=now.rank;const top=first?.[1]||0,secondVal=second?.[1]||0;
  if(top<8)return{mode:'soft',domains:[first?.[0]||'work'],now};
  if(secondVal>0&&top/secondVal<1.18)return{mode:'dual',domains:[first[0],second[0]],now};
  return{mode:'single',domains:[first[0]],now};
}
function themeCopy(t){
  const ds=t.domains;if(t.mode==='soft')return{title:'지금은 한 가지 문제만 유독 튀는 때는 아닙니다.',body:'대신 여러 일을 한꺼번에 정리하고 다음 방향을 준비하는 흐름이 더 강합니다. 억지로 한 가지 고민을 찍기보다, 아래에서 실제로 강하게 움직이는 시기를 먼저 보겠습니다.'};
  if(t.mode==='dual')return{title:`지금은 ‘${DOMAIN_LABEL[ds[0]]}’과 ‘${DOMAIN_LABEL[ds[1]]}’가 같이 걸립니다.`,body:`${DOMAIN_DESC[ds[0]]} 동시에 ${DOMAIN_DESC[ds[1]]}`};
  return{title:`지금 가장 먼저 보이는 건 ‘${DOMAIN_LABEL[ds[0]]}’입니다.`,body:DOMAIN_DESC[ds[0]]};
}
function pastStrong(r,b){
  const now=new Date().getFullYear(),arr=[];for(let y=now-10;y<now;y++)arr.push(yearInfo(r,b,y));arr.sort((a,b)=>b.intensity-a.intensity);const max=arr[0]?.intensity||0;
  let chosen=arr.filter(x=>x.intensity>=Math.max(8.5,max*.84)).slice(0,2);if(!chosen.length&&max>=7)chosen=[arr[0]];return chosen.sort((a,b)=>a.year-b.year);
}
function topDomain(info){return Object.entries(info.scores).sort((a,b)=>b[1]-a[1])[0]?.[0]||'work';}
function eventVerb(info){const t=info.interactions[0]?.type;return t==='combine'?'새로 묶이거나 관계가 정해지는':t==='clash'?'끊거나 바꾸는 결정을 하기 쉬운':t==='harm'||t==='punish'?'겉으로는 버티지만 속으로 마찰이 커지기 쉬운':'평소보다 선택이 커지기 쉬운';}
function pastCopy(r,b,info){const d=topDomain(info),months=monthWindow(r,b,info.year,d),m=months.length?`특히 ${months.join('월·')} 전후가 더 강하게 잡힙니다.`:'';const verb=eventVerb(info);const map={
  work:`${info.year}년은 일 때문에 방향을 다시 잡았을 가능성을 먼저 봅니다. 실제 퇴사나 이직이 아니어도 맡은 일이 갑자기 바뀌거나, 책임이 늘거나, 상사·조직 때문에 “계속 이렇게 해야 하나”라는 생각이 커지는 식으로 나타날 수 있습니다.`,
  money:`${info.year}년은 돈의 크기나 쓰임이 평소와 달라지기 쉬운 해로 봅니다. 수입이 달라지거나, 큰 지출·계약·투입 비용이 생기거나, 사람과 돈이 얽혀 신경 쓸 일이 늘어나는 형태가 먼저 잡힙니다.`,
  relationship:`${info.year}년은 가까운 사람과의 관계를 그대로 두기보다 ${verb} 시기였을 가능성을 봅니다. 연애뿐 아니라 배우자, 오래된 친구, 가까운 동료와의 거리나 약속이 달라지는 식으로 나타날 수 있습니다.`,
  home:`${info.year}년은 생활 터전을 그대로 두기보다 바꾸는 힘이 강했던 때로 봅니다. 실제 이사뿐 아니라 독립·합가·출퇴근 변화·직장 이동 때문에 생활 반경이 달라진 경우도 여기에 들어갑니다.`,
  family:`${info.year}년은 내 일보다 가족 안의 일에 신경을 많이 썼을 가능성을 봅니다. 부모·형제·자녀 계획이나 돌봄처럼 “내가 챙겨야 할 몫”이 커지는 형태로 나타날 수 있습니다.`,
  study:`${info.year}년은 시험·자격·공부·서류처럼 준비하고 확인해야 할 일이 커졌을 가능성을 봅니다. 결과 자체보다 “새로 배우거나 증명해야 하는 상황”이 생기는 쪽이 먼저 잡힙니다.`};
  return{domain:d,text:map[d],timing:m};
}
function futureStrong(r,b){const y=new Date().getFullYear(),arr=[];for(let i=0;i<4;i++)arr.push(yearInfo(r,b,y+i));arr.sort((a,b)=>b.intensity-a.intensity);return arr[0];}
function futureCopy(r,b,info){const d=topDomain(info),months=monthWindow(r,b,info.year,d);return{domain:d,title:`가까운 미래에서는 ${info.year}년을 눈여겨봅니다.`,body:`이 시기에는 ‘${DOMAIN_LABEL[d]}’ 쪽 변화가 다른 영역보다 크게 움직일 가능성이 먼저 보입니다. ${DOMAIN_DESC[d]} ${months.length?`특히 ${months.join('월·')} 전후가 더 눈에 띕니다.`:''}`};}
function sealText(text,input){const payload=JSON.stringify({text,birth:[input.year,input.month,input.day,input.hour,input.minute,input.city],created:new Date().toISOString().slice(0,10)});return crypto.subtle.digest('SHA-256',new TextEncoder().encode(payload)).then(b=>[...new Uint8Array(b)].map(x=>x.toString(16).padStart(2,'0')).join('').slice(0,10).toUpperCase());}
function renderBlind(r,b){
  const mount=$('#blindMount');if(!mount)return;const theme=blindTheme(r,b),tc=themeCopy(theme),past=pastStrong(r,b),future=futureStrong(r,b),fc=futureCopy(r,b,future);lastBlind={theme,past,future};
  const pastHtml=past.length?past.map(x=>{const c=pastCopy(r,b,x);return`<article class="v8Paper"><div class="v8Ey">지나온 흐름 · ${x.year}</div><h2>${esc(c.text.split('.')[0])}.</h2><p>${esc(c.text)}</p>${c.timing?`<div class="v8Timing">${esc(c.timing)}</div>`:''}</article>`}).join(''):`<article class="v8Paper"><div class="v8Ey">지나온 흐름</div><h2>최근 10년에서 특정 한 해를 억지로 찍지는 않겠습니다.</h2><p>지금 계산에서는 “이 해가 유독 강하다”고 말할 만큼 다른 해와 차이가 크지 않습니다. 현월당은 숫자를 채우기 위해 과거 연도를 만들어내지 않습니다.</p></article>`;
  mount.innerHTML=`<section class="v8Paper v8Lead"><div class="v8Ey">지금 가장 먼저 보이는 것</div><h2>${esc(tc.title)}</h2><p>${esc(tc.body)}</p></section>${pastHtml}<article class="v8Paper"><div class="v8Ey">가까운 미래</div><h2>${esc(fc.title)}</h2><p>${esc(fc.body)}</p></article><section id="v8Question" class="v8Paper v8Question"><div class="v8Ey">이제 질문하세요</div><h2>첫 풀이가 끝났습니다.<br>이제 궁금한 것을 고르세요.</h2><p>여기서부터는 선택한 문제만 따로 깊게 봅니다.</p><div class="v8Grid">${topics.map(([id,label])=>`<button type="button" class="v8Topic" data-topic="${id}">${label}</button>`).join('')}</div><textarea id="v8Custom" rows="3" maxlength="180" placeholder="구체적인 상황이 있다면 적어도 됩니다. 예: 올해 안에 이사를 가는 게 나을까요?"></textarea><div id="v8Answer"></div></section><section class="v8Seal"><span>첫 풀이 기록</span><b id="v8SealCode">기록 중...</b><small>이 기기에서는 오늘의 첫 풀이를 같은 내용으로 다시 확인할 수 있게 기록합니다.</small></section>`;
  $$('.v8Topic').forEach(btn=>btn.addEventListener('click',()=>{$$('.v8Topic').forEach(x=>x.classList.remove('on'));btn.classList.add('on');renderTopic(btn.dataset.topic,r,b);}));
  const plain=[tc.title,tc.body,...past.map(x=>pastCopy(r,b,x).text),fc.title,fc.body].join('\n');sealText(plain,b).then(code=>{const e=$('#v8SealCode');if(e)e.textContent=`${new Date().toLocaleDateString('ko-KR')} · ${code}`;try{localStorage.setItem('hyunwoldang-first-'+[b.year,b.month,b.day,b.hour,b.minute,b.city].join('-'),JSON.stringify({date:new Date().toISOString(),code,text:plain}));}catch{}});
}
function topicYearScore(info,topic){const d=topicMap[topic]||'work';let s=info.scores[d]||0;const gods=[info.stemGod,info.branchGod];if(topic==='business'&&gods.some(x=>['식신','상관','편재'].includes(x)))s+=3;if(topic==='sidejob'&&gods.some(x=>['식신','상관','편재','정재'].includes(x)))s+=2.5;if(topic==='jobchange'&&info.interactions.some(x=>x.pos==='month'&&['clash','break','harm','punish'].includes(x.type)))s+=4;if(topic==='promotion'&&gods.some(x=>['정관','편관','정인'].includes(x)))s+=3;if(topic==='investment'&&gods.some(x=>['편재','겁재'].includes(x)))s+=2.5;if(['newlove','reunion','marriage'].includes(topic)&&info.interactions.some(x=>x.pos==='day'))s+=4;if(topic==='reunion'&&info.interactions.some(x=>x.pos==='day'&&x.type==='combine'))s+=3;if(topic==='marriage'&&gods.some(x=>['정관','정재'].includes(x)))s+=2.5;if(topic==='realestate'&&info.interactions.some(x=>['day','month'].includes(x.pos)))s+=3;if(topic==='exam'&&gods.some(x=>['정인','편인','정관'].includes(x)))s+=3;if(topic==='children'&&info.interactions.some(x=>x.pos==='hour'))s+=4;return s;}
function bestTopicYear(r,b,topic){const y=new Date().getFullYear(),arr=[];for(let i=0;i<5;i++){const info=yearInfo(r,b,y+i);arr.push({info,score:topicYearScore(info,topic)});}return arr.sort((a,b)=>b.score-a.score)[0];}
function renderTopic(topic,r,b){const box=$('#v8Answer');if(!box)return;if(topic==='compatibility'){box.innerHTML='<div class="v8AnswerCard"><h3>궁합은 상대방 정보가 있어야 제대로 볼 수 있습니다.</h3><p>한 사람 사주만 보고 “두 사람이 잘 맞는다”고 말하지 않겠습니다. 상대방의 생년월일과 태어난 시간을 함께 받아야 두 사람의 관계 흐름을 비교할 수 있습니다.</p></div>';return;}
  const best=bestTopicYear(r,b,topic),y=best.info.year,months=monthWindow(r,b,y,topicMap[topic]||'work'),custom=$('#v8Custom')?.value.trim();const lead={
    business:`사업·창업만 놓고 보면 ${y}년 전후가 가장 크게 움직입니다. 지금 당장 회사를 그만둘지보다, 실제 돈을 내는 고객이 생기는지 먼저 확인한 뒤 키우는 방식이 더 안전합니다.`,
    sidejob:`부업·투잡은 ${y}년 전후에 수입 방식을 하나 더 만드는 흐름이 커집니다. 처음부터 큰돈보다 “작지만 반복해서 들어오는 돈”을 만드는 쪽을 먼저 봅니다.`,
    jobchange:`이직·퇴사는 ${y}년 전후가 가장 강합니다. 감정적으로 먼저 나가기보다 조건이 바뀌거나 다른 자리가 구체적으로 보일 때 움직이는 편이 낫습니다.`,
    promotion:`직장·승진은 ${y}년 전후에 역할과 책임이 커지는 흐름이 강합니다. 일이 많아지는 것과 인정받는 것은 다르기 때문에 직책·평가·보상까지 같이 확인해야 합니다.`,
    money:`돈의 흐름은 ${y}년 전후가 가장 크게 움직입니다. 좋은 해라고 단정하기보다 들어오는 돈과 나가는 돈의 크기가 함께 커질 수 있는 때로 보는 게 맞습니다.`,
    investment:`투자·계약은 ${y}년 전후에 결정이 커지기 쉽습니다. 사주보다 실제 가격, 계약서, 대출·상환 조건을 먼저 확인하고 “지금 아니면 안 된다”는 마음이 들수록 한 번 더 검토하는 게 좋습니다.`,
    newlove:`새 연애는 ${y}년 전후에 사람을 만나거나 관계의 모양이 달라지는 흐름이 강합니다. 만남 자체보다 “이 관계를 이어갈지” 결정하는 힘이 함께 커질 수 있습니다.`,
    reunion:`재회는 단순히 다시 연락이 오는가보다, ${y}년 전후에 그 관계를 다시 받아들일지 정리할지가 더 크게 움직입니다. 연락 가능성과 좋은 관계로 이어지는지는 따로 봐야 합니다.`,
    marriage:`결혼은 ${y}년 전후에 관계를 실제 약속이나 생활 변화로 옮기는 힘이 커집니다. 연애가 좋다는 말과 결혼하기 좋은 시기는 같은 뜻이 아닙니다.`,
    children:`자녀 문제는 ${y}년 전후에 계획·돌봄·가족 역할을 많이 생각하게 되는 흐름이 강합니다. 임신 가능성이나 건강 문제를 사주로 단정하지는 않습니다.`,
    relations:`인간관계는 ${y}년 전후에 가까이 둘 사람과 거리를 둘 사람이 더 분명해지기 쉽습니다. 사람 수보다 관계의 역할이 바뀌는 쪽을 먼저 봅니다.`,
    exam:`시험·합격은 ${y}년 전후에 준비와 결과가 크게 연결됩니다. 운이 좋다는 말보다 공부량, 일정, 응시 조건을 현실적으로 맞추는 것이 우선입니다.`,
    realestate:`이사·부동산은 ${y}년 전후에 생활 터전이나 집과 관련된 변화가 가장 크게 잡힙니다. 이사, 독립, 합가, 직장 때문에 거주지가 바뀌는 경우도 포함됩니다.`,
    family:`가족 문제는 ${y}년 전후에 내가 챙겨야 할 몫이나 가족 안의 역할이 커지기 쉽습니다. 혼자 해결하려 하기보다 역할을 나누는 게 중요합니다.`,
    annual:`올해 전체 흐름은 한 가지 운보다 일·돈·관계 중 어디가 가장 크게 움직이는지를 먼저 보는 게 맞습니다. ${y}년 전후가 가까운 흐름에서 가장 큰 변곡으로 잡힙니다.`
  }[topic]||`${y}년 전후가 가장 크게 움직입니다.`;
  box.innerHTML=`<div class="v8AnswerCard"><div class="v8Ey">무료 질문 풀이</div><h3>${esc(lead)}</h3><p>${months.length?`특히 ${months.join('월·')} 전후가 더 눈에 띕니다. 시기는 앞뒤로 조금 달라질 수 있습니다.`:''}</p>${custom?`<p class="v8CustomEcho">적어주신 상황: “${esc(custom)}”<br>현재 MVP에서는 질문의 세부 문장까지 해석하는 AI 상담 연결 전 단계라, 선택한 주제를 기준으로 풀이했습니다.</p>`:''}</div>`;
}
function installStyle(){const s=document.createElement('style');s.textContent=`#pastYears,.feedbackBox,.answer,.chart{display:none!important}.locked{margin-top:18px}.v8Paper{margin:14px 0;padding:22px 20px;background:linear-gradient(180deg,#f2eadb,#e8decb);color:#211811;border:1px solid rgba(151,120,82,.38);box-shadow:0 18px 45px rgba(0,0,0,.2);position:relative}.v8Lead{border-top:3px solid #7d2a23}.v8Ey{font-size:10px;font-weight:900;letter-spacing:.14em;color:#812e27;margin-bottom:8px}.v8Paper h2{margin:0 0 12px;font-family:Georgia,'Noto Serif KR',serif;font-size:22px;line-height:1.45}.v8Paper p{margin:0;font-size:14px;line-height:1.9;color:#50483f}.v8Timing{margin-top:14px;padding:10px 12px;border-left:3px solid #8a3027;background:rgba(132,46,38,.07);font-size:12px;line-height:1.6}.v8Question{margin-top:20px}.v8Grid{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin:16px 0}.v8Topic{appearance:none;border:1px solid #ab987a;background:rgba(255,255,255,.28);padding:12px 8px;font-size:12px;font-weight:800;color:#30261f}.v8Topic.on{background:#752820;color:#f4eade;border-color:#752820}.v8Question textarea{width:100%;box-sizing:border-box;border:1px solid #aa9677;background:rgba(255,255,255,.38);padding:12px;font-size:13px;line-height:1.6;color:#211811}.v8AnswerCard{margin-top:14px;padding-top:16px;border-top:1px solid rgba(116,85,55,.25)}.v8AnswerCard h3{font-size:17px;line-height:1.7;margin:0 0 10px}.v8CustomEcho{margin-top:12px!important;padding:10px;background:rgba(127,45,37,.05);font-size:12px!important}.v8Seal{margin:16px 0 4px;padding:16px 18px;background:#120c0a;border:1px solid #3d2a22;color:#d8cab8}.v8Seal span,.v8Seal small{display:block;font-size:10px;color:#958679}.v8Seal b{display:block;margin:6px 0;font-family:monospace;letter-spacing:.08em;color:#eadfce}.resultHero p{max-width:320px}@media(max-width:390px){.v8Paper{padding:19px 16px}.v8Paper h2{font-size:20px}}`;document.head.appendChild(s);}

installStyle();
try{lib=await import('https://cdn.jsdelivr.net/npm/manseryeok@2.0.0/+esm');}catch(e){console.error(e);}
$('#f')?.addEventListener('submit',()=>{readingInput=getInput();});
let rendered=false;
const obs=new MutationObserver(()=>{if(rendered||$('#result')?.classList.contains('hide')||!lib)return;rendered=true;readingInput=readingInput||getInput();try{lastNatal=lib.calculateFourPillars(readingInput);renderBlind(lastNatal,readingInput);const locked=$('.locked');if(locked)locked.style.display='none';const q=$('#v8Question');if(q){q.addEventListener('click',e=>{if(e.target.closest('.v8Topic')&&locked)locked.style.display='block';},{once:true});}}catch(e){console.error(e);}});
obs.observe(document.body,{attributes:true,subtree:true,childList:true});