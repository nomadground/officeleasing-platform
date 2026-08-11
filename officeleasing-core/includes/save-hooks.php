<?php
// 저장 시점에 "무엇을 어떤 순서로 실행할지"만 담당하는 오케스트레이션 레이어.
// 실제 계산은 calculations.php, 지역 동기화는 region-sync.php에 위임한다.
// plain save_post_{type} 훅을 쓰는 이유: wp-admin ACF 화면이든 향후 커스텀 관리자 페이지의
// wp_insert_post()/wp_update_post()든 저장 경로와 무관하게 항상 실행되어야 하기 때문.
// update_field()는 save_post를 재호출하지 않으므로 무한루프 가드가 필요 없다.
if (!defined('ABSPATH')) {
    exit;
}

add_action('save_post_listing', 'ol_handle_listing_save', 20);
function ol_handle_listing_save($post_id) {
    if (!ol_is_real_save($post_id, 'listing')) {
        return;
    }
    ol_calculate_listing_fields($post_id);
    ol_sync_region_from_building($post_id);

    // building 집계 캐시 갱신. 매물이 다른 빌딩으로 재배정된 경우 이전 빌딩도 함께 갱신한다.
    $building_id = (int) get_field('related_building', $post_id);
    $prev_building_id = (int) get_post_meta($post_id, '_ol_prev_building', true);
    if ($building_id) {
        ol_recount_building_cache($building_id);
        update_post_meta($post_id, '_ol_prev_building', $building_id);
    }
    if ($prev_building_id && $prev_building_id !== $building_id) {
        ol_recount_building_cache($prev_building_id);
    }
}

add_action('save_post_building', 'ol_handle_building_save', 20);
function ol_handle_building_save($post_id) {
    if (!ol_is_real_save($post_id, 'building')) {
        return;
    }
    ol_calculate_building_fields($post_id);
    ol_cascade_region_to_listings($post_id);
}
