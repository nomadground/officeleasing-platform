<?php
// listing/building 파생 필드 계산. 훅 등록은 save-hooks.php가 담당하고, 이 파일은
// "post_id를 받아 계산해서 update_field로 저장한다"는 순수 작업 함수만 제공한다.
if (!defined('ABSPATH')) {
    exit;
}

function ol_calculate_listing_fields($post_id) {
    // 면적은 ㎡가 원본 입력, 평은 역산이다(building_total_area_sqm 등 건물 면적과 동일한 방향으로
    // 통일 - 예전에는 매물만 평이 입력이라 건물과 방향이 반대였다, README-ACF.md 참고).
    $exclusive_sqm = (float) get_field('exclusive_area_sqm', $post_id);
    $lease_sqm = (float) get_field('lease_area_sqm', $post_id);
    $exclusive_pyeong = ol_calc_pyeong_from_sqm($exclusive_sqm);
    $lease_pyeong = ol_calc_pyeong_from_sqm($lease_sqm);
    update_field('exclusive_area_pyeong', $exclusive_pyeong, $post_id);
    update_field('lease_area_pyeong', $lease_pyeong, $post_id);

    // 보증금/임대료/관리비는 전부 "만원 단위 단일 입력" -> 원 단위로 환산만 한다
    $deposit_amount = ol_calc_won_from_manwon(get_field('deposit_manwon', $post_id));
    $monthly_rent = ol_calc_won_from_manwon(get_field('monthly_rent_manwon', $post_id));
    $maintenance_fee = ol_calc_won_from_manwon(get_field('maintenance_fee_manwon', $post_id));

    update_field('deposit_amount', $deposit_amount, $post_id);
    update_field('monthly_rent', $monthly_rent, $post_id);
    update_field('maintenance_fee', $maintenance_fee, $post_id);

    $monthly_total_cost = ol_calc_monthly_total_cost($monthly_rent, $maintenance_fee);
    update_field('monthly_total_cost', $monthly_total_cost, $post_id);

    update_field('rent_per_lease_pyeong', ol_calc_per_pyeong($monthly_rent, $lease_pyeong), $post_id);
    update_field('maintenance_per_lease_pyeong', ol_calc_per_pyeong($maintenance_fee, $lease_pyeong), $post_id);
    update_field('noc_per_exclusive_pyeong', ol_calc_per_pyeong($monthly_total_cost, $exclusive_pyeong), $post_id);
    // deposit_per_exclusive_pyeong은 관리자 화면(admin-summary-box.php "전용평당 보증금")이 계속 쓰므로
    // 그대로 유지한다 - 공개 페이지(single-building.php) 표시가 임대평당 기준으로 바뀌는 것과는 별개다.
    update_field('deposit_per_exclusive_pyeong', ol_calc_per_pyeong($deposit_amount, $exclusive_pyeong), $post_id);
    // [단가 기준 통일] 공개 페이지의 보증금 평당가를 임대료/관리비와 같은 "임대평당" 기준으로 맞춘다 -
    // 기존엔 보증금만 전용평당, 임대료/관리비는 임대(공급)평당이라 세 값의 분모가 서로 달라 나란히
    // 놓고 비교하기 어려웠다. rent_per_lease_pyeong/maintenance_per_lease_pyeong과 동일한 패턴.
    update_field('deposit_per_lease_pyeong', ol_calc_per_pyeong($deposit_amount, $lease_pyeong), $post_id);
}

function ol_calculate_building_fields($post_id) {
    // 건물 연면적/기준층면적은 ㎡가 원본 입력 - 평은 역산한다 (매물 면적과 입력 방향이 반대)
    $total_sqm = (float) get_field('building_total_area_sqm', $post_id);
    update_field('building_total_area_pyeong', ol_calc_pyeong_from_sqm($total_sqm), $post_id);

    $standard_sqm = (float) get_field('building_standard_floor_area_sqm', $post_id);
    update_field('building_standard_floor_area_pyeong', ol_calc_pyeong_from_sqm($standard_sqm), $post_id);
}
