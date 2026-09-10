const $20=s=>document.querySelector(s),$$20=s=>[...document.querySelectorAll(s)];

const SALES20={
 money:{promise:'돈이 들어오는 시기만이 아니라, 실제로 남는 돈과 큰돈을 움직일 때의 순서까지 봅니다.',want:['앞으로 12개월 중 돈을 밀어볼 구간과 한 번 더 확인할 구간','새 수입원·반복 수입·보상·정산이 붙는 흐름','대출·투자·계약·목돈이 겹칠 때 조심할 시기','향후 5년의 재물 흐름과 각 해의 역할','A/B/C 세 가지 돈 선택을 비교하는 기준','앞으로 30일 안에 먼저 정리할 돈 문제'],fit:['돈을 더 버는 것보다 실제로 남는 구조가 궁금한 분','부업·사업·연봉·투자 중 무엇을 먼저 움직일지 고민인 분','목돈을 써야 할지 지켜야 할지 판단 기준이 필요한 분'],preview:['결론부터','돈이 움직이는 가장 중요한 시기','12개월의 밀 때와 확인할 때','돈이 들어오는 통로와 빠지는 통로','5년 재물 흐름','A/B/C 선택 비교']},
 business:{promise:'사업을 시작해도 되는지가 아니라, 무엇부터 검증하고 언제 규모를 키울지까지 봅니다.',want:['고객·상품·채널 중 무엇을 먼저 검증할지','반복 매출이 붙기 쉬운 시기','고정비·인력·광고비를 늘릴 때의 주의 구간','향후 5년의 확장·검증·정리 순서','현직 유지·단계적 독립·즉시 전환 비교','앞으로 30일 안에 검증할 한 가지'],fit:['창업을 고민하지만 퇴사부터 해도 될지 불안한 분','매출은 생기는데 현금이 남지 않는 분','사업을 키울지 줄일지 시기와 순서가 필요한 분'],preview:['결론부터','반복 매출이 붙는 시기','비용을 늘려도 되는 구간','5년 사업 흐름','사람·계약의 영향','세 가지 사업 시나리오']},
 career:{promise:'퇴사할 수 있는 때보다, 옮긴 뒤 같은 문제를 반복하지 않을 조건과 시기를 봅니다.',want:['지금 버티는 것과 움직이는 것 중 무엇이 먼저인지','지원·면접·오퍼·협상을 밀어볼 구간','연봉·역할·상사·조직 중 핵심 문제','퇴사 전 확보해야 할 현실 조건','향후 5년의 직장·역할 변화','현직 유지·이직·퇴사 후 탐색 비교'],fit:['퇴사 충동이 감정인지 실제 변곡인지 알고 싶은 분','이직 제안이 와도 지금 움직여도 될지 고민인 분','연봉뿐 아니라 역할과 성장까지 함께 보고 싶은 분'],preview:['결론부터','움직임이 강한 시기','지원·면접을 밀어볼 구간','연봉·역할의 조건','5년 커리어 흐름','세 가지 이직 시나리오']},
 love:{promise:'연락이 오는 때보다, 관계가 실제로 이어질 조건과 시기를 더 깊게 봅니다.',want:['가까운 12개월의 관계 변화','새 인연·재회·관계 확정의 시기 차이','관계를 밀어볼 때와 거리를 둘 때','현실 조건과 감정이 충돌하는 구간','향후 5년의 관계 흐름','A/B/C 관계 선택을 비교하는 기준'],fit:['새 인연과 재회 사이에서 마음이 흔들리는 분','결혼·관계 확정의 현실 시기가 궁금한 분','연락 여부보다 오래 갈 조건을 보고 싶은 분'],preview:['결론부터','관계가 움직이는 시기','밀어볼 때와 거리를 둘 때','사람의 영향','5년 관계 흐름','선택 시나리오']},
 property:{promise:'이사하고 싶은 때와 실제 계약·매수를 움직일 때를 나눠서 봅니다.',want:['생활 이동과 자산 매수의 시기 차이','계약을 밀어볼 때와 조건을 더 볼 때','대출·보증금·목돈이 겹치는 구간','직장·가족 때문에 움직이는 흐름','향후 5년의 주거·자산 흐름','전세·매매·기다리기 비교'],fit:['이사와 매수를 같은 문제로 볼지 고민인 분','계약 시기와 목돈 부담을 같이 보고 싶은 분','실거주와 자산 선택을 구분해 보고 싶은 분'],preview:['결론부터','이동이 강한 시기','계약을 확인할 시기','목돈의 영향','5년 주거 흐름','세 가지 선택 비교']},
 study:{promise:'공부를 많이 하는 때가 아니라, 준비를 실제 평가와 결과로 연결할 시기를 봅니다.',want:['응시·제출·면접을 앞세울 구간','준비와 실전의 비중을 바꿀 때','문서·평가·자격 조건이 중요해지는 시기','집중이 흐트러질 때 보완할 방식','향후 5년의 평가 흐름','지금 도전·준비 연장·다른 경로 비교'],fit:['시험을 계속 준비할지 실제 응시할지 고민인 분','자격·면접·서류 일정의 우선순위가 필요한 분','준비 기간을 언제 끝낼지 보고 싶은 분'],preview:['결론부터','실전을 앞세울 시기','준비를 더 할 시기','평가·문서의 영향','5년 흐름','세 가지 선택 비교']},
 family:{promise:'막연한 가족운보다, 실제 시간·돈·책임이 움직이는 시기와 순서를 봅니다.',want:['가족 책임이 커지는 구간','내 몫과 다른 가족의 몫을 나눌 때','주거·돈·돌봄 조건이 겹치는 시기','가족계획에서 현실적으로 먼저 볼 조건','향후 5년의 가족 흐름','지금 결정·준비·기다리기 비교'],fit:['가족 문제를 혼자 떠안고 있는 분','주거·돈·돌봄이 한꺼번에 걸려 있는 분','가족계획의 현실 조건을 차분히 정리하고 싶은 분'],preview:['결론부터','책임이 움직이는 시기','돈·주거의 영향','사람과 역할','5년 가족 흐름','세 가지 선택 비교']},
 annual:{promise:'올해가 좋다·나쁘다가 아니라, 무엇을 먼저 움직이고 무엇을 뒤로 미룰지 봅니다.',want:['올해 가장 먼저 움직이는 영역','상반기와 하반기의 역할 차이','앞으로 12개월의 밀 때와 확인할 때','일·돈·관계가 충돌할 때 우선순위','향후 5년의 큰 흐름','앞으로 30일 안에 먼저 정리할 것'],fit:['올해 무엇부터 손대야 할지 모르겠는 분','일·돈·관계를 동시에 바꾸고 싶은 분','큰 흐름과 현실 행동을 함께 보고 싶은 분'],preview:['결론부터','가장 중요한 시기','12개월 흐름','선택의 우선순위','5년 큰 흐름','30일 행동']}
};

function topic20(){return ($20('.hwTopic.on')?.textContent||$20('#checkoutTitle')?.textContent?.replace(' 심층 풀이','')||'재물·돈 흐름').trim()}
function family20(t){if(/재물|돈|투자|계약/.test(t))return'money';if(/사업|창업|부업|투잡/.test(t))return'business';if(/이직|퇴사|직장|승진/.test(t))return'career';if(/연애|재회|결혼|궁합|인간관계/.test(t))return'love';if(/이사|부동산/.test(t))return'property';if(/시험|자격/.test(t))return'study';if(/가족|자녀/.test(t))return'family';return'annual'}
function esc20(s=''){return String(s).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]))}

function hideAll20(){['landing','loading','result','checkout','paid','sales'].forEach(id=>$20('#'+id)?.classList.add('hide'))}
function showSales20(t=topic20()){
 const sales=$20('#sales'); if(!sales)return; const d=SALES20[family20(t)]||SALES20.annual;
 hideAll20(); sales.classList.remove('hide');
 sales.innerHTML=`<div class="v20Hero"><div class="v20Ey">玄月堂 深層 풀이</div><h1>${esc20(t)}<br><em>결론과 시기까지</em> 더 깊게 봅니다.</h1><p>${esc20(d.promise)}</p></div>
 <section class="v20Paper v20Dark"><div class="v20Ey">이 풀이를 열면</div><h2>지금 가장 궁금한 답부터<br>먼저 확인합니다.</h2><div class="v20Preview">${d.preview.map((x,i)=>`<div><b>${String(i+1).padStart(2,'0')}</b><span>${esc20(x)}</span></div>`).join('')}</div></section>
 <section class="v20Paper"><div class="v20Ey">29,000원에 포함됩니다</div><h2>한두 문장이 아니라,<br>이 질문을 여러 방향에서 끝까지 봅니다.</h2><div class="v20Items">${d.want.map(x=>`<p><span>✓</span>${esc20(x)}</p>`).join('')}</div></section>
 <section class="v20Paper"><div class="v20Ey">무료 풀이와의 차이</div><h2>무료가 “무엇이 움직이는지”였다면,<br>심층은 “언제, 어떻게 움직일지”까지 갑니다.</h2><div class="v20Compare"><div><small>무료 첫 풀이</small><b>큰 흐름을 확인</b><p>타고난 결, 지나온 변곡, 지금의 중심과 앞으로 먼저 움직이는 방향을 봅니다.</p></div><div class="on"><small>심층 풀이</small><b>실제 선택에 필요한 순서까지</b><p>5년 흐름과 향후 12개월, 밀어볼 시기, 확인할 시기, 사람·돈·계약의 영향과 선택 시나리오를 이어서 봅니다.</p></div></div></section>
 <section class="v20Paper"><div class="v20Ey">이런 분에게 특히 맞습니다</div><h2>결정을 앞두고 있다면<br>더 구체적으로 볼 수 있습니다.</h2><div class="v20Fit">${d.fit.map(x=>`<p>✓ ${esc20(x)}</p>`).join('')}</div></section>
 <section class="v20Paper v20Offer"><div class="v20Ey">玄月堂 ${esc20(t)} 深層 풀이</div><h2>보고 나서도 “그래서 뭘 해야 하지?”가<br>남지 않도록 구성했습니다.</h2><div class="v20Stats"><div><b>32</b><span>심층 장면</span></div><div><b>5년</b><span>연도별 흐름</span></div><div><b>12개월</b><span>시기 분석</span></div><div><b>3안</b><span>선택 비교</span></div></div><div class="v20Price"><span>${esc20(t)} 심층 풀이</span><b>29,000원</b></div><button id="salesBuy20" class="cta ritual red">29,000원 · 심층 풀이 열기</button><button id="salesBack20" class="cta ghost">← 무료 풀이로 돌아가기</button><p class="v20Fine">현재 테스트 페이지에서는 실제 결제가 진행되지 않습니다.</p></section>`;
 $20('#salesBuy20').onclick=()=>showCheckout20(t);
 $20('#salesBack20').onclick=()=>{hideAll20();$20('#result')?.classList.remove('hide');scrollTo(0,Math.max(0,($20('#questionMount')?.offsetTop||0)-40))};
 scrollTo(0,0);
}
function showCheckout20(t){
 hideAll20(); const c=$20('#checkout');c?.classList.remove('hide');
 if($20('#checkoutTitle'))$20('#checkoutTitle').textContent=`${t} 심층 풀이`;
 if($20('#orderLabel'))$20('#orderLabel').textContent=`${t} · 전체 심층 풀이`;
 scrollTo(0,0);
}

function consumerizePaid20(){
 const mount=$20('#paidMount');if(!mount||mount.dataset.v20==='1'||!mount.children.length)return;
 const p20=$20('#v17p20'); if(p20){const h=p20.querySelector('h2');if(h)h.textContent='앞으로 12개월, 움직일 때와 지켜볼 때가 분명히 나뉩니다.';const b=p20.querySelector('.v17Body');if(b)b.innerHTML='<p>앞으로 12개월을 한 가지 속도로 밀기보다, 행동을 앞세울 구간과 돈·계약·사람의 조건을 먼저 확인할 구간을 나눠서 보세요. 각 시기 아래에는 왜 그런 흐름으로 읽히는지 함께 풀었습니다.</p>'}
 const p21=$20('#v17p21');if(p21){const h=p21.querySelector('h2');if(h)h.textContent='이 구간에서는 준비해 둔 행동을 앞에 두세요.'}
 const p22=$20('#v17p22');if(p22){const h=p22.querySelector('h2');if(h)h.textContent='이 구간에서는 속도보다 조건을 먼저 확인하세요.'}
 $$20('.v18Why summary').forEach(x=>x.textContent='이 시기를 이렇게 보는 이유');
 const order=[1,18,20,21,22,23,29,28,30,13,14,15,16,17,24,25,26,27,2,3,4,5,6,7,8,9,10,11,12,19,31,32];
 const pages=order.map(n=>$20('#v17p'+n)).filter(Boolean);pages.forEach(p=>mount.appendChild(p));
 pages.forEach((p,i)=>{const num=p.querySelector('.v17Num');if(num)num.textContent=`${String(i+1).padStart(2,'0')} / ${pages.length}`});
 const toc=$20('.v17TocList');if(toc)toc.innerHTML=pages.map((p,i)=>`<a href="#${p.id}">${String(i+1).padStart(2,'0')} ${esc20(p.querySelector('.v17Kicker')?.textContent||p.querySelector('h2')?.textContent||'')}</a>`).join('');
 const hero=$20('#paid .resultHero h1');if(hero)hero.textContent=`${topic20()} · 전체 심층 풀이`;
 const sub=$20('#paid .resultHero p');if(sub)sub.textContent='결론과 가장 중요한 시기부터 먼저 보고, 그 다음에 12개월·5년 흐름과 선택 기준을 이어갑니다.';
 mount.dataset.v20='1';scrollTo(0,0);
}

function styles20(){const s=document.createElement('style');s.textContent=`#sales{background:#080605}.v20Hero{background:#080605;color:#efe3d4;text-align:center;padding:72px 22px 62px;border-bottom:1px solid #51342a}.v20Ey{font-size:11px;font-weight:900;letter-spacing:.12em;color:#a43b31}.v20Hero h1,.v20Paper h2{font-family:Georgia,'Noto Serif KR',serif}.v20Hero h1{font-size:36px;line-height:1.45;margin:18px 0}.v20Hero h1 em{font-style:normal;color:#b34a3e}.v20Hero p{max-width:620px;margin:auto;color:#b9aa99;line-height:1.9}.v20Paper{max-width:820px;margin:auto;padding:44px 28px;background:linear-gradient(180deg,#f2eadb,#e9dfcc);color:#241a15;border-bottom:12px solid #d4c9b5;box-sizing:border-box}.v20Paper h2{font-size:28px;line-height:1.55;margin:14px 0 26px}.v20Dark{background:#17100d;color:#eee0d0}.v20Dark h2{color:#eee0d0}.v20Preview{display:grid;grid-template-columns:1fr 1fr;gap:10px}.v20Preview div{display:grid;grid-template-columns:38px 1fr;gap:10px;padding:16px;border:1px solid #4a3329}.v20Preview b{color:#c25749;font-family:Georgia,serif}.v20Preview span{font-size:13px;line-height:1.7;color:#d4c4b2}.v20Items p,.v20Fit p{font-size:14px;line-height:1.8;padding:12px 0;margin:0;border-bottom:1px solid rgba(100,75,50,.18)}.v20Items span{color:#8b3027;margin-right:10px}.v20Compare{display:grid;grid-template-columns:1fr 1fr;gap:12px}.v20Compare>div{padding:20px;border:1px solid #c8b797}.v20Compare .on{background:#21120e;color:#eee1d1;border-color:#6f3b31}.v20Compare small,.v20Compare b{display:block}.v20Compare small{color:#8c3b32;font-weight:900}.v20Compare .on small{color:#d26d5e}.v20Compare b{font-size:17px;margin:8px 0}.v20Compare p{font-size:13px;line-height:1.8;color:#6a5d51;margin:0}.v20Compare .on p{color:#c7b7a5}.v20Offer{text-align:center}.v20Stats{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin:25px 0}.v20Stats div{padding:16px 6px;border:1px solid #c3b18f}.v20Stats b,.v20Stats span{display:block}.v20Stats b{font-family:Georgia,serif;font-size:24px}.v20Stats span{font-size:10px;color:#746556;margin-top:5px}.v20Price{display:flex;justify-content:space-between;align-items:center;padding:18px 0;border-top:1px solid #b8a486;border-bottom:1px solid #b8a486;margin:22px 0;text-align:left}.v20Price b{font-size:24px}.v20Offer .cta{width:100%;margin-top:10px}.v20Fine{font-size:10px;color:#897b6c;margin-top:12px}@media(max-width:560px){.v20Hero{padding:56px 18px 46px}.v20Hero h1{font-size:30px}.v20Paper{padding:34px 20px}.v20Paper h2{font-size:24px}.v20Preview,.v20Compare{grid-template-columns:1fr}.v20Stats{grid-template-columns:1fr 1fr}}`;document.head.appendChild(s)}

function init20(){styles20();document.addEventListener('click',e=>{const b=e.target.closest('.v16Unlock,#unlock');if(!b)return;e.preventDefault();e.stopPropagation();e.stopImmediatePropagation();showSales20(topic20())},true);const obs=new MutationObserver(()=>{if(!$20('#paid')?.classList.contains('hide')){setTimeout(consumerizePaid20,250);setTimeout(consumerizePaid20,900)}});obs.observe(document.body,{subtree:true,childList:true,attributes:true,attributeFilter:['class']})}
init20();