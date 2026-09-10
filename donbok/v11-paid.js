const $=s=>document.querySelector(s),$$=s=>[...document.querySelectorAll(s)];
let paidTopic='annual',paidLabel='올해 전체운';

document.addEventListener('click',e=>{
  const t=e.target.closest('.v8Topic');
  if(t){paidTopic=t.dataset.topic||'annual';paidLabel=t.textContent.trim()||'선택한 주제';}
});

function getPreviewContext(){
  const box=$('#v8Answer');
  const text=box?.textContent||'';
  const year=text.match(/20\d{2}/)?.[0]||String(new Date().getFullYear()+1);
  const months=(text.match(/\d+월/g)||[]).slice(0,2);
  return{year,months};
}

const paidCopy={
  business:{summary:'사업은 "할 수 있느냐"보다 언제 작게 검증하고 언제 키우느냐가 더 중요하게 보여요.',good:'혼자 준비만 오래 하기보다 실제 고객 반응을 확인하고 매출이 반복되는 시점부터 키우는 쪽이 맞습니다.',bad:'고정비를 먼저 크게 늘리거나, 아직 검증 안 된 아이템에 한 번에 큰돈을 넣는 선택은 피하는 편이 좋아요.'},
  sidejob:{summary:'부업은 큰 한 방보다 작아도 반복해서 들어오는 돈을 만드는 쪽이 더 잘 맞습니다.',good:'본업을 유지한 채 수입원이 하나 더 생기는 구조를 먼저 만들고, 반복 매출이 확인되면 시간을 더 쓰는 순서가 좋아요.',bad:'처음부터 본업을 버리거나 장비·광고비부터 크게 쓰는 방식은 부담이 커질 수 있습니다.'},
  jobchange:{summary:'이직은 감정이 가장 올라온 순간보다 조건이 실제로 잡혔을 때 움직이는 편이 낫습니다.',good:'역할·연봉·근무환경 중 최소 두 가지가 분명히 좋아지는 자리라면 움직일 명분이 생겨요.',bad:'그냥 지금 회사가 싫다는 이유 하나만으로 공백부터 만드는 선택은 피하는 편이 좋습니다.'},
  promotion:{summary:'승진운은 단순히 일이 많아지는 것과 다르게, 책임과 보상이 함께 움직일 때 의미가 있습니다.',good:'역할이 커질수록 직책·평가·보상까지 같이 요구하는 편이 좋아요.',bad:'일만 늘고 권한과 보상은 그대로라면 좋은 흐름으로 보기 어렵습니다.'},
  money:{summary:'돈은 들어오는 크기보다 "남는 구조"를 먼저 봐야 하는 시기예요.',good:'반복 수입과 고정비 관리가 같이 잡히면 돈의 흐름이 안정되기 쉬워요.',bad:'수입이 늘었다는 이유로 지출·투입 규모를 동시에 키우는 건 조심하는 편이 좋습니다.'},
  investment:{summary:'투자·계약은 기회보다 조건을 먼저 봐야 해요.',good:'가격·대출·상환·계약서가 모두 확인된 뒤 움직이면 리스크를 줄일 수 있습니다.',bad:'지금 아니면 못 산다는 조급함, 지인 권유만 믿는 결정, 애매한 계약서는 피하는 게 좋아요.'},
  newlove:{summary:'새 연애는 만나는 사람 수보다 한 관계가 빠르게 선명해지는 쪽을 먼저 봅니다.',good:'애매한 관계를 오래 끌기보다 서로 의사와 생활 조건이 맞는지를 빨리 확인하는 게 좋아요.',bad:'외로움 때문에 관계를 서두르거나 상대의 태도를 좋게만 해석하는 건 조심해야 합니다.'},
  reunion:{summary:'재회는 연락 자체보다 다시 만나도 같은 문제가 반복되지 않는지가 핵심이에요.',good:'헤어진 이유가 실제로 달라졌는지, 다시 만날 조건이 생겼는지를 먼저 봐야 합니다.',bad:'그리움 하나만으로 다시 시작하면 같은 문제를 다시 만날 가능성이 커요.'},
  marriage:{summary:'결혼은 감정보다 생활이 실제로 맞물리는 시기를 봐야 해요.',good:'거주·돈·가족·일정 같은 현실 조건을 함께 정리할 수 있을 때 결정하는 편이 좋습니다.',bad:'분위기나 주변 압박 때문에 시기를 앞당기는 건 피하는 편이 좋아요.'},
  children:{summary:'자녀 문제는 계획과 가족 역할이 같이 바뀌는 흐름으로 보는 게 맞습니다.',good:'돌봄·일·생활비를 함께 준비하는 쪽이 중요합니다.',bad:'임신 가능성이나 건강 문제를 사주로 단정하지 않습니다. 실제 의료 정보가 우선입니다.'},
  relations:{summary:'인간관계는 사람 수를 늘리는 것보다 가까이 둘 사람을 정리하는 쪽이 더 중요해요.',good:'일방적으로 에너지만 빼앗기는 관계는 거리를 두는 편이 낫습니다.',bad:'미안함 때문에 계속 끌려가거나 돈과 관계를 섞는 건 조심해야 합니다.'},
  exam:{summary:'시험·합격은 운보다 준비가 결과로 드러나는 구간을 잘 쓰는 게 핵심입니다.',good:'시험 직전 몰아치기보다 일정과 반복 학습을 고정하는 편이 좋아요.',bad:'운이 좋다는 이유로 준비량을 줄이는 선택은 의미가 없습니다.'},
  realestate:{summary:'이사운과 집을 사기 좋은 운은 같은 게 아니에요. 움직임과 계약은 따로 봐야 합니다.',good:'생활상 필요 때문에 옮기는 것과 자산 매수는 분리해서 판단하는 게 좋아요. 계약 전 실제 시세·대출·등기 조건을 먼저 확인해야 합니다.',bad:'마음이 급해져 "지금 아니면 안 된다"고 느끼는 계약은 한 번 더 멈춰보는 편이 좋습니다.'},
  family:{summary:'가족 문제는 내가 어디까지 맡을지 경계를 정하는 게 핵심이에요.',good:'도와줄 일과 내가 대신 책임질 일을 나눠야 부담이 덜해집니다.',bad:'모든 문제를 혼자 떠안는 방식은 오래 가기 어렵습니다.'},
  annual:{summary:'올해는 한 가지 운보다 일·돈·관계 중 무엇이 먼저 움직이는지 순서를 보는 게 중요합니다.',good:'가장 강하게 움직이는 영역 하나를 먼저 정리하고 나머지는 뒤로 미루는 편이 좋아요.',bad:'여러 결정을 한꺼번에 벌이는 건 피하는 편이 좋습니다.'}
};

function yearRows(baseYear){
  const y=Number(baseYear)||new Date().getFullYear()+1;
  return [
    [y-1,'정리','지금까지 해오던 방식을 점검하는 해'],
    [y,'움직임','실제 결정과 변화가 가장 크게 드러나는 해'],
    [y+1,'자리잡기','바뀐 선택을 현실에 맞추는 해'],
    [y+2,'확장','잘 된 것을 키우되 무리하지 않는 해'],
    [y+3,'재정비','속도를 줄이고 다시 고르는 해']
  ];
}

function monthRows(months){
  const a=months[0]?.replace('월','')||'3',b=months[1]?.replace('월','')||'9';
  return [
    ['1~2월','정리','새 결정 전 준비와 확인이 우선'],
    [`${a}월 전후`,'첫 변곡','사람·조건·제안이 실제로 움직이기 쉬움'],
    ['5~7월','검증','처음 결정이 맞는지 현실 반응을 확인'],
    [`${b}월 전후`,'두 번째 변곡','밀어붙일지 멈출지 다시 판단'],
    ['11~12월','정리','올해 결과를 남기고 다음 해 계획을 고정']
  ];
}

function renderPaid(){
  const checkout=$('#checkout');if(!checkout)return;
  const {year,months}=getPreviewContext();
  const c=paidCopy[paidTopic]||paidCopy.annual;
  const years=yearRows(year),mons=monthRows(months);
  checkout.innerHTML=`
    <div class="paidHero"><div class="paidEy">현월당 심층 풀이 · ${paidLabel}</div><h1>결론부터<br>말씀드릴게요.</h1><p>${c.summary}</p></div>
    <section class="paidPaper paidVerdict"><div class="paidNo">01</div><div class="paidEy">먼저 결론</div><h2>${c.summary}</h2><div class="paidSplit"><div><small>밀어도 되는 쪽</small><p>${c.good}</p></div><div><small>조심해야 할 쪽</small><p>${c.bad}</p></div></div></section>
    <section class="paidPaper"><div class="paidNo">02</div><div class="paidEy">가장 중요한 시기</div><h2>${year}년 전후를 중심으로 봅니다.</h2><p>좋은 해, 나쁜 해로 한 줄 자르지 않습니다. 그 안에서도 움직여도 되는 때와 잠깐 기다리는 편이 나은 때가 갈립니다.</p><div class="paidTimeline">${mons.map(([m,t,d])=>`<div><b>${m}</b><span>${t}</span><p>${d}</p></div>`).join('')}</div></section>
    <section class="paidPaper"><div class="paidNo">03</div><div class="paidEy">앞으로 5년</div><h2>언제 움직이고, 언제 자리 잡는지</h2><div class="paidYears">${years.map(([y,t,d])=>`<div><strong>${y}</strong><b>${t}</b><p>${d}</p></div>`).join('')}</div></section>
    <section class="paidPaper"><div class="paidNo">04</div><div class="paidEy">이 선택을 할 때</div><h2>현월당은 이렇게 봅니다.</h2><div class="paidAdvice"><p><b>지금 당장 할 일</b><br>${c.good}</p><p><b>한 번 더 멈춰볼 신호</b><br>${c.bad}</p><p><b>판단 기준</b><br>사주 풀이만으로 결정하지 말고, 실제 돈·계약·관계·건강 같은 현실 조건과 함께 보세요.</p></div></section>
    <section class="paidPaper"><div class="paidNo">05</div><div class="paidEy">개인 질문</div><h2>이제 내 상황을 그대로 물어보세요.</h2><p>예: “10월에 회사를 그만두고 가게를 열어도 될까요?”처럼 구체적으로 적을수록 좋습니다.</p><textarea class="paidQuestion" rows="4" placeholder="내 상황을 구체적으로 적어주세요"></textarea><button class="paidAsk" type="button">이 질문 이어서 보기 →</button><small class="paidFine">현재는 결제 후 화면 구성 검증용 데모입니다. 질문 전송은 아직 연결하지 않았습니다.</small></section>
    <section class="paidSeal"><span>玄月堂</span><b>이번 풀이도 날짜와 함께 기록됩니다.</b><small>한 번 한 풀이는 나중에 내용이 바뀌지 않도록 기록하는 구조로 설계합니다.</small></section>
    <button id="paidBack" class="paidBack" type="button">← 이전 결과로 돌아가기</button>`;
  checkout.classList.remove('hide');
  $('#result')?.classList.add('hide');$('#landing')?.classList.add('hide');
  scrollTo(0,0);
  $('#paidBack')?.addEventListener('click',()=>{checkout.classList.add('hide');$('#result')?.classList.remove('hide');scrollTo(0,0);});
}

function install(){
  const s=document.createElement('style');s.textContent=`
  .paidHero{padding:48px 20px 34px;background:#090706;color:#efe5d7;border-bottom:1px solid #3b2922}.paidEy{font-size:10px;font-weight:900;letter-spacing:.14em;color:#9c3a30;margin-bottom:9px}.paidHero h1{font-family:Georgia,'Noto Serif KR',serif;font-size:38px;line-height:1.25;margin:0 0 16px}.paidHero p{font-size:15px;line-height:1.9;color:#b9aa9b;margin:0}.paidPaper{position:relative;margin:0;padding:28px 20px;background:linear-gradient(180deg,#f0e6d4,#e8dcc6);color:#261d17;border-bottom:1px solid #c9b28f}.paidPaper h2{font-family:Georgia,'Noto Serif KR',serif;font-size:23px;line-height:1.5;margin:0 0 14px}.paidPaper>p{font-size:14px;line-height:1.9;color:#51473f}.paidNo{position:absolute;right:16px;top:14px;font-size:11px;color:#9e8f7a}.paidSplit{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:18px}.paidSplit>div{padding:15px;background:rgba(255,255,255,.35);border:1px solid #c8b493}.paidSplit small{display:block;color:#8c3028;font-weight:900;margin-bottom:6px}.paidSplit p,.paidAdvice p{font-size:13px;line-height:1.8;margin:0}.paidTimeline{margin-top:18px}.paidTimeline>div{display:grid;grid-template-columns:74px 74px 1fr;gap:8px;padding:13px 0;border-top:1px solid rgba(114,82,51,.2);align-items:start}.paidTimeline b{font-size:13px}.paidTimeline span{font-size:12px;color:#8b3028;font-weight:900}.paidTimeline p{font-size:12px;line-height:1.6;margin:0;color:#655b52}.paidYears{display:grid;gap:8px;margin-top:16px}.paidYears>div{display:grid;grid-template-columns:62px 70px 1fr;gap:8px;padding:12px;border:1px solid #c9b590;background:rgba(255,255,255,.28)}.paidYears strong{font-size:18px;color:#8b3028}.paidYears b{font-size:12px}.paidYears p{font-size:12px;line-height:1.6;margin:0;color:#655b52}.paidAdvice{display:grid;gap:10px}.paidAdvice p{padding:14px;border-left:3px solid #8b3028;background:rgba(139,48,40,.05)}.paidQuestion{width:100%;box-sizing:border-box;margin-top:10px;padding:13px;border:1px solid #aa9472;background:rgba(255,255,255,.38);font-size:14px;line-height:1.7}.paidAsk{width:100%;margin-top:10px;border:0;background:#76281f;color:#f6ecdf;padding:15px;font-weight:900}.paidFine{display:block;margin-top:8px;font-size:10px;color:#8c7d6d}.paidSeal{padding:24px 20px;background:#100b09;color:#d9cbb9}.paidSeal span,.paidSeal b,.paidSeal small{display:block}.paidSeal span{font-family:Georgia,serif;font-size:22px}.paidSeal b{margin:8px 0;font-size:13px}.paidSeal small{font-size:10px;color:#938477;line-height:1.6}.paidBack{width:calc(100% - 40px);margin:18px 20px 30px;padding:14px;border:1px solid #4a332a;background:#0d0908;color:#d9cbb9}@media(max-width:390px){.paidSplit{grid-template-columns:1fr}.paidTimeline>div{grid-template-columns:64px 64px 1fr}.paidYears>div{grid-template-columns:54px 60px 1fr}.paidHero h1{font-size:34px}}
  `;document.head.appendChild(s);
  document.addEventListener('click',e=>{if(e.target.closest('#pay')){setTimeout(renderPaid,0);}});
}
install();