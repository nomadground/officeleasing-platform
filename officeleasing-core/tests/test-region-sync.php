<?php
/**
 * region-sync.php의 두 동기화 함수를 WP 없이 검증한다. 특히 3-7에서 고친 버그
 * (ol_cascade_region_to_listings가 빌딩 권역이 빈 배열일 때 listing의 stale 권역을 안 지우던 문제)를
 * 실제 함수를 include해서 재현/검증한다.
 *
 * 실행: php tests/test-region-sync.php
 */

define('ABSPATH', __DIR__ . '/');

// ── 인메모리 term-relationship "DB" ──
$GLOBALS['__building_terms'] = []; // building_id => [term_id, ...]
$GLOBALS['__listing_terms'] = [];  // listing_id => [term_id, ...] (wp_set_object_terms가 여기에 씀)
$GLOBALS['__related_building'] = []; // listing_id => building_id
$GLOBALS['__listings_of_building'] = []; // building_id => [listing_id, ...]

function get_field($name, $post_id) {
    if ('related_building' === $name) {
        return $GLOBALS['__related_building'][$post_id] ?? 0;
    }
    return '';
}
function wp_get_object_terms($object_id, $taxonomy, $args = []) {
    return $GLOBALS['__building_terms'][$object_id] ?? [];
}
function wp_set_object_terms($object_id, $terms, $taxonomy, $append = false) {
    $GLOBALS['__listing_terms'][$object_id] = $terms;
    return $terms;
}
function is_wp_error($thing) {
    return false;
}
function get_posts($args) {
    if (($args['post_type'] ?? '') === 'listing') {
        $building_id = null;
        foreach ($args['meta_query'] as $mq) {
            if (($mq['key'] ?? '') === 'related_building') {
                $building_id = $mq['value'];
            }
        }
        return $GLOBALS['__listings_of_building'][$building_id] ?? [];
    }
    return [];
}
function add_action() {
    return true;
}

require __DIR__ . '/../includes/region-sync.php';

$pass = 0;
$fail = 0;
function check($label, $got, $expected) {
    global $pass, $fail;
    if ($got === $expected) {
        $pass++;
        echo "[PASS] {$label}\n";
    } else {
        $fail++;
        echo "[FAIL] {$label}\n  got:      " . json_encode($got) . "\n  expected: " . json_encode($expected) . "\n";
    }
}

// ── 1. 정방향: listing 저장 시 빌딩의 term을 그대로 복사 ──
$GLOBALS['__related_building'][501] = 100;
$GLOBALS['__building_terms'][100] = [1, 2];
ol_sync_region_from_building(501);
check('정방향: listing이 빌딩 term을 복사받음', $GLOBALS['__listing_terms'][501], [1, 2]);

// ── 2. 정방향: 빌딩 권역이 비어있어도(권역 제거) listing의 stale term을 지운다 ──
$GLOBALS['__building_terms'][100] = [];
ol_sync_region_from_building(501);
check('정방향: 권역 제거 시 listing term도 빈 배열로 정리', $GLOBALS['__listing_terms'][501], []);

// ── 3. [3-7 버그 수정 검증] 역방향: 빌딩 저장 시 연결된 listing 전부에 term을 캐스케이드 ──
$GLOBALS['__building_terms'][200] = [5, 6];
$GLOBALS['__listings_of_building'][200] = [601, 602];
$GLOBALS['__listing_terms'][601] = [999]; // 이전 권역(다른 값)이 남아있는 상태를 시뮬레이션
$GLOBALS['__listing_terms'][602] = [999];
ol_cascade_region_to_listings(200);
check('역방향: 매물1이 새 빌딩 term으로 교체됨', $GLOBALS['__listing_terms'][601], [5, 6]);
check('역방향: 매물2도 동일하게 교체됨', $GLOBALS['__listing_terms'][602], [5, 6]);

// ── 4. [3-7 핵심 버그 수정] 빌딩 권역을 전부 지운 뒤 저장 -> 연결된 listing들의 stale 권역도 지워져야 한다 ──
// 수정 전에는 `empty($terms)`에서 그냥 return 해버려서 601/602가 [5,6]에 그대로 멈춰있는 버그였다.
$GLOBALS['__building_terms'][200] = [];
ol_cascade_region_to_listings(200);
check('역방향: 빌딩 권역 전체 삭제 시 매물1의 stale 권역도 정리됨', $GLOBALS['__listing_terms'][601], []);
check('역방향: 빌딩 권역 전체 삭제 시 매물2의 stale 권역도 정리됨', $GLOBALS['__listing_terms'][602], []);

// ── 5. 연결된 listing이 아예 없으면 아무 것도 건드리지 않고 조용히 종료 ──
$GLOBALS['__listings_of_building'][300] = [];
$GLOBALS['__building_terms'][300] = [7];
ol_cascade_region_to_listings(300); // 예외 없이 통과하면 성공
check('연결된 매물 없음: 에러 없이 종료', true, true);

printf("\n%d passed, %d failed\n", $pass, $fail);
if ($fail > 0) {
    exit(1);
}
