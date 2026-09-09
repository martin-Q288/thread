const $=s=>document.querySelector(s),$$=s=>[...document.querySelectorAll(s)];

const push=(event,extra={})=>{window.dataLayer=window.dataLayer||[];window.dataLayer.push({event,...extra,hyunwoldang_version:'9.0.0'});};

const domainTone={
  work:{title:'지금은 일 쪽이 먼저 보여요.',body:'그냥 일이 많다는 얘기는 아니에요. 지금 하던 방식을 계속 가져갈지, 역할을 바꿀지, 아예 다른 길을 만들지 마음이 자꾸 그쪽으로 가기 쉬운 때예요.'},
  money:{title:'지금은 돈의 흐름을 먼저 봐야겠어요.',body:'얼마를 버느냐보다 돈이 들어오고 나가는 방식이 달라지는 쪽이 더 커 보여요. 수입원을 하나 더 만들거나, 반대로 큰돈이 한 번에 나갈 일이 생기는 식으로요.'},
  relationship:{title:'지금은 사람 관계가 먼저 걸려요.',body:'새 사람을 만나는 문제만은 아니에요. 이미 가까운 사람과 거리를 어떻게 둘지, 이 관계를 계속 가져갈지 정해야 하는 일이 더 먼저 보여요.'},
  home:{title:'지금은 집이나 생활환경 쪽이 먼저 보여요.',body:'사는 곳을 그대로 둘지 바꿀지, 독립이나 합가를 할지, 출퇴근이나 일 때문에 생활 반경이 달라지는 문제가 먼저 걸립니다.'},
  family:{title:'지금은 가족 쪽 일이 먼저 보여요.',body:'내 일만 챙기기보다 부모나 형제, 자녀 문제처럼 가족 안에서 내가 맡아야 할 몫이 커지기 쉬운 때예요.'},
  study:{title:'지금은 준비하고 확인해야 할 일이 먼저 보여요.',body:'시험이나 자격, 공부, 계약서나 중요한 서류처럼 대충 넘기면 안 되는 일이 커지기 쉬워요. 결과보다 준비 과정이 더 중요하게 잡힙니다.'}
};

function detectDomain(text=''){
  if(/일·직장|일 때문에|직장|역할/.test(text)) return 'work';
  if(/돈·수입|돈의 크기|수입|지출/.test(text)) return 'money';
  if(/가까운 관계|가까운 사람|연애|관계/.test(text)) return 'relationship';
  if(/집·이사|생활 터전|생활환경|이사/.test(text)) return 'home';
  if(/가족·자녀|가족 안|부모|자녀/.test(text)) return 'family';
  if(/시험·배움|시험·자격|공부|서류/.test(text)) return 'study';
  return null;
}
function getYear(card){const m=(card.querySelector('.v8Ey')?.textContent||'').match(/(20\d{2})/);return m?m[1]:'';}
function getMonths(card){return card.querySelector('.v8Timing')?.textContent.match(/\d+월/g)?.join('·')||'';}

function humanizeBlind(){
  const mount=$('#blindMount');if(!mount||mount.dataset.humanized==='1')return;
  const papers=[...mount.querySelectorAll('.v8Paper')];if(!papers.length)return;
  const lead=mount.querySelector('.v8Lead');
  if(lead){
    const h=lead.querySelector('h2'),p=lead.querySelector('p');
    const txt=(h?.textContent||'')+' '+(p?.textContent||'');
    const domains=[...Object.keys(domainTone)].filter(d=>{
      const key={work:'일·직장',money:'돈·수입',relationship:'가까운 관계',home:'집·이사',family:'가족·자녀',study:'시험·배움'}[d];return txt.includes(key);
    });
    if(domains.length>=2){
      const a=domainTone[domains[0]],b=domainTone[domains[1]];
      h.textContent=`지금은 ${a.title.replace('지금은 ','').replace('이 먼저 보여요.','')}만 볼 때는 아니에요.`;
      p.textContent=`${a.body} 그리고 ${b.body}`;
    }else{
      const d=detectDomain(txt);if(d){h.textContent=domainTone[d].title;p.textContent=domainTone[d].body;}
      else if(h&&/한 가지 문제/.test(h.textContent)){h.textContent='지금은 한 가지만 딱 집어 말하기 어렵네요.';p.textContent='여러 일이 같이 움직이는 때라 하나를 억지로 찍지는 않을게요. 대신 지나온 시기와 앞으로 크게 움직이는 때를 보면 방향이 더 선명해집니다.';}
    }
  }
  papers.forEach(card=>{
    const ey=card.querySelector('.v8Ey')?.textContent||'';if(!ey.includes('지나온 흐름'))return;
    const h=card.querySelector('h2'),p=card.querySelector('p');const y=getYear(card),d=detectDomain((h?.textContent||'')+' '+(p?.textContent||'')),months=getMonths(card);
    const copy={
      work:`${y}년, 이 해는 일 때문에 마음이 한 번 크게 흔들렸을 수 있어요. 꼭 회사를 옮겼다는 뜻은 아니고요. 맡은 일이 갑자기 바뀌었거나 책임이 늘면서 ‘계속 이렇게 해야 하나’ 싶었던 쪽에 가깝습니다.`,
      money:`${y}년은 돈이 평소처럼 조용히 지나간 해로는 안 보여요. 수입이 달라졌거나 큰돈이 나갔거나, 계약이나 사람 때문에 돈 문제를 오래 신경 썼을 수 있습니다.`,
      relationship:`${y}년은 가까운 사람과의 관계가 한 번 크게 움직였을 수 있어요. 새 사람을 만났다기보다, 이미 가까운 사람과 거리를 두거나 관계를 다시 정한 쪽이 더 먼저 보입니다.`,
      home:`${y}년은 사는 곳이나 생활 반경이 바뀌었을 수 있어요. 실제 이사뿐 아니라 독립, 합가, 출퇴근 변화처럼 생활 자체가 달라진 경우도 여기에 들어갑니다.`,
      family:`${y}년은 내 일보다 가족 일에 마음을 많이 썼을 수 있어요. 부모나 형제, 자녀 문제처럼 내가 챙겨야 할 일이 갑자기 커졌던 쪽입니다.`,
      study:`${y}년은 뭔가 새로 준비하고 증명해야 했던 해로 보여요. 시험, 자격, 공부, 중요한 서류처럼 그냥 넘기기 어려운 일이 있었을 수 있습니다.`
    }[d];
    if(copy){h.textContent=`${y}년, 이 해는 그냥 지나가진 않았어요.`;p.textContent=copy+(months?` 특히 ${months} 전후를 한번 떠올려보세요.`:'');}
  });
  const future=papers.find(x=>x.querySelector('.v8Ey')?.textContent.includes('가까운 미래'));
  if(future){const h=future.querySelector('h2'),p=future.querySelector('p'),y=(h?.textContent||'').match(/20\d{2}/)?.[0]||'',d=detectDomain((h?.textContent||'')+' '+(p?.textContent||'')),months=getMonths(future);const label={work:'일',money:'돈',relationship:'사람 관계',home:'집이나 생활환경',family:'가족',study:'시험이나 준비할 일'}[d];if(h&&y)h.textContent=`앞으로는 ${y}년을 좀 봐야 해요.`;if(p&&label)p.textContent=`이때는 ${label} 쪽이 지금보다 크게 움직일 가능성이 있어요.${months?` 특히 ${months} 전후가 눈에 띕니다.`:''} 좋은지 나쁜지 한마디로 자르기보다, 그때 무엇을 선택하느냐가 더 중요해요.`;}
  const q=mount.querySelector('.v8Question');if(q){const h=q.querySelector('h2'),p=q.querySelector('p');if(h)h.innerHTML='이제 하나만<br>물어보세요.';if(p)p.textContent='고른 주제는 바로 결제시키지 않습니다. 먼저 중요한 부분까지 풀어드리고, 더 깊은 내용부터 잠깁니다.';}
  mount.dataset.humanized='1';
}

function previewCopy(topic,year,months){
  const when=months?` 특히 ${months} 전후를 봐야 하고요.`:'';
  const m={
    business:[`사업은 ${year}년 쪽이 먼저 보여요.`,`지금 당장 본업을 던지고 올인하는 그림보다는, 실제로 돈을 내는 고객이 생기는지 먼저 확인하고 키우는 쪽에 가깝습니다.${when}`],
    sidejob:[`부업은 ${year}년 전후가 눈에 띄어요.`,`처음부터 큰돈을 만드는 것보다 작아도 반복해서 들어오는 수입을 하나 더 만드는 쪽이 맞습니다.${when}`],
    jobchange:[`이직은 ${year}년 전후를 먼저 봐야 해요.`,`감정이 올라온 날 바로 나가는 것보다, 다른 자리나 조건이 실제로 잡힐 때 움직이는 게 낫습니다.${when}`],
    promotion:[`직장에서는 ${year}년 전후에 역할이 커질 수 있어요.`,`일이 많아지는 것과 제대로 인정받는 건 달라요. 직책이나 평가, 보상까지 같이 움직이는지를 봐야 합니다.${when}`],
    money:[`돈은 ${year}년 전후에 움직임이 커져요.`,`무조건 돈이 많이 들어온다는 뜻은 아니에요. 들어오는 돈과 나가는 돈이 같이 커질 수 있어서 무엇에 돈을 묶느냐가 중요합니다.${when}`],
    investment:[`투자나 계약은 ${year}년 전후에 큰 결정을 하기 쉬워요.`,`이럴 때는 ‘지금 아니면 안 된다’는 마음이 들수록 한 번 더 보는 게 좋습니다. 계약 조건과 실제 숫자를 먼저 확인해야 해요.${when}`],
    newlove:[`새 인연은 ${year}년 전후가 눈에 띄어요.`,`사람을 만나는 것 자체보다, 만난 뒤 이 관계를 계속 가져갈지 빨리 정해지는 쪽이 더 강합니다.${when}`],
    reunion:[`재회는 ${year}년 전후에 다시 움직일 여지가 있어요.`,`다만 연락이 오는 것과 다시 좋은 관계가 되는 건 다른 문제예요. 다시 잡는 게 맞는 관계인지까지 따로 봐야 합니다.${when}`],
    marriage:[`결혼은 ${year}년 전후를 먼저 봐야 해요.`,`연애가 잘 되는 시기와 실제로 약속을 하고 같이 사는 시기는 다릅니다. 이때는 관계를 현실로 옮기는 힘이 커질 수 있어요.${when}`],
    children:[`자녀 문제는 ${year}년 전후에 생각이 커질 수 있어요.`,`계획이나 돌봄, 가족 안에서의 역할이 달라지는 쪽을 먼저 봅니다. 임신 여부나 건강을 사주로 단정하지는 않아요.${when}`],
    relations:[`사람 관계는 ${year}년 전후에 정리가 한 번 들어올 수 있어요.`,`누가 새로 생기느냐보다, 누구를 가까이 두고 누구와 거리를 둘지가 더 분명해지는 때에 가깝습니다.${when}`],
    exam:[`시험이나 합격은 ${year}년 전후에 결과가 크게 갈릴 수 있어요.`,`운만 믿고 밀어붙이는 시기라기보다 준비한 만큼 결과가 드러나는 쪽입니다.${when}`],
    realestate:[`이사·부동산은 ${year}년 전후에 움직임이 커져요.`,`실제 이사뿐 아니라 독립, 합가, 직장 때문에 사는 곳이 바뀌는 일도 포함됩니다. 계약과 돈은 따로 꼼꼼히 봐야 해요.${when}`],
    family:[`가족 문제는 ${year}년 전후에 내가 챙길 일이 늘 수 있어요.`,`내 일보다 가족 안에서 맡아야 할 역할이 커지는 쪽이 먼저 보입니다.${when}`],
    annual:[`올해는 한 가지만 보는 것보다 크게 움직이는 영역부터 봐야 해요.`,`일·돈·사람 중 어디가 먼저 흔들리는지 보고, 그 다음에 시기를 좁혀야 합니다.${when}`]
  };return m[topic]||[`이 주제는 ${year}년 전후가 먼저 보여요.`,`큰 흐름부터 보면 이때 움직임이 가장 큽니다.${when}`];
}

const lockedBullets={
  business:['지금 시작할 때인지, 기다릴 때인지','사업이 커지는 시기와 돈이 새는 시기','혼자 하는 게 나은지 사람을 붙일지'],
  sidejob:['어떤 방식의 부수입이 맞는지','본업과 같이 가져가기 좋은 시기','부업이 본업을 넘길 수 있는 구간'],
  jobchange:['나가도 되는 시기와 버텨야 하는 시기','다음 자리의 조건이 좋아지는 때','퇴사 후 공백을 조심할 구간'],
  promotion:['승진·평가가 실제 보상으로 이어지는지','윗사람과 부딪히기 쉬운 시기','지금 자리에서 더 챙겨야 할 것'],
  money:['돈이 들어오는 해와 지켜야 하는 해','큰 지출·계약을 조심할 시기','수입원이 바뀌는 구간'],
  investment:['계약하기 좋은 때와 보류할 때','사람과 돈이 얽히기 쉬운 시기','큰돈이 묶이지 않게 볼 부분'],
  newlove:['인연이 들어오는 시기','짧게 끝날 관계와 오래 갈 관계의 차이','관계를 결정하기 좋은 때'],
  reunion:['연락 가능성이 높아지는 때','다시 만나도 같은 문제가 반복될지','잡아야 할 관계인지 놓아야 할 관계인지'],
  marriage:['결혼을 결정하기 좋은 시기','같이 살면서 부딪힐 부분','관계를 미루는 게 나은 구간'],
  children:['가족 계획이 커지는 시기','돌봄과 역할 변화가 큰 때','생활 계획을 어떻게 잡는 게 나은지'],
  relations:['가까이 둘 사람과 거리를 둘 사람','사람 때문에 손해 보기 쉬운 때','관계가 새로 묶이는 시기'],
  exam:['결과가 잘 드러나는 시기','집중력이 흔들리기 쉬운 구간','시험·자격 준비의 흐름'],
  realestate:['이사하기 좋은 때와 기다릴 때','계약·잔금·대출을 특히 조심할 시기','사는 곳이 바뀌면 함께 달라지는 것'],
  family:['가족 안에서 내 역할이 커지는 때','누구 문제를 내가 떠안기 쉬운지','가족과 거리를 조절해야 할 시기'],
  annual:['올해 가장 크게 움직이는 문제','월별로 강하고 약한 시기','올해 꼭 피해야 할 선택']
};

function buildPreview(btn){
  const box=$('#v8Answer');if(!box)return;const topic=btn.dataset.topic,label=btn.textContent.trim();
  const old=box.textContent;const year=old.match(/20\d{2}/)?.[0]||new Date().getFullYear();const months=(old.match(/\d+월/g)||[]).slice(0,2).join('·');
  if(topic==='compatibility'){
    box.innerHTML=`<div class="v9Preview"><div class="v8Ey">궁합</div><h3>궁합은 두 사람 정보가 있어야 봐요.</h3><p>한 사람 생년월일만 보고 잘 맞는다, 안 맞는다 말하는 건 의미가 없습니다. 상대방 생년월일과 태어난 시간을 함께 받아 두 사람을 비교해야 해요.</p></div>`;return;
  }
  const [title,body]=previewCopy(topic,year,months);const items=lockedBullets[topic]||['가장 중요한 시기','조심해야 할 선택','앞으로의 흐름'];
  box.innerHTML=`<div class="v9Preview"><div class="v8Ey">${label} · 먼저 여기까지 볼게요</div><h3>${title}</h3><p>${body}</p><div class="v9OpenNote">여기까지는 무료로 봅니다. 아래부터는 같은 주제를 더 좁혀서 봐요.</div></div><div class="v9BlurWrap"><div class="v9BlurContent">${items.map((x,i)=>`<div class="v9LockedRow"><small>0${i+1}</small><b>${x}</b><p>${i===0?`${year}년 안에서도 움직여도 되는 때와 기다리는 편이 나은 때가 갈립니다.`:i===1?'겉으로 좋은 흐름처럼 보여도 같이 따라오는 위험 신호를 따로 봅니다.':'앞으로의 시기를 월 단위까지 더 좁혀서 이어서 풉니다.'}</p></div>`).join('')}</div><div class="v9Paywall"><span>여기서부터 심층 풀이</span><h3>${label}, 중요한 부분은 지금부터입니다.</h3><p>무료에서 큰 방향을 봤다면, 유료에서는 ‘언제 / 무엇을 / 왜 조심해야 하는지’까지 이어서 봅니다.</p><strong>29,000원</strong><button type="button" id="v9Unlock">이 풀이 끝까지 보기 <b>→</b></button></div></div>`;
  const oldLocked=$('.locked');if(oldLocked)oldLocked.style.display='none';
  $('#v9Unlock')?.addEventListener('click',()=>{push('topic_paywall_click',{topic,label});$('#buy')?.click();});
  push('topic_preview_view',{topic,label});
  box.scrollIntoView({behavior:'smooth',block:'start'});
}

function install(){
  const style=document.createElement('style');style.textContent=`
  .v9Preview{margin-top:18px;padding-top:18px;border-top:1px solid rgba(112,82,55,.28)}.v9Preview h3{font-size:20px!important;line-height:1.6!important;margin:0 0 10px!important}.v9Preview p{font-size:14px!important;line-height:1.9!important}.v9OpenNote{margin-top:16px;padding:11px 12px;border-left:3px solid #7b2b24;background:rgba(123,43,36,.06);font-size:12px;line-height:1.7;color:#62574d}.v9BlurWrap{position:relative;margin-top:14px;overflow:hidden;border-top:1px solid rgba(112,82,55,.2)}.v9BlurContent{filter:blur(5px);user-select:none;pointer-events:none;opacity:.58;padding:10px 0 155px}.v9LockedRow{padding:14px 2px;border-bottom:1px solid rgba(112,82,55,.18)}.v9LockedRow small{display:block;color:#832f28;font-weight:900;margin-bottom:4px}.v9LockedRow b{display:block;font-size:16px;margin-bottom:5px}.v9LockedRow p{font-size:12px!important;line-height:1.7!important}.v9Paywall{position:absolute;left:0;right:0;bottom:0;padding:58px 14px 18px;background:linear-gradient(180deg,rgba(235,225,207,0),#eadfca 34%,#e7dcc7 100%);text-align:center}.v9Paywall span{font-size:10px;font-weight:900;letter-spacing:.12em;color:#812e27}.v9Paywall h3{font-size:18px!important;line-height:1.55!important;margin:7px 0!important}.v9Paywall p{font-size:12px!important;line-height:1.7!important;margin:0 auto 8px!important;max-width:310px}.v9Paywall strong{display:block;font-size:22px;margin:8px 0 12px;color:#291c16}.v9Paywall button{width:100%;border:0;background:#74271f;color:#f6ecdf;padding:15px 14px;font-weight:900;font-size:14px}.v8Paper h2,.v8Paper p{word-break:keep-all}.v8Ey{text-transform:none!important}
  `;document.head.appendChild(style);
  const observer=new MutationObserver(()=>humanizeBlind());observer.observe(document.body,{subtree:true,childList:true});humanizeBlind();
  document.addEventListener('click',e=>{const btn=e.target.closest('.v8Topic');if(!btn)return;setTimeout(()=>buildPreview(btn),0);});
}
install();
