<?php
// building 목록/허브 카드가 카드마다 소속 매물을 재쿼리하지 않도록,
// 활성 매물 집계값을 building의 캐시 필드에 미리 계산해 저장한다(쓰기 시점 1회 비용, 읽기는 항상 빠름).
// 갱신 트리거: 매물 저장/상태변경, 휴지통 이동, 복원, 영구삭제.
if (!defined('ABSPATH')) {
    exit;
}

// 카드/집계에 포함하는 "공개 활성" 매물 상태
function ol_active_listing_statuses() {
    return ['available', 'reserved', 'contract_pending'];
}

/**
 * 특정 building의 캐시 집계값 4개를 실제 매물에서 재계산해 저장한다.
 * $exclude_listing_id: 삭제 직전(post가 아직 publish인 시점)에 그 매물을 집계에서 빼기 위한 예외.
 */
function ol_recount_building_cache($building_id, $exclude_listing_id = 0) {
    $building_id = (int) $building_id;
    if (!$building_id || get_post_type($building_id) !== 'building') {
        return;
    }

    $args = [
        'post_type'      => 'listing',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_query'     => [
            ['key' => 'related_building', 'value' => $building_id],
            ['key' => 'listing_status', 'value' => ol_active_listing_statuses(), 'compare' => 'IN'],
        ],
    ];
    if ($exclude_listing_id) {
        $args['post__not_in'] = [(int) $exclude_listing_id];
    }
    $listing_ids = get_posts($args);

    $count = count($listing_ids);
    $min_area = null;
    $max_area = null;
    $min_rent = null;
    $last_verified = 0;

    foreach ($listing_ids as $lid) {
        $area = (float) get_field('exclusive_area_pyeong', $lid);
        $rent = (float) get_field('monthly_rent', $lid);
        if ($area > 0) {
            $min_area = ($min_area === null) ? $area : min($min_area, $area);
            $max_area = ($max_area === null) ? $area : max($max_area, $area);
        }
        if ($rent > 0) {
            $min_rent = ($min_rent === null) ? $rent : min($min_rent, $rent);
        }
        // verified_at은 ACF date_picker return_format="Ymd" (예: "20260716") 이므로
        // 정수로 캐스팅해 그대로 최댓값 비교가 가능하다. 비어있으면 0이 되어 자동 제외.
        $verified = (int) get_field('verified_at', $lid);
        if ($verified > $last_verified) {
            $last_verified = $verified;
        }
    }

    update_field('building_active_listing_count', $count, $building_id);
    update_field('building_min_exclusive_area_pyeong', $min_area ?? 0, $building_id);
    update_field('building_max_exclusive_area_pyeong', $max_area ?? 0, $building_id);
    update_field('building_min_rent', $min_rent ?? 0, $building_id);
    update_field('building_last_verified_at', $last_verified, $building_id);
}

// 저장 시: save-hooks.php의 ol_handle_listing_save가 계산 마지막에 호출한다(별도 add_action 없음).
// 아래는 삭제/휴지통/복원 경로만 담당한다.

add_action('trashed_post', 'ol_cache_on_listing_trash');
function ol_cache_on_listing_trash($post_id) {
    if (get_post_type($post_id) !== 'listing') {
        return;
    }
    // trashed_post는 상태가 이미 'trash'로 바뀐 뒤 실행되므로 publish 필터로 자동 제외된다.
    $building_id = (int) get_field('related_building', $post_id);
    if ($building_id) {
        ol_recount_building_cache($building_id);
    }
}

add_action('untrashed_post', 'ol_cache_on_listing_untrash');
function ol_cache_on_listing_untrash($post_id) {
    if (get_post_type($post_id) !== 'listing') {
        return;
    }
    $building_id = (int) get_field('related_building', $post_id);
    if ($building_id) {
        ol_recount_building_cache($building_id);
    }
}

add_action('before_delete_post', 'ol_cache_on_listing_delete');
function ol_cache_on_listing_delete($post_id) {
    if (get_post_type($post_id) !== 'listing') {
        return;
    }
    // 영구삭제 직전엔 post가 아직 존재(트래시 비우기면 이미 제외 상태)하므로 명시적으로 제외한다.
    $building_id = (int) get_field('related_building', $post_id);
    if ($building_id) {
        ol_recount_building_cache($building_id, $post_id);
    }
}
