# Office Leasing GeneratePress Child (v0.2.0)

v3.6 목업을 **OfficeLeasing v1 Design System**으로 확정하고, HTML → WordPress 템플릿으로 변환한 버전입니다.
모든 값은 하드코딩이 아니라 `get_field()`(ACF) / WordPress 함수로 출력됩니다.

## 설치
1. 부모 테마 **GeneratePress** 설치
2. **officeleasing-core 플러그인 + ACF** 활성화 (데이터/필드/계산 담당)
3. 이 폴더를 zip으로 압축해 외모 → 테마 → 새 테마 추가 → 업로드 → 활성화
4. 빌딩 글 1개를 채워 넣고 `/?post_type=building&p=ID`로 렌더 확인

## 아키텍처 원칙
- **데이터/로직 = 플러그인(ol_)**, **표현/템플릿 = 테마(olt_)**. 함수 프리픽스를 분리해 `Cannot redeclare` 충돌을 원천 차단.
- 금액 포맷은 플러그인의 `ol_format_manwon()`(억 단위로 안 쪼개고 항상 "1,532만원" 콤마표기) 등을 테마 `olt_won()`이 래핑(플러그인 비활성 시 fallback).
- **디자인은 `assets/css/officeleasing.css` 하나로 전 템플릿이 공유.** 새 스타일을 만들지 않고 `.olx-*` 컴포넌트를 재사용.

## 파일 구조
```
officeleasing-generatepress-child/
├─ style.css                 ← 테마 헤더 + 소소한 호환 CSS (디자인 본체는 assets/css)
├─ functions.php             ← CSS/JS enqueue(조건부), nav 메뉴, 헬퍼 로드
├─ header.php                ← 로고 + GNB + 권역바 (get_header로 전역 재사용)
├─ footer.php                ← 푸터 + 모바일 sticky CTA
├─ front-page.php            ← ★ Home V1 (Hero → Why → 권역4 → Contact)
├─ single-building.php       ← ★ 매물 상세 메인 템플릿 (빌딩 URL, 매물 병합 렌더)
├─ single-listing.php        ← 개별 매물 URL → 연결 빌딩으로 301 (URL 정책)
├─ archive-building.php      ← 전체 빌딩 목록 (/사무실임대/)
├─ taxonomy-office_region.php ← 권역/동 허브 (레벨 조건분기 + empty state)
├─ inc/
│  ├─ theme-helpers.php      ← olt_* 표현 헬퍼 (금액/㎡/노선색/권역라벨/이미지수집/매물조회/입주일포맷/core활성확인)
│  └─ class-gnb-walker.php   ← GNB를 .olx-gnb 마크업(직계 <a>)으로 출력
├─ template-parts/
│  ├─ region-bar.php         ← 권역 필터 바 (office_region term 동적 출력) · 재사용
│  ├─ listing-card.php       ← ★ 매물 카드 · 재사용
│  ├─ building-card.php      ← ★ 빌딩 카드(집계 캐시만 출력, 재쿼리 없음) · 목록/허브 공용
│  ├─ region-nav.php         ← 권역/하위지역 버튼 네비
│  ├─ filter-bar.php         ← 필터 UI(파라미터만 확정, 쿼리 미연결 — 의도된 상태)
│  ├─ pagination.php         ← paginate_links() 래퍼
│  ├─ faq-section.php        ← FAQ 아코디언 섹션(빌딩/지역 공용)
│  ├─ contact-cta.php        ← 상담 CTA · 재사용 (Lead 확장 슬롯 포함)
│  ├─ home-hero.php          ← ★ Home Hero (H1 1개, ACF 무의존)
│  ├─ home-why.php           ← ★ Why Office Leasing (.olx-summary 재사용 + 등록번호)
│  └─ home-region-section.php ← ★ 권역 섹션 1개 = 4권역이 이 파일 하나를 반복 호출
└─ assets/
   ├─ css/officeleasing.css         ← v1 디자인 시스템 (목업에서 그대로 추출, 변경 금지)
   ├─ css/officeleasing-archive.css ← 빌딩 카드/그리드 컴포넌트 (목록·허브·Home에서 조건부 로드)
   ├─ css/officeleasing-home.css    ← ★ Home 전용 (Hero/권역/슬라이더) · is_front_page()에서만
   └─ js/
      ├─ single.js        ← 갤러리 썸네일 전환 (상세페이지에서만 로드)
      ├─ kakao-map.js     ← 카카오맵 렌더링 (SDK 로드된 경우에만)
      └─ home-slider.js   ← ★ 권역 슬라이더 좌우 버튼 (Vanilla, is_front_page()에서만)
```

## 컴포넌트 재사용 방식
- **매물 카드**: `get_template_part( 'template-parts/listing-card', null, array( 'listing_id' => $id ) );`
- **빌딩 카드**: `get_template_part( 'template-parts/building-card', null, array( 'building_id' => $id ) );` → `archive-building.php`, `taxonomy-office_region.php`가 이 한 줄을 루프에서 반복
- **권역 바 / 권역 네비 / FAQ / 상담 CTA / 헤더 / 푸터**도 동일하게 각 템플릿에서 재호출.

## single-building.php 렌더 분기 (URL 정책 반영)
- 활성 매물 **1개** → 목업과 동일한 매물 상세(Hero 가격·임대정보·핵심포인트)
- 활성 매물 **2개+** → Hero는 빌딩 요약, 임대정보 자리에 매물 카드 그리드
- 활성 매물 **0개** → 빌딩 정보 + "임대가능 매물 없음" 안내
- "활성"의 기준: `listing_status` ∈ (available, reserved, contract_pending)

## CTA — Lead 확장 대비
`template-parts/contact-cta.php` 하단에 `do_action( 'olt_contact_lead_slot' )` 훅을 뒀습니다.
추후 AI 선택형 대화창(Lead)은 별도 코드에서 이 훅에 버튼을 주입하면 되고, 지금 디자인은 그대로 유지됩니다.

## Sprint 02.5 (허브 SEO 스키마)
- `taxonomy-office_region.php`의 브레드크럼에 **"홈" 크럼 추가**(이전엔 "사무실 임대"로 바로 시작 - `archive-building.php`와 불일치했고, 플러그인 `schema-hub.php`의 BreadcrumbList가 화면과 동일한 경로를 갖도록 맞춤).
- `olt_archive_seo_intro()`/`olt_archive_faqs()`가 이제 플러그인의 `ol_default_archive_intro()`/`ol_default_archive_faqs()`를 우선 호출합니다(플러그인 비활성 시엔 기존처럼 자체 하드코딩 사본으로 폴백) — 화면 문구와 허브 스키마(FAQPage/CollectionPage) 문구가 항상 같은 소스를 쓰도록 통일. `taxonomy-office_region.php`의 `region_intro` 기본 폴백 문장도 같은 이유로 `ol_default_region_intro()`를 우선 호출하도록 변경(문구가 term 이름 스타일링 없이 원본 이름을 쓰도록 살짝 바뀜 - Core가 테마의 표시 헬퍼를 호출하지 않는다는 원칙 때문).
- CollectionPage/ItemList/BreadcrumbList/FAQPage 스키마 자체는 플러그인 `schema-hub.php`가 담당하며, 이 테마 쪽엔 코드 추가가 없습니다(위 두 항목이 전부).

## Sprint 01.5 3-4 (Building Detail 버그 수정)

- **하드코딩 제거**: `single-building.php` 하단 "전체 사무실 매물" 링크가 `home_url('/사무실임대/')`로 문자열 조립되어 있었다 → `get_post_type_archive_link('building')`로 교체(URL 문자열 조립 금지 원칙, Home V1과 동일 기준 적용)
- **대표 매물(primary listing) 선정 버그**: `olt_get_building_listings()`가 `monthly_total_cost` **DESC**(최고가 우선)로 정렬해, 활성 매물 1개 케이스에서 Hero에 "가장 비싼" 매물을 대표로 노출하고 있었다. 사이트 전반이 강조하는 "최저 임대료"(`building_min_rent` 캐시) 브랜딩과 어긋나는 불일치였음 → **ASC(최저가 우선)**로 수정. 이 함수를 쓰는 곳이 이 템플릿 한 곳뿐임을 확인 후 변경.
- **AIO 요약 3종 미출력 해소**: `building_location_summary`/`building_transportation_summary`/`building_feature_summary`/`building_recommended_tenant_summary`가 ACF엔 저장되는데 어떤 템플릿에서도 안 쓰던 죽은 데이터였다 → "빌딩 정보" 섹션 바로 뒤에 `#building-summary`(AT A GLANCE) 섹션을 추가해 값이 있는 항목만 노출. 새 CSS 컴포넌트를 만들지 않고 바로 위 섹션과 동일한 `.olx-specs`/`.row`를 재사용.
- **ACF 연결 점검**: `single-building.php`의 모든 `get_field()` 호출을 실제 ACF 필드명과 대조 검증 — 불일치 없음(연결 정상).
- **JSON-LD**: 이 템플릿엔 원래 JSON-LD가 없었다(스키마 자체는 3-5 범위). 이번 라운드에서 정리한 대표 매물 선정 로직·AIO 필드가 3-5의 `schema.php`가 그대로 가져다 쓸 데이터 소스가 된다.

## Home V1 (front-page.php)

### 설치 후 필요한 WordPress 설정
`front-page.php`는 **설정→읽기가 '최신 글'이든 '정적 페이지'든 상관없이** WordPress 템플릿 우선순위상 프론트 페이지에 자동 적용됩니다. **빈 페이지를 새로 만들 필요가 없습니다.**
- 현재 '최신 글' 설정이면 → 추가 설정 없이 그대로 Home V1이 뜹니다 (권장)
- '정적 페이지'로 지정된 페이지가 있으면 → 그 페이지의 본문 내용은 무시되고 `front-page.php`가 렌더됩니다. 의도한 게 아니면 설정→읽기를 '최신 글'로 되돌리세요
- GNB 메뉴는 **모양→메뉴**에서 primary 위치에 ABOUT / FOR LEASE / CONTACT를 지정하는 걸 권장합니다. 미설정 시 fallback이 뜨지만, 그때는 **실제로 존재하는 경로만** 렌더합니다(`/about/`·`/contact/` 페이지가 없으면 그 항목은 아예 안 나옴 — 임시 `#` 링크를 만들지 않음)
- **플러그인 교체 후 `wp officeleasing rebuild-cache` 또는 빌딩 목록 화면의 "지금 전체 재생성" 1회 실행** — 신규 캐시 필드 `building_last_verified_at`이 기존 데이터엔 비어있어 Home 정렬이 최근수정일 기준으로만 동작합니다

### 구조
Hero → (확장 슬롯) → Why → GBD → CBD → YBD → ETC → Contact CTA
- **H1은 Hero의 것 하나뿐**, 권역별 H2, 카드 제목 H3
- 각 섹션 고유 id: `#hero` `#why` `#region-GBD` … `#contact`
- 권역 섹션은 `home-region-section.php` **한 파일을 4번 호출**(HTML 복사 없음). 배경만 흰색→틴트→흰색→웜그레이로 교차
- 권역 문구(Eyebrow/제목/설명)는 `olt_home_region_copy()`가 관리. ETC는 화면에 'ETC'를 크게 쓰지 않고 `OTHER BUSINESS DISTRICTS` / `서울 주요 업무권역`으로 노출(카드 뱃지·관리 데이터는 계속 ETC 코드)
- 세부 지역 링크는 **실제 존재하는 자식 term만** `get_term_link()`로 생성. 없으면 링크 줄 자체를 렌더하지 않음

### 슬라이더
CSS `overflow-x` + `scroll-snap`이 본체이고, `home-slider.js`는 좌우 버튼만 담당하는 **순수 개선**입니다 — JS가 없거나 실패해도 스와이프·트랙패드·키보드(`tabindex="0"`)로 모든 카드에 접근할 수 있습니다. 외부 라이브러리·jQuery·자동재생·무한루프 없음.
- 데스크탑 4개 / 태블릿 2.6개 / 모바일 정확히 2개. 폭은 `--home-slider-gap` 하나로 계산: `calc((100% - gap*3)/4)`, 모바일 `calc((100% - gap)/2)`
- 버튼 이동량은 DOM에서 카드 실폭+gap을 측정해 **카드 단위 정수배**로만 이동(중간에 애매하게 걸리지 않음). 고정 px을 쓰지 않으므로 CSS 값이 바뀌어도 JS 수정 불필요
- 카드 **5개 이상일 때만** 화살표 표시(4개 이하는 숨김, 빈 카드로 채우지 않음). 모바일은 화살표를 숨기고 스와이프만 사용
- 끝에 도달하면 버튼 `disabled`, resize 시 재계산(디바운스 150ms), `prefers-reduced-motion`에서 smooth 스크롤 해제

### 접근성
섹션마다 `aria-labelledby`로 제목 연결, 트랙에 `aria-label`(빌딩 N건), 화살표는 `<button>` + `aria-label`, 카드 링크 안에 중첩 `<a>` 없음, 포커스 표시 유지.

### 성능
- 카드는 building **캐시 필드만** 읽어 매물 재쿼리 0회. 권역당 쿼리 1번(총 4번) + 결과 transient 캐시
- `olt_get_region_terms()`를 `wp_get_object_terms()` → **`get_the_terms()`**로 교체했습니다. 전자는 캐시를 무시하고 매번 DB를 조회해서 카드 32개면 term 쿼리 32번(N+1)이 나갑니다. 이 수정은 목록/허브 페이지 성능도 같이 개선합니다
- 카드 이미지는 `wp_get_attachment_image()`로 출력 → `srcset`/`width`/`height` 자동(CLS 방지), `sizes="(max-width:700px) 45vw, 280px"`로 모바일에 데스크탑용 대형 이미지가 내려가지 않게 함, `ol-building-thumb`(480×640, hard crop) 사이즈 사용(원본 직접 출력 안 함) — `docs/IMAGE_PERFORMANCE_GUIDELINES.md` 기준
- 첫 권역의 첫 4장만 `loading="eager"`, 나머지 전부 `lazy`. Hero에 이미지가 없으므로 `fetchpriority="high"`는 지정하지 않음
- Home CSS/JS는 `is_front_page()`에서만 로드

### 회사 정보 / region-bar
회사 정보(상호·전화·주소·등록번호·운영시간)는 템플릿에 하드코딩하지 않고 `olt_company()` 한 곳을 참조합니다(플러그인 `ol_company_info()`가 정본, 플러그인 꺼져도 Footer 정상 노출). Footer에 전체 주소(`서울 강남구 언주로 550 청광빌딩 2층`)를 반영했습니다.

**region-bar는 Home에서만 숨겼습니다** — front-page가 권역 4개를 세부지역 링크까지 전부 펼쳐 보여주므로 상단 권역 바가 같은 내비게이션을 중복하면서 Hero를 아래로 밀어냅니다. 되살리려면 `add_filter( 'olt_show_region_bar', fn( $s ) => $s || is_front_page() )`. 상세·아카이브·허브에서는 **그대로 유지**됩니다.

### 향후 Checklist / AI Agent 삽입 위치
`front-page.php`의 Hero 직후에 `do_action( 'olt_home_after_hero' )` 훅이 있습니다. `add_action('olt_home_after_hero', ...)`로 섹션을 주입하면 Home 구조를 다시 쓰지 않고 OFFICE CHECKLIST / AI OFFICE FINDER를 넣을 수 있습니다. Contact CTA 안에는 기존 `olt_contact_lead_slot` 훅이 그대로 있습니다.

## 아직 안 들어간 것 / 다음 sprint
- **JSON-LD 구조화 데이터는 이 테마에 없습니다.** 플러그인 `officeleasing-core/includes/schema.php`에서 `wp_head`로 출력 예정(목업 `<head>`의 @graph를 ACF 기반으로 동적 생성). **다음 sprint 1순위.**
- **AIO 요약 3종 미출력**: `building_transportation_summary`/`building_feature_summary`/`building_recommended_tenant_summary`가 ACF엔 있지만 어느 템플릿에서도 안 씀. JSON-LD 작업과 함께 배치 예정.
- **title/description/canonical/OG 메타**: 테마에서 하드코딩하지 않았습니다. Rank Math가 `wp_head()`에서 담당합니다(스택 원칙). `add_theme_support('title-tag')`만 켜둠.
- **한글 계층 URL 적용 완료(Sprint 01.5 3-1)**: `/강남사무실임대/삼성동/파르나스타워/` 형태가 플러그인 `officeleasing-core/includes/permalinks.php`에서 `post_type_link`/`term_link` 필터로 동작함. 이 테마 쪽은 애초 설계대로 `get_term_link()`/`get_permalink()`만 써왔기 때문에 **템플릿 수정이 전혀 필요 없었음** — URL이 필터를 통해 자동으로 바뀜. 카드 컴포넌트의 권역 뱃지만 term 이름이 길어진 것에 대응해 `olt_region_short_code()`로 짧은 코드 표시를 유지하도록 수정함.
- **필터 쿼리 미연결**: `filter-bar.php`는 UI만, `region`/`area_min`/`area_max`/`budget_min`/`budget_max` 파라미터는 아직 실제 쿼리에 안 붙음 — 의도된 상태(다음 단계 ③검색→④필터에서 연결).
- **Home V1은 완료**(위 섹션 참고). 다음 확장 대상: OFFICE CHECKLIST, AI OFFICE FINDER, 지도 검색, 조건 필터, 콘텐츠 허브 — 전부 `olt_home_after_hero` 훅으로 삽입 가능.
- Home에서 제외한 항목(의도된 것): ItemList/FAQPage/SearchAction 스키마, 인사이트 최신 글, 후기, 고객사 로고, 통계 카운터, `building_featured` 추천 노출 플래그.

## GeneratePress 관련 주의
자식 테마가 `header.php`/`footer.php`를 제공하므로 **GP의 기본 헤더/푸터는 전역적으로 이 테마의 것으로 대체**됩니다(디자인 일관성 목적, 의도된 동작). 권역 바는 매물 관련 컨텍스트에서만 노출되도록 `olt_show_region_bar` 필터로 스코프를 제한했습니다.

## 목록/허브 라운드 (archive-building / taxonomy-office_region)
새로 추가된 최상위 템플릿:
- `archive-building.php` — 전체 빌딩 목록 (`/사무실임대/`). 플러그인에서 building `has_archive`를 `'사무실임대'`로 켰습니다. **활성화 후 설정→고유주소에서 한 번 저장(rewrite flush)해야 이 URL이 뜹니다.**
- `taxonomy-office_region.php` — 권역/동 허브 (한 파일에서 `$term->parent === 0`로 레벨 분기)

새 재사용 컴포넌트(전부 `template-parts/` 플랫 구조 유지 — 기존 컴포넌트와 일관성, single-building 참조 안 깨지게):
- `building-card.php` (빌딩 1개 + 집계값 카드), `region-nav.php`, `filter-bar.php`(UI만), `pagination.php`, `faq-section.php`
- `contact-cta.php`는 목록/허브에서도 그대로 재사용

빌딩 카드는 매물을 재쿼리하지 않고 building의 **캐시 필드**(`building_active_listing_count`, `building_min/max_exclusive_area_pyeong`, `building_min_rent`)만 읽어 출력합니다. 이 캐시는 플러그인 `building-cache.php`가 매물 저장/삭제 시 갱신합니다.

목록/허브 전용 CSS(`assets/css/officeleasing-archive.css`)는 해당 페이지에서만 조건부 로드됩니다.

## 검증 상태
- 전 PHP 파일 `php -l` 문법 검사 통과 (플러그인 31개 + 테마 21개).
- 플러그인 오프라인 테스트 7종(계산/permalink/home-query/schema/aio-status/region-sync/schema-hub) 총 89개 assertion 통과, 플러그인 내 함수명 중복 없음 확인. ACF 3그룹 중복 필드명 없음.
- **단, 실제 WordPress + ACF 환경에서의 런타임 동작은 아직 미검증**입니다(이 환경엔 WP가 없음). 한글 계층 URL(`/강남사무실임대/삼성동/`)의 rewrite rule/canonical 리다이렉트는 플러그인 쪽 오프라인 정규식 테스트(`tests/test-permalinks.php`, 12개 assertion)만 통과했고, 실제 `WP_Rewrite` 매칭·flush·301 동작은 라이브 사이트에서 확인이 필요합니다(Region 변경/slug 변경/중복 slug/404/rewrite flush/redirect loop 체크리스트는 플러그인 README 참고).
