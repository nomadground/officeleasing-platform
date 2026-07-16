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

## Phase 1에서 제외(후속 단계)
OCR 폼, 이미지 업로드/워터마크 파이프라인, NOC 차트·비교지도, 인쇄 레이아웃,
officeleasing 원본 검색·연동(mapper 실제 구현) — Phase 2~6.

## 검증
```
php tests/test-calculations.php
```
