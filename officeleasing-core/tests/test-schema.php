<?php
/**
 * schema.php의 노드 조립 함수를 WP 없이 검증한다. add_action/wp_head 배관은 제외하고,
 * 실제 그래프를 만드는 순수 함수(ol_schema_office_building_node 등)를 직접 호출해
 * "화면에 보이는 매물 개수만큼 RealEstateListing 노드가 나오는지", "자동초안이면 description을
 * 뺴는지" 같은 핵심 로직만 확인한다. 최소한의 WP/ACF 스텁을 이 파일 안에 직접 구현한다.
 *
 * 실행: php tests/test-schema.php
 */

define('ABSPATH', __DIR__ . '/');

// ── 최소 WP/ACF 스텁: 인메모리 "DB" ──
$GLOBALS['__fields'] = []; // [post_id][field_name] => value
$GLOBALS['__meta'] = [];   // [post_id][meta_key] => value
$GLOBALS['__terms'] = [];  // [post_id] => [ (object) ['term_id'=>, 'parent'=>, 'name'=>], ... ]
$GLOBALS['__titles'] = []; // [post_id] => title
$GLOBALS['__listings'] = []; // building_id => [listing_id, ...] (활성 매물만 - get_posts 대체)

function get_field($name, $post_id) {
    return $GLOBALS['__fields'][$post_id][$name] ?? '';
}
function get_post_meta($post_id, $key, $single = false) {
    return $GLOBALS['__meta'][$post_id][$key] ?? '';
}
function get_the_terms($post_id, $taxonomy) {
    return $GLOBALS['__terms'][$post_id] ?? [];
}
function get_the_title($post_id) {
    return $GLOBALS['__titles'][$post_id] ?? '';
}
function is_wp_error($thing) {
    return false;
}
function apply_filters($tag, $value) {
    return $value;
}
function add_action() {
    return true;
}
function home_url($path = '/') {
    return 'https://officeleasing.co.kr' . $path;
}
function trailingslashit($s) {
    return rtrim($s, '/') . '/';
}
// 테스트 대상 함수가 실제로 쓰는 것: building_id에 연결된 활성 매물 ID 배열을 그대로 반환하도록 스텁.
function get_posts($args) {
    if (($args['post_type'] ?? '') === 'listing') {
        $building_id = null;
        foreach ($args['meta_query'] as $mq) {
            if (($mq['key'] ?? '') === 'related_building') {
                $building_id = $mq['value'];
            }
        }
        return $GLOBALS['__listings'][$building_id] ?? [];
    }
    return [];
}
function ol_active_listing_statuses() {
    return ['available', 'reserved', 'contract_pending'];
}

// schema.php가 ol_money_field_value()(helpers.php)를 쓴다 - 실제 플러그인 부트스트랩에서는
// officeleasing-core.php가 helpers.php를 schema.php보다 먼저 로드해 문제 없지만, 이 테스트는
// 부트스트랩을 거치지 않고 schema.php만 직접 require하므로 여기서도 순서를 맞춰야 한다.
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/schema.php';
require __DIR__ . '/../includes/schema-home.php';

$pass = 0;
$fail = 0;
function check($label, $got, $expected) {
    global $pass, $fail;
    if ($got === $expected) {
        $pass++;
        echo "[PASS] {$label}\n";
    } else {
        $fail++;
        echo "[FAIL] {$label}\n  got:      " . json_encode($got, JSON_UNESCAPED_UNICODE) . "\n  expected: " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n";
    }
}

// ── 픽스처: 빌딩 1개, 활성 매물 0/1/N 케이스를 각각 다른 building_id로 구성 ──
$GLOBALS['__titles'][100] = '파르나스타워';
$GLOBALS['__fields'][100] = [
    'building_address_road' => '테헤란로 521',
    'building_lat' => 37.5089,
    'building_lng' => 127.0632,
    'building_ground_floors' => 40,
    'building_parking' => '지하 4~7층 총 320대',
    'building_elevator_count' => 12,
    'building_usage_type' => '업무시설',
    'building_hvac_type' => '개별 냉난방(EHP)',
];
$GLOBALS['__terms'][100] = [
    (object) ['term_id' => 1, 'parent' => 0, 'name' => '강남사무실임대'],
    (object) ['term_id' => 2, 'parent' => 1, 'name' => '삼성동'],
];

// ── 1. OfficeBuilding 노드 기본 필드 ──
$node = ol_schema_office_building_node(100, 'https://officeleasing.co.kr/강남사무실임대/삼성동/파르나스타워/');
check('OfficeBuilding @type', $node['@type'], 'OfficeBuilding');
check('OfficeBuilding @id는 permalink 기반', $node['@id'], 'https://officeleasing.co.kr/강남사무실임대/삼성동/파르나스타워/#building');
// [listing-detail-ux-pass3] "Leasing Point" 섹션 삭제로 building_location_summary 필드 자체가
// 없어졌다 - description은 이제 어떤 소스도 없어 항상 미포함이다(SEO 트레이드오프, README-ACF.md 참고).
check('description 필드 자체가 없어 항상 미포함', isset($node['description']), false);
check('address.addressLocality는 자식 term(삼성동)', $node['address']['addressLocality'], '삼성동');
check('geo 좌표 포함', $node['geo'], ['@type' => 'GeoCoordinates', 'latitude' => 37.5089, 'longitude' => 127.0632]);
// [리뷰 반영] additionalProperty 배열 인덱스로 직접 찾으면(예: [3]) 다른 PropertyValue가 앞에
// 추가/삭제될 때마다 로직은 멀쩡한데 테스트만 깨진다 - name으로 찾도록 바꿔 순서 변경에 강하게 한다.
check('additionalProperty에 용도 포함(화면 빌딩정보 섹션과 동일 소스)', find_property($node['additionalProperty'], '용도'), ['@type' => 'PropertyValue', 'name' => '용도', 'value' => '업무시설']);
check('additionalProperty에 냉난방방식 포함', find_property($node['additionalProperty'], '냉난방방식'), ['@type' => 'PropertyValue', 'name' => '냉난방방식', 'value' => '개별 냉난방(EHP)']);

function find_property($additional_properties, $name) {
    foreach ((array) $additional_properties as $prop) {
        if (($prop['name'] ?? null) === $name) {
            return $prop;
        }
    }
    return null;
}

// ── 3. 좌표가 없으면 geo 자체를 넣지 않는다(값이 없는데 0,0으로 지어내지 않음) ──
$GLOBALS['__fields'][101] = ['building_address_road' => '', 'building_lat' => 0, 'building_lng' => 0];
$GLOBALS['__titles'][101] = '노좌표빌딩';
$node_nogeo = ol_schema_office_building_node(101, 'https://officeleasing.co.kr/y/');
check('좌표 0이면 geo 미포함', isset($node_nogeo['geo']), false);
check('주소 없으면 address 미포함', isset($node_nogeo['address']), false);

// ── 4. 활성 매물 0개 -> RealEstateListing 없음 (화면과 일치) ──
$GLOBALS['__listings'][100] = [];
$listing_ids_0 = ol_schema_active_listing_ids(100);
check('활성 매물 0개', count($listing_ids_0), 0);

// ── 5. 활성 매물 1개 -> 노드 1개, 화면과 동일한 값 ──
$GLOBALS['__listings'][100] = [501];
$GLOBALS['__fields'][501] = [
    'floor_display' => '21층',
    'listing_status' => 'available',
    'exclusive_area_sqm' => 108.1,
    'monthly_rent' => 13070000,
    'deposit_amount' => 156838000,
    'maintenance_fee' => 3638000,
];
$listing_node = ol_schema_real_estate_listing_node(501, 100, 'https://officeleasing.co.kr/b/');
check('RealEstateListing @type', $listing_node['@type'], 'RealEstateListing');
check('url은 리다이렉트되는 listing URL이 아니라 building permalink', $listing_node['url'], 'https://officeleasing.co.kr/b/');
check('about이 building @id 참조', $listing_node['about'], ['@id' => 'https://officeleasing.co.kr/b/#building']);
check('offers.price는 monthly_rent', $listing_node['offers']['price'], '13070000');
check('offers.availability는 available -> InStock', $listing_node['offers']['availability'], 'https://schema.org/InStock');
check('offers.seller는 organization @id 참조(회사정보 재선언 안 함)', $listing_node['offers']['seller'], ['@id' => 'https://officeleasing.co.kr/#organization']);
check('floorSize는 exclusive_area_sqm', $listing_node['floorSize']['value'], 108.1);

// ── 6. 활성 매물 3개 -> 노드 3개(동일 빌딩 중복 없이 매물별 1개씩) ──
$GLOBALS['__listings'][100] = [501, 502, 503];
$GLOBALS['__fields'][502] = ['floor_display' => '22층', 'listing_status' => 'reserved', 'monthly_rent' => 9000000];
$GLOBALS['__fields'][503] = ['floor_display' => '23층', 'listing_status' => 'contract_pending', 'monthly_rent' => 15000000];
$ids3 = ol_schema_active_listing_ids(100);
check('활성 매물 3개 -> ID 3개 반환', count($ids3), 3);
$node502 = ol_schema_real_estate_listing_node(502, 100, 'https://officeleasing.co.kr/b/');
check('두 번째 매물 availability -> LimitedAvailability', $node502['offers']['availability'], 'https://schema.org/LimitedAvailability');
check('두 번째 매물 @id가 첫 번째와 다름(고유)', $node502['@id'] !== $listing_node['@id'], true);

// ── 6-1. 보증금/임대료/관리비 0원은 "값 없음"이 아니라 실제 0으로 구조화 데이터에 들어가야 한다
// (2차 리뷰 지적: ol_money_field_value() 적용 전엔 >0 체크 때문에 0원이 통째로 빠졌었다) ──
$GLOBALS['__fields'][504] = [
    'floor_display' => '5층',
    'listing_status' => 'available',
    'monthly_rent' => 0,
    'deposit_amount' => 0,
    'maintenance_fee' => 500000,
];
$node504 = ol_schema_real_estate_listing_node(504, 100, 'https://officeleasing.co.kr/b/');
check('임대료 0원도 offers.price에 포함(생략 아님)', $node504['offers']['price'], '0');
check('보증금 0원도 additionalProperty에 포함', $node504['offers']['additionalProperty'][0]['value'], '0');
check('관리비는 정상값 그대로 포함', $node504['offers']['additionalProperty'][1]['value'], '500000');

// ── 6-2. 필드 자체가 입력된 적 없는 매물은 여전히 생략(0원과는 다름) ──
$GLOBALS['__fields'][505] = ['floor_display' => '6층', 'listing_status' => 'available'];
$node505 = ol_schema_real_estate_listing_node(505, 100, 'https://officeleasing.co.kr/b/');
check('금액 필드 자체가 없는 매물은 offers.price 자체가 없음(0으로 채워지지 않음)', isset($node505['offers']['price']), false);
check('금액 필드 자체가 없는 매물은 additionalProperty 자체가 없음', isset($node505['offers']['additionalProperty']), false);

// ── 7. 전체 payload가 JSON으로 안전하게 인코딩되는지(한글 깨짐/에러 없음) ──
$json = wp_json_encode_stub([$node, $listing_node, $node502]);
check('JSON 인코딩 성공(문자열 반환)', is_string($json), true);
check('한글이 유니코드 이스케이프 없이 그대로 들어감', str_contains($json, '파르나스타워'), true);

function wp_json_encode_stub($data) {
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

printf("\n%d passed, %d failed\n", $pass, $fail);
if ($fail > 0) {
    exit(1);
}
