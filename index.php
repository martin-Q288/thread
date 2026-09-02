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
body{font-family:system-ui,-apple-system,sans-serif;margin:0;background:#f6f7fb;color:#111;padding:24px}.wrap{max-width:1100px;margin:auto}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px}.card{background:#fff;border:1px solid #e8e8ef;border-radius:16px;padding:16px;margin-bottom:14px}.num{font-size:28px;font-weight:800}.row{display:grid;grid-template-columns:1fr 1fr;gap:14px}input,textarea,button{width:100%;box-sizing:border-box;padding:11px;margin-top:8px;border:1px solid #ddd;border-radius:10px}button{background:#111;color:#fff;font-weight:700;cursor:pointer}.secondary{background:#fff;color:#111}.muted{color:#777}.ok{color:#0a7b3e}.warn{color:#a65b00}.badge{display:inline-block;padding:3px 8px;border-radius:999px;background:#f0f1f5;font-size:12px}pre{white-space:pre-wrap;background:#111;color:#eee;padding:12px;border-radius:10px}@media(max-width:760px){.row{grid-template-columns:1fr}}
</style>
</head>
<body><div class="wrap">
<h1>MANMO Revenue Engine</h1>
<p class="muted">31살 자취 여성 페르소나 · 식비 절약 × 맛있는 다이어트 × 홈트</p>
<div class="card"><b>Threads:</b> <?php if($auth['access_token'] && $auth['user_id']): ?><span class="ok">연결됨 @<?=htmlspecialchars((string)$auth['username'])?></span><?php else: ?><span class="warn">연결 필요</span> · <a href="/auth/threads/start.php">Threads 연결하기</a><?php endif; ?></div>
<div id="stats" class="grid"></div>
<div class="row">
  <div class="card">
    <h3>상품 추가</h3>
    <input id="name" placeholder="상품명">
    <input id="category" placeholder="카테고리 예: 다이어트 식품">
    <input id="price" type="number" placeholder="가격">
    <textarea id="description" placeholder="제품 특징"></textarea>
    <input id="url" placeholder="토스 쉐어링크">
    <button onclick="saveProduct()">상품 저장</button>
  </div>
  <div class="card"><h3>운영 원칙</h3><p>첫 줄 후킹 → 생활 고민 → 실용 기준 → 제품은 댓글.</p><p>식비 절약 45% / 맛있는 다이어트 40% / 홈트·생활관리 15%.</p><p class="muted">자동발행 기본값: 08:00~23:00 매시간 1개, 최대 17개/일.</p></div>
</div>
<div class="card"><h3>상품</h3><div id="products"></div></div>
<div class="card"><h3>게시물</h3><div id="posts"></div></div>
</div>
<script>
let adminKey=localStorage.getItem('manmo_admin_key')||'';
if(!adminKey){const k=prompt('MANMO 관리자 키가 설정되어 있다면 입력하세요. 아직 설정 전이면 취소하세요.');if(k){adminKey=k;localStorage.setItem('manmo_admin_key',k)}}
async function api(url,opt={}){opt.headers={...(opt.headers||{}),'Content-Type':'application/json'};if(adminKey)opt.headers['X-MANMO-KEY']=adminKey;const r=await fetch(url,opt);const t=await r.text();try{return JSON.parse(t)}catch(e){return{error:'invalid_response',raw:t}}}
async function refresh(){
  const s=await api('/api/status.php');
  const vals=[['상품',s.products||0],['게시물',s.posts||0],['조회',s.views||0],['댓글',s.comments||0],['클릭',s.clicks||0],['주문',s.orders||0],['추적매출',Number(s.revenue||0).toLocaleString()+'원'],['예상수익',Number(s.estimated_income||0).toLocaleString()+'원']];
  stats.innerHTML=vals.map(v=>`<div class="card"><div class="num">${v[1]}</div><small>${v[0]}</small></div>`).join('');
  const ps=await api('/api/products.php');
  products.innerHTML=(ps||[]).map(p=>`<div class="card"><b>${p.name}</b> · ${p.category||'-'} · ${Number(p.price||0).toLocaleString()}원<br><span class="muted">${p.description||''}</span><button onclick="draft(${p.id})">훅 20개 + 글 생성</button></div>`).join('')||'상품 없음';
  const po=await api('/api/posts.php');
  posts.innerHTML=(po||[]).map(p=>`<div class="card"><span class="badge">${p.hook_type||'hook'}</span> <b>${p.hook||''}</b><pre>${p.body||''}</pre><pre>${p.first_comment||''}</pre><div class="muted">상태: ${p.status||'draft'}</div>${p.status==='draft'?`<button onclick="publishPost(${p.id})">Threads에 게시 + 첫 댓글</button>`:''}</div>`).join('')||'게시물 없음';
}
async function saveProduct(){const b={name:name.value,category:category.value,price:+price.value||0,description:description.value,toss_share_url:url.value};const r=await api('/api/products.php',{method:'POST',body:JSON.stringify(b)});if(r.error)alert(r.error);else refresh()}
async function draft(id){const r=await api('/api/draft.php',{method:'POST',body:JSON.stringify({product_id:id})});if(r.error)return alert(r.error);alert('초안 생성 완료');refresh()}
async function publishPost(id){if(!confirm('이 글을 Threads에 실제 게시하고 15초 뒤 첫 댓글까지 달까요?'))return;const r=await api('/api/publish.php',{method:'POST',body:JSON.stringify({post_id:id})});if(r.error)alert(r.message||r.error);else{alert('게시 완료');refresh()}}
refresh();
</script>
</body></html>
