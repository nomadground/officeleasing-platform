<?php
// 자동계산 필드를 메인 폼에서 숨긴 대신(admin-hidden-fields.php), 필요할 때 값을 확인할 수 있는
// 우측 사이드바 요약 박스. 순정 워드프레스 메타박스이며 ACF 폼과 무관하게 읽기전용으로만 표시한다.
if (!defined('ABSPATH')) {
    exit;
}

add_action('add_meta_boxes', function () {
    add_meta_box('ol_listing_summary', '계산값 요약 (읽기전용)', 'ol_render_listing_summary_box', 'listing', 'side', 'default');
    add_meta_box('ol_building_summary', '계산값 요약 (읽기전용)', 'ol_render_building_summary_box', 'building', 'side', 'default');
});

function ol_render_listing_summary_box($post) {
    ol_render_summary_table([
        '전용면적' => ol_format_sqm(get_field('exclusive_area_sqm', $post->ID)),
        '공급면적' => ol_format_sqm(get_field('lease_area_sqm', $post->ID)),
        '보증금' => ol_format_manwon(get_field('deposit_amount', $post->ID)),
        '임대료' => ol_format_manwon(get_field('monthly_rent', $post->ID)),
        '관리비' => ol_format_manwon(get_field('maintenance_fee', $post->ID)),
        '월 총비용' => ol_format_manwon(get_field('monthly_total_cost', $post->ID)),
        '공급평당 임대료' => ol_format_krw_per_pyeong(get_field('rent_per_lease_pyeong', $post->ID)),
        '공급평당 관리비' => ol_format_krw_per_pyeong(get_field('maintenance_per_lease_pyeong', $post->ID)),
        '전용평당 NOC' => ol_format_krw_per_pyeong(get_field('noc_per_exclusive_pyeong', $post->ID)),
        '전용평당 보증금' => ol_format_krw_per_pyeong(get_field('deposit_per_exclusive_pyeong', $post->ID)),
    ]);
}

function ol_render_building_summary_box($post) {
    $min_area = get_field('building_min_exclusive_area_pyeong', $post->ID);
    $max_area = get_field('building_max_exclusive_area_pyeong', $post->ID);
    $area_range = ( $min_area || $max_area )
        ? number_format((float) $min_area, 0) . ' ~ ' . number_format((float) $max_area, 0) . ' 평'
        : '-';
    $generation_labels = ['auto_generated' => '자동 생성', 'human_written' => '직접 작성'];
    $generation_status = get_field('aio_generation_status', $post->ID);

    ol_render_summary_table([
        '연면적' => ol_format_pyeong_value(get_field('building_total_area_pyeong', $post->ID)),
        '기준층면적' => ol_format_pyeong_value(get_field('building_standard_floor_area_pyeong', $post->ID)),
        '활성 매물 수' => (int) get_field('building_active_listing_count', $post->ID) . '건',
        '전용면적 범위' => $area_range,
        '최저 임대료' => ol_format_manwon(get_field('building_min_rent', $post->ID)),
        // 검수 상태는 위쪽 필드(aio_review_status)에서 직접 바꿀 수 있으므로 여기선 참고용 읽기전용 표시만.
        'AIO 입지요약 생성방식' => $generation_labels[$generation_status] ?? '-',
    ]);
}

function ol_format_pyeong_value($value) {
    return $value ? number_format((float) $value, 1) . ' 평' : '-';
}

function ol_format_sqm($value) {
    return $value ? number_format((float) $value, 1) . ' ㎡' : '-';
}

function ol_render_summary_table(array $rows) {
    echo '<table style="width:100%;font-size:12px;border-collapse:collapse;">';
    foreach ($rows as $label => $value) {
        printf(
            '<tr><td style="padding:4px 0;color:#666;">%s</td><td style="padding:4px 0;text-align:right;font-weight:600;">%s</td></tr>',
            esc_html($label),
            esc_html((string) $value)
        );
    }
    echo '</table><p style="color:#999;font-size:11px;margin-top:8px;">저장하면 자동 갱신됩니다.</p>';
}
