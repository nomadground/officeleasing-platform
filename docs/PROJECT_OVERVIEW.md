# OFFICE LEASING Project Overview

## 1. 프로젝트 개요

`officeleasing.co.kr`은 서울 주요 업무권역의 사무실 임대 정보를 체계적으로 제공하고, 사용자가 실제 임대 의사결정을 빠르게 내릴 수 있도록 돕는 WordPress 기반 상업용 부동산 플랫폼이다.

운영 주체는 강남 오피스 임대 전문 중개법인인 힌트부동산중개법인이며, 단순 매물 나열 사이트가 아니라 다음을 결합한 전문 플랫폼을 목표로 한다.

- 건물 중심의 임대 매물 탐색
- 권역·지역·면적·임대조건 기반 검색
- 빌딩 상세정보와 현재 공실 통합 제공
- 사무실 임대 실무형 Insight 콘텐츠
- 고객 제안용 Leasing Flyer
- SEO, AIO, GEO에 최적화된 구조화된 정보 제공
- 상담 및 중개 전환

## 2. 개발 목적

### 사용자 관점

- 복잡한 사무실 임대 정보를 쉽고 빠르게 이해하게 한다.
- 건물과 현재 임대 가능한 공간을 한 화면에서 비교하게 한다.
- 지역, 면적, 가격, 주차, 접근성 등 실제 의사결정 기준을 제공한다.
- 관련 Insight, 지역 매물, Building 상세, Contact로 자연스럽게 이동하게 한다.

### 운영 관점

- 수천 건까지 확장 가능한 Building/Listing 데이터 구조를 구축한다.
- 직원이 매물과 콘텐츠를 반복적으로 등록·수정하기 쉽게 한다.
- 동일 정보를 홈페이지, 지역 목록, Building 상세, Leasing Flyer에서 재사용한다.
- 데이터 계산, URL, 검색, 스키마, 캐시, 보안을 코드로 일관되게 관리한다.
- 향후 대화형 AI 매물 검색과 고객 제안 자동화에 연결 가능한 기반을 만든다.

### 검색 및 브랜드 관점

- 서울 오피스 임대 전문 브랜드로서 검색 신뢰도와 전문성을 축적한다.
- 지역·건물·실무 콘텐츠를 연결해 Topic Cluster를 형성한다.
- SEO뿐 아니라 AI 검색과 생성형 검색에서 이해 가능한 명확한 구조와 요약 정보를 제공한다.
- 숨겨진 키워드가 아니라 실제 사용자에게 유용한 가시적 콘텐츠와 구조화 데이터로 신뢰를 높인다.

## 3. 서비스 범위

### 주요 업무권역

- GBD: 역삼, 논현, 삼성, 대치, 신사, 청담 등
- YBD: 여의도
- CBD: 종로, 중구
- ETC: 서초, 교대, 잠실, 성수, 용산 등

### 주요 콘텐츠 단위

- Building: 외부에 공개되는 핵심 콘텐츠 단위
- Listing: Building에 연결되는 내부 공실·임대조건 데이터
- Insight: 사무실 임대 체크리스트, 가이드, 시장·입지·규모·업종 콘텐츠
- Contact: 조건 상담과 중개 전환
- Leasing Flyer: 고객에게 전달하는 비교·제안용 독립 플러그인

## 4. 핵심 데이터 원칙

### Building 중심 공개 구조

사용자와 검색엔진에는 Building 상세 페이지를 대표 URL로 제공한다.

- Building 상세에서 건물 정보와 활성 Listing을 통합한다.
- Listing 단독 상세는 원칙적으로 공개 탐색 대상이 아니다.
- Listing URL 접근 시 부모 Building으로 리디렉션한다.
- 하나의 Building에 여러 Listing을 연결할 수 있다.

### URL 원칙

목표 공개 URL 예시:

```text
/강남사무실임대/삼성동/파르나스타워/
```

URL은 권역·지역·건물 관계를 반영하고, WordPress API와 프로젝트 URL Helper를 통해 생성한다. 공개 URL을 템플릿에 직접 하드코딩하지 않는다.

## 5. 주요 기능

### 5.1 매물 및 빌딩 관리

- Building CPT
- Listing CPT
- 권역 및 지역 Taxonomy
- Building-Listing 관계
- 활성 Listing 집계
- 보증금, 임대료, 관리비, 면적, NOC 등 계산
- 대표 이미지, 갤러리, 지도, 주차, 접근성, 건물 사양 관리
- 저장 시 계산값·권역·URL 관련 데이터 동기화

### 5.2 For Lease 탐색

- GBD, YBD, CBD, ETC 권역별 목록
- 주요 동별 지역 목록
- Building Card
- 지역·면적·임대조건 필터
- 페이지네이션
- 지도 기반 탐색 확장
- 필터·페이지네이션 URL의 canonical/noindex 정책

### 5.3 Building 상세

- 대표 이미지와 지도
- 핵심 건물 정보
- 내부 갤러리
- 현재 임대 가능한 공간
- 임대조건과 계산값
- 관련 지역·권역 매물 이동
- 관련 Insight와 Contact CTA
- OfficeBuilding, RealEstateListing, FAQPage, BreadcrumbList 등 구조화 데이터

### 5.4 홈페이지

홈페이지 V1의 기본 방향:

- Header
- Hero
- OFFICE LEASING의 차별점
- GBD/CBD/YBD/ETC 권역별 Building 섹션
- 데스크톱 4개, 모바일 2개 기준의 Building 슬라이더
- Insight 진입
- Contact CTA
- Footer

홈페이지는 단순 홍보 페이지가 아니라 지역·Building·Insight로 연결되는 탐색 허브 역할을 한다.

### 5.5 Insight 콘텐츠 시스템

- 기존 체크리스트와 가이드의 중복·충돌 분석
- 사무실 찾기, 임대조건 이해, 계약 전 확인, 입주와 운영, 계약 종료 등 Topic Cluster
- 쉽고 간결한 실무형 콘텐츠
- 관련 콘텐츠 2~4개 연결
- 지역 매물, 조건별 목록, Building, Contact 연결
- Article, FAQ, Breadcrumb 스키마
- 기존 hintoffice.com 콘텐츠와 검색 의도 분리 또는 이전 전략 관리

세부 기획은 다음 문서를 따른다.

```text
docs/content/INSIGHT_CONTENT_SYSTEM_V1.md
```

### 5.6 Leasing Flyer

- 고객 전달용 임대매물 리스트 생성
- 매물 조건 스냅샷 저장
- 공유 가능한 공개 URL
- 데스크톱·모바일·인쇄 레이아웃
- 매물 비교와 NOC 차트
- 지도 및 핵심 조건 표시
- officeleasing-core와 독립적으로 동작 가능한 구조 유지
- 일반 개발 중 ZIP을 반복 생성하지 않고, 명시적 배포·릴리스 시에만 패키징

### 5.7 SEO / AIO / GEO

- 명확한 제목·본문·요약 구조
- 지역·Building·Insight 간 내부링크
- Breadcrumb
- canonical 및 noindex 제어
- 중복 메타·중복 Schema 방지
- ItemList, OfficeBuilding, RealEstateListing, Article, FAQPage 등 적절한 Schema
- AI가 이해하기 쉬운 요약과 핵심 정보
- 실제 사용자에게 보이는 콘텐츠만 사용
- 숨겨진 SEO 텍스트, 가짜 데이터, 임시 링크 금지

### 5.8 향후 확장

- 대화형 AI 매물 검색
- 사용자가 말한 조건을 WordPress DB에서 구조화 검색
- Leasing Flyer 자동 리스트업
- 관리자용 매물·콘텐츠 등록 UI
- 반복 업무 자동화
- 고객 제안 및 상담 흐름 고도화

## 6. 기술 아키텍처

### WordPress 구성

- GeneratePress
- GeneratePress Child Theme
- GenerateBlocks
- ACF Free
- Rank Math Pro
- WP Rocket
- Smush Pro
- custom `officeleasing-core`
- HINT Leasing Flyer

### 책임 분리

#### officeleasing-core

- CPT 및 Taxonomy
- ACF 연동
- 계산
- 저장 훅
- Query Helper
- URL 및 Rewrite
- 캐시
- 보안
- 데이터 동기화
- Schema 데이터 준비

#### GeneratePress Child Theme

- 템플릿
- Template Part
- CSS
- JavaScript
- 접근성
- 반응형 UI
- 화면 렌더링

#### Leasing Flyer

- 고객 제안용 독립 기능
- 자체 데이터 스냅샷, 공개 화면, 인쇄 UI
- OfficeLeasing 데이터와 연결 가능하되 독립 식별 가능한 모듈 유지

## 7. 비기능 요구사항

우선순위:

1. 속도와 안정성
2. 단순하고 명확한 UI
3. SEO/AIO/GEO
4. 등록과 운영 편의성
5. 장기 확장성

필수 원칙:

- ACF 또는 Core 일부가 없어도 공개 페이지가 치명적 오류를 내지 않게 방어한다.
- 무제한 Query, N+1 Query, 중복 계산을 피한다.
- nonce, capability, whitelist 등 WordPress 보안 원칙을 적용한다.
- 모바일 가독성과 접근성을 기본으로 한다.
- 실제 변경 범위만 확인하고 작은 단위로 테스트·커밋·push한다.
- 일반 개발에서 전체 저장소 재탐색이나 ZIP 재생성을 반복하지 않는다.

## 8. 개발 단계

### 기반 정리

- 기존 NMD 저장소에서 OfficeLeasing 및 Leasing Flyer 브랜치 복사
- NMD 데이터는 원본 저장소에 그대로 유지
- 세 브랜치 비교 후 깨끗한 기준선 수립

### 핵심 개발

- URL·보안·안정성
- For Lease 목록과 필터
- Building 상세
- 홈페이지
- Insight 콘텐츠 허브
- 관리자 입력 경험

### 확장 개발

- 지도 탐색
- AI 검색
- Flyer 자동 연계
- 고급 자동화

## 9. 에이전트 역할

세부 역할은 `docs/AGENT_ROLES.md`를 따른다.

- GPT: 기획 책임, 우선순위, 검색 의도, UX, 완료 기준
- Claude: 기획 검토 및 계획 총괄, 콘텐츠 분석·작성
- Claude Code: 주 코딩 및 단계별 구현
- Codex: 서브코딩, diff 검토, 테스트, 보안·성능 보완

## 10. 프로젝트 경계

이 저장소에는 OFFICE LEASING와 Leasing Flyer에 직접 필요한 코드와 문서만 포함한다.

포함하지 않는 범위:

- NMD Blueprint
- NMD Business Plan
- NMD Nest
- NMD Residence
- NMD Company OS
- unrelated prototypes
- credentials, API keys, `.env`, `wp-config.php`
- database dumps, logs, caches, generated ZIPs, local backups

NMD 원본 저장소와 데이터는 별도로 유지하며, OFFICE LEASING 작업 중 수정하거나 삭제하지 않는다.
