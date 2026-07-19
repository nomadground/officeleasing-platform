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
- REST(1-G): `hlf/v1` CRUD/reorder/publish. refresh-source·images는 Phase 2(501 stub).

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

## 후속 단계에서 제외
OCR 폼, 이미지 업로드/워터마크 파이프라인, NOC 차트·비교지도, 인쇄 레이아웃, Refresh from Source,
검색 지역(region) 필터, 10개 제한/재정렬의 동시성·트랜잭션 처리(현재 저사용량 내부 운영 기준으로는
불필요) — 후속 phase.

## 검증
```
php tests/test-calculations.php
```
그 외 Phase 1/2-1/2-2 REST·리포지토리 회귀 테스트는 실제 워드프레스 설치 없이도 돌아가는 최소 스텁
하네스(`WP_Post`/`WP_Error`/`WP_Query`/`$wpdb` 등을 흉내)로 검증한다(리포지토리에는 포함하지 않음 —
개발 중 검증 전용).
