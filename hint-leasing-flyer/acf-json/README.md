# acf-json (선택적 · Phase 3/4 예정)

이 폴더는 ACF가 활성화된 환경에서 관리자 편집 UI를 얹기 위한 필드그룹 원본 경로다.
`HLF_Plugin::maybe_add_acf_json_path()`가 `acf/settings/load_json` 필터로 이 경로를 등록한다.

Phase 1 원칙:
- 필드의 **단일 진실원천은 `includes/class-hlf-meta-schema.php`**(register_post_meta + 네이티브
  get_post_meta/update_post_meta)다. 플러그인은 ACF 없이도 동작해야 하므로 읽기/쓰기는 절대
  `get_field()`에 의존하지 않는다.
- 관리자 등록 UI는 Phase 3/4에서 구현하며, 그때 이 폴더에 `leasing_flyer_item` 필드그룹 JSON을
  추가한다(meta_key = 스키마 필드명으로 두어 네이티브 경로와 완전히 일치시킨다).
- 따라서 지금은 렌더되지 않는 죽은 필드그룹을 미리 넣지 않는다(오해 방지).
