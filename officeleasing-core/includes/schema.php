<?php
// building 단일 페이지(빌딩 URL) JSON-LD: OfficeBuilding + RealEstateListing.
//
// 범위(Sprint 01.5 3-5 확정): OfficeBuilding + RealEstateListing + AIO 요약만.
// ItemList/CollectionPage/Hub Breadcrumb/FAQPage는 허브(권역·동) 단계 스키마이므로 이번 범위가 아니다
// (Sprint 02.5로 별도 분리 - archive-building.php/taxonomy-office_region.php는 아직 스키마 없음).
//
// listing 전용 페이지는 항상 building 페이지로 301 리다이렉트되므로(URL 정책 - single-listing.php),
// listing 자체를 위한 별도 <script> 출력 지점은 없다. 대신 이 building 페이지의 RealEstateListing
// 노드(들)가 실제로 화면에 보이는 매물 정보를 그대로 커버한다 - "Schema는 화면에 실제 보이는 내용과
// 일치해야 한다"는 원칙에 따라 매물 개수(0/1/N)와 노드 개수를 정확히 맞춘다.
//
// [중복 출력 방지] Rank Math 등 SEO 플러그인이 활성화돼 있으면 이 파일은 아무것도 렌더하지 않는다
// (schema-home.php와 동일한 감지 로직·필터를 재사용). seller는 회사 정보를 다시 선언하지 않고
// schema-home.php가 정의한 {home}/#organization 을 @id로만 참조한다.
if (!defined('ABSPATH')) {
    exit;
}

function ol_should_output_building_schema() {
    return (bool) apply_filters('ol_output_building_schema', !ol_seo_plugin_outputs_schema());
}

add_action('wp_head', 'ol_render_building_schema', 20);
function ol_render_building_schema() {
    if (!is_singular('building') || is_paged()) {
        return;
    }
    if (!ol_should_output_building_schema()) {
        return;
    }

    $building_id = get_queried_object_id();
    if (!$building_id) {
        return;
    }

    $permalink = get_permalink($building_id);
    if (!$permalink) {
        return;
    }

    $graph = [ol_schema_office_building_node($building_id, $permalink)];

    foreach (ol_schema_active_listing_ids($building_id) as $listing_id) {
        $graph[] = ol_schema_real_estate_listing_node($listing_id, $building_id, $permalink);
    }

    $payload = [
        '@context' => 'https://schema.org',
        '@graph' => $graph,
    ];

    echo "\n<!-- OfficeLeasing Building Schema -->\n";
    echo '<script type="application/ld+json">'
        . wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        . "</script>\n";
}

/** 화면에 실제로 노출되는(공개 활성) 매물 ID만 - building-cache.php와 동일한 상태 기준을 재사용. */
function ol_schema_active_listing_ids($building_id) {
    return get_posts([
        'post_type' => 'listing',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'meta_query' => [
            ['key' => 'related_building', 'value' => $building_id],
            ['key' => 'listing_status', 'value' => ol_active_listing_statuses(), 'compare' => 'IN'],
        ],
    ]);
}

/** building_image_1..8 중 값이 있는 것만 ImageObject 배열로. 테마 헬퍼(olt_collect_images)에
 * 의존하지 않는다 - Core는 Theme 함수를 호출하지 않는다는 아키텍처 원칙. */
function ol_schema_building_photos($building_id) {
    $photos = [];
    for ($i = 1; $i <= 8; $i++) {
        $img = get_field('building_image_' . $i, $building_id);
        if (!is_array($img) || empty($img['url'])) {
            continue;
        }
        $photos[] = [
            '@type' => 'ImageObject',
            'url' => $img['url'],
        ];
    }
    return $photos;
}

function ol_schema_office_building_node($building_id, $permalink) {
    $name = get_the_title($building_id);
    $address_road = get_field('building_address_road', $building_id);
    $lat = (float) get_field('building_lat', $building_id);
    $lng = (float) get_field('building_lng', $building_id);

    $regions = get_the_terms($building_id, 'office_region');
    $district_name = '';
    if (!is_wp_error($regions) && !empty($regions)) {
        foreach ($regions as $term) {
            if ($term->parent) {
                $district_name = $term->name;
                break;
            }
        }
    }

    $node = [
        '@type' => 'OfficeBuilding',
        '@id' => $permalink . '#building',
        'name' => $name,
        'url' => $permalink,
    ];

    // [listing-detail-ux-pass3] "Leasing Point"(AT A GLANCE) 섹션 전체 삭제 요청에 따라
    // building_location_summary 필드 자체를 없앴다 - description은 그냥 채우지 않는다(SEO 설명문이
    // 빠지는 결과가 되므로 이 변경의 트레이드오프로 최종 보고에서 별도로 알린다).

    if ($address_road) {
        $node['address'] = [
            '@type' => 'PostalAddress',
            'streetAddress' => $address_road,
            'addressLocality' => $district_name ?: '서울특별시',
            'addressRegion' => '서울특별시',
            'addressCountry' => 'KR',
        ];
    }

    if ($lat && $lng) {
        $node['geo'] = [
            '@type' => 'GeoCoordinates',
            'latitude' => $lat,
            'longitude' => $lng,
        ];
    }

    $photos = ol_schema_building_photos($building_id);
    if (!empty($photos)) {
        $node['photo'] = $photos;
    }

    // 화면 "빌딩 정보" 섹션에 실제로 보이는 값만 additionalProperty로 - 화면에 없는 값은 만들지 않는다.
    $additional = [];
    $ground_floors = get_field('building_ground_floors', $building_id);
    if ($ground_floors) {
        $additional[] = ['@type' => 'PropertyValue', 'name' => '총 층수', 'value' => (string) $ground_floors . '층'];
    }
    $parking = get_field('building_parking', $building_id);
    if ($parking) {
        $additional[] = ['@type' => 'PropertyValue', 'name' => '주차', 'value' => $parking];
    }
    $elevator = get_field('building_elevator_count', $building_id);
    if ($elevator) {
        $additional[] = ['@type' => 'PropertyValue', 'name' => '엘리베이터', 'value' => (string) $elevator . '대'];
    }
    // [listing-detail-ux-pass3] 임대정보 섹션에 새로 노출한 용도/냉난방방식도 같은 원칙(화면에 실제로
    // 보이는 값만 additionalProperty로)에 따라 함께 추가한다.
    $usage_type = get_field('building_usage_type', $building_id);
    if ($usage_type) {
        $additional[] = ['@type' => 'PropertyValue', 'name' => '용도', 'value' => $usage_type];
    }
    $hvac_type = get_field('building_hvac_type', $building_id);
    if ($hvac_type) {
        $additional[] = ['@type' => 'PropertyValue', 'name' => '냉난방방식', 'value' => $hvac_type];
    }
    if (!empty($additional)) {
        $node['additionalProperty'] = $additional;
    }

    return $node;
}

function ol_schema_real_estate_listing_node($listing_id, $building_id, $permalink) {
    $building_name = get_the_title($building_id);
    $floor_display = get_field('floor_display', $listing_id);
    $status = get_field('listing_status', $listing_id);
    $exclusive_sqm = (float) get_field('exclusive_area_sqm', $listing_id);
    // 보증금/임대료/관리비는 0이 실제 유효값일 수 있어(building-cache.php와 동일 근거 - ACF 필드가
    // required=1 + min=0) ol_money_field_value()로 "입력 안 됨"(null)과 "0으로 입력됨"을 구분한다.
    // 아래 $monthly_rent > 0 등 단순 >0 체크로는 0원 조건이 구조화 데이터에서 빠졌다(2차 리뷰 지적).
    $monthly_rent = ol_money_field_value(get_field('monthly_rent', $listing_id));
    $deposit = ol_money_field_value(get_field('deposit_amount', $listing_id));
    $maintenance = ol_money_field_value(get_field('maintenance_fee', $listing_id));

    $availability_map = [
        'available' => 'https://schema.org/InStock',
        'reserved' => 'https://schema.org/LimitedAvailability',
        'contract_pending' => 'https://schema.org/LimitedAvailability',
    ];

    $node = [
        '@type' => 'RealEstateListing',
        '@id' => $permalink . '#listing-' . $listing_id,
        // listing 전용 URL은 항상 이 building URL로 301되므로(URL 정책), url도 실제 도달 지점인
        // building permalink로 맞춘다 - 리다이렉트되는 URL을 구조화 데이터에 넣지 않는다.
        'url' => $permalink,
        'name' => trim($building_name . ' ' . $floor_display . ' 사무실 임대'),
        'about' => ['@id' => $permalink . '#building'],
    ];

    $offer = [
        '@type' => 'Offer',
        'priceCurrency' => 'KRW',
        // GoodRelations 확장 - 매매가 아닌 임대(리스)임을 명시하는 통용 값
        'businessFunction' => 'http://purl.org/goodrelations/v1#LeaseOut',
        'seller' => ['@id' => ol_schema_organization_id()],
    ];
    if ($monthly_rent !== null) {
        $offer['price'] = (string) $monthly_rent;
    }
    if (isset($availability_map[$status])) {
        $offer['availability'] = $availability_map[$status];
    }
    $offer_additional = [];
    if ($deposit !== null) {
        $offer_additional[] = ['@type' => 'PropertyValue', 'name' => '보증금', 'value' => (string) $deposit];
    }
    if ($maintenance !== null) {
        $offer_additional[] = ['@type' => 'PropertyValue', 'name' => '관리비', 'value' => (string) $maintenance];
    }
    if (!empty($offer_additional)) {
        $offer['additionalProperty'] = $offer_additional;
    }
    $node['offers'] = $offer;

    if ($exclusive_sqm > 0) {
        $node['floorSize'] = [
            '@type' => 'QuantitativeValue',
            'value' => $exclusive_sqm,
            'unitCode' => 'MTK', // UN/CEFACT: square metre
        ];
    }

    return $node;
}
