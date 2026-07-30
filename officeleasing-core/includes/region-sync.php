<?php
// building <-> listing 간 office_region 동기화. 관리자가 listing에서 직접 입력하지 않는 값이며
// (taxonomies.php에서 listing 화면의 office_region 메타박스를 숨김), 이 파일이 유일한 쓰기 경로다.
if (!defined('ABSPATH')) {
    exit;
}

// building 저장 시: 연결된 listing에 building의 지역을 복사 (검색용 비정규화)
// [버그 수정] 빌딩 권역이 비어있어도 early-return 하지 않는다. 빈 배열로 set 하면
// 매물의 옛 권역이 제거되어 stale 데이터가 남지 않는다(권역 "제거" 케이스 대응).
function ol_sync_region_from_building($listing_id) {
    $building_id = get_field('related_building', $listing_id);
    if (!$building_id) {
        return;
    }
    $terms = wp_get_object_terms($building_id, 'office_region', ['fields' => 'ids']);
    if (is_wp_error($terms)) {
        return;
    }
    wp_set_object_terms($listing_id, $terms ?: [], 'office_region', false);
}

// building의 office_region이 바뀌면 하위 listing 전부 재동기화 (역방향 케이스)
// posts_per_page=-1이지만 대상은 "이 빌딩 하나"에 연결된 매물뿐이라 실무상 N은 작다(수십 건).
// no_found_rows로 불필요한 페이지네이션 카운트 쿼리만 제거한다.
//
// [Sprint 01.5 3-7 버그 수정] ol_sync_region_from_building()은 "빌딩의 권역이 비어도 early-return
// 하지 않는다"로 이미 고쳐졌는데(위 함수 참고), 이 역방향 함수는 그 수정이 반영되지 않은 채 남아있었다:
// `empty($terms)`일 때 그대로 return 해버려서, 빌딩의 권역을 전부 지운 뒤 저장해도 이미 연결된
// listing들에는 예전 권역 term이 그대로 남는(stale) 실제 버그였다. 위 함수와 동일한 기준으로 맞춘다.
function ol_cascade_region_to_listings($building_id) {
    $listings = get_posts([
        'post_type' => 'listing',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'meta_query' => [[
            'key' => 'related_building',
            'value' => $building_id,
        ]],
    ]);
    if (empty($listings)) {
        return;
    }
    $terms = wp_get_object_terms($building_id, 'office_region', ['fields' => 'ids']);
    if (is_wp_error($terms)) {
        return;
    }
    foreach ($listings as $listing_id) {
        wp_set_object_terms($listing_id, $terms ?: [], 'office_region', false);
    }
}

// office_region term이 삭제되면 WordPress 코어가 해당 term과의 관계(wp_term_relationships)를
// 알아서 정리한다(우리가 따로 손댈 게 없음) - 이건 그대로 두고, Home 권역별 결과 캐시만 무효화하면
// 된다. 그 무효화는 이미 home-query.php의 delete_office_region/edited_office_region 훅이 담당한다
// (URL Foundation 라운드에서 함께 처리됨) - 여기서 중복으로 걸지 않는다.

// [Sprint 01.5 3-7] term의 slug/부모가 admin 화면에서 바뀌면(권역 이름 수정, 세부지역 재배정 등)
// permalinks.php의 커스텀 rewrite rule은 "마지막 flush 시점의 slug 목록"으로 고정돼 있어 그대로
// 방치하면 이전 slug로 진입한 URL이 깨지거나(새 URL 미반영), 부모-자식 조합이 갱신되지 않는다.
// term 편집은 관리자가 어쩌다 한 번 하는 드문 작업이라 즉시 flush해도 성능에 영향이 없다 -
// (요청마다 도는 코드가 아니라 이 훅이 발화하는 순간에만 1회 실행된다).
add_action('edited_office_region', 'ol_flush_rewrite_rules_on_region_edit');
function ol_flush_rewrite_rules_on_region_edit() {
    flush_rewrite_rules();
}
