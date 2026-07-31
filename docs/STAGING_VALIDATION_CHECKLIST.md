# Staging Validation Checklist — officeleasing-core + GeneratePress Child + Leasing Flyer

## 목적

`main`에 들어간 세 구성요소(`officeleasing-core`, `officeleasing-generatepress-child`, `hint-leasing-flyer`)가
**실제 WordPress 환경에서 함께** 정상 작동하는지 확인하기 위한 체크리스트와 절차 문서다.

**이 문서 자체는 코드가 아니다.** 이 브랜치(`test/wordpress-staging-baseline`)는 신규 기능 개발이나
리팩터링을 하지 않는다 — 오직 실 WordPress 스테이징 환경에서 사람이 따라 할 검증 절차만 정의한다.
지금까지 이 프로젝트의 모든 코드는 실제 WordPress 없는 샌드박스에서 `php -l` / 오프라인 테스트로만
검증됐으므로, 이 문서가 처음으로 실환경 동작을 확인하는 단계다.

---

## 1. 설치·활성화 순서

세 구성요소의 실제 의존 관계를 코드에서 직접 확인한 결과는 다음과 같다.

| 구성요소 | 실제 의존 관계 |
|---|---|
| `officeleasing-core` | `officeleasing-core.php` 헤더에 `Requires Plugins: advanced-custom-fields` 명시 — **ACF(무료) 없이는 워드프레스가 활성화 자체를 막는다**(WP 6.5+ 기능). |
| `officeleasing-generatepress-child` | `style.css` 헤더에 `Template: generatepress` — **GeneratePress 부모 테마가 `wp-content/themes/`에 설치돼 있어야** 자식 테마를 활성화할 수 있다(부모 테마 자체를 "활성" 상태로 둘 필요는 없음, 자식 테마 활성화가 곧 전환). |
| `hint-leasing-flyer` | 플러그인 헤더에 명시: **"officeleasing-core에 의존하지 않고 단독 동작한다."** 데이터 접근은 전부 `get_post_meta()`/`register_post_meta()` 네이티브 함수만 쓴다. 단, `class-hlf-officeleasing-mapper.php`의 "officeleasing에서 가져오기" 기능만 `get_field()`로 building/listing ACF 데이터를 **읽어온다** — 이 한 기능만 officeleasing-core + ACF가 활성 상태여야 정상 동작한다. 실제 코드 확인 결과, 없을 경우 빈 값으로 조용히 매핑되는 게 아니라 `function_exists('get_field') && post_type_exists('listing') && post_type_exists('building')` 가용성 체크를 통과하지 못하면 `new WP_Error('hlf_core_unavailable', ...)`를 즉시 반환한다(fatal은 아니지만 명시적 에러 반환이지 "빈 값 매핑"이 아님). |

**권장 설치·활성화 순서:**

1. **ACF(무료)** 플러그인 설치·활성화
2. **officeleasing-core** 플러그인 업로드·활성화
   - 활성화 순간 `building`/`listing` CPT, `office_region` taxonomy 등록 + 권역 term 시딩 + `flush_rewrite_rules()` 실행
3. **GeneratePress**(부모) 테마 설치 (활성화는 다음 단계에서 자동 전환됨)
4. **officeleasing-generatepress-child**(자식) 테마 업로드·활성화
5. **hint-leasing-flyer** 플러그인 업로드·활성화 (순서 무관 — 위 4단계 전에 먼저 활성화해도 무방. 단, "officeleasing에서 가져오기" 기능을 테스트하려면 2번이 먼저 끝나 있어야 함)
6. (선택) Rank Math 등 SEO 플러그인 — 있으면 `schema-home.php`/`schema.php`/`schema-hub.php`가 자동으로 스키마 출력을 비활성화한다(§6-10 참고)

---

## 2. 필수 플러그인·버전 의존성

| 항목 | 요구사항 | 근거 |
|---|---|---|
| ACF | 무료판, 최신 버전 권장 | `officeleasing-core.php`의 `Requires Plugins` 헤더. Repeater/Gallery/Options Page 등 Pro 전용 기능은 **일절 사용하지 않음**(무료 제약 대응은 `README-ACF.md` "무료 ACF 제약 대응" 참고) |
| WordPress | 6.5 이상 권장 | `Requires Plugins` 헤더 자체가 WP 6.5부터 지원되는 기능. 6.5 미만이면 이 헤더가 무시되고 ACF 없이도 활성화가 될 수 있어 별도 확인 필요 |
| PHP | **8.0 이상** | `hint-leasing-flyer.php` 헤더에 `Requires PHP: 8.0` 명시(union return type을 실제로 사용하므로 8.0 미만에서는 파싱 자체가 실패). officeleasing-core/테마는 별도 하한 명시가 없으나 동일 서버에서 돌아가므로 사실상 이 값이 전체 최소 기준 |
| GeneratePress | 무료판으로 충분 | GenerateBlocks 등 프리미엄 애드온 불필요(이 프로젝트는 GeneratePress 네이티브 + 커스텀 템플릿만 사용) |
| Rank Math | 선택 | 있으면 스키마 중복 방지 로직이 자동으로 우리 쪽 출력을 끈다. 없어도 fatal 없음(§6-10) |
| Smush Pro | **이번 단계 미포함** | 유료 구독 플러그인이라 도입 여부는 별도 승인 필요. 이번 검증은 무료 도구만 사용(§7) |

---

## 3. ACF JSON 자동 로드 경로 확인

`officeleasing-core/includes/acf-json.php`가 `acf/settings/save_json`/`acf/settings/load_json` 필터로
`officeleasing-core/acf-json/`를 필드그룹 원본 경로로 등록한다.

**확인 절차:**
1. ACF 활성화 후 워드프레스 관리자 → 사용자 정의 필드 → 필드 그룹 화면 진입
2. `group_ol_building`, `group_ol_listing`, `group_ol_region` 3개 필드그룹이 **자동으로 목록에 나타나는지** 확인(수동 임포트 불필요해야 정상)
3. 목록에서 "동기화 가능(Sync available)" 배지가 보일 수 있음 — DB에 필드그룹 레코드가 아직 없고 JSON만 있는 상태라 나오는 정상 표시(`README-ACF.md` 참고). building/listing 글쓰기 화면에 필드가 실제로 보이면(다음 단계) 정상 작동 중인 것이므로 배지 자체는 문제 아님
4. `building`/`listing` 글쓰기 화면에서 ACF 필드가 실제로 렌더되는지 확인
5. (선택) `hint-leasing-flyer/acf-json/`도 존재하지만 현재 비어있음(README만 있음, Phase 3/4 예정) — 필드그룹이 안 나타나는 게 **정상**

---

## 4. rewrite flush 필요 시점

`officeleasing-core`와 `hint-leasing-flyer` 두 플러그인은 "버전 옵션 비교 → 최초 1회만 자동 flush" 패턴을
쓴다. **`officeleasing-generatepress-child` 테마에는 rewrite flush 로직 자체가 없다**(코드 확인:
`grep -rn "flush_rewrite_rules" officeleasing-generatepress-child` 결과 0건) — 테마는 rewrite 규칙을
직접 등록하지 않으므로 flush도 필요 없다. 즉 **정상적인 신규 설치·활성화라면(두 플러그인 기준)
수동 flush가 필요 없어야 한다.** 다만 아래 시점에서는 확인이 필요하다.

| 트리거 | 위치 | 자동 flush 여부 |
|---|---|---|
| `officeleasing-core` 최초 활성화 | `officeleasing-core.php` 활성화 훅 | 자동 (`flush_rewrite_rules()` 즉시 호출) |
| `officeleasing-core` 배포 후 코드만 갱신(재활성화 없이) | `includes/permalinks.php`의 `OL_PERMALINKS_VERSION` 옵션 비교 | 자동 (버전 상수를 올렸을 때만, `init` 훅에서 1회) — **현재 버전은 1**. 향후 rewrite 로직을 또 바꾸면 이 상수를 올려야 자동 flush가 걸림 |
| `office_region` term의 이름/슬러그/부모를 관리자가 직접 수정 | `includes/region-sync.php`의 `edited_office_region` 훅 | 자동 (수정 즉시 flush) — 드문 관리자 액션이라 매 요청 비용 문제 없음 |
| `hint-leasing-flyer` 최초 활성화 | `hint-leasing-flyer.php` 활성화 훅 | 자동 |
| `hint-leasing-flyer` 배포 후 코드만 갱신 | `includes/class-hlf-routes.php`의 `HLF_REWRITE_VERSION` 옵션 비교(Core의 permalinks.php와 동일 패턴) | 자동 — **현재 버전은 3** |
| 위 자동 flush가 실패했거나 URL이 404로 뜨는 경우 | 설정 → 고유주소 → 저장(재저장만 해도 강제 flush) | 수동 — 문제 발생 시의 안전망 |

**확인 절차:** 신규 설치 직후 별도로 "설정 → 고유주소" 저장 없이 바로 `/사무실임대/`, `/강남사무실임대/`,
`/list/` 등 커스텀 URL이 뜨는지 먼저 시도한다. 안 뜨면 고유주소 재저장 후 재시도하고, 재시도로 해결됐다면
자동 flush 로직에 실제 결함이 있다는 뜻이므로 이슈로 기록한다.

---

## 5. Building/Listing 샘플 데이터 최소 세트

권역 시딩은 `officeleasing-core` 활성화 시 자동으로 이뤄진다(강남/도심권/여의도/기타권역 4개 부모 +
14개 자식 term). 아래는 그 위에 **수동으로 입력해야 할 최소 검증 데이터**다.

| 목적 | 최소 데이터 |
|---|---|
| 한글 계층형 URL(부모만) | 빌딩 A: `office_region` = 부모 term만 배정(자식 없음) |
| 한글 계층형 URL(부모+자식) | 빌딩 B: `office_region` = 부모 + 자식 term 둘 다 배정 |
| 활성 매물 0건 케이스 | 빌딩 C: listing 없음 (또는 전부 `leased`/`expired` 상태) |
| 활성 매물 1건 케이스(Hero 병합 렌더) | 빌딩 D: `available` 상태 listing 1개 |
| 활성 매물 N건 케이스(카드 그리드) | 빌딩 E: `available`/`reserved`/`contract_pending` 섞어서 listing 3개 이상 |
| Listing → Building 301 | 위 D 또는 E의 listing 개별 URL 직접 접근 |
| 홈 카드 슬라이더(권역당 8개) | 같은 권역에 활성 매물 있는 빌딩 5개 이상(5개 이상이어야 슬라이더 화살표가 나타남, §6 참고) |
| 지역 다양성 로직 확인 | 같은 부모 권역, 서로 다른 자식(동) term에 최소 2곳 이상 분산 배치 |
| 카카오맵 | 최소 1개 빌딩에 유효한 위도/경도(주소 검색 위젯으로 입력) |
| 이미지/카드 비율 비교(§9) | **최소 10개 빌딩**, 외관 사진 세로형/가로형 혼합해서 업로드 |
| Flyer 가져오기 기능 | 위 빌딩 중 1곳의 listing을 Flyer "officeleasing에서 가져오기"로 스냅샷 생성 |

---

## 6. 기능별 검증 절차

각 항목은 "무엇을 어떻게 확인하는가"만 정의한다 — 실행은 실제 스테이징 사이트에서 사람이 한다.

### 6-1. Building/Listing 등록·저장
- 관리자에서 빌딩/매물 각각 신규 작성 → 저장 → 재편집 화면에서 입력값이 그대로 남아있는지 확인
- 계산 필드(연면적 평 환산, 임대료 원 환산, 평당가 등)가 저장 즉시 자동 채워지는지 확인(`admin-summary-box.php`의 "계산값 요약" 박스에서 확인 가능)
- 주소 검색 위젯(카카오 Geocoder)으로 도로명/지번/위도/경도 4개 필드가 한 번에 채워지는지 확인 — 이 4개 필드는 직접 타이핑이 막혀 있어야 정상(회색 readonly)

### 6-2. Listing → Building 301
- listing 개별 URL(`/listing/{slug}/`)에 직접 접근 → 연결된 building URL로 301 리다이렉트되는지 브라우저 개발자도구 Network 탭에서 상태 코드 확인

### 6-3. 한글 계층형 URL
- 부모만 배정된 빌딩(§5 빌딩 A) → `/{부모슬러그}/{빌딩슬러그}/` 형태로 뜨는지
- 부모+자식 배정된 빌딩(§5 빌딩 B) → `/{부모슬러그}/{자식슬러그}/{빌딩슬러그}/` 형태로 뜨는지
- 권역 허브(부모 term) → `/{부모슬러그}/`
- 동 허브(자식 term) → `/{부모슬러그}/{자식슬러그}/`
- 예전 `/building/{slug}/` 폴백 URL로 접근했을 때 위 정식 URL로 301 되는지(워드프레스 코어 `redirect_canonical()`이 처리 — 별도 커스텀 리다이렉트 코드 없음, `README-ACF.md` "URL Foundation" 참고)

### 6-4. 메인페이지 카드 / 권역·동 허브 / 빌딩 상세
- 홈(`front-page.php`)에서 4개 권역 섹션이 전부 뜨는지, 각 권역 카드가 `building_active_listing_count > 0`인 빌딩만 보여주는지
- 활성 매물 없는 빌딩(§5 빌딩 C)이 카드 목록에서 **제외**되는지
- 권역·동 허브 페이지에서 building-card.php가 정상 렌더되는지, 빈 권역일 때 Empty State 문구가 뜨는지
- 빌딩 상세에서 활성 매물 0/1/N 케이스 3가지가 각각 §5의 C/D/E로 정확히 분기되는지(Hero 병합 vs 카드 그리드 vs "임대가능 매물 없음" 안내)

### 6-5. 카카오맵
- 빌딩 상세 페이지의 지도가 실제 렌더되는지(빈 회색 박스로 남으면 카카오 디벨로퍼스 콘솔의 Web 플랫폼 도메인 등록 누락 가능성 — 과거 이슈 이력 있음, `README-ACF.md`/이전 세션 기록 참고)
- 관리자 편집화면의 "위치 찾기" 주소 검색 위젯도 별도로 동작하는지(같은 SDK를 쓰지만 다른 화면)

### 6-6. 모바일 2열 · 데스크톱 4열
- 홈 권역 슬라이더: 데스크톱 폭에서 카드 정확히 4개가 한 화면에 보이는지, 5개 이상일 때만 좌우 화살표가 뜨는지
- 모바일 폭(320/360/390/430px 각각)에서 카드가 정확히 2개씩 보이는지, 텍스트/가격/뱃지가 서로 겹치지 않는지
- 카드 5개 미만일 때 화살표 없이 정적으로만 표시되는지, 4개 이하일 때 빈 공간을 억지로 채우지 않는지

### 6-7. Hero 모바일·데스크톱 이미지 분기
- 빌딩 상세 Hero 이미지: 브라우저 개발자도구에서 뷰포트를 900px 기준으로 오가며 실제로 다른 파일(`ol-hero-mobile` vs `ol-hero-desktop`)이 로드되는지 Network 탭에서 확인
- Hero 이미지에 `fetchpriority="high"`가 붙어있고 `loading="lazy"` 속성이 **없는지**(LCP 이미지이므로) 확인

### 6-8. 갤러리 썸네일 전환
- 빌딩 상세에서 썸네일 1~4번을 각각 클릭 → 메인 Hero 이미지가 클릭한 사진으로 바뀌는지
- 클릭 후 표시되는 이미지가 `ol-interior-large`(900×600) 파생인지 Network 탭에서 파일명으로 확인
- 모바일 폭(900px 이하)에서도 동일하게 전환되는지(이 부분이 이전에 실제로 깨졌던 버그였으므로 반드시 확인)

### 6-9. Rank Math와 JSON-LD 스키마 중복
- Rank Math **비활성** 상태: 홈/빌딩 상세/아카이브·허브 페이지 소스보기에서 `<script type="application/ld+json">` 블록이 각각 1세트씩만 있는지(Core가 출력하는 것)
- Rank Math **활성화 후 재확인**: 같은 페이지들에서 Core 쪽 JSON-LD 블록(`<!-- OfficeLeasing ... Schema -->` 주석)이 **사라지는지**(자동 비활성화 로직 확인, `ol_seo_plugin_outputs_schema()`) — Rank Math 자체 스키마만 남아야 정상
- 페이지 소스에서 `@id` 값이 실제로 유효한 URL 형태인지, 같은 `@id`가 두 번 중복 정의되지 않는지

### 6-10. Flyer 출력·인쇄
- Flyer 관리자 화면에서 신규 Flyer 생성 → item 추가(수동 입력 + "officeleasing에서 가져오기" 양쪽 다) 확인
- 공개 URL(`/list/...`)에서 발행된 Flyer가 정상 출력되는지
- 인쇄 미리보기(`templates/public/partials/print-item-detail.php` 경로) 레이아웃이 깨지지 않는지
- "officeleasing에서 가져오기" 기능은 officeleasing-core+ACF 활성 상태에서 실제 building/listing 데이터가 정확히 매핑되는지(§1 매핑 근거 표 참고 — `building_address_road`, `deposit_manwon` 등 특정 필드명 대조)

---

## 7. 이미지 파생 크기 재생성 절차

`add_image_size()`는 **신규 업로드 이미지부터만** 파생 이미지를 생성한다. 기존에 이미 올라간 미디어에는
`ol-building-thumb` 등 새 사이즈의 파일이 없으므로, 재생성 전에는 워드프레스가 원본이나 다른 크기로
폴백해 기대한 전송량 절감이 나오지 않는다.

**이번 단계에서 쓸 도구(무료만):**
- **WP-CLI**: `wp media regenerate --yes` (전체 재생성) 또는 특정 첨부 ID만: `wp media regenerate <ID> --yes`
- 또는 **Regenerate Thumbnails**(무료 플러그인) — WP-CLI 접근이 없는 호스팅 환경 대비

**Smush Pro는 이번 단계에 포함하지 않는다** — 유료 구독 플러그인이라 결제/도입 여부는 별도 승인이 필요하다.
필요성이 실제로 확인되면(예: 무료 도구만으로는 WebP/AVIF 변환이 안 돼 파일 용량이 가이드라인 목표치를
못 맞추는 경우) 그때 별도로 검토한다.

**재생성 확인 대상 5종:**
- `ol-building-thumb` (480×640)
- `ol-interior` (600×400)
- `ol-interior-large` (900×600)
- `ol-hero-desktop` (1600×800)
- `ol-hero-mobile` (768×600)

`ol-map-thumb`(240×180)은 **현재 코드 어디에서도 실사용되지 않으므로**(카카오맵은 정적 이미지가 아니라
JS 캔버스), 재생성 여부만 확인하고 실제 화면 반영 여부는 확인 대상에서 제외한다.

**확인 절차:**
1. 재생성 명령 실행 전, 미디어 라이브러리에서 기존 첨부 하나를 선택 → "첨부파일 세부정보"에서 사용 가능한
   이미지 크기 목록에 위 5종이 있는지 확인(없으면 재생성 필요 확정)
2. 재생성 명령 실행
3. 같은 첨부파일에서 다시 확인 → 5종 전부 생성됐는지, 각 크기의 실제 치수가 등록값과 일치하는지
4. 빌딩 상세/카드 화면에서 브라우저 Network 탭으로 실제 전송되는 파일이 새 파생 이미지인지(원본 파일명이
   아닌지) 최종 확인

---

## 8. WP_DEBUG_LOG 확인 항목

스테이징 `wp-config.php`에 아래 설정 후 각 페이지를 한 번씩 방문하며 `wp-content/debug.log`를 확인한다.

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false ); // 화면에는 안 띄우고 로그에만
```

**찾아야 할 오류 유형:**
- `PHP Fatal error` — 플러그인/테마 어디서든 하나라도 있으면 즉시 blocking
- `PHP Warning: Undefined array key` / `Undefined variable` — 특히 ACF 필드가 비어있는 신규 빌딩/매물에서 발생 여부(빈 값 방어 로직 누락 가능성)
- `Call to undefined function` — Core 비활성/ACF 비활성 상태에서 테마 페이지 접근 시 발생하면 안 됨(`olt_core_active()` 가드 확인, §6-4와 별개로 **Core를 일부러 꺼둔 상태**에서도 한 번 테스트 권장)
- ACF 관련 notice(`acf_get_field` 등에서 나오는 필드 미존재 경고) — acf-json 로드 실패 의심
- `hlf_` 프리픽스가 붙은 에러 — Leasing Flyer 쪽 문제
- 카카오맵 SDK 관련 JS 콘솔 에러(PHP 로그는 아니지만 브라우저 콘솔도 같이 확인) — 도메인 미등록 시 발생했던 전례 있음

**기록 방식:** 발견된 각 오류는 (1) 재현 경로, (2) 로그 원문, (3) 관련 파일/함수 추정 을 묶어서 별도로
정리한다. 이번 브랜치에서는 **오류를 고치지 않고 기록만** 한다(요청서 "실환경 오류만 고치는 것이 좋아"와
달리, 이번 문서 작성 단계에서는 기록까지만 — 실제 수정은 이 체크리스트로 검증을 마친 뒤 별도 브랜치/커밋으로 진행).

---

## 9. 카드 이미지 비율 비교 자료 (결정 아님 — 비교 자료 준비 절차만)

### 배경
현재 상태:
```
이미지 파일: ol-building-thumb 480×640 (세로형, hard crop)
화면 박스:   .olx-card-img { aspect-ratio: 1.35 } (가로형)
표시 방식:   object-fit: cover
```
세로로 크롭된 원본을 가로 박스에 다시 잘라 넣는 상태라, 실제 빌딩 외관 사진에 따라 좌우 정보 손실이 클 수 있다.

### 이 결정을 Claude Code가 내리지 않는 이유
카드 비율은 실제 업로드될 빌딩 외관 사진의 실제 종횡비(고층 빌딩 저상부 촬영 사진은 세로가 많고, 로비/외부
광각 사진은 가로가 많은 경향)를 실물로 보고 판단해야 하는 디자인 정책 결정이다. 코드 관점에서는 두 대안 다
구현 가능하고 각자 트레이드오프가 있으므로, 여기서는 **비교 자료를 준비하는 절차만** 정의한다.

### 대안 A — 세로 카드
- 이미지 480×640 그대로, 카드 비율 3:4
- 장점: 고층 빌딩 외관에 자연스러움, 애초 의도했던 작은 세로형 빌딩 사진 컨셉과 일치
- 단점: 목록 카드 세로 길이가 길어져 한 화면에 보이는 카드 수(스크롤 없이)가 줄어듦

### 대안 B — 가로 카드 유지
- 이미지 600×450 또는 600×400, 카드 비율 4:3 또는 3:2
- 장점: 내부사진·외관사진 혼용이 쉬움, 현재 UI(.olx-card-img 1.35)에 가장 가까움
- 단점: 고층 빌딩 외관 사진이 좌우로 많이 잘릴 수 있음

### 비교 자료 준비 절차 (스테이징 접근 가능한 사람이 수행)
1. §5에서 정의한 최소 10개 빌딩에 **실제 외관 사진**(세로형·가로형 혼합) 업로드
2. 대안 A: `.olx-card-img`의 `aspect-ratio`를 `0.75`(3:4)로 임시 변경한 스테이징 사본에서 홈/아카이브 카드 그리드 스크린샷
3. 대안 B: 현재 상태(`aspect-ratio:1.35`) 그대로 동일 10개 빌딩 카드 그리드 스크린샷
4. 두 스크린샷을 나란히 비교해 실제로 어느 쪽이 빌딩별 정보 손실이 적은지 판단
5. **어느 쪽으로 갈지는 이 단계에서 결정하지 않는다** — 비교 스크린샷과 장단점만 정리해 보고한다

### 이 문서를 작성한 세션에서 실행하지 못한 것
이 작업은 **실제 WordPress 사이트 + 실제 업로드된 빌딩 사진**이 있어야 가능하다. 이 세션(코드 작성 환경)에는
워드프레스 실행 환경도, 실제 빌딩 사진 데이터도 없어 스크린샷 자체를 생성할 수 없었다. 위 절차대로
스테이징 사이트에서 직접 수행해야 한다 — 실사진 없이 만든 임의의 목업 이미지로 비교본을 대신하면
"실제로 어느 쪽이 나은가"라는 이 절차의 목적 자체가 무의미해지므로, 여기서는 절차 문서만 남긴다.

---

## 10. 이번 브랜치 범위 확인

이 브랜치(`test/wordpress-staging-baseline`)에서 변경된 파일은 이 문서 하나뿐이다. `officeleasing-core`,
`officeleasing-generatepress-child`, `hint-leasing-flyer`의 실제 코드는 **한 줄도 수정하지 않았다** —
`git diff main..test/wordpress-staging-baseline --stat`로 확인 가능.

## 다음 단계
이 체크리스트로 실 스테이징 검증을 마친 뒤:
1. §8에서 기록된 실환경 오류 수정 (별도 브랜치)
2. §7 이미지 재생성 실행
3. §9 카드 비율 결정 (실사진 비교 후)
4. 이후 Sprint A(For Lease MVP 완성) → Sprint B(운영 관리자 기능) → Sprint C(콘텐츠·SEO 허브) → Sprint D(AI Agent) 순서로 진행
