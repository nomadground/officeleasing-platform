# OFFICE LEASING Insight Content System V1

## 1. 목적

기존에 `hintoffice.com`에 업로드된 사무실 임대 체크리스트와 가이드 콘텐츠를 기반으로, `officeleasing.co.kr` 런칭에 맞춘 독립적인 정보성 콘텐츠 시스템을 설계한다.

단순 복제나 문장 축약이 아니라 다음을 목표로 한다.

- SEO 강화
- AIO 및 GEO 대응
- 사용자가 빠르게 이해할 수 있는 쉬운 설명
- 체크리스트와 가이드 사이의 중복·충돌 제거
- 각 개념 간 연관성 검토
- 지역·매물·연계 콘텐츠·Contact로 이어지는 내부링크 활성화
- OFFICE LEASING만의 실무형 콘텐츠 체계 구축

## 2. 저장소 및 브랜치

- 저장소: `nomadground/officeleasing-platform`
- 기획 브랜치: `planning/insight-content-system-v1`
- 원고 작성 브랜치: `content/insight-v1`
- WordPress 구현 브랜치: `feature/insight-content-hub-v1`

이 문서는 기획 브랜치에서 관리한다. 기획 승인 전에는 대량 원고 작성이나 WordPress 구현을 시작하지 않는다.

## 3. 기존 콘텐츠 처리 원칙

기존 `hintoffice.com` 콘텐츠를 단순히 축약하여 다시 게시하지 않는다. 각 콘텐츠마다 다음 중 하나를 결정한다.

### A. OFFICE LEASING로 완전 이전

- `officeleasing.co.kr`을 대표 콘텐츠로 설정
- 기존 `hintoffice.com` 글은 301 리디렉션 또는 축약 안내 페이지로 전환
- 검색 신호를 신규 대표 URL에 집중

### B. 두 사이트의 검색 의도 분리

예시:

- `hintoffice.com`: 법률·계약 실무 중심의 상세 설명
- `officeleasing.co.kr`: 사무실을 찾는 임차인의 빠른 의사결정 가이드

두 콘텐츠는 대상 독자, 제목, 목차, 사례, CTA, 내부링크, 결론이 실질적으로 달라야 한다.

### C. OFFICE LEASING는 허브·요약형으로 운영

예시:

- `officeleasing.co.kr`: 사무실 계약 전 반드시 확인할 10가지
- `hintoffice.com`: 각 항목의 상세 법률·실무 해설

## 4. 1단계: 콘텐츠 인벤토리 및 구조 기획

먼저 기존 콘텐츠를 전수 조사하여 다음을 기록한다.

- 기존 제목
- 기존 URL
- 콘텐츠 유형
- 핵심 개념
- 검색 의도
- 주요 대상 독자
- 중복 콘텐츠
- 유사·충돌 개념
- 유지·통합·분리·폐기 여부
- OFFICE LEASING 신규 제목
- 신규 슬러그
- 우선순위
- 내부링크 후보
- 매물·지역·Contact 연결 가능성

권장 문서 구조:

```text
docs/content/
├─ INSIGHT_CONTENT_SYSTEM_V1.md
├─ CONTENT_INVENTORY.md
├─ CONTENT_TAXONOMY.md
├─ TOPIC_CLUSTER_MAP.md
├─ INTERNAL_LINKING_MAP.md
├─ CONTENT_MIGRATION_MATRIX.md
├─ SEO_AIO_GEO_GUIDELINES.md
└─ PUBLISHING_ROADMAP.md
```

## 5. 권장 Topic Cluster

### OFFICE INSIGHT

#### 사무실 찾기

- 입지
- 전용면적
- 직원 수 기준 면적
- 주차
- 접근성
- 현장답사
- 소유주 확인

#### 임대조건 이해

- 보증금
- 임대료
- 관리비
- NOC
- 전용률
- 렌트프리
- TI
- 임대료 인상

#### 계약 전 확인

- 건축물 용도
- 용도변경·표시변경
- 신탁부동산
- 전대차
- LOI
- 전세권
- 제소전화해조서
- 허위매물 확인

#### 입주와 운영

- 인테리어
- 시설비 승계
- 냉난방
- 운영시간
- 수리비 부담
- 관리규정

#### 계약 종료

- 중도퇴실
- 원상복구
- 묵시적 갱신
- 보증금 반환

## 6. 콘텐츠 제작 원칙

OFFICE LEASING 콘텐츠는 기존 블로그보다 쉽고 간결하게 작성하되 정보 밀도를 유지한다.

### 기본 구성

1. 사용자가 가장 먼저 알아야 할 결론
2. 핵심 개념의 쉬운 설명
3. 실제 계약·답사 시 확인할 사항
4. 자주 발생하는 오해 또는 실수
5. 관련 콘텐츠
6. 관련 지역·매물 목록
7. Contact CTA

### 작성 기준

- 한 문단은 모바일 기준 2~4줄
- 불필요한 법률·업계 전문용어는 풀어서 설명
- 정의만 나열하지 않고 의사결정에 필요한 이유를 설명
- 서로 다른 글에서 같은 개념을 다르게 정의하지 않음
- 숫자·계산식·계약 관행은 관련 글과 교차 검증
- 관련 없는 키워드 반복이나 숨겨진 SEO 문구 금지
- 사용자에게 실질적인 다음 행동을 제시

## 7. 메타데이터 규격

각 원고는 다음 메타데이터를 가진다.

```yaml
title:
slug:
content_type:
primary_keyword:
secondary_keywords:
search_intent:
target_reader:
summary:
related_region_pages:
related_listing_pages:
related_building_pages:
related_content:
contact_cta:
schema_type:
source_content:
migration_strategy:
migration_status:
review_status:
```

## 8. 내부링크 규칙

내부링크는 단순한 링크 수 증가가 아니라 사용자의 다음 행동을 연결하는 방식으로 설계한다.

### 상단

- 상위 허브 또는 시리즈 허브 1개

### 본문 중간

- 개념상 직접 연결되는 콘텐츠 2~4개
- 설명형 앵커 텍스트 사용
- `자세히 보기`와 같은 모호한 앵커 텍스트 최소화

### 하단

사용자의 다음 행동에 따라 구성한다.

- 관련 지역 매물 보기
- 조건별 매물 목록 보기
- 관련 Building 상세 보기
- 연계 Insight 보기
- 전문가에게 문의하기

### 연결 예시

#### NOC 콘텐츠

```text
NOC 개념
→ 관리비 가이드
→ 전용률 가이드
→ 지역별 매물 목록
→ Contact
```

#### 원상복구 콘텐츠

```text
원상복구
→ 계약 전 현장확인
→ 시설비 승계
→ 중도퇴실
→ Contact
```

## 9. WordPress 구현 범위

`feature/insight-content-hub-v1`에서 다음 기능을 구현한다.

- Insight 허브 템플릿
- 카테고리 또는 Topic Cluster 탐색 구조
- 관련 콘텐츠 자동·수동 연결
- 지역·매물 목록 CTA
- Building 상세 CTA
- Contact CTA
- Breadcrumb
- Article·FAQ·Breadcrumb 스키마
- 편집자가 내부링크를 관리할 수 있는 필드
- 중복 메타·중복 스키마 방지
- 모바일 가독성

## 10. 단계별 작업 순서

### Phase 1 — 기획

브랜치: `planning/insight-content-system-v1`

- 기존 콘텐츠 인벤토리 작성
- 중복·유사·충돌 개념 검토
- Topic Cluster 확정
- 콘텐츠별 이전 전략 확정
- 내부링크 지도 작성
- SEO·AIO·GEO 작성 기준 확정
- 우선순위 및 발행 로드맵 확정

### Phase 2 — 원고

브랜치: `content/insight-v1`

- 허브 원고 작성
- 우선순위 콘텐츠부터 개별 작성
- 메타데이터 입력
- 개념·내부링크 교차 검토
- 기존 hintoffice.com 콘텐츠와 검색 의도 중복 확인

### Phase 3 — 구현

브랜치: `feature/insight-content-hub-v1`

- CPT·Taxonomy 또는 기존 구조 확인
- 템플릿 구현
- 내부링크·CTA 구현
- 스키마 구현
- 모바일·접근성·성능 검증

### Phase 4 — 발행 검증

- URL·canonical·redirect 검토
- Search Intent 중복 검토
- 실제 내부링크 작동 확인
- Sitemap·Breadcrumb·Schema 확인
- 지역·매물·Contact 전환 경로 확인

## 11. AI 에이전트 역할

- GPT: 콘텐츠 전략, 구조, 검색 의도, 우선순위, 완료 기준
- Claude: 기획 누락·충돌·복잡도 및 전체 계획 검토
- Claude Code: 문서·WordPress·템플릿·필드·내부링크 기능 구현
- Codex: diff 검토, 구조·보안·성능·회귀 검증, 좁은 범위 패치

모든 에이전트는 `AGENTS.md`, `CLAUDE.md`, `docs/AGENT_ROLES.md`의 리소스 절약 원칙을 따른다.

## 12. 금지사항

- 기획 승인 전 대량 콘텐츠 작성
- 기존 콘텐츠를 거의 동일하게 복사
- 두 사이트에 동일 검색 의도의 콘텐츠를 무계획하게 중복 게시
- 모든 글에 같은 링크를 기계적으로 반복
- 존재하지 않는 매물·지역·URL을 임의 생성
- 일반 개발 중 매번 ZIP 생성
- 저장소 전체를 매 작업마다 재탐색
- 콘텐츠 기획과 WordPress 구조 변경을 한 작업에 혼합

## 13. 완료 기준

기획 단계는 다음이 준비되면 완료한다.

- 기존 콘텐츠 인벤토리
- 콘텐츠 이전 매트릭스
- Topic Cluster 지도
- 내부링크 지도
- SEO·AIO·GEO 작성 가이드
- 우선 발행 콘텐츠 목록
- hintoffice.com과 officeleasing.co.kr 역할 구분
- 구현 요구사항 및 완료 조건

각 단계 완료 시 관련 diff를 검토하고, 작은 단위로 커밋·push한 뒤 완료 범위와 남은 작업을 보고한다.
