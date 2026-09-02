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
body{font-family:system-ui,-apple-system,sans-serif;margin:0;background:#f6f7fb;color:#111;padding:24px}.wrap{max-width:1100px;margin:auto}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px}.card{background:#fff;border:1px solid #e8e8ef;border-radius:16px;padding:16px;margin-bottom:14px}.num{font-size:28px;font-weight:800}.row{display:grid;grid-template-columns:1fr 1fr;gap:14px}input,textarea,button{width:100%;box-sizing:border-box;padding:11px;margin-top:8px;border:1px solid #ddd;border-radius:10px}button{background:#111;color:#fff;font-weight:700;cursor:pointer}.muted{color:#777}.ok{color:#0a7b3e}.warn{color:#a65b00}pre{white-space:pre-wrap;background:#111;color:#eee;padding:12px;border-radius:10px}@media(max-width:760px){.row{grid-template-columns:1fr}}
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
  <div class="card"><h3>운영 원칙</h3><p>첫 줄 후킹 → 생활 고민 → 실용 기준 → 제품은 댓글.</p><p>판매글만 반복하지 않고 식비 절약 45% / 맛있는 다이어트 40% / 홈트·생활관리 15% 비율을 유지합니다.</p></div>
</div>
<div class="card"><h3>상품</h3><div id="products"></div></div>
<div class="card"><h3>게시물</h3><div id="posts"></div></div>
</div>
<script>
const adminKey=localStorage.getItem('manmo_admin_key')||'';
async function api(url,opt={}){opt.headers={...(opt.headers||{}),'Content-Type':'application/json'};if(adminKey)opt.headers['X-MANMO-KEY']=adminKey;const r=await fetch(url,opt);return r.json()}
async function refresh(){const s=await api('/api/status.php');const vals=[['상품',s.products],['게시물',s.posts],['조회',s.views],['댓글',s.comments],['클릭',s.clicks],['주문',s.orders],['추적매출',Number(s.revenue||0).toLocaleString()+'원'],['예상수익',Number(s.estimated_income||0).toLocaleString()+'원']];stats.innerHTML=vals.map(v=>`<div class="card"><div class="num">${v[1]}</div><small>${v[0]}</small></div>`).join('');const ps=await api('/api/products.php');products.innerHTML=(ps||[]).map(p=>`<div class="card"><b>${p.name}</b> · ${p.category||'-'} · ${Number(p.price||0).toLocaleString()}원<br>${p.description||''}<button onclick="draft(${p.id})">훅 20개 + 글 생성</button></div>`).join('')||'상품 없음';try{const r=await fetch('/storage/db.json');}catch(e){}
}
async function saveProduct(){const b={name:name.value,category:category.value,price:+price.value||0,description:description.value,toss_share_url:url.value};const r=await api('/api/products.php',{method:'POST',body:JSON.stringify(b)});if(r.error)alert(r.error);else refresh()}
async function draft(id){const r=await api('/api/draft.php',{method:'POST',body:JSON.stringify({product_id:id})});if(r.error)return alert(r.error);alert('초안 생성 완료: '+r.hook+'\n\n'+r.body)}
refresh();
</script>
</body></html>
