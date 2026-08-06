# officeleasing-core (무료 ACF 기준)

## 설치 (신규 워드프레스 기준)
1. 워드프레스 설치 → **ACF(무료) 플러그인 먼저 설치·활성화**
2. `officeleasing-core` 폴더를 zip으로 압축한 상태로 워드프레스 관리자 → 플러그인 → 새로 추가 → 플러그인 업로드로 업로드
   - (또는 FTP로 `wp-content/plugins/officeleasing-core/`에 폴더째 배치)
3. 플러그인 활성화
   - 활성화 순간 `building`/`listing` CPT, `office_region`/`listing_feature` taxonomy 등록, `office_region` 기본 term(강남/도심권/여의도/기타권역 + 하위 동) 자동 시딩, rewrite flush까지 한 번에 실행됨
   - ACF가 없는 상태로 활성화하면 화면 상단에 경고 문구만 뜨고 CPT/taxonomy는 정상 등록됨(콘텐츠 구조는 ACF 유무와 무관)
4. 워드프레스 관리자 메뉴에 "빌딩", "매물" 항목이 보이면 정상
5. `building`/`listing` 글쓰기 화면 진입 → ACF 필드가 `acf-json/`에서 자동 로드되어 나타남 (수동 재입력 불필요)
6. (선택, 로컬 PC에서) `php tests/test-calculations.php`로 계산 로직 단독 검증 가능 — WP 구동 없이 즉시 pass/fail 확인

**참고**: ACF 관리자 화면(맞춤 필드 → 필드 그룹)에서 이 두 필드그룹에 "동기화 가능" 배지가 뜰 수 있습니다 — DB에 아직 필드그룹 레코드가 없고 JSON만 있는 상태라 나오는 정상 표시입니다. building/listing 글쓰기 화면에 필드가 보이는 것과는 무관하게(그건 이미 정상 작동), ACF 자체 UI로 필드를 수정하고 싶을 때만 한 번 동기화하면 됩니다.

## 폴더 구조
```
officeleasing-core/
├─ officeleasing-core.php   ← 플러그인 헤더 + 부트스트랩(require 순서, ACF 의존성 체크, 활성화 훅)
├─ uninstall.php            ← 플러그인 삭제 시 옵션만 정리 (매물 데이터는 보존)
├─ includes/
│  ├─ post-types.php        ← building/listing CPT 등록 (building has_archive = '사무실임대')
│  ├─ taxonomies.php        ← office_region/listing_feature 등록 + 기본 term 시딩 + 레거시 영문명 마이그레이션
│  ├─ permalinks.php        ← ★ URL Foundation: 빌딩/권역 한글 계층 URL (post_type_link/term_link 필터 + rewrite rule)
│  ├─ acf-json.php          ← acf-json 폴더를 필드그룹 원본 경로로 지정
│  ├─ helpers.php           ← 순수 계산/포맷 함수 + 저장가드 (WP 비의존, 단독 테스트 가능)
│  ├─ maps.php               ← 카카오맵 SDK 조건부 로드(빌딩 상세 전용) + 좌표 검증
│  ├─ admin-map-picker.php  ← Building 편집화면 "위치 찾기" 위젯(카카오 Geocoder, REST 키 불필요)
│  ├─ calculations.php      ← "어떻게 계산하는가" (get_field로 읽어 update_field로 저장)
│  ├─ region-sync.php       ← "지역을 어떻게 동기화하는가" (building<->listing office_region 복제)
│  ├─ building-cache.php    ← 빌딩별 매물 집계 캐시(활성매물수/면적범위/최저임대료) 갱신
│  ├─ cache-rebuild.php     ← 집계 캐시 일괄 재생성 (WP-CLI `wp officeleasing rebuild-cache` + 관리자 화면 버튼)
│  ├─ save-hooks.php        ← "저장 시 무엇을 어떤 순서로 실행하는가" (훅 등록 + 오케스트레이션만)
│  ├─ save-api.php          ← 미래 커스텀 관리자페이지 전용 저장 진입점 (아래 별도 설명)
│  ├─ archive-query.php     ← 아카이브/허브 메인쿼리 보정 (post_type 제한, include_children, per_page)
│  ├─ home-query.php       ← ★ Home 권역별 빌딩 선정(활성매물/최근확인일/지역다양성) + 캐시 무효화
│  ├─ schema-home.php      ← ★ Home JSON-LD (WebSite/WebPage/RealEstateAgent, Rank Math 있으면 자동 비활성)
│  ├─ schema.php           ← ★ 빌딩 페이지 JSON-LD (OfficeBuilding + RealEstateListing, Rank Math 있으면 자동 비활성)
│  ├─ schema-hub.php       ← ★ 허브(아카이브·권역·동) JSON-LD (CollectionPage/ItemList/BreadcrumbList/FAQPage)
│  ├─ validation.php        ← acf/validate_value로 저장 시점 값 검증
│  ├─ admin-columns.php     ← 목록 컬럼/필터 (활성매물수는 캐시필드에서 읽음, 재쿼리 없음)
│  ├─ admin-hidden-fields.php ← 자동계산 필드를 편집화면 폼에서 숨김 (아래 별도 설명)
│  └─ admin-summary-box.php ← 숨긴 계산값을 사이드바에 읽기전용으로 표시
├─ assets/
│  └─ admin-map-picker.js   ← 위치 찾기 위젯 JS (주소검색/지도클릭 -> 좌표+주소 자동입력)
├─ acf-json/
│  ├─ group_ol_building.json
│  ├─ group_ol_listing.json
│  └─ group_ol_region.json  ← office_region term 필드(region_intro, region_faq_q1~5/a1~5)
└─ tests/
   ├─ test-calculations.php
   ├─ test-permalinks.php     ← rewrite 정규식 생성 로직만 오프라인으로 검증(실제 WP_Rewrite 매칭은 미검증)
   ├─ test-home-query.php     ← Home 지역 다양성 로직 + 회사정보 헬퍼 검증(실제 파일을 include해서 테스트)
   ├─ test-schema.php         ← 빌딩 스키마 노드 조립 검증(실제 파일을 include, 최소 WP/ACF 스텁으로 테스트)
   ├─ test-aio-status.php     ← AIO 생성방식/검수상태 상태 전이 검증
   ├─ test-region-sync.php    ← 정/역방향 권역 동기화 검증(3-7 버그 재현 케이스 포함)
   └─ test-schema-hub.php     ← 허브 스키마 노드 조립 검증(아카이브/상위권역/동 3가지 컨텍스트)
```

## save-hooks.php / calculations.php / region-sync.php 역할 분리
- **save-hooks.php**: `add_action('save_post_listing', ...)` / `add_action('save_post_building', ...)` 등록과 실행 순서만 담당. "무엇을 계산하는가"는 모른다.
- **calculations.php**: `ol_calculate_listing_fields($post_id)` 등 — post_id를 받아 계산하고 `update_field()`로 저장하는 작업 함수만 제공. 언제 호출되는지는 모른다.
- **region-sync.php**: `ol_sync_region_from_building()` / `ol_cascade_region_to_listings()` — office_region 복제만 담당.

세 파일 다 함수 정의만 하고(save-hooks.php만 예외적으로 `add_action` 등록), 실제 실행 순서는 save-hooks.php 한 곳에서만 결정되므로 계산 로직이나 동기화 로직을 수정해도 "언제 실행되는가"를 건드릴 필요가 없습니다.

## 지난 라운드 대비 수정된 버그
- **보증금/임대료 Group 필드 읽기 오류 수정**: `deposit_group`/`monthly_rent_group`의 서브필드 name이 `deposit_uk`/`deposit_man`, `rent_uk`/`rent_man`인데 PHP에서 `['uk']`/`['man']`으로 읽고 있어 계산값이 항상 0이 될 뻔했음 → 실제 name으로 수정
- **핵심포인트 3개 Group의 서브필드 name 충돌 수정**: ACF Group 필드는 서브필드를 그룹명 없이 자기 name으로 그대로 postmeta에 저장하기 때문에, 3개 그룹이 전부 `title`/`description`이라는 같은 name을 쓰면 셋 다 같은 값을 덮어씀 → `kp1_title`/`kp2_title`/`kp3_title` 등으로 고유화

## 이번 라운드 필드 구조 변경
- **보증금/임대료: 억+만원 Group 입력 폐기 → 만원 단위 단일 Number 필드로 회귀** (`deposit_manwon`, `monthly_rent_manwon`). 오타 방지 목적으로 분리했었지만 실사용 시 불편하다는 피드백 반영.
- **부가세 포함(`vat_included`) 필드 삭제** — 이 사업은 항상 부가세 별도라 토글 자체가 불필요.
- **건물 연면적/기준층면적: 입력 단위를 평 → ㎡로 전환**. `building_total_area_sqm`/`building_standard_floor_area_sqm`이 이제 입력(필수), `building_total_area_pyeong`/`building_standard_floor_area_pyeong`이 자동계산(readonly).
- **[후속 라운드] 매물 전용/공급면적도 평 → ㎡로 입력 방향 통일**. 처음엔 매물만 평이 입력이라 건물과 방향이 반대였는데(바로 위 항목과 헷갈리는 원인이었음), `exclusive_area_sqm`/`lease_area_sqm`이 입력(필수)으로, `exclusive_area_pyeong`/`lease_area_pyeong`이 자동계산(readonly)으로 바뀌어 이제 건물·매물 전부 ㎡가 원본 입력으로 통일됐다. `ol_calculate_listing_fields()`(calculations.php)가 계산 방향을 반대로 뒤집었고, `admin-hidden-fields.php`/`validation.php`/`save-api.php`의 화이트리스트도 함께 sqm 쪽으로 바뀌었다.
- **`building_total_floors`(총층수) + `building_scale_text`(층규모 텍스트) 삭제 → `building_basement_floors`(지하층수) + `building_ground_floors`(지상층수) 2개로 대체**. "지하 7층 ~ 지상 40층" 같은 표시 문구는 이제 저장하지 않고 테마의 `olt_format_building_scale()`이 두 숫자로 매번 조합해서 보여줍니다. 필터/정렬은 `building_ground_floors` 기준(예전 `building_total_floors` 역할을 이어받음).
- **지하철 도보시간(`building_subway1_walk`, `building_subway2_walk`) 필드 삭제**. 역명+노선만 남기고, 도보시간처럼 세부적인 내용은 필요하면 AIO 요약/핵심포인트 자유 텍스트에 녹여서 쓰는 방향.
- **주소/좌표 4필드를 readonly로 잠금**: `building_address_road`, `building_address_jibun`, `building_lat`, `building_lng`는 관리자가 직접 타이핑 못 하고, 편집화면 맨 위 "위치 찾기"(카카오맵) 위젯으로만 채워집니다. 위젯은 새로 만든 `field_ol_bld_map_picker_slot`(message 타입, 필드 목록 맨 앞)에 렌더됩니다.
- 대부분의 "예: ..." 안내문구 제거 (필드가 줄고 readonly 필드가 늘면서 불필요해짐)

## 무료 ACF 제약 대응
- Repeater 없음 → 지하철 2개, FAQ 5개, 핵심포인트 3개는 번호형/고정 슬롯 Group으로 처리
- Gallery 없음 → 빌딩 8장, 매물 6장 개별 Image 필드 (커스텀 갤러리 메타박스는 관리자페이지 단계로 이연)
- Options Page 없음 → 현재 필드 설계엔 불필요

## 입력값 vs 자동계산값
아래는 **관리자 편집화면 메인 폼에는 아예 표시되지 않습니다** (`admin-hidden-fields.php`). 입력 필드 바로 아래 readonly 필드가 중복으로 보이는 문제를 없애기 위해, `acf/prepare_field` 필터로 렌더링만 건너뜁니다 — 필드 정의(key/name)나 postmeta 저장 방식은 전혀 바뀌지 않고, `save_post_listing`/`save_post_building` 훅이 저장 시 여전히 자동으로 채웁니다. 값을 확인하고 싶으면 편집화면 우측 사이드바의 **"계산값 요약(읽기전용)"** 박스(`admin-summary-box.php`)에서 볼 수 있습니다.

| 자동계산 필드 | 원본 입력 필드 |
|---|---|
| `exclusive_area_pyeong` | `exclusive_area_sqm` (매물도 건물과 동일하게 ㎡가 입력 — 후속 라운드에서 통일) |
| `lease_area_pyeong` | `lease_area_sqm` (매물도 건물과 동일하게 ㎡가 입력) |
| `deposit_amount` | `deposit_manwon` (만원 단위 단일 입력) |
| `monthly_rent` | `monthly_rent_manwon` (만원 단위 단일 입력) |
| `maintenance_fee` | `maintenance_fee_manwon` |
| `monthly_total_cost` | `monthly_rent` + `maintenance_fee` |
| `rent_per_lease_pyeong` | `monthly_rent` ÷ `lease_area_pyeong`(자동계산값을 다시 씀) |
| `maintenance_per_lease_pyeong` | `maintenance_fee` ÷ `lease_area_pyeong`(자동계산값을 다시 씀) |
| `noc_per_exclusive_pyeong` | `monthly_total_cost` ÷ `exclusive_area_pyeong`(자동계산값을 다시 씀) |
| `deposit_per_exclusive_pyeong` | `deposit_amount` ÷ `exclusive_area_pyeong`(자동계산값을 다시 씀) |
| `building_total_area_pyeong` | `building_total_area_sqm` |
| `building_standard_floor_area_pyeong` | `building_standard_floor_area_sqm` |

보증금/임대료는 만원 단위 숫자 하나만 입력합니다(예: `1532` 입력 → 저장 시 15,320,000원으로 환산, 화면엔 "1,532만원"으로 표기). 억+만원 분리 입력은 폐기했습니다.

화면 표기용 포맷 함수는 `helpers.php`의 `ol_format_manwon($won)` → "1,532만원"(억 단위로 쪼개지 않고 항상 만원 콤마 표기로 통일), `ol_format_krw_per_pyeong($won)` → "479.6만원"(평당가처럼 만원 미만 단수가 있는 값용).

**readonly(직접 입력 불가) 필드**: `building_address_road`, `building_address_jibun`, `building_lat`, `building_lng` 4개는 편집화면 맨 위의 "위치 찾기" 위젯(카카오맵)으로만 채워집니다. 관리자가 직접 타이핑할 수 없도록 회색으로 잠가뒀습니다 — 주소 검색 결과가 도로명·지번·좌표를 한 번에 다 채워주므로 수기입력 자체가 필요 없습니다.

## 검증 규칙 (validation.php)
- 면적·좌표·층수·엘리베이터·전용률 등 숫자 필드에 음수/비숫자 입력 시 **저장 자체가 막히고** 필드 아래 인라인 에러 표시 (ACF 자체 AJAX 검증 단계, 무료 기능)
- 전용면적이 공급면적보다 크면 저장 차단
- 위도 33~39, 경도 124~132 범위를 벗어나면 저장 차단 (한국 밖 좌표 오입력 방지)
- 나눗셈 계산은 `helpers.php`의 `ol_safe_divide()`가 분모 0 이하일 때 0을 반환해 경고 없이 방어

## 향후 관리자 페이지 연동 시 반드시 지킬 것

**현재 ACF 관리자 화면 경로는 안전이 구조적으로 보장됩니다.** 워드프레스 코어는 `wp_insert_post()`/`wp_update_post()`에서 항상 범용 `save_post`를 먼저 발화하고 그다음 `save_post_{post_type}`을 발화합니다. ACF는 자기 필드 저장 로직을 범용 `save_post`에 걸어두므로, 우선순위를 뭘 주든 `save_post_listing`/`save_post_building`(이 플러그인의 계산·동기화 훅)은 항상 ACF 저장 이후에 실행됩니다. wp-admin의 ACF 편집화면으로 저장하는 한 이 순서는 절대 안 깨집니다.

**단, 미래 커스텀 관리자 페이지가 아래 패턴으로 짜면 깨집니다:**
```php
$post_id = wp_insert_post([...]);   // 이 시점에 save_post_listing이 이미 실행되고 지나감
update_field('exclusive_area_sqm', $value, $post_id); // 이후에 채운 값은 계산에 반영 안 됨
```
`wp_insert_post()`가 먼저 `save_post_listing`을 실행시켜 버리기 때문에, 그 뒤에 `update_field()`로 채우는 값은 계산 시점에 아직 없던 값이라 반영되지 않습니다.

**그래서 미래 관리자 페이지는 `update_field()`를 직접 낱개로 호출하지 말고, `save-api.php`가 제공하는 전용 함수를 쓰세요:**
```php
$post_id = wp_insert_post(['post_type' => 'listing', 'post_title' => '...', 'post_status' => 'publish']);
ol_save_listing_fields($post_id, [
    'exclusive_area_sqm' => 1081.0,
    'lease_area_sqm' => 2036.4,
    'related_building' => $building_id,
    // ...
]);
// 빌딩은 ol_save_building_fields($post_id, [...]) 사용
```
이 함수는 필드값을 전부 세팅한 뒤 계산·동기화 훅 핸들러(`ol_handle_listing_save`/`ol_handle_building_save`)를 명시적으로 재호출하므로, 저장 순서와 무관하게 항상 올바른 계산값이 남습니다. 내부적으로는 여전히 `update_field()`를 쓰므로(직접 `update_post_meta()` 금지 원칙은 동일) ACF의 필드 타입별 저장 포맷도 그대로 보장됩니다.

`acf/validate_value` 검증은 ACF 자체 저장 경로(관리자 화면 AJAX)에서만 자동 적용되므로, `ol_save_listing_fields()`를 거치더라도 음수·비정상값 검증은 자동으로 안 됩니다. 커스텀 관리자 페이지에서 사용자 입력을 받는다면 저장 전에 직접 검증하거나 최소한 `helpers.php`의 방어 로직(0/음수 방어)에 의존해야 합니다.

## AIO 초안 자동삽입
`building_location_summary`가 비어있으면 템플릿 문장이 자동 삽입되고 `_ol_aio_draft` post meta가 `1`로 세팅됩니다. Rank Math 등에서 이 메타가 있는 글은 noindex 권장 — 미편집 boilerplate가 그대로 색인되면 여러 빌딩 페이지가 거의 동일한 문장으로 중복 색인되어 GEO/SEO에 역효과입니다. 관리자가 문장을 직접 수정해 저장하면 플래그가 자동 해제됩니다.

## URL Foundation (Sprint 01.5, 3-1)
목표 형태: 권역 허브 `/강남사무실임대/`, 지역 허브 `/강남사무실임대/삼성동/`, 빌딩 `/강남사무실임대/삼성동/파르나스타워/` (자식 지역 미배정 빌딩은 `/강남사무실임대/파르나스타워/`, 권역 자체 미배정 빌딩은 기존 `/building/{slug}/` 그대로 폴백).

- **`office_region` 부모 term 이름/슬러그 마이그레이션**: 기존 영문 코드(`GBD`/`CBD`/`YBD`/`ETC`)를 한글(`강남사무실임대`/`도심권사무실임대`/`여의도사무실임대`/`기타권역사무실임대`)로 1회성 변경. 신규 설치는 처음부터 한글로 시딩됨. 활성화 훅에서는 마이그레이션 → 시딩 순서로 실행(순서가 바뀌면 슬러그 중복으로 부모 term이 중복 생성될 수 있어 순서 고정이 중요).
- **`includes/permalinks.php`**: `post_type_link`(building)/`term_link`(office_region) 필터로 URL을 동적 구성하고, `generate_rewrite_rules` 훅에서 **실제 존재하는 부모/자식 slug만으로 한정한 정규식**(범용 3단 와일드카드 아님)을 등록해 다른 라우트와의 충돌 위험을 최소화함. `office_region` taxonomy 자체의 자동 rewrite는 `false`로 꺼서 `/office-region/{slug}/` 중복 URL이 남지 않도록 함.
- **테마 템플릿은 전혀 수정하지 않음**: `get_permalink()`/`get_term_link()`를 쓰는 기존 코드가 WP 내부적으로 이 필터를 그대로 거쳐가므로 호출부 변경이 필요 없었음(테마 README에 이미 명시돼 있던 설계 원칙 그대로).
- **레거시 URL 리다이렉트는 별도 코드 없음**: 워드프레스 코어 `redirect_canonical()`이 `get_permalink()`로 계산한 현재 canonical과 요청 URL을 비교해 자동 301 처리하므로, 중복 구현을 피하기 위해 커스텀 `template_redirect` 훅을 추가하지 않았음.
- **`tests/test-permalinks.php`**: rewrite 정규식이 의도한 샘플 경로(3단/2단 허브/2단 building fallback/1단 허브)에는 매칭되고 무관한 경로(`/building/{slug}`, `/사무실임대`, 임의 페이지)는 안 건드리는지 오프라인으로 검증(12개 assertion, 실제 WP_Rewrite 엔진 자체는 미검증).
- **카드 뱃지 표시 수정**: `building-card.php`/`listing-card.php`가 권역 뱃지에 term 이름을 그대로 찍고 있었는데, 이름이 "GBD"에서 "강남사무실임대"로 길어지면서 그대로 두면 뱃지가 깨짐 → `olt_region_short_code()` 헬퍼를 새로 추가해 뱃지에는 계속 짧은 코드(GBD 등)만 표시.

**⚠️ 라이브 워드프레스에서 반드시 확인해야 하는 것** (이 환경엔 WP가 없어 미검증):
- 플러그인 버전을 올리면(`OL_PERMALINKS_VERSION` 상수) 다음 요청에서 `flush_rewrite_rules()`가 1회 자동 실행됨 — 그래도 문제가 있으면 설정→고유주소에서 수동 저장으로 강제 flush 가능
- Region 변경(빌딩의 office_region 재배정) 후 URL이 즉시 바뀌는지, 이전 URL 접속 시 301로 새 URL로 가는지
- 빌딩/지역 slug 변경 후 이전 URL 301 확인
- 동명 slug(예: 실제로는 없지만 빌딩명이 우연히 동/구 이름과 같은 경우) 발생 시 동작
- 완전히 없는 경로 접속 시 정상 404
- 리다이렉트 루프가 발생하지 않는지(설계상 발생하지 않아야 하나, 실제 환경 검증 필요)

## Home V1 (front-page) 지원 기능

### 회사 정보 단일 소스
`helpers.php`의 **`ol_company_info()`** 가 정본입니다. 상호/대표전화/주소/등록번호/운영시간/카카오URL을 한 곳에서만 관리하고, Footer·Contact CTA·JSON-LD가 전부 이 값을 참조합니다. 값 변경은 이 함수(또는 `ol_company_info` 필터) 한 곳만 고치면 됩니다.
- `ol_company('phone')` — 단일 값 조회
- `ol_tel_href()` — `tel:` 링크용 정규화
- 테마에는 `olt_company()` 래퍼가 있어 **플러그인이 꺼져도 Footer 회사정보는 정상 노출**됩니다(동일 값 사본).
- `kakao_url`은 기본값이 **빈 문자열**입니다. 실제 채널 주소가 확정되면 여기에 넣으면 Contact CTA가 자동으로 버튼을 노출하고, 비어있으면 `/contact/` 페이지가 있으면 그쪽으로, 그것도 없으면 버튼을 아예 숨깁니다(임의의 외부 URL을 만들지 않음).

### 권역별 빌딩 선정 — `ol_get_home_region_buildings( $term_id, $limit = 8, $args = [] )`
Building **ID 배열**을 반환합니다(테마는 이 ID를 기존 `building-card.php`에 넘겨 렌더). 선정 순서:
1. 활성 매물 1개 이상 — `building_active_listing_count > 0` **캐시 필드로만** 판정(매물 재쿼리 없음)
2. 최근 확인일 내림차순 — `building_last_verified_at`
3. 세부 지역 다양성 — 한 동이 결과의 절반(8개 중 4개)을 넘지 않게 조정. **데이터가 부족하면 강제하지 않음**(빈 카드를 만들지 않는다)
4. 최근 수정일 내림차순

`building_featured`(추천 노출 플래그)는 이번 범위에서 **제외**했습니다 — 지원 ACF 필드가 없어 새로 만들기보다 V1 최소 범위로 갔습니다. `$args`가 확장 슬롯이라 나중에 `'featured_first' => true`를 추가해도 함수 시그니처를 깨지 않습니다.

**성능**: 권역당 쿼리 1번. 후보 풀을 `limit*3`(최대 40)으로 한정해 뽑은 뒤 정렬·다양성 조정은 PHP에서 처리합니다. 최근확인일을 meta orderby로 하지 않은 이유는 `building_last_verified_at`이 비어있는 빌딩(검수 워크플로우 미가동)이 많아 meta orderby가 그 빌딩들을 밀어내거나 순서를 뒤섞기 때문입니다.

### 최근 확인일 — `building_last_verified_at` (신규 캐시 필드)
연결된 활성 매물들의 `verified_at` **최댓값**을 building에 비정규화해 저장합니다. `verified_at`의 ACF `return_format`이 `"Ymd"`(예: `20260716`)라서 정수로 그대로 비교·정렬됩니다. 확인된 매물이 없으면 `0`.
- 갱신 트리거는 기존 집계 캐시와 동일 (`building-cache.php`의 `ol_recount_building_cache()`)
- **기존 데이터에는 값이 없으므로** 플러그인 교체 후 `wp officeleasing rebuild-cache` 또는 빌딩 목록 화면의 "지금 전체 재생성" 버튼을 1회 실행해야 채워집니다
- 화면에 "최근 확인" 날짜를 표시할 때는 이 값(=실제 검수일)만 쓰고, `post_modified`를 검수일처럼 표시하지 않습니다

### Home 캐시 무효화 — 버전 카운터 방식
권역별 결과를 transient에 12시간 저장하되, **TTL만으로 최신성을 보장하지 않습니다.** `ol_home_cache_version` 옵션을 1 올리면 모든 권역 캐시 키가 한 번에 무효가 됩니다. 트리거: building/listing 저장, 휴지통·복원·영구삭제(building/listing만), `office_region` term 생성·수정·삭제, 빌딩의 권역 재배정(`set_object_terms`, office_region만 필터링).
개별 term 캐시를 지우는 방식을 쓰지 않은 이유: 빌딩이 권역을 옮기면 "이전 권역"과 "새 권역"이 동시에 바뀌고 term 삭제·이동까지 겹치면 누락이 생기기 쉽습니다. 카운터는 정확하고 비용도 `update_option` 1회뿐입니다.
캐시 저장이 실패해도 이미 실시간 쿼리 결과를 손에 들고 있으므로 그대로 반환됩니다(안전한 fallback).

### Home JSON-LD — Rank Math와 중복 방지
`schema-home.php`가 `WebSite` / `RealEstateAgent` / `WebPage`를 출력합니다. @id 규칙:
- `{home}/#website` · `{home}/#organization` · `{home}/#webpage`
- `WebPage.isPartOf` → WebSite, `WebSite.publisher` → Organization

**Rank Math(또는 Yoast)가 활성이면 같은 엔티티를 이미 출력하므로 이 파일은 아무것도 렌더하지 않습니다.** 강제로 켜려면 `add_filter('ol_output_home_schema', '__return_true')`. 실제 사이트에 Rank Math Pro가 깔려 있으므로 **기본 동작은 "우리 스키마 비활성"**입니다 — Rank Math의 Organization 설정(상호/전화/주소/등록번호)을 채우는 쪽이 정석입니다.
향후 빌딩/매물 페이지의 `seller` 노드는 회사 정보를 다시 선언하지 말고 `{home}/#organization`을 `@id`로 참조하세요.

`title`/`description`/`robots`/`canonical`/OG/Twitter Card는 테마·Core 어디서도 출력하지 않습니다(Rank Math 담당). 테마는 `add_theme_support('title-tag')`만 켜둬서 Rank Math가 없을 때도 기본 title은 나옵니다.

권장 Home 메타(Rank Math에 직접 입력):
- Title: `서울 사무실 임대·기업 이전 전문 | OFFICE LEASING`
- Description: `강남 GBD·도심 CBD·여의도 YBD를 중심으로 서울 주요 업무지구의 빌딩과 사무실 임대 정보를 제공하는 OFFICE LEASING입니다.`

### 빌딩 페이지 JSON-LD — `schema.php` (Sprint 01.5 3-5)
`is_singular('building')`에서 `OfficeBuilding` + `RealEstateListing`(활성 매물 수만큼, 0/1/N)을 출력합니다. **범위는 이 두 타입 + AIO 요약뿐**입니다 — `ItemList`/`CollectionPage`/`Hub Breadcrumb`/`FAQPage`는 허브(권역·동) 단계 스키마라 명시적으로 제외했고(Sprint 02.5로 분리), building_faq_q1-5/a1-5가 화면엔 이미 있지만 이번 범위엔 FAQPage 스키마를 넣지 않았습니다.

- **listing 전용 `<script>` 출력 지점 없음**: `single-listing.php`가 항상 building URL로 301되므로(URL 정책), listing만을 위한 스키마 페이지가 없습니다. 대신 building 페이지의 `RealEstateListing` 노드(들)가 화면에 보이는 매물 정보를 그대로 커버합니다. 매물이 0개면 노드도 0개, 1개면 1개, N개(카드 그리드로 전부 보이는 경우)면 N개 — **화면 매물 개수와 노드 개수가 항상 일치**합니다.
- **`RealEstateListing.url`은 building permalink**입니다. listing 자체 URL(항상 리다이렉트됨)을 구조화 데이터에 넣지 않습니다.
- **`offers.seller`는 `{home}/#organization` @id만 참조**합니다 — 매물마다 회사 정보를 통째로 재선언하지 않습니다(Home V1 패치 문서의 원칙을 실제로 적용).
- **자동초안(`_ol_aio_draft`) 방어**: `building_location_summary`가 관리자 미편집 자동초안 상태면 `description`에 넣지 않습니다. 여러 빌딩에 거의 동일한 문장이 그대로 색인되는 중복 콘텐츠 위험은 meta description뿐 아니라 JSON-LD에도 동일하게 적용됩니다.
- **좌표/주소가 없으면 `geo`/`address` 자체를 안 넣습니다** — `0, 0`을 지어내지 않습니다.
- `additionalProperty`(총 층수/주차/엘리베이터/교통·특징·추천입주업종 AIO 요약, offer 쪽 보증금/관리비)는 전부 **화면(빌딩 정보·임대 정보 섹션)에 실제로 보이는 값만** 값이 있을 때만 추가합니다.
- Rank Math/Yoast가 활성이면 `schema-home.php`와 동일한 감지 로직으로 자동 비활성됩니다(`ol_output_building_schema` 필터로 강제 가능).

## Sprint 01.5 3-6 (AIO 생성방식/검수상태 분리)
예전엔 `_ol_aio_draft`(불리언) 하나로 "미편집 자동초안"만 표현했다. 이제 두 필드로 분리한다:
- **`aio_generation_status`**(자동계산, 폼에서 숨김) — `auto_generated` | `human_written`. `building_location_summary`가 저장된 자동초안 해시와 같은지로 매 저장마다 판정.
- **`aio_review_status`**(관리자가 직접 조작, 폼에 노출) — `pending` | `reviewed`. 기본값 `pending`.

**핵심 개선**: `aio_generation_status`가 `auto_generated`여도 관리자가 `aio_review_status`를 직접 `검수 완료`로 바꾸면 그 값을 유지합니다(다음 저장에서 pending으로 강제 리셋하지 않음) — 즉 **"자동초안 문장을 한 글자도 안 고치고 그대로 승인"하는 경로가 이제 가능**합니다. 예전 단일 불리언 방식으로는 이 조합 자체를 표현할 수 없었습니다.
`schema.php`의 description 억제 조건도 `auto_generated && review_status !== reviewed`로 갱신했습니다(검수완료된 자동초안은 이제 구조화 데이터에도 포함됩니다).
`tests/test-aio-status.php`(11 assertion) + `tests/test-schema.php`의 관련 케이스로 상태 전이를 검증했습니다.

## Sprint 01.5 3-7 (Region Sync Engine 업그레이드)
- **버그 수정**: `ol_cascade_region_to_listings()`(빌딩→매물 역방향 동기화)가 `empty($terms)`일 때 early-return 하는 버그가 남아있었습니다. 정방향 함수(`ol_sync_region_from_building`)는 이전 라운드에 이미 고쳤는데 역방향엔 반영이 안 돼 있었던 것 — **빌딩의 권역을 전부 지우고 저장해도 이미 연결된 매물들엔 예전 권역이 그대로 남는 실제 버그**였습니다. 동일 기준(`is_wp_error`만 체크)으로 통일했습니다.
- **term 편집 시 자동 rewrite flush**: 관리자가 `office_region` term의 이름/슬러그/부모를 wp-admin에서 바꾸면, `permalinks.php`의 커스텀 rewrite rule은 "마지막 flush 시점의 slug 목록"으로 고정돼 있어 URL이 갱신되지 않는 문제가 있었습니다. `edited_office_region` 훅에 `flush_rewrite_rules()`를 걸어 term 편집 즉시(드문 admin 액션이라 성능 영향 없음) 반영되도록 했습니다.
- term 삭제 시 term-relationship 정리는 WordPress 코어가 자동으로 처리하고(우리가 손댈 것 없음), Home 캐시 무효화는 이미 3-1/Home V1 라운드의 `home-query.php`가 `delete_office_region`/`edited_office_region`에 걸려 있어 중복으로 추가하지 않았습니다.
- `tests/test-region-sync.php`(7 assertion, 버그 재현 케이스 포함)로 검증했습니다.

## Sprint 02.5 (허브 SEO 스키마) — `schema-hub.php`
전체 빌딩 아카이브(`/사무실임대/`)와 권역·동 허브(`/{parent}/[{child}]/`)에 `CollectionPage` + `ItemList` + `BreadcrumbList` + `FAQPage`를 출력합니다. `schema.php`/`schema-home.php`와 동일한 원칙(Rank Math 감지 시 자동 비활성, `@id` 참조 재사용)을 그대로 따릅니다.

- **ItemList는 실제 메인 쿼리를 그대로 반영**합니다. `global $wp_query; $wp_query->posts`를 직접 읽어 만들고, **`have_posts()`/`the_post()`는 절대 호출하지 않습니다** — `wp_head`는 템플릿의 메인 루프보다 먼저 실행되는데, 여기서 루프 포인터를 건드리면 나중에 화면에 렌더될 실제 목록이 틀어집니다. `numberOfItems`는 페이지네이션 전체 개수(`found_posts`), `itemListElement`는 **현재 페이지에 실제로 보이는 것만** 담습니다.
- **FAQPage는 화면과 100% 같은 소스를 읽습니다**: 권역·동 허브는 `region_faq_q1~5`/`a1~5` ACF term 필드(없으면 아카이브 기본값 폴백), 전체 아카이브는 `ol_default_archive_faqs()`. 화면에 없는 FAQ를 스키마에만 만들어 넣지 않습니다.
- **BreadcrumbList는 화면 `.olx-crumb` 내비게이션과 동일한 경로**를 따릅니다: 홈 → 사무실 임대 → [상위 권역 →] 현재. 이번 라운드에 `taxonomy-office_region.php`의 화면 브레드크럼에도 "홈" 크럼을 추가해(이전엔 없었음) 화면과 스키마가 같은 깊이를 갖도록 맞췄습니다.
- **CollectionPage.description**은 `region_intro` ACF 필드(있으면) 또는 `ol_default_region_intro()`/`ol_default_archive_intro()` 폴백을 씁니다. 이 두 폴백 함수는 이번 라운드에 **Core로 중앙화**했습니다 — 예전엔 테마의 `olt_archive_seo_intro()`/`olt_archive_faqs()`에 문구가 하드코딩돼 있어서, 화면 문구를 고쳐도 스키마엔 반영 안 되는 사고가 날 수 있었습니다. 이제 Core의 `ol_default_archive_intro()`/`ol_default_archive_faqs()`/`ol_default_region_intro()`가 정본이고, 테마 함수는 이걸 우선 호출하고(플러그인 비활성 시엔 자체 사본으로 폴백) 씁니다.
- **FAQPage는 1페이지에서만** 출력합니다(페이지네이션된 URL마다 중복 스키마가 생기지 않도록).
- **제외한 것(명시적 스코프)**: SearchAction, AI Agent 관련 스키마, 화면에 없는 콘텐츠 - 전부 이번 범위 밖.
- `tests/test-schema-hub.php`(25 assertion)로 아카이브/상위권역/동 3가지 컨텍스트 전부 검증했습니다.

## 이번 라운드(Codex 리뷰 반영)에서 고친 것
- **권역 삭제 동기화 버그**: 빌딩의 office_region을 전부 지워도 `wp_get_object_terms()`가 빈 배열을 반환하면 `empty()` 체크로 early-return 하던 걸 제거 — 이제 빈 배열로 `wp_set_object_terms()`가 호출되어 매물 쪽 stale 지역 태그가 실제로 지워짐
- **AIO 자동초안 플래그 버그**: 초안 문장의 sha256 해시(`_ol_aio_draft_hash`)를 같이 저장하고, 다음 저장 시 현재 내용이 그 해시와 다를 때만(=관리자가 진짜 수정) 플래그 해제. 예전엔 "비어있지 않으면" 무조건 검수완료 처리돼서, 미편집 자동생성 문장이 그대로 색인될 위험이 있었음
- **save-api.php 강화**: `get_post_type()` 일치 검증(listing/building 잘못 지정 시 거부) 추가. capability 체크·화이트리스트는 지난 라운드에 이미 적용됨
- **캐시 일괄 재생성**: `cache-rebuild.php` 추가 — `wp officeleasing rebuild-cache` (WP-CLI) 또는 빌딩 목록 화면 안내 배너의 버튼으로 전체 빌딩 캐시를 한 번에 재계산. 플러그인 설치 직후나 매물이 이미 쌓여있는 상태에서 캐시 기능을 추가했을 때 0으로 남는 문제 대응
- **`available_from` → `move_in_type`(즉시입주/협의가능/날짜지정) + `move_in_date`(조건부 노출)**로 교체. save-api 화이트리스트도 동기화

## 아직 안 만든 것 (다음 sprint)
- **JSON-LD (`schema.php`)**: `OfficeBuilding`+`RealEstateListing`+`FAQPage`+`BreadcrumbList`, `seller.identifier`(중개업 등록번호) 전부 미구현. **이 프로젝트 최우선 목표(SEO/GEO/AIO)라 다음 sprint 1순위**
- **AIO 요약 3종 화면 미출력**: `building_transportation_summary`/`building_feature_summary`/`building_recommended_tenant_summary`는 ACF엔 있는데 템플릿·JSON-LD 어디서도 안 씀 (저장만 되는 죽은 데이터). JSON-LD 작업과 같이 배치하는 게 자연스러움
- 필터 UI(`region`/`dong`/`area_min`/`area_max`/`budget_min`/`budget_max`)는 **의도적으로 쿼리 미연결 상태** — 아카이브/허브 스펙에 "이번 단계는 UI만" 명시, 로드맵 ③검색→④필터 단계에서 실제 쿼리 연결 예정
- `listing_feature` taxonomy 기반 특징 태그 UI 연동
- 커스텀 갤러리 메타박스(지금은 번호형 Image 필드로 대체 중)
- 300개 초기 데이터용 CSV 일괄입력 스크립트 (`ol_save_listing_fields()`/`ol_save_building_fields()` 그대로 활용 가능)
