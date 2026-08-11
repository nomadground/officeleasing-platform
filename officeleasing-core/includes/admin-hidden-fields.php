<?php
// 시스템 자동계산 필드를 관리자 입력 화면(메인 폼)에서 숨긴다.
// acf/prepare_field는 "이 필드를 폼에 그릴지"만 결정하는 필터라, 여기서 false를 반환해도
// 필드 정의(key/name)나 postmeta 저장 방식은 전혀 바뀌지 않는다. ACF는 $_POST['acf']에
// 실제로 제출된 필드만 저장 로직을 돌리므로, 폼에서 안 그려진 필드는 저장 단계에서 아예
// 건드려지지 않고 - save-hooks.php -> calculations.php가 update_field()로 채우는 값이 그대로 유지된다.
// acf-json의 필드 정의는 이 파일과 무관하게 원본 그대로다.
if (!defined('ABSPATH')) {
    exit;
}

$ol_hidden_calculated_fields = [
    // listing - ㎡가 원본 입력이라 자동계산되는 평 값을 숨긴다(building_total_area_pyeong 등과
    // 동일한 방향으로 통일 - README-ACF.md 참고, 예전엔 매물만 반대 방향이었다)
    'exclusive_area_pyeong',
    'lease_area_pyeong',
    'deposit_amount',
    'monthly_rent',
    'maintenance_fee',
    'monthly_total_cost',
    'rent_per_lease_pyeong',
    'maintenance_per_lease_pyeong',
    'noc_per_exclusive_pyeong',
    'deposit_per_exclusive_pyeong',
    // [listing-detail-ux-pass2 리뷰 지적, 실제 누락 확인] 새로 추가한 자동계산 필드가 이 목록에서
    // 빠지면 readonly ACF 필드가 관리자 메인 입력폼에 그대로 노출된다(readonly는 UI 표시만 막고
    // 이 필터가 실제로 폼에서 감춘다 - 파일 상단 주석 참고).
    'deposit_per_lease_pyeong',
    // building - ㎡가 원본 입력이라 자동계산되는 평 값을 숨긴다 (sqm은 손대지 않음)
    'building_total_area_pyeong',
    'building_standard_floor_area_pyeong',
    // building 집계 캐시 - listing 저장/삭제 시 자동 계산되므로 폼에서 숨긴다
    'building_active_listing_count',
    'building_min_exclusive_area_pyeong',
    'building_max_exclusive_area_pyeong',
    'building_min_rent',
    'building_last_verified_at',
];

foreach ($ol_hidden_calculated_fields as $ol_field_name) {
    add_filter("acf/prepare_field/name={$ol_field_name}", '__return_false');
}
