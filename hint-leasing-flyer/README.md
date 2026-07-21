# HINT Leasing Flyer (플러그인)

임대매물 전달용 Leasing Flyer. 발행 시점 조건을 **스냅샷**으로 저장하고 `/listup/` 공개 URL로 공유한다.
officeleasing-core / ACF가 없어도 활성화·동작한다(데이터 접근은 워드프레스 네이티브 메타 API로만).

## Phase 1 구현 범위
- 부트스트랩(`hint-leasing-flyer.php`, `class-hlf-plugin.php`) — core의 plugins_loaded 패턴 참고, ACF 독립.
- CPT: `leasing_flyer`(공개=false, UI=true, REST=true, publicly_queryable=false) +
  `leasing_flyer_item`(비공개) + 커스텀 상태 `hlf_archived`.
- Capability(1-F): administrator/editor/leasing_flyer_staff 부여.
- 메타 스키마(1-B) 전체: `class-hlf-meta-schema.php`가 단일 진실원천.
- 계산(1-C): `class-hlf-calculations.php` — HLF NOC = ((보증금×0.035÷12)+임대료+관리비)/전용평.
  core의 noc_per_exclusive_pyeong(보증금 미포함)과 **완전히 분리**.
- 고유번호(1-D): Flyer = `LF-000123`(post ID 기반), Item = `I0001`(부모 flyer 시퀀스 원자 증가).
  display_order(표시순)와 item_number(불변 URL 식별자) 분리.
- URL(1-E): `/listup/{flyer}/`, `/listup/{flyer}/{item}/` rewrite + template_include 서버 렌더링.
  버전비교 flush(permalinks.php 패턴). draft=권한필요, published=공개, archived=읽기전용.
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
  "실제로 존재하는 attachment 포스트인지"만 확인한다 — 소유권(post_parent가 이 item_id인지)은 검증하지
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

## 후속 단계에서 제외
AI 이미지 적합성 판별, 워터마크 제거/자동 보정, 얼굴·번호판 블러, 이미지 Drag & Drop/크롭 편집기,
이미지 순서 변경(위/아래) UI, officeleasing 원본 이미지 자동 동기화, PDF 생성, 인쇄 밀도별 레이아웃,
10개 제한/재정렬의 동시성·트랜잭션 처리(현재 저사용량 내부 운영 기준으로는 불필요), OCR 캡처 입력
(다음 라운드) — 후속 phase.

## 검증
```
php tests/test-calculations.php
```
그 외 Phase 1/2-1/2-2 REST·리포지토리 회귀 테스트는 실제 워드프레스 설치 없이도 돌아가는 최소 스텁
하네스(`WP_Post`/`WP_Error`/`WP_Query`/`$wpdb` 등을 흉내)로 검증한다(리포지토리에는 포함하지 않음 —
개발 중 검증 전용).
