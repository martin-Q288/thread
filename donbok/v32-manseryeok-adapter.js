import * as Base from './vendor/manseryeok-2.0.0.bundle.js';
export * from './vendor/manseryeok-2.0.0.bundle.js';

export const HYEONWOLDANG_MANSE_STANDARD = Object.freeze({
  version: 'kasi-v1.2',
  officialCalendarSource: 'KASI 월력요항',
  yearBoundary: 'lichun',
  monthBoundary: 'jie',
  dayBoundary: 'midnight',
  trueSolarTime: false,
  unknownBirthTime: 'omit-hour-from-interpretation'
});

export function calculateFourPillars(birthInfo = {}) {
  const normalized = {...birthInfo, dayBoundary:'midnight'};
  delete normalized.trueSolarTime;
  return Base.calculateFourPillars(normalized);
}

export const __base = Base;
