# HINT Leasing Flyer (플러그인)

임대매물 전달용 Leasing Flyer. 발행 시점 조건을 **스냅샷**으로 저장하고 `/list/` 공개 URL로 공유한다
(예전 `/listup/{flyer}/{item}?/` 매물 URL은 legacy alias로 계속 유효하다 — 이미 공유된 링크를 깨지
않기 위해 새 `/list/` 규칙과 함께 등록만 유지한다, `class-hlf-routes.php`).
officeleasing-core / ACF가 없어도 활성화·동작한다(데이터 접근은 워드프레스 네이티브 메타 API로만).

## Phase 1 구현 범위
- 부트스트랩(`hint-leasing-flyer.php`, `class-hlf-plugin.php`) — core의 plugins_loaded 패턴 참고, ACF 독립.
- CPT: `leasing_flyer`(공개=false, show_ui=false, show_in_rest=false, publicly_queryable=false) +
  `leasing_flyer_item`(비공개) + 커스텀 상태 `hlf_archived`. 기본 워드프레스 관리자 화면/코어 REST는
  쓰지 않는다 — 전부 이 플러그인 전용 REST 네임스페이스(`hlf/v1`, `class-hlf-rest-controller.php`)와
  그 REST를 쓰는 관리자 UI(`class-hlf-admin-ui.php` + `assets/js/admin-listup.js`/`portal.js`)로만 다룬다.
- Capability(1-F): administrator/editor/leasing_flyer_staff 부여.
- 메타 스키마(1-B) 전체: `class-hlf-meta-schema.php`가 단일 진실원천.
- 계산(1-C): `class-hlf-calculations.php` — HLF NOC = ((보증금×0.035÷12)+임대료+관리비)/전용평.
  core의 noc_per_exclusive_pyeong(보증금 미포함)과 **완전히 분리**.
- 고유번호(1-D): Flyer = `LF-000123`(post ID 기반, 옛 형식) 또는 날짜 6자리+일일 순번(신규 형식),
  Item = `I0001`(부모 flyer 시퀀스 원자 증가, 옛 형식) 또는 순수 숫자(신규 형식).
  display_order(표시순)와 item_number(불변 URL 식별자) 분리.
- URL(1-E): `/list/{flyer}/`, `/list/{flyer}/{item}/` rewrite + template_include 서버 렌더링(옛
  `/listup/{flyer}/{item}?/` 규칙은 legacy alias로 함께 유지). 버전비교 flush(permalinks.php 패턴).
  draft=권한필요, published=공개, archived=읽기전용.
- REST(1-G): `hlf/v1` CRUD/reorder/publish. refresh-source는 아직 501 stub, images는 Phase 3에서 구현.

## Phase 2-1 구현 범위 — 관리자 CRUD UI
- `class-hlf-admin-ui.php` + `assets/js/admin-flyer-list.js`/`admin-flyer-edit.js` — 빌드 없이
  `<script>` 태그로 로드되는 REST 기반 관리 화면(Flyer 목록/생성/수정/상태변경, Item 추가/수정/삭제/재정렬).
- 10개 항목 제한, item_number 불변성, display_order 재정렬은 전부 `class-hlf-item-repository.php`가
  서버 측에서 최종 검증(클라이언트 우회 방지).
- archived Flyer는 읽기 전용 — Flyer 수정, Item 추가/수정/삭제/재정렬은 전부 차단(409)하되 상태변경
  자체(archived → draft/published로 되돌리기)와 읽기는 허용한다(`HLF_Flyer_Repository::assert_not_archived()`
  공용 게이트로 중복 없이 구현).

## Phase 2-2 구현 범위 — officeleasing 원본 매물 가져오기(Import)
- `class-hlf-officeleasing-mapper.php`(순수 변환) → `class-hlf-officeleasing-import-service.php`(오케스트레이션)
  → `class-hlf-item-repository.php`(저장) 계층 분리. Mapper는 부작용이 전혀 없다(읽기만).
- `class-hlf-officeleasing-search.php`: listing/building 제목·주소로 검색해 Import 후보를 찾는다
  (officeleasing-core 비수정, WP 표준 쿼리만 사용). 항상 `post_status = publish`만 노출.
- 가져오기 시점의 값을 **스냅샷**으로 복사 저장 — 이후 원본이 바뀌어도 이미 만든 Item은 영향받지
  않는다(Refresh는 후속 단계). 출처 기록(`source_listing_id`/`source_building_id`/`snapshot_created_at`/
  `snapshot_refreshed_at`/`snapshot_version`)은 일반 클라이언트 입력 화이트리스트를 거치지 않는 서버
  전용 setter(`set_snapshot_metadata()`)로만 기록하며, 저장 직후 값을 다시 읽어 검증한다(불일치 시
  방금 만든 Item을 롤백해 고아 데이터를 남기지 않음).
- Import는 대상 listing/연결된 building 모두 `post_status = publish` + `current_user_can('read_post')`인
  경우에만 허용한다(검색을 우회해 draft/private id를 직접 넘기는 경로 차단, 403). officeleasing의
  업무용 `listing_status`(협의중/거래완료 등) ACF 필드는 이 게이트와 무관한 정보성 값이다.

## Phase 3 구현 범위 — 매물 사진(WordPress Media Library)
- 이미지는 외부 검색·다운로드가 아니라 워드프레스 기본 Media Library(`wp.media`)에서 관리자가
  직접 선택한다 — 새로 업로드하거나, 사이트에 이미 있는 미디어를 그대로 골라도 된다.
- 저장은 항상 `class-hlf-item-repository.php::set_images()`/`delete_image()`를 통해서만 한다(REST
  컨트롤러·템플릿에서 `update_post_meta()` 직접 호출 없음). `set_images()`는 선택한 attachment ID가
  "실제로 존재하는 이미지 attachment인지"(`wp_attachment_is_image()`)를 확인하고, 이번 요청에서
  새로 추가되는 ID에 한해 `current_user_can('read_post', $id)`로 읽기 권한도 확인한다(다른 사람의
  비공개 첨부를 임의로 바인딩하는 것을 차단) — 단, 소유권(post_parent가 이 item_id인지)은 검증하지
  않는다(다른 글/다른 매물에 이미 쓰이고 있는 미디어도 선택할 수 있어야 하므로). post_parent를 이
  item_id로 재설정(reparent)하지도 않는다 — 공유 중인 첨부의 소속을 바꾸면 다른 곳에 영향을 줄 수 있다.
- `delete_image()`는 Item의 `exterior_image_id`/`interior_image_ids`에서 뗄 뿐 Attachment 파일 자체는
  절대 삭제하지 않는다(detach-only) — Media Library에서 고른 이미지는 사이트 다른 곳에서도 쓰이고
  있을 수 있다.
- 새 메타 필드는 추가하지 않았다 — 기존에 예약돼 있던 `exterior_image_id`(대표)/`interior_image_ids`
  (나머지)를 그대로 쓴다. 둘 다 `writable_fields()` 밖이라 일반 Item PUT으로는 못 바꾸고, 전용
  setter(`set_images()`)만 쓸 수 있으며 `set_snapshot_metadata()`와 같은 "쓰고 다시 읽어 검증" 패턴이다.
- REST: `PUT /flyers/{id}/items/{item_id}/images`(대표 지정), `DELETE .../images/{attachment_id}`(detach).
- 관리자 UI: Item 편집 폼에 "매물 사진" 섹션 — "사진 선택" 버튼이 `wp.media({multiple:true,
  library:{type:'image'}})` 모달을 열고, 업로드/기존 미디어 선택 둘 다 표준 그대로 지원한다. 대표사진은
  각 썸네일 아래 라디오 버튼으로 표시·변경. 삭제는 이 매물에서만 뗀다(파일은 유지). 이미지 순서 변경
  UI는 아직 없다(대표/나머지 구분만).
- 공개/Print: 대표 이미지가 상세 화면 상단에 크게, 나머지는 썸네일 스트립으로. 목록 화면에도 작은
  대표 썸네일. 이미지가 없으면 아무 마크업도 렌더링하지 않는다(깨진 img 없음). Print는 대표 이미지만
  출력하고 썸네일 스트립은 숨긴다(한 장짜리 인쇄물이 여러 장으로 늘어지지 않도록).
- **알려진 정책(의도된 설계, 버그 아님)**: Item/원본 매물의 텍스트 값(주소·금액 등)은 저장 시점의
  완전한 스냅샷이지만, 사진은 attachment ID만 참조하고 파일을 복제하지 않는다 — 원본 attachment가
  Media Library에서 삭제되거나 다른 파일로 교체되면 이미 발행된 Flyer의 사진도 함께 사라지거나
  바뀐다. 관리자 UI(Item 편집/List Up "전체 매물" 폼의 매물 사진 섹션)에 이 사실을 안내하는 문구를
  넣어 두었다. 완전한 이미지 스냅샷(포함 시점에 파일 자체를 복제)이 필요하면 별도 스토리지 비용/구현
  범위를 감안해 추후 검토한다.
- **업로드 이미지 최적화(`class-hlf-image-pipeline.php`)**: 새 이미지 처리 코드를 짜지 않고 워드프레스
  코어 훅만 조합한다 — `big_image_size_threshold`(900px, EXIF 방향 보정도 이 코어 경로가 함께 처리),
  `wp_editor_set_quality`(JPEG 82%), `image_editor_output_format`(PNG로 올라온 사진의 파생 이미지는
  JPEG로 출력), `wp_handle_upload_prefilter`(이미지 업로드 12MB/10000px 상한 — 900px 자동 리사이즈가
  실제 최적화를 담당하고, 이 상한은 서버 리사이즈 처리 중 메모리를 과도하게 잡아먹는 비정상적으로
  큰 원본만 걸러내는 안전망). 네 필터 전부 `wp.media` 업로더가 `uploader.params.hlf_upload='1'`로
  표시한 요청에만 적용된다(GPT 코드 감사 P0#2) — 이 표시가 없는 업로드(테마 로고, ACF 이미지, 다른
  플러그인, 관리자 계정의 일반 미디어 업로드 등)는 전혀 손대지 않는다. WebP 생성은 Smush Pro 같은
  전용 플러그인의 영역이라 넣지 않았다(이 플러그인은 다른 플러그인에 의존하지 않는다는 기존 원칙과
  같은 이유).
- **Flyer 번호 원자적 발급**: 날짜별 순번을 `wp_options`의 `UNIQUE(option_name)` + `INSERT ... ON
  DUPLICATE KEY UPDATE ... LAST_INSERT_ID(expr)`로 원자적으로 증가시킨다(`HLF_Flyer_Repository::
  next_daily_sequence()`) — `class-hlf-item-repository.php`의 Item 번호 발급과 같은 패턴. 직원이
  거의 동시에 Flyer를 두 번 만들어도(더블클릭, 느린 네트워크 재요청, 여러 PC 동시 사용) 같은 번호가
  나오지 않는다(GPT 코드 감사 P1#8).
- **전체 매물 목록/대시보드 통계 성능**: `source_link_map()`(전체 Item을 훑어 원본↔Flyer 연결을
  계산)은 linked/unlinked 필터가 실제로 걸렸을 때만 쓰고, 기본 목록은 이번 페이지에 뽑힌 source_id
  범위로만 좁힌 `source_link_map_for()`를 쓴다. 대시보드 `stats()`(전체 Source + 전체 Item을 훑는
  계산)는 30초 TTL transient로 캐시하고 매물 포함/해제 시 즉시 무효화한다(GPT 코드 감사 P1#3/#4 —
  매물 수가 늘어도 목록/대시보드 로딩이 그에 비례해 느려지지 않게 한다).

## Phase 4 구현 범위 — NOC 비교차트 · 위치 비교 지도 · 공유 · 라이트박스
공개 화면(list/detail)에 최소한의 순수 표시 JS(`assets/js/public-flyer.js`)를 처음 도입했다 —
데이터 계산·저장 로직은 전혀 갖지 않으며(서버가 이미 계산·정규화한 값을 data-* 속성으로 그대로
받아 그리기만 함), REST 호출도 하지 않는다(공개 화면은 저장 로직이 없다는 원칙 유지).

- **NOC 비교 차트**: `HLF_Calculations`가 계산한 NOC를 막대로 표시. 값 범위에 맞춰 막대 높이를
  적응형으로 스케일(최댓값-최솟값의 1.5배를 표시 범위로 잡아 매물 1개뿐이거나 값 차이가 커도
  찌그러지지 않음). NOC가 0 이하(면적 미입력 등 계산 불가)인 항목은 차트에서 제외.
- **위치 비교 지도(목록) + 개별 지도(상세)**: 카카오 지도 JS SDK. 좌표(`latitude`/`longitude`,
  기존 필드 그대로)가 있는 매물만 마커로 표시하고, 좌표 없는 매물이 있어도 전체 지도는 정상
  동작한다. 매물이 1개면 그 지점으로 센터+확대, 여러 개면 `LatLngBounds`로 전부 보이게 자동 조정.
  마커 번호는 리스트 순번(표시 순서)과 항상 동일 — 좌표 없는 매물 때문에 번호가 당겨지지 않는다.
- **리스트·차트·지도 3자 연동**: `item_number`를 공용 key로 삼아(`ListingSync` 모듈, 리스트 행은
  서버가 `data-hlf-listing-key`로 렌더링, 차트 막대·지도 마커는 JS가 생성 시점에 같은 key로 등록)
  하나에 마우스오버/포커스하면 나머지 둘도 함께 `.is-active`로 강조된다.
- **카카오 키**: `wp-config.php`에 `define('HLF_KAKAO_JS_KEY', '...')` (지도 SDK, 브라우저에 노출되는
  게 정상 — Kakao Maps JS SDK 자체가 도메인 제한 방식의 공개 키다) /
  `define('HLF_KAKAO_REST_API_KEY', '...')` (주소 검색, **서버에서만** 사용)를 정의해야 지도·주소
  검색이 동작한다(Naver 자격증명과 같은 패턴 — 옵션 테이블 방식 채택 안 함). 미설정 시 지도 자리에
  안내 문구만 뜨고 나머지 화면(리스트/차트/공유 등)은 그대로 동작한다.
- **관리자 주소 검색**: Item 편집 폼의 지번주소 옆 "주소 검색" 버튼 → `GET hlf/v1/kakao/address-search`
  (신규, `can_edit_flyers` 권한) → 서버가 카카오 Local API를 대신 호출(`wp_remote_get` +
  `Authorization: KakaoAK ...`)해 도로명주소/좌표를 반환 → 폼에 자동 채움. **REST API 키는 이
  서버 사이드 호출에만 붙고 브라우저로는 절대 전달되지 않는다**(원본 기준 HTML의 클라이언트 직접
  호출 방식과 다른 점 — REST 키는 도메인 제한이 없는 시크릿에 가까워 서버 프록시로 격리했다).
- **공유 링크**: 목록/상세 양쪽에 공유 버튼(Clipboard API, 실패 시 `execCommand('copy')` fallback).
  이미 화면에 있는 공개 URL만 복사하며 내부 ID·REST endpoint는 노출하지 않는다.
- **상세 갤러리 라이트박스**: 썸네일 클릭 → 이전/다음 탐색 가능한 오버레이(키보드 ←/→/Esc 지원).
- **평당단가**: 상세 화면 보증금/임대료/관리비 아래에 공급평당 환산값(기존
  `deposit_per_lease_pyeong`/`rent_per_lease_pyeong`/`maintenance_per_lease_pyeong`, 계산 재사용) 표시.
- **Print**: 공유 버튼·라이트박스는 인쇄에서 숨김. NOC 차트는 화면에 그려진 막대를 그대로 인쇄(가로
  스크롤 없이 한 페이지 폭 안에서 줄바꿈). 지도는 인쇄로 재현하기 어려워 순번-주소 텍스트 목록으로
  대체.

## Phase 4 구현 범위 — 네이버부동산 캡처 OCR(선택 입력 보조)
Item 편집 폼 상단에 "네이버부동산 캡처로 자동 입력" 섹션(`renderOcrSection`/`bindOcrSection`,
`admin-flyer-edit.js`). 실제 OCR 엔진(Tesseract.js, CDN, `kor+eng`)을 client-side에서만 구동하며,
서버 OCR 서비스는 없다(가짜 결과를 만들지 않음 — 실제로 이만큼만 존재).

- 캡처 이미지 선택 → 미리보기 → "텍스트 추출"(grayscale+contrast 전처리 후 Tesseract 인식) →
  결과 원문을 textarea에 표시 → 필드 자동 채움. 원문은 직접 수정 가능하고 "원문에서 항목 채우기"로
  언제든 재분석할 수 있다(원문 다시 분석 요구사항).
- 파싱 로직(`ocrParseLeaseAmounts`/`ocrParseAreas`/`ocrParseFloor`/`ocrParsePropertyTable`/
  `parseOcrText`)은 순수 텍스트 정규화이지 계산이 아니다 — NOC 등 파생 지표는 여전히 서버
  (`HLF_Calculations`)만 계산하며, 여기서는 캡처 텍스트를 필드값 후보로만 바꾼다.
- 매물번호/층/면적/보증금·임대료·관리비/지번주소/방향/입주가능일/총주차대수/사용승인일/
  건축물용도/매물특징만 채운다 — 난방·사무실 수·화장실 수·위반건축물 여부는 Item 스키마에 아예
  없는 필드라(요청서 확인 결과 불필요) 의도적으로 추출하지 않는다.
- 추출/파싱 결과는 항상 "제안값"일 뿐이다 — 폼 필드에만 채워질 뿐 자동 저장되지 않고, 기존 "매물
  추가/수정" 저장 버튼을 직접 눌러야 실제로 반영된다.

## 코드 리뷰 반영(접근성/정리) — v0.4.0-beta.6
- **`uninstall.php`**: `hlf_contact_directory` 옵션과, 날짜별로 하루 하나씩 무기한 쌓이던
  `hlf_flyer_seq_*`(Flyer 번호 원자적 카운터, `HLF_Flyer_Repository::next_daily_sequence()`) 옵션들을
  삭제 시점에 정리한다.
- **전화번호 링크**: `tel:` href에 `esc_attr()` 대신 워드프레스 관례대로 `esc_url()`을 쓴다(`tel`은
  코어의 기본 허용 프로토콜 목록에 포함돼 있어 실제로 잘려나가지 않는다).
- **배지 accent 팔레트**: `hlf_item_accent_color()`(PHP)/`ACCENT_PALETTE`(JS)의 5번째 색을
  `#b8862e`(흰 글자 대비 3.24:1, WCAG AA 미달) → `#936b25`(4.81:1)로 교체.
- **공개 페이지 `<h1>`**: 목록 페이지는 헤더 브랜드(`HINT`), 상세 페이지는 헤더 주소를 `<h1>`로 —
  페이지당 정확히 하나, 스크린리더가 페이지 구조를 파악할 수 있게 한다.
- **라이트박스**: `role="dialog" aria-modal="true"` + 열 때 닫기 버튼으로 포커스 이동, 닫을 때 원래
  트리거로 포커스 복원, Tab이 라이트박스 밖으로 새지 않는 최소 focus trap.
- **지도 마커**: 클릭 가능한 마커(카카오 `CustomOverlay`라 `<a>`/`<button>`으로 바꿀 수 없음)에
  `role="button"`/`tabindex="0"`/`aria-label` + Enter·Space 키보드 실행 지원.

## 요청서 반영 — v0.4.0-beta.7 ~ beta.8
- **지도 마커 번호**: 상세페이지·인쇄물 지도 모두 매물의 실제 순번(`order`)을 쓰도록 수정(이전엔
  항상 "01"로 하드코딩돼 있었음).
- **리스트페이지 인쇄 지도**: 인쇄 시작 시 `display:none`이던 매물별 지도 컨테이너를 실제 크기가
  잡히도록 잠깐 `visibility:hidden`으로 프라이밍하고, `.hlf-detail-hero--map-only`(사진 없는 매물)
  전용 CSS 규칙이 뒤에 오는 동일 특이도 규칙에 덮이던 캐스케이드 문제도 함께 해결.
- **갤러리 카드**: 하단 썸네일을 슬라이드/스크롤 없이 4장이면 4등분(25%씩) 고정 폭으로 표시.
- **갤러리 호버 스왑**: 페이지 로드 시 전체 갤러리 사진을 미리 브라우저 캐시에 올려(preload),
  실제 네트워크 지연 환경에서 마우스가 짧게 스쳐 지나가면 사진이 못 바뀌는 것처럼 보이는 문제를
  완화.
- **목록 버튼**: 상세페이지 "← 목록" 버튼을 전화번호 버튼과 같은 둥근 사각형 스타일로 통일.
- **모바일 인쇄 방향**: 인쇄 시작 직전 프린트용 지도 컨테이너를 곧바로 원상복구하던 처리를
  제거(인쇄 종료 후에만 정리) — 인쇄 시작 순간의 급격한 레이아웃 변화가 일부 모바일 브라우저의
  인쇄 방향(세로/가로) 자동판단을 흔드는 것으로 보여 이를 없앰.
- **지도 중심 이동 버튼**: 상세페이지 Location 제목 옆에 지도를 매물 위치로 다시 맞추는 버튼 추가.
- **사진별 블러(워터마크) on/off**: 매물 사진 관리 화면(임대안내문 개별 매물 편집·전체 매물
  등록/편집)에서 사진마다 "블러 처리" 체크박스로 HINT 워터마크 표시 여부를 정할 수 있다. 기본값은
  꺼짐(체크 안 함 = 블러 없음). 이 값은 첨부파일(attachment) 메타(`_hlf_photo_blur`)로 저장되며,
  워드프레스 코어 REST(`/wp/v2/media/{id}`)를 통해 갱신된다.
- **리스트페이지 모바일 "위치 확인" 지도**: 모바일 화면 폭에서만 기본보다 2단계 더 줌아웃해서
  표시(상세페이지 지도는 영향 없음).

## 요청서 반영 — v0.4.0-beta.9
- **인쇄 여백 회귀**: beta.8까지 사이 늘어난 요소들(목록/전화 버튼 재배치, 지도 중심 이동 버튼,
  사진별 블러 체크박스 등) 때문에 매물 상세 인쇄가 실측 기준 1페이지 예산을 약 12px 넘어서서
  거의 빈 2번째 페이지가 한 장 더 붙는 회귀가 있었다. 갤러리/지도 인쇄 세로 높이(85mm)를 76mm로
  다시 줄여 여유를 확보(리스트 인쇄물의 매물별 상세 블록도 같은 CSS 클래스를 그대로 쓰므로 함께
  해결됨).

## 요청서 반영 — v0.4.0-beta.10
- **사진 삭제 버그(실사용 확인)**: 4장 상한이 생기기 전부터 이미 6장씩 등록돼 있던 매물은, 사진을
  한 장씩 삭제해도 남는 개수(6→5, 5→4)가 여전히 4장을 넘는다는 이유로 매번 저장이 거부되어 사진을
  하나도 뗄 수 없었다. `set_images()`(Item/원본 매물 공용)의 상한 검사를 "원래 갖고 있던 개수보다
  늘어나는 경우만" 거부하도록 고쳐, 기존 매물은 4장까지 점진적으로 줄일 수 있게 하고 신규/이미
  4장인 매물은 여전히 4장에서 막는다. (참고: 갤러리 호버 스왑이 "어떤 매물은 되고 어떤 매물은 안
  된다"고 하셨던 것도 이 레거시 6장 매물에서 갤러리 레이아웃(4등분 고정폭 가정)이 어긋나 생긴
  증상일 가능성이 높다 — 이번 수정으로 4장 이하로 정리하면 함께 해결된다.)
- **NOC 비교차트 단위 표기**: "단위:만원/전용면적(평)" 텍스트를 제목 옆(상단)에서 차트 하단
  밑줄 아래, 우측 정렬로 이동.
- **리스트페이지 위치 확인 지도 줌아웃**: 데스크톱 화면에서도 기본보다 1단계 줌아웃(모바일은 기존
  2단계 유지).

## 요청서 반영 — v0.4.0-beta.11
- **캐시 자동 비우기**: 매물/임대안내문을 저장·삭제해도 공개 화면에 바로 반영되지 않아 매번
  워드프레스 관리자 화면에서 수동으로 캐시를 지워야 했던 문제 — 흔한 캐시 플러그인(LiteSpeed
  Cache, WP Super Cache, W3 Total Cache, WP Rocket, WP Fastest Cache, Cache Enabler, SG
  Optimizer, WP Engine)의 "전체 캐시 비우기" API가 설치돼 있으면 자동으로 호출하도록
  `HLF_Cache_Purge`를 추가했다(설치돼 있지 않은 플러그인의 함수는 안전하게 건너뛴다). 저장이
  postmeta만 바꾸는 경우(예: 사진 목록 저장)까지 빠짐없이 잡도록 `save_post`뿐 아니라
  `updated_post_meta`/`deleted_post` 등 postmeta 훅에도 걸었고, 한 요청 안에서 여러 번 저장돼도
  캐시 비우기는 요청 종료 시점에 한 번만 실행된다.

## 요청서 반영 — v0.4.0-beta.12 (갤러리 호버 스왑 실제 원인 발견)
- **갤러리 호버 스왑이 "어떤 매물은 되고 어떤 매물은 안 되는" 진짜 원인을 찾았다**: 실사용 중인
  두 매물의 실제 HTML을 직접 비교해 확인함 — 대표 사진의 원본 해상도가 `hlf-item-photo`(960×640,
  하드 크롭) 기준보다 작으면, 워드프레스 코어가 그 크기를 만들지 못하고 대신 medium/medium_large 등
  이미 있는 다른 사이즈들로 `srcset`/`sizes`를 자동으로 채워 넣는다. 이 `srcset`이 있는 상태에서
  JS가 `src`만 바꾸면, 브라우저는 `src`가 아니라 여전히(이전 사진의) `srcset` 후보 중에서 그림을
  골라 그린다 — 그래서 `src` 속성값은 바뀌는데 화면은 그대로였다. 사진 해상도가 960px 이상이라
  `srcset`이 안 붙는 매물은 정상으로 보였고, 그렇지 않은 매물만 이 버그를 겪었다. 이제 호버 시
  `srcset`/`sizes`를 함께 지우고(그래야 브라우저가 지정한 `src`를 그대로 쓴다), 대표 사진으로
  되돌아갈 때 원래 값을 복원한다. 실제 이미지 파일로 재현 → 수정 확인함(Playwright,
  `img.currentSrc` 비교).

## 요청서 반영 — v0.4.0-beta.13
- **모바일 라이트박스 크기**: 갤러리 사진을 클릭해 확대했을 때, 확대창 폭 계산이 자기 자신의
  좌우 padding(24px×2)을 빼지 않고 있었다 — 화면이 좁을수록(특히 모바일) 실제로 남는 공간보다
  커져 오른쪽으로 삐져나갔다. 항상 실제 남는 공간 안에 들어오도록 고쳤고, 모바일에서는 확대
  크기를 갤러리 카드 폭 정도로 더 좁게 제한했다.
- **리스트페이지 지도 줌 조정 방어 코드**: 데스크톱 줌아웃 조정(beta.10)이 `getLevel()`이 예상치
  못한 값을 돌려주는 경우에도 지도를 깨뜨리지 않도록 방어 코드를 추가했다.

## 요청서 반영 — v0.4.0-beta.14
- **리스트페이지 인쇄 시 3~6페이지 공백/헤더·푸터 중복 (실사용 버그)**: 1~6번 섹션을 전부 체크해
  인쇄하면 매물 상세 페이지들이 완전히 빈 페이지로 나오거나, 마지막 페이지에 문의처 푸터가 두 번
  찍히는 문제. 원인 두 가지를 실제 PDF(Playwright `page.pdf()` + `pdftoppm` 시각 확인, `emulateMedia`
  만으로는 화면 상태를 잘못 재는 착시가 있어 반드시 진짜 PDF로 확인)로 추적해 찾았다:
  1) `public.css`의 `.hlf-print-item-detail`용 지도 프리로딩 트릭 클래스(`.hlf-print-priming`,
     `visibility:hidden`)가 `@media` 범위 없이 선언돼 있어 실제 인쇄 시점에도 계속 적용되고 있었다 —
     이 클래스는 `afterprint`에야 정리되므로(다른 회귀 방지 목적으로 일부러 그렇게 둔 것) 인쇄
     스냅샷을 찍는 순간엔 항상 걸려 있어, 선택된 매물 상세가 공간은 차지하되 안 보이는 상태로
     찍혔다. `@media screen`으로 범위를 좁혀 인쇄에는 영향을 주지 않게 고쳤다.
  2) 매물 상세 블록(`print-item-detail.php`)은 자체 문의처 푸터를 이미 담고 있는데, 문서 맨 끝의
     전역 푸터(`public-flyer-list.php`, `.hlf-shell` 바로 아래)가 항상 같이 찍혀 매물 상세가 인쇄에
     하나라도 포함되면(항상 문서 맨 마지막에 옴) 그 뒤에 같은 푸터가 한 번 더 붙었다. 인쇄 확정
     시점에 포함된 섹션 중 매물 상세가 있으면 전역 푸터를 숨기도록 JS가 body 클래스를 붙인다
     (`assets/js/public-flyer.js` bindPrintButton, `print.css`).
- **모바일 갤러리 라이트박스 비활성화**: 모바일 화면에서는 사진을 눌러도 확대(라이트박스)하지 않고,
  마우스 호버와 같은 대표 사진 전환만 탭으로 하도록 바꿨다(확대해도 크기 차이가 거의 없어 불필요).
- **NOC 비교 차트 막대 모바일 탭**: 모바일에서 차트 막대를 탭하면 상세 페이지로 이동하지 않고
  지도 위치만 그 매물로 이동하도록 바꿨다(데스크톱 클릭은 기존과 동일하게 이동).
- **리스트 순번 배지 축소(모바일)**: 지번주소가 배지 크기 때문에 한 줄에 다 안 들어가고 다음 줄로
  넘어가는 문제 — 배지 크기/간격을 줄여 지번 숫자 2자리 정도 여유를 더 확보했다.
- **상세페이지 지도 재중심 버튼 문구**: "⊙ 중심 이동" → "← 매물 위치".
- **상세페이지 좌측 상단 "← 목록" 제거, "HINT" 헤더 링크로 대체**: 리스트페이지 헤더와 통일된
  형태로, 눌렀을 때 목록으로 이동한다.
- **Leasing Info 카드 스타일 변경**: 보증금/임대료/관리비/환산임대료가 각각 네모 박스 안에 들어가
  있던 것을, 리스트페이지와 같은 세로 구분선 스타일로 통일했다(데스크톱/모바일 공통).
- **라벨 변경**: 리스트페이지 "층" → "층수", 상세페이지 Property Details의 "해당층" → "기준층"
  (인쇄물도 동일하게 변경).
- **원본 매물 수정 자동 반영**: 임대안내문에 이미 포함된 매물의 원본 정보를 수정하면, 빼서 다시
  넣지 않아도 그 즉시 포함된 항목에 반영된다(사진은 제외 — 사진 설정은 각 임대안내문에서 독립
  적으로 관리). 보관된(archived) 임대안내문에 포함된 항목은 기존 읽기 전용 정책에 따라 자동 반영
  대상에서 제외된다.
- **관리자 화면 "포함매물관리"에 보기 버튼 추가**: 현재 임대안내문에 포함된 매물이면 실제 공개
  상세 페이지를 새 탭으로 바로 볼 수 있는 "보기" 버튼을 작업 열에 추가했다.

## RC 안정화(외부 감사 GPT/Codex 교차검증 반영) — v0.4.0-beta.18

외부 감사 리포트의 지적을 코드로 하나씩 재현·검증해, **실제로 확인된 것만** 수정했다.

### P0 — 날짜별 Flyer 연번이 첫 발급에서 오염되던 버그(실사용 확인)
`wp_options.option_id`는 AUTO_INCREMENT다. 기존 코드는
`INSERT ... VALUES(%s,'1','no') ON DUPLICATE KEY UPDATE option_value = LAST_INSERT_ID(option_value+1)`
한 문장만 실행하고 `SELECT LAST_INSERT_ID()`를 읽었는데, **그 날짜의 첫 요청**은 중복키가 없어
UPDATE 분기(=`LAST_INSERT_ID(expr)`)를 타지 않으므로 MySQL이 `LAST_INSERT_ID()`를 "새로 만들어진
option_id"로 설정한다. 그 결과 날짜별 첫 Flyer 번호가 `26072401`이 아니라 `2607245516`처럼
발급되고(5516 = 새 wp_options 행의 option_id), 다음 Flyer는 저장값 1에서 이어져 `02`로 건너뛰어
`01`이 영영 비었다 — 실제 운영에서 관측된 번호와 정확히 일치한다.

수정: (1) `INSERT IGNORE`로 시드 행(0)만 만들고 그 문의 LAST_INSERT_ID는 읽지 않는다, (2) 증가는
항상 `UPDATE ... LAST_INSERT_ID(expr)` 경로로만 처리한다(UPDATE는 AUTO_INCREMENT 컬럼을 건드리지
않으므로 항상 우리가 넣은 값이 돌아온다). 원자성(행 잠금)과 기존 날짜 카운터 하위호환은 그대로다.
아울러 SQL 실패·1 미만 값·번호 메타 저장 실패를 전부 `WP_Error`로 올리고, 실패 시 방금 만든
Flyer를 정리한다(fail-closed — 번호 없는 Flyer가 남으면 공개 URL로 영영 열 수 없다).
회귀 테스트(`test-flyer-seq-bug.php`)는 MySQL 시맨틱을 모델링해 옛 코드의 오염을 재현하고 새
코드가 항상 `01`부터 연속 발급함을 검증한다. **기존 하네스가 이 버그를 놓친 이유도 함께 수정했다** —
가짜 wpdb가 옛 SQL을 "항상 1부터 증가"하도록 실제 MySQL과 다르게 모델링하고 있었다.

### P1 — 매물 저장마다 사이트 전체 오브젝트 캐시를 비우던 문제
`purge_all()`이 `wp_cache_flush()`로 이 플러그인과 무관한 **사이트 전체** 오브젝트 캐시(다른
플러그인·테마·트랜지언트 포함)까지 날려, 매물을 저장할 때마다 사이트 전체가 캐시 미스로 떨어지고
있었다. 오브젝트 캐시는 워드프레스 코어가 이미 정확히 무효화하므로(`clean_post_cache()`, 메타
캐시, `WP_Query`의 last_changed) 제거했다. 실제로 풀어야 할 "공개 페이지 HTML이 캐시돼 수정이
반영되지 않는" 문제는 페이지 캐시 플러그인 API 호출로 그대로 해결된다.

### P1 — 그 외 확인되어 수정한 항목
- **삭제 시 캐시 무효화 누락 가능성**: `deleted_post`는 포스트 행이 이미 지워진 뒤라 `get_post_type()`이
  `false`가 될 수 있어 우리 CPT인지 판별하지 못한다 — 포스트가 아직 살아 있는 `before_delete_post`를
  함께 건다.
- **대시보드 통계 캐시 무효화 불완전**: 예전엔 포함/해제에서만 무효화해 원본 생성·삭제와 Flyer 삭제
  cascade가 최대 TTL만큼 묵었다. 통계를 바꾸는 모든 경로에서 무효화하도록 완성하고, 그 위에서 TTL을
  30초 → 5분으로 올려 대시보드를 열 때마다 전체를 다시 훑던 비용을 줄였다.
- **업로드 원본 상한 900px → 1920px**: 갤러리 대표 사이즈 `hlf-item-photo`가 960×640 하드크롭인데
  원본을 900px로 줄이면 워드프레스는 확대하지 않으므로 그 크롭을 **아예 만들 수 없었다** — 코어가
  다른 사이즈로 srcset을 채우고, 그 srcset 때문에 브라우저가 src 교체를 무시해 "호버해도 사진이 안
  바뀐다"는 실사용 버그(beta.12에서 증상만 막았던)의 근본 원인이었다.
- **공개 상세 갤러리 preload가 초기 로딩과 경쟁**: 페이지를 열자마자 갤러리 큰 사진을 전부 받던 것을
  첫 화면 렌더 완료 후(`load` → `requestIdleCallback`)로 미뤘다. 호버 예열 효과는 그대로 유지되고,
  데이터 절약 모드/2G 회선에서는 아예 건너뛴다.
- **OCR worker 누수 · CDN 재시도 불가**: `terminate()`가 성공 경로에만 있어 전처리·인식 실패 시
  WASM worker가 계속 살아남았다(반복 실패 시 누적). 성공/실패 모두 정리하고, terminate 실패가 원래
  오류를 덮지 않게 했다. CDN 로드 실패 promise가 영구 캐시돼 새로고침 전엔 재시도조차 못 하던 것도
  정리해 다시 시도할 수 있게 했다.
- **uninstall SQL LIKE 이스케이프**: `hlf_flyer_seq_%`의 `_`가 SQL 와일드카드라 무관한 옵션까지
  지울 수 있었다 — `esc_like()` + `prepare()`로 정확한 접두사만 지우고, 통계 transient도 함께 정리한다.

### 검토했으나 이번에 바꾸지 않은 지적(근거)
- **Snapshot vs 자동 동기화 정책(A/B/C안)**: 코드 오류가 아니라 **제품 정책 결정**이다. 현재는
  "활성 Flyer는 자동 동기화, 보관 Flyer는 고정"(C안)이고 이는 사용자가 직접 요청한 동작이다
  (v0.4.0-beta.14). 임의로 바꾸면 실제 업무 흐름이 깨지므로 그대로 두고, 문서 표현만 이 동작에
  맞춰 유지한다. A/B안으로 바꿀지는 사용자 결정 사항.
- **portal.js / admin-listup.js 공통화, REST Controller 분리**: 유지보수성 개선이지 현재 동작
  오류가 아니다. 대규모 리팩터링을 이번 안정화 커밋에 섞으면 회귀 위험이 급증하므로 분리한다.
- **로그인 rate limit 자체 구현**: 포털은 `wp_signon()`을 그대로 쓰므로 보안 플러그인/WAF의
  `authenticate` 훅이 이미 적용된다. 중복 limiter를 급조하기보다 운영 환경에서 실제 적용 여부를
  확인하는 편이 안전하다(운영 검증 항목).
- **linked/unlinked 필터의 전체 Item 스캔**: 현재 데이터 규모에서는 체감 문제가 없고, 해결하려면
  역정규화 카운터나 관계 테이블 도입이 필요해 마이그레이션 위험이 크다 — 규모가 커지면 착수.

## 보안·효율·안정성 정밀 감사 — v0.4.0-beta.17
GPT/Codex/Claude 교차검증 관점으로 전 파일(REST 컨트롤러·저장소·포털·라우트·이미지 파이프라인·
캐시 퍼지·메타 스키마·공개 템플릿)을 정밀 감사했다. **핵심 보안·안전 골격은 이미 견고**함을
코드로 재확인했고(REST capability/nonce 일관, 첨부 IDOR 차단 read_post 검증, fail-closed 공개
라우팅, 네이티브 인증 포털, 출력 이스케이프, 화이트리스트+타입 sanitize 단일 경계, 원자적 카운터,
orphan cleanup, 보관 가드, N+1 제거 배치 쿼리), 그 위에 저위험 하드닝만 추가로 적용했다:
- **REST 자유텍스트 인자 길이 상한**: `/source-listings`의 `search`(200)/`contact`(100),
  `/officeleasing/listings`의 `search`(200)에 `maxLength`를 추가했다 — 비정상적으로 긴 입력을 WP
  코어의 args 검증 단계에서 400으로 거른다(기존 카카오 주소검색 `q`의 maxLength·`per_page` maximum과
  같은 방어 패턴). 정상적인 주소·건물명·담당자 검색은 이 길이를 넘지 않으므로 실사용에 영향이 없다.
- **`HLF_Meta_Schema::to_bool()` 데드코드 정리**: 참으로 인정하는 문자열 목록에 `'true'`가 중복돼
  있던 것을 제거했다(동작 변화 없음).
감사 결과 나머지 영역은 이미 잘 되어 있어 손대지 않았다 — 지어낸 변경은 오히려 회귀 위험이기
때문이다. 관찰 사항 1건(캐시 자동 퍼지가 `wp_cache_flush()`로 사이트 전체 오브젝트 캐시를 비우는
점)은, 제거하면 오브젝트 캐시에 페이지를 담는 환경(Batcache/WP.com류)에서 "저장 후 수동 캐시
비우기" 버그가 재발할 수 있어 의도적으로 그대로 두었다(페이지 캐시 플러그인별 함수는 별도로 모두
호출한다).

## 요청서 반영 — v0.4.0-beta.15/beta.16
- **NOC 비교 차트 막대 순위 표시**: 막대(또는 연동된 리스트 행·지도 마커)에 마우스를 올리면 막대
  실제 높이 바로 위에 전체 매물 중 몇 위인지("1st"/"2nd"/...)를 보여준다. 순위는 화면에 보이는 막대
  순서가 아니라 NOC 값 기준으로 따로 매긴다.
- **인쇄 시 남아있던 마우스 오버 효과 제거(실사용 버그)**: 인쇄 버튼을 누르기 전에 리스트 행/NOC
  막대/지도 마커 중 하나에라도 마우스가 지나갔으면, 그 하이라이트(리스트 베이지 줄, 막대·마커
  강조)와 지도 이동(panTo)이 인쇄 스냅샷에도 그대로 남아 지도가 한쪽으로 치우쳐 찍히는 문제가
  있었다. 인쇄 직전(자체 인쇄 버튼 흐름과 브라우저 자체 단축키/Ctrl+P 경로 둘 다) 하이라이트를
  전부 해제하고 지도는 항상 전체 매물이 보이도록 다시 맞춘다.
- **OCR 추출 개선(매물등록화면)**:
  - 사용승인일: 라벨 주변 잡음을 무시하고 연/월/일 숫자만 뽑아 항상 "2003.06.10" 형식으로
    재조합한다. 날짜를 못 찾으면 억지로 채우지 않고 비워 둔다.
  - 방향/건축물용도를 드롭다운으로 바꿔 오타 없이 정해진 값만 고를 수 있게 했다(옛 데이터나 OCR이
    뽑은 세부 값이 목록에 없어도 조용히 사라지지 않도록 그 값을 선택지에 그대로 추가한다). 건축물
    용도 추출 목록에 "오피스텔"도 추가했다.
  - 입주가능일은 실제 특정 날짜도 입력해야 해서 자유 입력은 그대로 두고, "즉시입주"/"빠른협의"
    문구만 자동완성 제안(datalist)으로 추가했다.
  - 지하층 오인식 수정: "B1"이 OCR에서 "81"/"61"처럼 숫자로 잘못 인식되는 경우, 실제 건물에
    60/80층대가 있을 수 없으므로 2자리 이상 숫자의 맨 앞이 6이나 8이면 B로 되돌린다.
  - 공급/전용면적은 소수점 둘째 자리까지만 인식한다 — 원본 표기 자체가 둘째 자리가 최대라, 셋째
    자리 이상이 OCR에 잡히면 그건 오인식 잡음이다. 반올림하면 그 잡음이 둘째 자리 숫자까지 바꿔
    버리므로(예: "132.256" -> 반올림 시 132.26, 실제로는 132.25) 반올림이 아니라 애초에 둘째 자리
    까지만 숫자로 인식하고 그 뒤는 버린다(평→㎡ 변환도 반올림 대신 자르기로 통일).

## 후속 단계에서 제외
AI 이미지 적합성 판별, 워터마크 제거/자동 보정, 얼굴·번호판 블러, 이미지 Drag & Drop/크롭 편집기,
이미지 순서 변경(위/아래) UI, officeleasing 원본 이미지 자동 동기화, PDF 생성, 인쇄 밀도별 레이아웃,
10개 제한/재정렬의 동시성·트랜잭션 처리(현재 저사용량 내부 운영 기준으로는 불필요) — 후속 phase.

## 검증
```
php tests/test-calculations.php
```
그 외 Phase 1/2-1/2-2 REST·리포지토리 회귀 테스트는 실제 워드프레스 설치 없이도 돌아가는 최소 스텁
하네스(`WP_Post`/`WP_Error`/`WP_Query`/`$wpdb` 등을 흉내)로 검증한다(리포지토리에는 포함하지 않음 —
개발 중 검증 전용).
