<?php
// 미래 커스텀 관리자페이지가 써야 하는 공식 저장 진입점.
//
// wp_insert_post()로 글을 만든 뒤 update_field()를 한 번씩 호출하고 끝내면,
// save_post_listing/save_post_building이 wp_insert_post() 시점에 이미 실행되어 지나가버려서
// 그 뒤에 채운 필드값이 계산·동기화에 반영되지 않는다 (계산이 빈 값 기준으로 도는 문제).
//
// 이 함수를 쓰면 "필드 세팅 -> 계산/동기화 강제 재실행"이 한 번에 묶여서 이 문제가 원천 차단된다.
// 사용법: $post_id = wp_insert_post([...]); ol_save_listing_fields($post_id, ['exclusive_area_sqm' => 1081.0, ...]);
//
// [보안] 이 함수는 관리자 컨텍스트 전용이다. capability 체크 + 쓰기 가능 필드 화이트리스트로
// 자동계산/내부 필드가 임의로 덮어써지는 것을 막는다. AJAX/REST 엔드포인트에서 이 함수를 부를 때는
// 호출 경계에서 반드시 nonce(wp_verify_nonce) 검증을 별도로 수행해야 한다 - nonce는 요청 컨텍스트가
// 있어야 검증 가능하므로 여기(순수 저장 헬퍼)가 아니라 호출부에서 처리한다.
if (!defined('ABSPATH')) {
    exit;
}

// listing에서 관리자가 직접 쓸 수 있는 필드(자동계산·동기화 필드는 제외)
function ol_listing_writable_fields() {
    return [
        'related_building', 'listing_status',
        'move_in_type', 'move_in_date',
        'verified_at', 'expires_at', 'leased_at',
        // ㎡가 원본 입력이다(pyeong은 자동계산 - ol_calculate_listing_fields()가 채움) -
        // building_total_area_sqm 등 빌딩 면적과 동일한 방향으로 통일했다.
        'floor_display', 'exclusive_area_sqm', 'lease_area_sqm',
        'deposit_manwon', 'monthly_rent_manwon', 'maintenance_fee_manwon',
        'listing_image_1', 'listing_image_2', 'listing_image_3',
        'listing_image_4', 'listing_image_5', 'listing_image_6',
        'listing_note',
        'listing_key_point_1', 'listing_key_point_2', 'listing_key_point_3',
    ];
}

// building에서 관리자가 직접 쓸 수 있는 필드(좌표/주소는 위치선택기로만, 캐시/평환산은 자동)
function ol_building_writable_fields() {
    $fields = [
        'building_address_road', 'building_address_jibun', 'building_lat', 'building_lng',
        'building_completion_date', 'building_basement_floors', 'building_ground_floors',
        'building_total_area_sqm', 'building_standard_floor_area_sqm',
        'building_exclusive_ratio', 'building_parking', 'building_orientation',
        // [listing-detail-ux-pass3] 임대정보 섹션에 새로 노출한 건물 속성(단순 텍스트 - 방향/주차와
        // 같은 성격이라 이 화이트리스트에도 나란히 둔다).
        'building_usage_type', 'building_hvac_type',
        'building_elevator_count',
        'building_subway1_line', 'building_subway1_station',
        'building_subway2_line', 'building_subway2_station',
        'building_nearby_infra',
    ];
    for ($i = 1; $i <= 8; $i++) {
        $fields[] = 'building_image_' . $i;
    }
    for ($i = 1; $i <= 5; $i++) {
        $fields[] = 'building_faq_q' . $i;
        $fields[] = 'building_faq_a' . $i;
    }
    return $fields;
}

// 화이트리스트에 있는 필드만 남기고, 나머지는 조용히 버린다.
function ol_filter_writable_fields(array $field_values, array $whitelist) {
    return array_intersect_key($field_values, array_flip($whitelist));
}

function ol_save_listing_fields($post_id, array $field_values) {
    if (get_post_type($post_id) !== 'listing') {
        return new WP_Error('ol_wrong_post_type', '대상 글이 매물(listing)이 아닙니다.');
    }
    if (!current_user_can('edit_post', $post_id)) {
        return new WP_Error('ol_forbidden', '이 매물을 수정할 권한이 없습니다.');
    }
    $field_values = ol_filter_writable_fields($field_values, ol_listing_writable_fields());
    foreach ($field_values as $field_name => $value) {
        update_field($field_name, $value, $post_id);
    }
    ol_handle_listing_save($post_id);
    return true;
}

function ol_save_building_fields($post_id, array $field_values) {
    if (get_post_type($post_id) !== 'building') {
        return new WP_Error('ol_wrong_post_type', '대상 글이 빌딩(building)이 아닙니다.');
    }
    if (!current_user_can('edit_post', $post_id)) {
        return new WP_Error('ol_forbidden', '이 빌딩을 수정할 권한이 없습니다.');
    }
    $field_values = ol_filter_writable_fields($field_values, ol_building_writable_fields());
    foreach ($field_values as $field_name => $value) {
        update_field($field_name, $value, $post_id);
    }
    ol_handle_building_save($post_id);
    return true;
}
