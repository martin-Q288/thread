import * as Base from 'https://esm.sh/manseryeok@2.0.0?bundle';
export * from 'https://esm.sh/manseryeok@2.0.0?bundle';

export const HYEONWOLDANG_MANSE_STANDARD = Object.freeze({
  version: 'kasi-v1.0',
  officialCalendarSource: 'KASI 월력요항',
  yearBoundary: 'lichun',
  monthBoundary: 'jie',
  dayBoundary: 'midnight',
  trueSolarTime: false,
  unknownBirthTime: 'omit-hour-from-interpretation'
});

/**
 * 현월당 표준 만세력 어댑터
 *
 * KASI가 정하는 것은 음양력·절기·일진 같은 천문/달력 사실이고,
 * 입춘 연주 경계·절월·자시 관법 등은 명리 계산 규칙이다.
 *
 * 현월당 v1 표준에서는 사용자 기록 시각을 그대로 사용하며,
 * 진태양시/경도/균시차 보정을 기본값으로 강제하지 않는다.
 */
export function calculateFourPillars(birthInfo = {}) {
  const normalized = {
    ...birthInfo,
    dayBoundary: 'midnight'
  };
  delete normalized.trueSolarTime;
  return Base.calculateFourPillars(normalized);
}

export const __base = Base;
