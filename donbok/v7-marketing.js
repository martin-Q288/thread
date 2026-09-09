const $=s=>document.querySelector(s),$$=s=>[...document.querySelectorAll(s)];
window.dataLayer=window.dataLayer||[];
const track=(event,extra={})=>window.dataLayer.push({event,...extra,hyunwoldang_version:'7.2.0'});

let pastViewed=false,answerViewed=false;
const readConcern=()=>({concern:$('#concern')?.value||'',label:$('#concernLabel')?.value||'',custom:$('#customQuestion')?.value.trim()||''});

$('#f')?.addEventListener('submit',()=>{const q=readConcern();track('reading_start',{concern:q.concern,concern_label:q.label,has_custom_question:Boolean(q.custom)});});

const locked=$('.locked');
if(locked){
  const title=locked.querySelector('.lockBody h2');
  const desc=locked.querySelector('.lockBody p');
  const list=locked.querySelector('.lockedList');
  const buy=$('#buy');
  if(title) title.innerHTML='이제 앞으로의 흐름을<br>이어보겠습니다.';
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
.fb,.feedbackBox{display:none!important}
.lockedList div{display:flex;flex-direction:column;gap:2px;text-align:left;padding:12px 0!important}.lockedList div b{font-size:13px;color:#efe3d5}.lockedList div span{font-size:10px;color:#9e9084}.proofStrip{margin:0;padding:18px 20px;background:#120c0a;color:#e9ddcf;border-top:1px solid #3a2720;border-bottom:1px solid #3a2720}.proofStrip strong{display:block;font-family:Georgia,serif;font-size:20px;line-height:1.35}.proofStrip p{margin:6px 0 0;color:#a99b8d;font-size:11px}
`;
document.head.appendChild(css);

const insertProofStrip=()=>{
  if($('#proofStrip')||!$('#pastYears')) return;
  const el=document.createElement('section');
  el.id='proofStrip';el.className='proofStrip';
  el.innerHTML='<strong>지난 흐름부터 짚습니다.</strong><p>그 다음, 지금 묻고 있는 문제와 앞으로의 시기를 이어서 풉니다.</p>';
  $('#pastYears').before(el);
};
insertProofStrip();

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
