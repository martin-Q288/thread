# MANMO Revenue Engine — Cloudways PHP

Cloudways Flexible의 Custom PHP App에서 구동하는 Threads × Toss Share 자동화 프로젝트입니다.

## 현재 구조

- `index.php` : MANMO 대시보드
- `auth/threads/start.php` : Threads OAuth 시작
- `auth/threads/callback.php` : authorization code → 장기 access token 교환
- `api/products.php` : 상품 저장/조회
- `api/draft.php` : 상품별 훅 20개 → 상위안 → Threads 글 생성
- `api/publish.php` : Threads 본문 게시 → 15초 후 첫 댓글 게시
- `api/performance.php` : 성과 데이터 저장
- `lib/threads.php` : Threads API 어댑터
- `lib/toss.php` : Toss Sharelink API 어댑터
- `lib/hooks.php` : 31살 자취 여성 페르소나 카피 엔진
- `storage/` : 토큰·DB 런타임 저장. GitHub에는 커밋하지 않음

## Cloudways 배포

Cloudways `Deployment via GIT` 설정:

```text
Repository: git@github.com:martin-Q288/thread.git
Branch: main
Deployment Path: public_html/
```

GitHub Deploy Key를 등록한 뒤 Cloudways에서 `Pull`을 실행합니다.

## 서버 비밀설정

저장소의 `.env.example`을 참고해 Cloudways 서버 `public_html/.env`를 직접 만드세요.
`.env`는 `.gitignore` 및 `.htaccess`로 보호하며 GitHub에는 절대 커밋하지 않습니다.

필수 값:

```text
APP_URL=https://manmo.neocarelab.co.kr
THREADS_APP_ID=
THREADS_APP_SECRET=
THREADS_REDIRECT_URI=https://manmo.neocarelab.co.kr/auth/threads/callback.php

TOSS_ACCESS_KEY=
TOSS_SECRET_KEY=
TOSS_MEMBER_ID=
```

Threads OAuth 연결이 완료되면 장기 토큰은 `storage/threads_auth.json`에 서버 내부 저장됩니다.

## Threads 연결

Meta Threads API 설정의 Redirect Callback URL에 아래를 등록합니다.

```text
https://manmo.neocarelab.co.kr/auth/threads/callback.php
```

그 후 브라우저에서 아래 주소를 엽니다.

```text
https://manmo.neocarelab.co.kr/auth/threads/start.php
```

Threads 권한 승인 → callback → 장기 access token 저장까지 자동으로 처리합니다.

## Toss Sharelink

Cloudways 서버 Public IP를 Toss 출발지 IP 화이트리스트에 `/32`로 등록합니다.

`lib/toss.php`는 인증 키를 환경변수로만 읽습니다. Toss 공식 Open API 가이드의 실제 Base URL / 상품조회 path / 쉐어링크 생성 path가 확인되면 `.env`의 아래 값만 채우면 됩니다.

```text
TOSS_API_BASE_URL=
TOSS_PRODUCTS_PATH=
TOSS_SHARELINK_PATH=
```

## 콘텐츠 페르소나

- 31살 한국 여성 직장인
- 자취 7년차
- 식비 절약
- 맛있는 다이어트
- 귀찮지 않은 홈트·생활관리
- 과장/천박한 말투 금지
- 사람에게 카톡하듯 자연스럽고 정돈된 말투
- 본문에서 문제/상황 먼저 제시
- 제품 링크는 첫 댓글
- 제휴 수익 고지 포함

## 보안

- `.env`, `storage/*.json`, runtime DB는 GitHub에서 제외
- API 비밀키는 공개 저장소에 저장하지 않음
- `storage/`, `data/`, `.env`는 웹 접근 차단
- 기존 Node MVP는 `pre-php-backup-20260902` branch에 백업됨
