# MANMO Revenue Engine MVP

## 실행
```bash
cp .env.example .env
npm install
npm start
```
브라우저: http://localhost:3000

기본값은 MOCK_MODE=true 입니다.

## 포함
- 상품 입력
- 훅 20개 생성
- TOP3/최종안 구조
- Threads 본문
- 첫 댓글 토스 링크/제휴고지
- 벤치마크/성과 저장 구조
- 월 2천만원 대시보드
- 30분 주기 스케줄러 뼈대

## 실제 연동
`.env`에 Threads/Toss 키와 최신 공식 API 엔드포인트를 입력하고 MOCK_MODE=false로 변경하세요.
비밀키는 GitHub에 커밋하지 마세요.

주의: 현재 ZIP은 '실제 API 호출 전 단계의 안전한 MVP'입니다. Threads/Toss API 규격은 계정/버전에 따라 달라질 수 있어 엔드포인트를 하드코딩하지 않았습니다.
