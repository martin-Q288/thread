const $=s=>document.querySelector(s),$$=s=>[...document.querySelectorAll(s)];
window.dataLayer=window.dataLayer||[];
const track=(event,extra={})=>window.dataLayer.push({event,...extra,hyunwoldang_version:'7.0.0'});

let readingStarted=false, pastViewed=false, answerViewed=false;
const readConcern=()=>({concern:$('#concern')?.value||'',label:$('#concernLabel')?.value||'',custom:$('#customQuestion')?.value.trim()||''});

$('#f')?.addEventListener('submit',()=>{readingStarted=true;const q=readConcern();track('reading_start',{concern:q.concern,concern_label:q.label,has_custom_question:Boolean(q.custom)});});

const locked=$('.locked');
if(locked){
  const title=locked.querySelector('.lockBody h2');
  const desc=locked.querySelector('.lockBody p');
  const list=locked.querySelector('.lockedList');
  const buy=$('#buy');
  if(title) title.innerHTML='맞았다면,<br>앞으로의 흐름을 이어서 보세요.';
  if(desc) desc.textContent='같은 명식으로 앞으로 언제 움직이고, 언제 지켜야 하는지 구체적으로 이어서 봅니다.';
  if(list) list.innerHTML=`
    <div><b>직장에 더 있어야 할지, 나가야 할지</b><span>움직여도 되는 시기와 버텨야 하는 시기</span></div>
    <div><b>돈이 들어오는 해와 지켜야 하는 해</b><span>수입 확대·지출 확대·계약 변화가 강한 때</span></div>
    <div><b>사업을 키워도 되는 시기</b><span>시작·검증·확장 중 지금 어디에 있는지</span></div>
    <div><b>결혼을 결정하기 좋은 흐름</b><span>관계의 시작보다 ‘결정’이 강해지는 때</span></div>
    <div><b>계약·동업을 특히 조심할 시기</b><span>사람과 돈이 함께 얽히는 구간</span></div>
    <div><b>앞으로 5년 중 가장 크게 움직이는 해</b><span>연도별 흐름과 12개월 월운까지</span></div>`;
  if(buy) buy.innerHTML='앞으로의 흐름 이어서 보기 <span>→</span>';
}

const css=document.createElement('style');
css.textContent=`
.lockedList div{display:flex;flex-direction:column;gap:2px;text-align:left;padding:12px 0!important}.lockedList div b{font-size:13px;color:#efe3d5}.lockedList div span{font-size:10px;color:#9e9084}.proofStrip{margin:0;padding:18px 20px;background:#120c0a;color:#e9ddcf;border-top:1px solid #3a2720;border-bottom:1px solid #3a2720}.proofStrip strong{display:block;font-family:Georgia,serif;font-size:20px;line-height:1.35}.proofStrip p{margin:6px 0 0;color:#a99b8d;font-size:11px}.feedbackNudge{margin-top:12px;padding:10px 12px;border-left:3px solid #7f2d25;background:rgba(116,42,32,.07);font-size:11px;color:#675c51}
`;
document.head.appendChild(css);

const insertProofStrip=()=>{
  if($('#proofStrip')||!$('#pastYears')) return;
  const el=document.createElement('section');
  el.id='proofStrip';el.className='proofStrip';
  el.innerHTML='<strong>용하다고 말하지 않습니다.<br>먼저 맞는지 확인하세요.</strong><p>과거 흐름이 맞았다면 같은 명식으로 앞으로의 시기를 이어서 봅니다.</p>';
  $('#pastYears').before(el);
};
insertProofStrip();

const decorateFeedback=()=>{
  if(!$('.feedbackBox')||$('.feedbackNudge')) return;
  const n=document.createElement('div');n.className='feedbackNudge';
  n.textContent='세 해 모두 확인해보세요. 맞지 않는다면 결제할 이유가 없습니다.';
  $('.feedbackBox').appendChild(n);
};
decorateFeedback();

const onFeedback=e=>{
  const b=e.target.closest('.fb button'); if(!b) return;
  const card=b.closest('.yearCard');
  const v=b.dataset.v;
  track(v==='yes'?'feedback_yes':v==='maybe'?'feedback_maybe':'feedback_no',{year:card?.dataset.year||''});
  setTimeout(()=>{
    const cards=$$('.yearCard'); const vals=cards.map(c=>c.dataset.feedback).filter(Boolean);
    if(vals.length===cards.length&&cards.length){
      const yes=vals.filter(x=>x==='yes').length;
      const maybe=vals.filter(x=>x==='maybe').length;
      const box=$('#feedbackSummary');
      if(box){
        if(yes>=2) box.textContent=`3개 확인 · ${yes}개가 맞았습니다. 아래에서 지금 질문에 대한 풀이를 확인하세요.`;
        else if(yes+maybe>=2) box.textContent='과거 흐름이 일부 맞았습니다. 아래 답변까지 보고 판단하세요.';
        else box.textContent='과거 흐름이 잘 맞지 않았습니다. 이 경우 결제를 권하지 않습니다.';
      }
    }
  },0);
};
document.addEventListener('click',onFeedback);

$('#buy')?.addEventListener('click',()=>track('paid_cta_click',readConcern()));
$('#pay')?.addEventListener('click',()=>track('checkout_start',readConcern()));

const observer=new MutationObserver(()=>{
  if(!$('#result')?.classList.contains('hide')){
    if(!pastViewed && $$('.yearCard').length){pastViewed=true;track('past_result_view',{count:$$('.yearCard').length});}
    if(!answerViewed && $('#answerLead')?.textContent.trim()){answerViewed=true;track('concern_answer_view',readConcern());}
    const q=readConcern();
    if(q.custom && $('#questionTitle') && !$('#questionTitle').dataset.v7){$('#questionTitle').dataset.v7='1';$('#questionTitle').textContent=q.custom;}
  }
});
observer.observe(document.body,{subtree:true,attributes:true,childList:true,characterData:true});
