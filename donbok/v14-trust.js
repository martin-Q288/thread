const labelFor=n=>n>=88?'근거 겹침: 매우 강함':n>=78?'근거 겹침: 강함':'근거 겹침: 확인됨';
function clean(root=document){for(const el of root.querySelectorAll?.('.hwConf, small')||[]){const m=(el.textContent||'').match(/근거 일치도\s*(\d+)%/);if(m)el.textContent=labelFor(Number(m[1]));}}
const observer=new MutationObserver(ms=>{for(const m of ms){for(const n of m.addedNodes){if(n.nodeType===1)clean(n)}}clean(document)});
observer.observe(document.documentElement,{childList:true,subtree:true});clean(document);