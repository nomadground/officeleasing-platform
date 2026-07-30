<?php
// 워드프레스가 "플러그인 삭제"를 실행할 때만 로드된다 (비활성화와 다름).
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// 플러그인이 만든 옵션만 정리한다. building/listing 포스트와 ACF 메타데이터는 삭제하지 않는다 -
// 플러그인을 지웠다고 매물 데이터가 함께 사라지면 안 되기 때문.
delete_option('ol_office_region_seeded');
