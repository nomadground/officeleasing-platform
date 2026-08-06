<?php
// 저장 전 필드값 검증. acf/validate_value 필터는 ACF 무료에도 포함된 코어 기능이며,
// AJAX 저장 단계에서 필드 아래 인라인 에러로 표시되어 저장 자체를 막는다(계산 이후 정리가 아님).
if (!defined('ABSPATH')) {
    exit;
}

function ol_validate_non_negative_number($valid, $value, $field, $input) {
    if ($valid !== true) {
        return $valid;
    }
    if ($value === '' || $value === null) {
        return $valid; // 빈 값은 required 규칙이 별도 처리
    }
    if (!is_numeric($value)) {
        return '숫자만 입력할 수 있습니다.';
    }
    if ((float) $value < 0) {
        return '0 이상의 값을 입력해야 합니다.';
    }
    return $valid;
}

$ol_non_negative_fields = [
    // listing - ㎡가 원본 입력이다(exclusive_area_pyeong/lease_area_pyeong은 이제 자동계산이라
    // 여기서 검증할 대상이 아니다 - 사람이 직접 입력하는 sqm 쪽만 방어하면 된다)
    'exclusive_area_sqm', 'lease_area_sqm',
    'deposit_manwon', 'monthly_rent_manwon', 'maintenance_fee_manwon',
    // building
    'building_total_area_sqm', 'building_standard_floor_area_sqm',
    'building_elevator_count', 'building_basement_floors', 'building_ground_floors',
    'building_exclusive_ratio', 'building_lat', 'building_lng',
];
foreach ($ol_non_negative_fields as $ol_field_name) {
    add_filter("acf/validate_value/name={$ol_field_name}", 'ol_validate_non_negative_number', 10, 4);
}

// 전용면적이 공급면적보다 크면 저장을 막는다 (필드키는 acf-json/group_ol_listing.json 기준 고정값).
// ㎡가 원본 입력이 됐으므로 이제 exclusive_area_sqm/lease_area_sqm을 비교한다.
add_filter('acf/validate_value/name=exclusive_area_sqm', function ($valid, $value, $field, $input) {
    if ($valid !== true || !is_numeric($value)) {
        return $valid;
    }
    $lease_value = isset($_POST['acf']['field_ol_lst_lease_area_sqm'])
        ? $_POST['acf']['field_ol_lst_lease_area_sqm']
        : null;
    if ($lease_value !== null && is_numeric($lease_value) && (float) $value > (float) $lease_value) {
        return '전용면적은 공급면적보다 클 수 없습니다.';
    }
    return $valid;
}, 20, 4);

// 위경도 대략 범위 체크 (한국 영역 밖 좌표 오입력 방지)
add_filter('acf/validate_value/name=building_lat', function ($valid, $value, $field, $input) {
    if ($valid !== true || $value === '') {
        return $valid;
    }
    if ((float) $value < 33 || (float) $value > 39) {
        return '위도 값이 한국 범위(33~39)를 벗어났습니다.';
    }
    return $valid;
}, 20, 4);
add_filter('acf/validate_value/name=building_lng', function ($valid, $value, $field, $input) {
    if ($valid !== true || $value === '') {
        return $valid;
    }
    if ((float) $value < 124 || (float) $value > 132) {
        return '경도 값이 한국 범위(124~132)를 벗어났습니다.';
    }
    return $valid;
}, 20, 4);
