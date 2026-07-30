<?php
// building/listing 관리자 목록 화면에 상태·연결·가격 컬럼과 필터를 추가한다.
// office_region 지역 필터는 taxonomy가 두 CPT에 등록돼 있으면 워드프레스가 자동으로 목록 상단에
// 드롭다운을 노출하므로 별도 구현이 필요 없다.
if (!defined('ABSPATH')) {
    exit;
}

function ol_listing_status_map() {
    return [
        'available' => ['임대가능', '#2f8f5b'],
        'reserved' => ['협의중', '#b17d00'],
        'contract_pending' => ['계약진행중', '#b17d00'],
        'leased' => ['거래완료', '#84919a'],
        'temporarily_hidden' => ['노출중지', '#c0392b'],
        'expired' => ['만료', '#84919a'],
    ];
}

// ---------------------------------------------------------------
// LISTING 목록
// ---------------------------------------------------------------
add_filter('manage_listing_posts_columns', function ($columns) {
    $new = [];
    foreach ($columns as $key => $label) {
        $new[$key] = $label;
        if ($key === 'title') {
            $new['ol_status'] = '상태';
            $new['ol_building'] = '연결빌딩';
            $new['ol_area'] = '전용/공급(평)';
            $new['ol_price'] = '보증금/임대료';
        }
    }
    return $new;
});

add_action('manage_listing_posts_custom_column', function ($column, $post_id) {
    switch ($column) {
        case 'ol_status':
            $status = get_field('listing_status', $post_id);
            $map = ol_listing_status_map();
            [$label, $color] = $map[$status] ?? [(string) $status, '#333'];
            printf('<span style="color:%s;font-weight:700">%s</span>', esc_attr($color), esc_html($label));
            break;
        case 'ol_building':
            $building_id = get_field('related_building', $post_id);
            echo $building_id ? esc_html(get_the_title($building_id)) : '&mdash;';
            break;
        case 'ol_area':
            printf(
                '%s평 / %s평',
                esc_html(get_field('exclusive_area_pyeong', $post_id) ?: '-'),
                esc_html(get_field('lease_area_pyeong', $post_id) ?: '-')
            );
            break;
        case 'ol_price':
            printf(
                '%s원 / %s원',
                esc_html(number_format((int) get_field('deposit_amount', $post_id))),
                esc_html(number_format((int) get_field('monthly_rent', $post_id)))
            );
            break;
    }
}, 10, 2);

add_filter('manage_edit-listing_sortable_columns', function ($columns) {
    $columns['ol_status'] = 'ol_status';
    return $columns;
});

add_action('restrict_manage_posts', function ($post_type) {
    if ($post_type !== 'listing') {
        return;
    }
    $current = isset($_GET['listing_status_filter']) ? sanitize_text_field(wp_unslash($_GET['listing_status_filter'])) : '';
    echo '<select name="listing_status_filter"><option value="">전체 상태</option>';
    foreach (ol_listing_status_map() as $value => $meta) {
        printf('<option value="%s" %s>%s</option>', esc_attr($value), selected($current, $value, false), esc_html($meta[0]));
    }
    echo '</select>';
});

add_action('pre_get_posts', function ($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }
    if ($query->get('post_type') !== 'listing') {
        return;
    }
    $meta_query = (array) $query->get('meta_query');

    if ($query->get('orderby') === 'ol_status') {
        $query->set('meta_key', 'listing_status');
        $query->set('orderby', 'meta_value');
    }
    if (!empty($_GET['listing_status_filter'])) {
        $meta_query[] = [
            'key' => 'listing_status',
            'value' => sanitize_text_field(wp_unslash($_GET['listing_status_filter'])),
        ];
        $query->set('meta_query', $meta_query);
    }
});

// ---------------------------------------------------------------
// BUILDING 목록
// ---------------------------------------------------------------
add_filter('manage_building_posts_columns', function ($columns) {
    $new = [];
    foreach ($columns as $key => $label) {
        $new[$key] = $label;
        if ($key === 'title') {
            $new['ol_address'] = '주소';
            $new['ol_floors'] = '지상층수';
            $new['ol_listing_count'] = '활성매물수';
        }
    }
    return $new;
});

add_action('manage_building_posts_custom_column', function ($column, $post_id) {
    switch ($column) {
        case 'ol_address':
            echo esc_html(get_field('building_address_road', $post_id) ?: '&mdash;');
            break;
        case 'ol_floors':
            echo esc_html(get_field('building_ground_floors', $post_id) ?: '&mdash;');
            break;
        case 'ol_listing_count':
            // 캐시 필드(building-cache.php가 매물 저장/삭제 시 갱신)를 그대로 읽는다 - 행마다 재쿼리하지 않음.
            echo esc_html((string) (int) get_field('building_active_listing_count', $post_id));
            break;
    }
}, 10, 2);

add_filter('manage_edit-building_sortable_columns', function ($columns) {
    $columns['ol_floors'] = 'ol_floors';
    return $columns;
});

add_action('pre_get_posts', function ($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }
    if ($query->get('post_type') !== 'building') {
        return;
    }
    if ($query->get('orderby') === 'ol_floors') {
        $query->set('meta_key', 'building_ground_floors');
        $query->set('orderby', 'meta_value_num');
    }
});
