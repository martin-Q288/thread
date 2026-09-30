// 현월당 V31: 브라우저별 CDN 실패를 견디는 만세력 로더
const SOURCES = [
  'https://esm.sh/manseryeok@2.0.0?bundle',
  'https://cdn.jsdelivr.net/npm/manseryeok@2.0.0/+esm',
  'https://cdn.skypack.dev/manseryeok@2.0.0'
];

function withTimeout(p, ms, label){
  return Promise.race([
    p,
    new Promise((_, reject)=>setTimeout(()=>reject(new Error(label+' timeout')), ms))
  ]);
}

async function loadBase(){
  const errors=[];
  for(const url of SOURCES){
    try{
      const mod=await withTimeout(import(url), 4500, url);
      if(typeof mod.calculateFourPillars==='function' &&
         typeof mod.getTenGod==='function' &&
         typeof mod.getBranchTenGod==='function'){
        return mod;
      }
      errors.push(url+': required exports missing');
    }catch(e){
      errors.push(url+': '+(e?.message||e));
    }
  }
  throw new Error('만세력 모듈 로딩 실패\n'+errors.join('\n'));
}

const Base = await loadBase();

export const HYEONWOLDANG_MANSE_STANDARD = Object.freeze({
  version:'kasi-v1.1',
  officialCalendarSource:'KASI 월력요항',
  yearBoundary:'lichun',
  monthBoundary:'jie',
  dayBoundary:'midnight',
  trueSolarTime:false,
  unknownBirthTime:'omit-hour-from-interpretation'
});

export function calculateFourPillars(birthInfo={}){
  const normalized={...birthInfo,dayBoundary:'midnight'};
  delete normalized.trueSolarTime;
  return Base.calculateFourPillars(normalized);
}
export function getTenGod(...args){ return Base.getTenGod(...args); }
export function getBranchTenGod(...args){ return Base.getBranchTenGod(...args); }
export const __base = Base;
