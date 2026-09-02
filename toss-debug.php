<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>MANMO Toss 진단</title>
<style>
body{font-family:system-ui,-apple-system,sans-serif;background:#f6f7fb;margin:0;padding:18px;color:#111}.wrap{max-width:720px;margin:auto}.card{background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:18px;margin-bottom:14px}input,button{width:100%;box-sizing:border-box;padding:14px;border-radius:12px;border:1px solid #ddd;font-size:16px;margin-top:10px}button{background:#111;color:#fff;font-weight:700}pre{white-space:pre-wrap;word-break:break-all;background:#111;color:#eee;padding:14px;border-radius:12px;overflow:auto}.muted{color:#777}
</style>
</head>
<body><div class="wrap">
<div class="card"><h2>MANMO Toss ID 진단</h2><p class="muted">상품 목록의 ID 누락 원인을 서버에서 직접 확인합니다. Secret/Access Key는 표시하지 않습니다.</p><input id="key" type="password" placeholder="MANMO_ADMIN_KEY"><button onclick="runDebug()">진단 실행</button></div>
<div class="card"><div id="summary">아직 실행하지 않음</div><pre id="raw" style="display:none"></pre></div>
</div>
<script>
const saved=localStorage.getItem('manmo_admin_key')||''; if(saved) key.value=saved;
async function runDebug(){const k=key.value.trim();if(!k)return alert('관리자 키를 입력하세요');localStorage.setItem('manmo_admin_key',k);summary.textContent='진단 중...';raw.style.display='none';const r=await fetch('/api/toss_debug.php',{method:'POST',headers:{'Content-Type':'application/json','X-MANMO-KEY':k},body:'{}'});const t=await r.text();let j;try{j=JSON.parse(t)}catch(e){j={error:'invalid_response',raw:t}};if(j.error){summary.textContent='실패: '+(j.message||j.error);raw.textContent=JSON.stringify(j,null,2);raw.style.display='block';return}summary.innerHTML=`목록 ID: <b>${j.list_tacalt_item_id??'없음'}</b><br>상품 URL ID: <b>${j.extracted_path_id||'추출 실패'}</b><br>상세조회 상품 수: <b>${j.detail_items_count}</b><br>상세 tacaltItemId: <b>${j.detail_tacalt_item_id??'없음'}</b><br>상세 tacald: <b>${j.detail_tacald??'없음'}</b><br>notFoundIds: <b>${JSON.stringify(j.not_found_ids||[])}</b>`;raw.textContent=JSON.stringify(j,null,2);raw.style.display='block'}
</script></body></html>
