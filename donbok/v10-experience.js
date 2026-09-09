const $=s=>document.querySelector(s),$$=s=>[...document.querySelectorAll(s)];
const track=(event,extra={})=>{window.dataLayer=window.dataLayer||[];window.dataLayer.push({event,...extra,hyunwoldang_version:'10.0.0'});};

const speech={
  work:{title:'일 얘기부터 할게요.',body:'요즘 그냥 바쁜 게 문제라기보다, “이걸 계속 이렇게 해야 하나”가 자꾸 걸리는 쪽이에요. 자리를 옮기는 문제일 수도 있고, 같은 일을 다른 방식으로 해보고 싶은 마음일 수도 있어요.'},
  money:{title:'돈 얘기가 먼저 걸려요.',body:'얼마를 버느냐 하나만 볼 때는 아니에요. 돈이 들어오는 길이 달라지거나, 한 번에 큰돈이 움직이는 일이 같이 생기기 쉬운 쪽입니다.'},
  relationship:{title:'사람 하나가 마음에 걸리는 때예요.',body:'새 사람을 만나는 문제보다 이미 가까운 사람과의 거리나 약속을 다시 정해야 하는 일이 먼저 보여요. 계속 가져갈 관계인지, 선을 다시 그을 관계인지가 더 중요해집니다.'},
  home:{title:'사는 곳 얘기부터 봐야겠어요.',body:'집값보다 생활 터전 자체가 먼저 걸려요. 이사·독립·합가처럼 실제 주소가 바뀌는 일일 수도 있고, 일 때문에 생활 반경이 달라지는 식일 수도 있어요.'},
  family:{title:'가족 쪽에서 내가 챙길 일이 보여요.',body:'내 일만 보고 가기보다 부모·형제·자녀처럼 가족 안에서 내 몫이 커질 수 있는 때예요. 누군가 대신 내가 결정해야 하는 일이 생기는 식으로도 나타납니다.'},
  study:{title:'뭔가 준비해서 넘겨야 하는 일이 보여요.',body:'시험이나 자격, 중요한 서류처럼 그냥 감으로 넘기기 어려운 일이 먼저 걸려요. 결과보다 준비를 제대로 해두는지가 더 중요한 때에 가깝습니다.'}
};
function domainFrom(text=''){
  if(/일 얘기|일 쪽|직장|역할|이직/.test(text))return'work';
  if(/돈 얘기|돈의 흐름|수입|지출/.test(text))return'money';
  if(/사람 하나|사람 관계|가까운 사람|연애|관계/.test(text))return'relationship';
  if(/사는 곳|집이나 생활|이사|생활환경|생활 터전/.test(text))return'home';
  if(/가족|부모|형제|자녀/.test(text))return'family';
  if(/준비해서|시험|자격|공부|서류/.test(text))return'study';
  return null;
}
function softenFirstReading(){
  const mount=$('#blindMount');if(!mount)return;
  const lead=mount.querySelector('.v8Lead');
  if(lead&&!lead.dataset.v10){
    const h=lead.querySelector('h2'),p=lead.querySelector('p'),d=domainFrom((h?.textContent||'')+' '+(p?.textContent||''));
    if(d){h.textContent=speech[d].title;p.textContent=speech[d].body;}
    lead.dataset.v10='1';
  }
  [...mount.querySelectorAll('.v8Paper')].forEach(card=>{
    const ey=card.querySelector('.v8Ey')?.textContent||'';
    if(!ey.includes('지나온 흐름')||card.dataset.v10)return;
    const h=card.querySelector('h2'),p=card.querySelector('p');
    const y=ey.match(/20\d{2}/)?.[0]||h?.textContent.match(/20\d{2}/)?.[0];
    if(y&&h)h.textContent=`${y}년, 여기는 한 번 짚고 갈게요.`;
    if(p){p.textContent=p.textContent.replace(/가능성을 먼저 봅니다\.?/g,'쪽이 먼저 보여요.').replace(/가능성이 있어요\.?/g,'수 있어요.').replace(/흐름입니다\.?/g,'때예요.').replace(/잡힙니다\.?/g,'보여요.');}
    card.dataset.v10='1';
  });
}

const extra={
 business:['지금 당장 올인하느냐보다 먼저 볼 게 있어요.','사업운이 움직여도 바로 회사를 그만두라는 뜻은 아니에요. 이 시기에는 “내가 잘할 수 있나”보다 실제로 돈을 내는 사람이 생기는지, 같은 방식으로 두세 번 다시 팔 수 있는지를 먼저 확인하는 쪽이 맞습니다.','유료에서는 시작하기 좋은 달, 돈을 먼저 쓰지 말아야 할 달, 본업을 줄여도 되는 시점을 따로 봅니다.'],
 sidejob:['부업은 ‘얼마 벌까’보다 ‘반복되나’를 먼저 봐요.','처음 한두 번 크게 버는 것보다 작은 금액이라도 다시 들어오는 구조가 생기는지가 중요해요. 본업과 부업이 서로 잡아먹는 시기인지, 같이 가져갈 수 있는 시기인지도 따로 봐야 합니다.','유료에서는 본업을 지키면서 키울 때와 수입 비중을 바꿔도 되는 때를 월 단위로 좁힙니다.'],
 jobchange:['퇴사 날짜보다 먼저 볼 건 ‘다음 자리’예요.','이직 흐름이 강해도 화난 날 사표부터 쓰는 식은 좋지 않아요. 다음 회사나 역할이 실제 조건으로 잡히는 때와, 그냥 답답해서 나가고 싶은 때를 구분해야 합니다.','유료에서는 지원·면접·퇴사 통보가 각각 유리한 시기와 피해야 할 시기를 따로 봅니다.'],
 promotion:['책임이 늘어나는 것과 승진은 같은 말이 아니에요.','일만 더 떠안고 보상은 그대로인 시기인지, 직책·평가·돈이 함께 움직이는 시기인지가 중요합니다. 그래서 “바빠진다”보다 무엇이 같이 따라오는지를 봐야 해요.','유료에서는 평가가 붙는 때, 협상하기 좋은 때, 책임만 커지기 쉬운 때를 나눠 봅니다.'],
 money:['돈이 움직인다고 전부 좋은 건 아니에요.','수입이 커지는 시기에도 같이 나가는 돈이 커질 수 있어요. 현월당에서는 “돈이 들어온다” 하나로 끝내지 않고, 벌어서 남는 돈인지 바로 다시 빠지는 돈인지 방향을 나눠서 봅니다.','유료에서는 돈이 들어오는 달, 큰 지출이 겹치는 달, 현금을 지키는 편이 나은 시기를 따로 풉니다.'],
 investment:['이 시기엔 수익률보다 계약 조건을 먼저 봐야 해요.','운이 움직인다는 이유로 투자 결정을 밀어붙이면 안 됩니다. 특히 대출, 중도해지, 공동명의, 동업처럼 나중에 빠져나오기 어려운 조건이 붙는지 먼저 확인해야 해요.','유료에서는 결정하기 좋은 때보다 “결정을 미루는 게 나은 때”를 더 구체적으로 공개합니다.'],
 newlove:['사람이 들어오는 때와 관계가 남는 때는 달라요.','만남 자체는 생겨도 오래 이어질 사람인지, 잠깐 강하게 끌리고 끝나는 사람인지는 다른 문제예요. 그래서 첫 만남보다 관계가 실제로 자리 잡는 시기를 더 중요하게 봅니다.','유료에서는 만남이 생기기 쉬운 때, 관계가 깊어지는 때, 빨리 결정하지 말아야 할 때를 나눠 봅니다.'],
 reunion:['연락이 오는 것만으로 재회라고 보진 않아요.','다시 연락할 가능성이 생기는 때와 다시 만나도 같은 문제가 반복되는 때는 구분해야 해요. “연락이 오느냐”보다 다시 잡을 관계인지가 더 중요합니다.','유료에서는 연락 가능성이 높은 시기와 실제 재결합에 유리한 시기를 따로 봅니다.'],
 marriage:['좋아하는 마음과 결혼할 시기는 별개예요.','결혼은 감정보다 집, 돈, 가족, 생활 방식이 같이 움직여야 현실이 됩니다. 그래서 관계가 좋은 때보다 실제 약속과 생활 변화가 붙는 시기를 더 중요하게 봐요.','유료에서는 상견례·동거·혼인 결정처럼 관계가 현실로 옮겨지기 좋은 시기와 부딪히기 쉬운 시기를 봅니다.'],
 children:['자녀 문제는 사주로 임신 여부를 단정하지 않아요.','대신 자녀 계획을 크게 생각하게 되는 때, 돌봄이나 가족 역할이 커지는 때처럼 생활의 변화를 봅니다. 건강이나 임신 가능성은 의료 정보로 확인해야 합니다.','유료에서는 가족 역할이 커지는 시기와 생활 변화가 겹치는 때를 더 자세히 봅니다.'],
 relations:['사람이 많아지는 것보다 정리가 먼저일 수 있어요.','누가 새로 들어오는지보다 누구와는 더 가까워지고, 누구와는 선을 그어야 하는지가 분명해지는 때가 있어요. 특히 일과 돈이 섞인 관계는 따로 봐야 합니다.','유료에서는 새 인연, 오래된 관계 정리, 돈이 섞인 관계를 각각 나눠 봅니다.'],
 exam:['운이 좋다는 말만으로 합격을 말하진 않아요.','시험은 준비량과 일정이 먼저예요. 다만 같은 공부를 해도 집중이 붙는 시기와 결과가 잘 드러나는 시기가 다를 수 있어서 그 차이를 봅니다.','유료에서는 준비가 붙는 달, 결과가 드러나는 달, 일정이 꼬이기 쉬운 달을 나눠 봅니다.'],
 realestate:['이사운과 집을 사기 좋은 운은 같은 게 아니에요.','사는 곳이 바뀌는 힘이 강해도 그게 매수 기회라는 뜻은 아닙니다. 직장 때문에 옮기는 건지, 독립·합가 때문인지, 돈과 계약이 같이 움직이는지를 따로 봐야 해요.','유료에서는 실제 이동에 좋은 때, 계약·잔금·대출을 더 조심할 때, 기다리는 편이 나은 시기를 나눠 봅니다.'],
 family:['가족 일은 “좋다·나쁘다”보다 역할이 어떻게 바뀌는지를 봐요.','내가 챙길 일이 늘어나는지, 반대로 거리를 두고 각자 책임을 나눠야 하는지에 따라 같은 가족운도 전혀 다르게 나타납니다.','유료에서는 누구 문제에 내가 끌려가기 쉬운지와 거리를 조절해야 할 시기를 더 좁혀 봅니다.'],
 annual:['올해 하나의 운으로 다 묶으면 오히려 틀려요.','일이 좋은 달과 돈을 지켜야 하는 달, 사람 관계를 조심할 달이 서로 다를 수 있어요. 그래서 올해 전체운은 큰 방향 하나보다 순서를 보는 게 더 중요합니다.','유료에서는 앞으로 12개월을 월별로 나눠 “움직일 때 / 지킬 때 / 정리할 때”를 구분합니다.']
};
function upgradePreview(){
  const box=$('#v8Answer');if(!box)return;
  const wrap=box.querySelector('.v9BlurWrap');if(!wrap||wrap.dataset.v10)return;
  const btn=$('.v8Topic.on');if(!btn)return;const topic=btn.dataset.topic,label=btn.textContent.trim();
  if(topic==='compatibility')return;
  const all=box.textContent;const year=all.match(/20\d{2}/)?.[0]||new Date().getFullYear();const months=(all.match(/\d+월/g)||[]).slice(0,2).join('·');
  const c=extra[topic]||['조금 더 볼게요.','큰 방향만 보고 바로 결제할 필요는 없어요. 이 주제가 실제 생활에서 어떻게 나타날 수 있는지 한 단계 더 보고 판단하세요.','유료에서는 시기를 더 좁혀서 풉니다.'];
  const more=document.createElement('section');more.className='v10MoreFree';
  more.innerHTML=`<div class="v10FreeTag">무료로 한 단계 더</div><h3>${c[0]}</h3><p>${c[1]}</p>${months?`<div class="v10Timing">현재 계산에서는 <b>${year}년 ${months} 전후</b>를 한 번 더 봐야 해요. 다만 이 시기가 곧바로 “좋은 때”라는 뜻은 아니고, 실제 선택의 방향은 아래 심층 풀이에서 갈립니다.</div>`:''}<p class="v10Next">${c[2]}</p>`;
  wrap.before(more);
  const blur=wrap.querySelector('.v9BlurContent');const first=blur?.querySelector('.v9LockedRow');
  if(first){const open=first.cloneNode(true);open.classList.add('v10OpenRow');const sm=open.querySelector('small');if(sm)sm.textContent='무료 공개';wrap.before(open);first.remove();}
  const note=box.querySelector('.v9OpenNote');if(note)note.textContent='큰 방향만 보고 결제하지 마세요. 아래 내용까지 읽어보고 더 보고 싶은지 판단하시면 됩니다.';
  const pay=wrap.querySelector('.v9Paywall');if(pay){
    const h=pay.querySelector('h3'),p=pay.querySelector('p');
    if(h)h.textContent=`${label}, 이제 시기를 더 좁혀봅니다.`;
    if(p)p.textContent='무료에서 방향과 대략적인 시기를 봤습니다. 심층 풀이에서는 좋은 때만 고르는 게 아니라, 움직여도 되는 때와 피해야 할 때를 월 단위로 나눠서 봅니다.';
  }
  wrap.dataset.v10='1';track('deeper_free_preview_view',{topic,label});
}
function install(){
  const s=document.createElement('style');s.textContent=`
  .v10MoreFree{margin:18px 0 4px;padding:18px 0;border-top:1px solid rgba(112,82,55,.22)}
  .v10FreeTag{font-size:10px;font-weight:900;letter-spacing:.08em;color:#812e27;margin-bottom:7px}
  .v10MoreFree h3{margin:0 0 9px!important;font-size:18px!important;line-height:1.55!important}
  .v10MoreFree p{font-size:13px!important;line-height:1.85!important;color:#51483f}
  .v10Timing{margin:13px 0;padding:12px 13px;border-left:3px solid #7d2b24;background:rgba(125,43,36,.055);font-size:12px;line-height:1.75;color:#554a40}
  .v10Next{margin-top:12px!important;font-weight:700;color:#392d25!important}
  .v10OpenRow{margin-top:5px;padding:15px 0;border-top:1px solid rgba(112,82,55,.18);border-bottom:1px solid rgba(112,82,55,.18)}
  .v10OpenRow small{display:block;color:#812e27;font-weight:900;font-size:10px;margin-bottom:5px}.v10OpenRow b{display:block;font-size:16px;margin-bottom:6px}.v10OpenRow p{font-size:12px!important;line-height:1.75!important}
  .v9BlurContent{filter:blur(4.5px)!important;opacity:.52!important;padding-bottom:168px!important}.v9Paywall{padding-top:72px!important}
  `;document.head.appendChild(s);
  const obs=new MutationObserver(()=>{softenFirstReading();upgradePreview();});obs.observe(document.body,{subtree:true,childList:true,characterData:true});
  document.addEventListener('click',e=>{if(e.target.closest('.v8Topic'))setTimeout(upgradePreview,40);});
  softenFirstReading();upgradePreview();
}
install();