<?php
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/threads.php';
$auth = threads_token_state();
?>
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>MANMO Revenue Engine</title>
<style>
body{font-family:system-ui,-apple-system,sans-serif;margin:0;background:#f6f7fb;color:#111;padding:24px}.wrap{max-width:1100px;margin:auto}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px}.card{background:#fff;border:1px solid #e8e8ef;border-radius:16px;padding:16px;margin-bottom:14px}.num{font-size:28px;font-weight:800}.row{display:grid;grid-template-columns:1fr 1fr;gap:14px}input,textarea,select,button{width:100%;box-sizing:border-box;padding:11px;margin-top:8px;border:1px solid #ddd;border-radius:10px}button{background:#111;color:#fff;font-weight:700;cursor:pointer}button:disabled{opacity:.35;cursor:not-allowed}.secondary{background:#fff;color:#111}.muted{color:#777}.ok{color:#0a7b3e}.warn{color:#a65b00}.badge{display:inline-block;padding:3px 8px;border-radius:999px;background:#f0f1f5;font-size:12px}pre{white-space:pre-wrap;background:#111;color:#eee;padding:12px;border-radius:10px;overflow:auto}.product{display:grid;grid-template-columns:84px 1fr;gap:12px;align-items:start}.thumb{width:84px;height:84px;object-fit:cover;border-radius:12px;background:#f0f1f5}.toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap}.toolbar input,.toolbar select{width:auto;min-width:120px;margin-top:0}.toolbar button{width:auto;margin-top:0;padding:10px 16px}.summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-top:12px}.summary>div{background:#f7f7fa;border-radius:12px;padding:12px}.summary b{display:block;font-size:20px;margin-top:4px}.researchBox{background:#f7f7fa;border-radius:12px;padding:12px;margin-top:10px}.researchBox ul{margin:8px 0;padding-left:20px}.postEditor{min-height:190px;background:#111;color:#eee;border-color:#2c2c2c;line-height:1.65;font:inherit;resize:vertical}.commentEditor{min-height:120px;background:#111;color:#eee;border-color:#2c2c2c;line-height:1.6;font:inherit;resize:vertical}.editActions{display:flex;gap:8px;flex-wrap:wrap}.editActions button{width:auto;padding:10px 16px}.saveBtn{background:#fff;color:#111}.videoBox{margin:14px 0;padding:14px;border:1px dashed #cfd3dd;border-radius:14px;background:#fafbfe}.videoBox input{background:#fff}.videoPreview{width:100%;max-height:520px;border-radius:12px;background:#000;margin-top:12px}.ready{color:#0a7b3e;font-weight:700}.required{color:#b42318;font-weight:700}.previewBox{display:none;margin-top:14px;padding:16px;border:1px solid #ddd;border-radius:14px;background:#fff}.previewBox.show{display:block}.previewText{white-space:pre-wrap;line-height:1.7;margin:12px 0;padding:14px;background:#f7f7fa;border-radius:12px}.previewComment{white-space:pre-wrap;line-height:1.6;margin-top:8px;padding:14px;background:#f7f7fa;border-radius:12px}@media(max-width:760px){body{padding:12px}.row{grid-template-columns:1fr}.product{grid-template-columns:64px 1fr}.thumb{width:64px;height:64px}.toolbar>*{width:100%!important}.editActions button{width:100%}}
</style>
</head>
<body><div class="wrap">
<h1>MANMO Revenue Engine</h1>
<p class="muted">Threads 바이럴 구조 × 실제 상품 후기 리서치 × 토스 쉐어링크</p>
<div class="card"><b>Threads:</b> <?php if($auth['access_token'] && $auth['user_id']): ?><span class="ok">연결됨 @<?=htmlspecialchars((string)$auth['username'])?></span> · <a href="/auth/threads/start.php">검색 권한 재연결</a><?php else: ?><span class="warn">연결 필요</span> · <a href="/auth/threads/start.php">Threads 연결하기</a><?php endif; ?></div>
<div id="stats" class="grid"></div>

<div class="card">
  <h3>Threads 바이럴 벤치마크 수집</h3>
  <p>제휴·특가·자취템·살림템 계열의 TOP 게시물을 찾고, <b>좋아요 10,000개 이상이 실제 확인된 글만</b> 벤치마크 DB에 저장합니다.</p>
  <p class="muted">문장을 복사하지 않고 첫 문장, 정보 공개 순서, 호기심 갭, 댓글 유도 구조만 글 생성에 사용합니다. 좋아요 수를 확인할 수 없는 글은 벤치마크로 채택하지 않습니다.</p>
  <div class="toolbar">
    <button onclick="collectBenchmarks()">1만+ 바이럴 글 수집</button>
    <a class="secondary" style="padding:10px 16px;border:1px solid #ddd;border-radius:10px;text-decoration:none;color:#111" href="/auth/threads/start.php">Threads 검색 권한 재연결</a>
  </div>
  <div id="benchmarkResult" class="muted" style="margin-top:10px"></div>
</div>

<div class="card">
  <h3>토스 상품 자동 가져오기</h3>
  <p class="muted">베스트·하루특가·카테고리 베스트에서 상품을 가져오고, 기존 상품은 재발급하지 않으며 신규 상품만 추적 가능한 shortUrl/originUrl을 발급해 저장합니다.</p>
  <div class="toolbar">
    <select id="tossSource" onchange="toggleCategory()"><option value="best-selling">전체 베스트</option><option value="today-deals">하루특가</option><option value="category">카테고리 베스트</option></select>
    <input id="tossCategory" placeholder="categoryId" style="display:none">
    <input id="tossSize" type="number" min="1" max="100" value="10">
    <button onclick="importToss()">상품 가져오기</button>
    <button id="tossNextBtn" class="secondary" onclick="importToss(true)" style="display:none">다음 페이지</button>
  </div>
  <div id="tossResult" class="muted" style="margin-top:10px"></div>
</div>

<div class="row">
  <div class="card">
    <h3>토스 실적 조회</h3>
    <div class="toolbar"><input id="perfFrom" type="date"><input id="perfTo" type="date"><button onclick="loadPerformance()">최근 실적 조회</button></div>
    <div id="perfSummary" class="summary"></div>
  </div>
  <div class="card">
    <h3>정산 실적 조회</h3>
    <div class="toolbar"><input id="settlementMonth" type="month"><button onclick="loadSettlement()">확정 수익 조회</button></div>
    <div id="settleSummary" class="summary"></div>
  </div>
</div>

<div class="row">
  <div class="card">
    <h3>상품 직접 추가</h3>
    <input id="name" placeholder="상품명">
    <input id="category" placeholder="카테고리 예: 다이어트 식품">
    <input id="price" type="number" placeholder="가격">
    <textarea id="description" placeholder="제품 특징"></textarea>
    <input id="url" placeholder="토스 쉐어링크">
    <button onclick="saveProduct()">상품 저장</button>
  </div>
  <div class="card"><h3>글 생성 원칙</h3><p>① 실제 상품 후기·재구매 이유 웹 리서치</p><p>② Threads 바이럴 구조와 패턴 A/B/C를 상품에 맞게 선택</p><p>③ 본문 수정 → 첫 댓글 수정 → 영상 업로드 → 미리보기 → 게시</p><p class="muted">허위 체험담·근거 없는 건강효과·본문 가격 남발은 금지합니다. 상품 링크와 광고 고지는 첫 댓글에 둡니다.</p></div>
</div>
<div class="card"><h3>상품</h3><div id="products"></div></div>
<div class="card"><h3>게시물</h3><p class="muted"><b>영상 1개 업로드가 필수입니다.</b> 본문과 첫 댓글을 직접 수정하고 저장한 뒤 영상까지 확인하고 게시하세요.</p><div id="posts"></div></div>
</div>
<script>
let adminKey=localStorage.getItem('manmo_admin_key')||'';
let tossCursor='';
if(!adminKey){const k=prompt('MANMO 관리자 키가 설정되어 있다면 입력하세요. 아직 설정 전이면 취소하세요.');if(k){adminKey=k;localStorage.setItem('manmo_admin_key',k)}}
async function api(url,opt={}){opt.headers={...(opt.headers||{}),'Content-Type':'application/json'};if(adminKey)opt.headers['X-MANMO-KEY']=adminKey;const r=await fetch(url,opt);const t=await r.text();try{return JSON.parse(t)}catch(e){return{error:'invalid_response',raw:t}}}
function money(v){return Number(v||0).toLocaleString()+'원'}
function esc(s){return String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]))}
function initDates(){const d=new Date(),to=d.toISOString().slice(0,10),from=new Date(d.getTime()-6*86400000).toISOString().slice(0,10);perfFrom.value=from;perfTo.value=to;settlementMonth.value=to.slice(0,7)}
function toggleCategory(){tossCategory.style.display=tossSource.value==='category'?'block':'none';tossSize.max=tossSource.value==='today-deals'?30:100;tossCursor='';tossNextBtn.style.display='none'}
async function refresh(){
  const s=await api('/api/status.php');
  const vals=[['상품',s.products||0],['게시물',s.posts||0],['1만+ 벤치마크',s.verified_benchmarks||0],['조회',s.views||0],['댓글',s.comments||0],['클릭',s.clicks||0],['주문',s.orders||0],['추적매출',money(s.revenue||0)],['예상수익',money(s.estimated_income||0)]];
  stats.innerHTML=vals.map(v=>`<div class="card"><div class="num">${v[1]}</div><small>${v[0]}</small></div>`).join('');
  if(!s.openai_ready) benchmarkResult.innerHTML='<span class="warn">OpenAI API 키가 서버에 아직 연결되지 않았습니다.</span>';
  const ps=await api('/api/products.php');
  products.innerHTML=(ps||[]).map(p=>`<div class="card product">${p.thumbnail_url?`<img class="thumb" src="${esc(p.thumbnail_url)}" alt="">`:'<div class="thumb"></div>'}<div><b>${esc(p.name)}</b> · ${esc(p.category||'-')} · ${Number(p.price||0).toLocaleString()}원 ${p.discount_rate?`<span class="badge">-${p.discount_rate}%</span>`:''}<br><span class="muted">${esc(p.description||'')}</span>${p.rank?`<br><span class="muted">랭킹 ${p.rank}위</span>`:''}<button onclick="draft(${p.id})">바이럴 리서치 + 훅 20개 + 글 생성</button></div></div>`).join('')||'상품 없음';
  const po=await api('/api/posts.php');
  posts.innerHTML=(po||[]).map(p=>{
    const positives=(p.research?.positive_opinions||[]).slice(0,4);
    const sources=(p.research?.sources||[]).slice(0,4);
    const research=(positives.length||sources.length)?`<div class="researchBox"><b>실제 상품 리서치</b>${positives.length?`<ul>${positives.map(x=>`<li>${esc(x)}</li>`).join('')}</ul>`:''}${sources.length?`<div class="muted">출처 ${sources.map(x=>esc(x.title||x.url||'')).join(' · ')}</div>`:''}<div class="muted">1만+ 벤치마크 ${Number(p.benchmark_count||0)}개 기반</div></div>`:'';
    if(p.status==='draft'){
      const hasVideo=!!p.video_url;
      const videoHtml=hasVideo?`<div class="ready">✓ 영상 업로드 완료 · ${esc(p.video_name||'video')}</div><video class="videoPreview" controls playsinline preload="metadata" src="${esc(p.video_url)}"></video>`:`<div class="required">영상 1개를 업로드해야 게시할 수 있습니다.</div>`;
      return `<div class="card"><span class="badge">${esc(p.hook_type||'hook')}</span> <b>${esc(p.hook||'')}</b><label class="muted">게시글 본문</label><textarea id="postBody${p.id}" class="postEditor">${esc(p.body||'')}</textarea><label class="muted">첫 댓글</label><textarea id="postComment${p.id}" class="commentEditor">${esc(p.first_comment||'')}</textarea><div class="videoBox"><b>게시 영상 <span class="required">필수</span></b><input id="videoFile${p.id}" type="file" accept="video/mp4,video/quicktime,video/x-m4v,.mp4,.mov,.m4v"><button class="secondary" onclick="uploadVideo(${p.id})">영상 업로드 / 교체</button><div id="videoStatus${p.id}" class="muted"></div>${videoHtml}</div>${research}<div class="muted">상태: draft</div><div class="editActions"><button class="saveBtn" onclick="savePost(${p.id})">수정 내용 저장</button><button class="secondary" onclick="togglePreview(${p.id})">미리보기</button><button ${hasVideo?'':'disabled'} onclick="publishPost(${p.id})">영상 + 본문 게시 + 첫 댓글</button></div><div id="preview${p.id}" class="previewBox"><b>Threads 게시 미리보기</b>${hasVideo?`<video class="videoPreview" controls playsinline preload="metadata" src="${esc(p.video_url)}"></video>`:''}<div id="previewBody${p.id}" class="previewText"></div><b>첫 댓글</b><div id="previewComment${p.id}" class="previewComment"></div></div></div>`;
    }
    return `<div class="card"><span class="badge">${esc(p.hook_type||'hook')}</span> <b>${esc(p.hook||'')}</b>${p.video_url?`<video class="videoPreview" controls playsinline preload="metadata" src="${esc(p.video_url)}"></video>`:''}<pre>${esc(p.body||'')}</pre><pre>${esc(p.first_comment||'')}</pre>${research}<div class="muted">상태: ${esc(p.status||'')}</div></div>`;
  }).join('')||'게시물 없음';
}
async function collectBenchmarks(){benchmarkResult.textContent='Threads TOP 글을 찾고 좋아요 1만+ 여부를 검증하는 중...';const r=await api('/api/benchmarks_collect.php',{method:'POST',body:JSON.stringify({limit_per_query:25})});if(r.error){benchmarkResult.innerHTML=`<span class="warn">실패: ${esc(r.message||r.error)}</span><br><span class="muted">검색 권한 오류라면 위의 Threads 검색 권한 재연결을 눌러 다시 승인하세요.</span>`;return}benchmarkResult.textContent=`완료 · ${r.searched||0}개 검색 / ${r.commerce_candidates||0}개 커머스 후보 / ${r.verified_10k_found||0}개 1만+ 확인 / ${r.saved||0}개 신규 저장 / 현재 검증 DB ${r.verified_total||0}개`;refresh()}
async function importToss(next=false){const btn=event.currentTarget;btn.disabled=true;tossResult.textContent='토스 API 조회 및 링크 발급 중...';const body={source:tossSource.value,size:+tossSize.value||10};if(tossSource.value==='category')body.category_id=tossCategory.value.trim();if(next&&tossCursor)body.cursor=tossCursor;const r=await api('/api/toss_import_v2.php',{method:'POST',body:JSON.stringify(body)});btn.disabled=false;if(r.error){tossResult.textContent='실패: '+(r.error_code||'')+' '+(r.message||r.error)+(r.version?` [${r.version}]`:'');return}tossCursor=r.next_cursor||'';tossNextBtn.style.display=r.has_next&&tossCursor?'inline-block':'none';tossResult.textContent=`완료 · ${r.received}개 조회 / ${r.imported}개 신규 저장 / ${r.duplicates||0}개 중복 / ${r.sold_out||0}개 품절 / ${r.invalid||0}개 ID없음 / ${r.expired||0}개 종료${r.version?` · ${r.version}`:''}`;refresh()}
async function loadPerformance(){perfSummary.innerHTML='조회 중...';const q=new URLSearchParams({fromDate:perfFrom.value,toDate:perfTo.value,size:'100'});const r=await api('/api/performance.php?'+q);if(r.error){perfSummary.innerHTML=`<span class="warn">${esc(r.message||r.error)}</span>`;return}const s=r.success?.summary||{};perfSummary.innerHTML=`<div>클릭<b>${Number(s.clickCount||0).toLocaleString()}</b></div><div>판매수량<b>${Number(s.soldQuantity||0).toLocaleString()}</b></div><div>매출<b>${money(s.salesAmount)}</b></div><div>실결제<b>${money(s.netPaymentAmount)}</b></div><div>예상수익<b>${money(s.expectedCommissionAmount)}</b></div><div>확정수익<b>${money(s.confirmedCommissionAmount)}</b></div>`}
async function loadSettlement(){settleSummary.innerHTML='조회 중...';const q=new URLSearchParams({settlementMonth:settlementMonth.value,size:'100'});const r=await api('/api/settlement.php?'+q);if(r.error){settleSummary.innerHTML=`<span class="warn">${esc(r.message||r.error)}</span>`;return}const s=r.success?.summary||{};settleSummary.innerHTML=`<div>구매확정<b>${Number(s.orderProductCount||0).toLocaleString()}</b></div><div>확정판매액<b>${money(s.productAmount)}</b></div><div>할인금액<b>${money(s.promotionCost)}</b></div><div>정산기준액<b>${money(s.settlementBase)}</b></div><div>확정수익<b>${money(s.commissionAmount)}</b></div>`}
async function saveProduct(){const b={name:name.value,category:category.value,price:+price.value||0,description:description.value,toss_share_url:url.value};const r=await api('/api/products.php',{method:'POST',body:JSON.stringify(b)});if(r.error)alert(r.error);else refresh()}
async function draft(id){const r=await api('/api/draft.php',{method:'POST',body:JSON.stringify({product_id:id})});if(r.error)return alert(r.message||r.error);alert(`리서치 초안 생성 완료 · 1만+ 벤치마크 ${r.benchmark_count||0}개 기반`);refresh()}
async function savePost(id,showAlert=true){const body=document.getElementById('postBody'+id)?.value||'';const first_comment=document.getElementById('postComment'+id)?.value||'';const r=await api('/api/posts.php',{method:'POST',body:JSON.stringify({id,body,first_comment})});if(r.error){alert(r.message||r.error);return false}if(showAlert)alert('수정 내용 저장 완료');return true}
async function uploadVideo(id){const input=document.getElementById('videoFile'+id);const status=document.getElementById('videoStatus'+id);if(!input?.files?.length)return alert('업로드할 영상을 선택하세요.');const file=input.files[0];if(file.size>100*1024*1024)return alert('영상은 100MB 이하로 업로드해 주세요.');status.textContent='영상 업로드 중...';const fd=new FormData();fd.append('post_id',String(id));fd.append('video',file);const headers={};if(adminKey)headers['X-MANMO-KEY']=adminKey;let r;try{const res=await fetch('/api/upload_video.php',{method:'POST',headers,body:fd});const text=await res.text();try{r=JSON.parse(text)}catch(e){r={error:'invalid_response',message:text||'업로드 응답 오류'}}}catch(e){r={error:'network_error',message:e.message}}if(r.error){status.innerHTML=`<span class="warn">${esc(r.message||r.error)}</span>`;return}status.innerHTML='<span class="ready">영상 업로드 완료</span>';refresh()}
function togglePreview(id){const box=document.getElementById('preview'+id);if(!box)return;const body=document.getElementById('postBody'+id)?.value||'';const comment=document.getElementById('postComment'+id)?.value||'';document.getElementById('previewBody'+id).textContent=body;document.getElementById('previewComment'+id).textContent=comment;box.classList.toggle('show')}
async function publishPost(id){const saved=await savePost(id,false);if(!saved)return;if(!confirm('영상 + 현재 본문을 Threads에 게시하고 첫 댓글까지 달까요?'))return;const r=await api('/api/publish.php',{method:'POST',body:JSON.stringify({post_id:id})});if(r.error)alert(r.message||r.error);else{alert(r.warning?'게시 완료 · '+r.warning:'게시 완료');refresh()}}
initDates();toggleCategory();refresh();
</script>
</body></html>