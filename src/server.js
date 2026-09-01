
import "dotenv/config";
import express from "express";
import fs from "fs";
import path from "path";
import cron from "node-cron";
import { fileURLToPath } from "url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const DB = path.join(__dirname, "../data/db.json");
const app = express();
app.use(express.json());
app.use(express.static(path.join(__dirname, "../public")));

const read=()=>JSON.parse(fs.readFileSync(DB,"utf-8"));
const write=(d)=>fs.writeFileSync(DB,JSON.stringify(d,null,2));
const add=(k,row)=>{const d=read();const id=(d[k].at(-1)?.id||0)+1;const x={id,...row};d[k].push(x);write(d);return x};

const types=["authority","loss","regret","surprise","price","fomo","empathy","warning","discovery","curiosity","comparison","target"];
const hooks={
 authority:["업계에서 일하는 지인이 이건 집에 두라고 하더라","이거 잘 아는 사람이 하나만 사라길래 봤는데 이유가 있었음"],
 loss:["이거 모르고 계속 돈 쓰고 있었던 게 제일 아까움","나는 이거 알기 전까지 매번 같은 데 돈 쓰고 있었음"],
 regret:["이걸 왜 이제 알았나 싶었음","진작 알았으면 괜히 고생 안 했을 텐데"],
 surprise:["솔직히 별 기대 안 했는데 생각보다 차이가 컸음","처음엔 굳이 필요하나 했는데 한번 쓰고 생각 바뀜"],
 price:["비싼 것보다 오히려 이쪽이 더 손이 자주 감","가격 보고 기대 안 했는데 의외로 제일 잘 쓰는 중"],
 fomo:["요즘 자꾸 보이길래 이유가 있나 싶어서 찾아봄","사람들이 계속 찾는 데는 이유가 있었음"],
 empathy:["이거 때문에 매번 짜증났던 사람은 바로 이해할 듯","집에서 이 불편 겪어본 사람은 무슨 말인지 알 거임"],
 warning:["이거 할 때 무조건 힘으로 해결하려고 하면 더 귀찮아짐","나도 계속 이렇게 했는데 알고 보니 방법이 따로 있었음"],
 discovery:["별생각 없이 찾다가 진짜 생활템 하나 발견함","이런 게 있는 줄 몰랐는데 한번 알고 나니 계속 보임"],
 curiosity:["이거 만든 사람 분명 생활하다가 한 번은 빡쳤을 것 같음","처음 봤을 땐 이게 왜 필요한지 전혀 몰랐음"],
 comparison:["예전에 쓰던 것보다 이게 훨씬 단순해서 결국 이것만 씀","기능 많은 것보다 딱 필요한 것만 있는 게 더 편했음"],
 target:["자취하는 사람은 이런 거 하나 있으면 진짜 편함","집안일 자주 하는 사람은 이 포인트에서 바로 체감할 듯"]
};

function makeHooks(){
  const out=[]; let i=0;
  while(out.length<20){
    const type=types[i%types.length], hook=hooks[type][Math.floor(i/types.length)%2];
    const stop=7+(i%3), curiosity=["curiosity","warning","surprise","authority"].includes(type)?9:7;
    const human=9, comment=["curiosity","discovery","warning"].includes(type)?9:7;
    const purchase=["empathy","target","comparison","price"].includes(type)?8:7;
    const total=stop*.30+curiosity*.25+human*.20+comment*.15+purchase*.10;
    out.push({hook,hook_type:type,stop,curiosity,human,comment,purchase,total:+total.toFixed(2)}); i++;
  }
  return out.sort((a,b)=>b.total-a.total);
}
function body(p,h){
  return `${h}

처음에는 그냥 흔한 제품인 줄 알았음.

근데 ${p.description||"생활에서 자주 생기는 불편을 줄여주는 제품"}이라는 포인트가 생각보다 실생활에서 체감이 컸음.

복잡한 기능보다 매번 귀찮았던 걸 하나 덜어주는 게 제일 좋더라.

${p.price?Number(p.price).toLocaleString()+"원대라":"가격 부담도 크지 않아서"} 한 번 확인해볼 만했음.

제품 좌표는 댓글에 남겨둘게 👇`;
}
function comment(p){
  return `내가 본 제품은 이거야 👇
${p.toss_share_url||"[토스 쉐어링크 입력 필요]"}

※ 이 링크를 통해 구매가 발생하면 수수료를 제공받을 수 있어.`;
}

app.get("/api/dashboard",(req,res)=>{
  const d=read(), perf=d.performance;
  const t=perf.reduce((a,p)=>({views:a.views+(+p.views||0),comments:a.comments+(+p.comments||0),clicks:a.clicks+(+p.link_clicks||0),orders:a.orders+(+p.orders||0),revenue:a.revenue+(+p.revenue||0)}),{views:0,comments:0,clicks:0,orders:0,revenue:0});
  res.json({products:d.products.length,posts:d.posts.length,benchmarks:d.benchmarks.length,winningHooks:d.winningHooks.slice(0,5),...t,estimatedIncome:Math.round(t.revenue*.1),goal:20000000});
});
app.get("/api/products",(req,res)=>res.json(read().products));
app.get("/api/posts",(req,res)=>res.json(read().posts));
app.post("/api/products",(req,res)=>res.json(add("products",{...req.body,created_at:new Date().toISOString()})));
app.post("/api/products/mock-import",(req,res)=>res.json(add("products",{name:"실리콘 물막이 정리템",category:"주방",price:8900,discount_rate:22,description:"싱크대 주변 물 고임을 줄이고 정리를 쉽게 하는 생활용품",toss_share_url:"https://example.com/mock-toss-share",created_at:new Date().toISOString()})));
app.post("/api/products/:id/draft",(req,res)=>{
  const d=read(), p=d.products.find(x=>x.id===+req.params.id); if(!p)return res.status(404).json({error:"not found"});
  const all=makeHooks(), top3=all.slice(0,3).map(x=>({...x,body:body(p,x.hook)})), w=top3[0];
  const post=add("posts",{product_id:p.id,hook:w.hook,hook_type:w.hook_type,body:w.body,first_comment:comment(p),top3,all_hooks:all,status:"draft",created_at:new Date().toISOString()});
  res.json(post);
});
app.post("/api/posts/:id/publish",(req,res)=>{
  const d=read(), p=d.posts.find(x=>x.id===+req.params.id); if(!p)return res.status(404).json({error:"not found"});
  p.status=process.env.MOCK_MODE==="false"?"ready_for_real_api":"published_mock";
  p.published_at=new Date().toISOString(); write(d); res.json(p);
});
app.post("/api/performance",(req,res)=>res.json(add("performance",{...req.body,measured_at:new Date().toISOString()})));
app.post("/api/benchmarks",(req,res)=>{
  const b=req.body,f=Math.max(1,+b.followers||1),likes=+b.likes||0,comments=+b.comments||0,reposts=+b.reposts||0,views=+b.views||0;
  res.json(add("benchmarks",{...b,engagement_rate:+(((likes+comments*2+reposts*3)/f)*100).toFixed(3),comment_rate:+((comments/f)*100).toFixed(3),virality_score:+(views*.1+likes+comments*3+reposts*5).toFixed(2),created_at:new Date().toISOString()}));
});
cron.schedule("*/30 * * * *",()=>{});
app.listen(process.env.PORT||3000,()=>console.log("MANMO http://localhost:"+(process.env.PORT||3000)));
