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
    update_field('deposit_per_exclusive_pyeong', ol_calc_per_pyeong($deposit_amount, $exclusive_pyeong), $post_id);
}

function ol_calculate_building_fields($post_id) {
    // 건물 연면적/기준층면적은 ㎡가 원본 입력 - 평은 역산한다 (매물 면적과 입력 방향이 반대)
    $total_sqm = (float) get_field('building_total_area_sqm', $post_id);
    update_field('building_total_area_pyeong', ol_calc_pyeong_from_sqm($total_sqm), $post_id);

    $standard_sqm = (float) get_field('building_standard_floor_area_sqm', $post_id);
    update_field('building_standard_floor_area_pyeong', ol_calc_pyeong_from_sqm($standard_sqm), $post_id);
}

// AIO 구조화 필드(building_location_summary)가 비어있으면 초안 템플릿을 삽입하고, "생성 방식"과
// "검수 상태"를 서로 다른 두 필드(aio_generation_status / aio_review_status)로 각각 관리한다.
//
// [Sprint 01.5 3-6] 예전엔 단일 불리언(_ol_aio_draft)으로 "미편집 자동초안"만 표현할 수 있었다.
// 그러면 관리자가 자동초안 문장을 검토하고 "이대로 발행해도 좋다"고 명시적으로 승인하는 경로 자체가
// 없었다 - 글자를 한 글자도 안 고치면 무조건 "미검수"로 영원히 남았다. 이제 두 축으로 분리한다:
//   - aio_generation_status: 이 문장이 "자동 생성"인지 "직접 작성"인지 (내용 자체의 출처, 자동계산)
//   - aio_review_status: 관리자가 검수를 마쳤는지 (auto_generated여도 관리자가 select에서 직접
//     '검수 완료'로 바꾸면 그 값을 유지한다 - 여기서 강제로 되돌리지 않는다)
// 즉 "자동생성 + 검수완료"(초안을 그대로 승인)라는, 예전엔 표현 불가능했던 상태가 이제 가능하다.
function ol_sync_aio_status($post_id) {
    $summary = get_field('building_location_summary', $post_id);
    $stored_hash = get_post_meta($post_id, '_ol_aio_draft_hash', true);

    if (empty($summary)) {
        $name = get_the_title($post_id);
        $address = get_field('building_address_road', $post_id);
        $station = get_field('building_subway1_station', $post_id);
        $standard_area = get_field('building_standard_floor_area_pyeong', $post_id);
        $ratio = get_field('building_exclusive_ratio', $post_id);

        $draft = sprintf(
            '%1$s은 %2$s에 위치한 업무시설입니다. %3$s와 인접해 있으며, 기준층 임대면적은 %4$s평, 전용률은 %5$s%%입니다.',
            $name ?: '{건물명}',
            $address ?: '{도로명주소}',
            $station ?: '{가장 가까운 지하철역}',
            $standard_area ?: '{기준층면적}',
            $ratio ?: '{전용률}'
        );

        update_field('building_location_summary', $draft, $post_id);
        update_field('aio_generation_status', 'auto_generated', $post_id);
        update_field('aio_review_status', 'pending', $post_id);
        update_post_meta($post_id, '_ol_aio_draft_hash', hash('sha256', $draft));
        return;
    }

    $hash_matches = $stored_hash && hash('sha256', (string) $summary) === $stored_hash;

    if ($hash_matches) {
        // 문장 자체는 자동초안과 동일 - 생성방식은 auto_generated. 검수상태는 관리자가 select에서
        // 직접 바꿨을 수 있으므로 여기서 손대지 않는다(단, 한 번도 세팅된 적 없으면 기본값만 채움).
        update_field('aio_generation_status', 'auto_generated', $post_id);
        if (!get_field('aio_review_status', $post_id)) {
            update_field('aio_review_status', 'pending', $post_id);
        }
    } else {
        // 자동초안과 다름 = 관리자가 직접 작성/수정 -> 작성 자체가 검수를 겸한다.
        update_field('aio_generation_status', 'human_written', $post_id);
        update_field('aio_review_status', 'reviewed', $post_id);
        update_post_meta($post_id, '_ol_aio_draft_hash', hash('sha256', (string) $summary));
    }
}
