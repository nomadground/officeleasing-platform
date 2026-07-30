<?php
// Home(front-page) 권역 섹션이 쓰는 빌딩 조회 로직. 테마는 이 함수가 돌려주는 ID 배열만 렌더한다.
//
// 선정 기준(Home V1 확정 - building_featured 추천 플래그는 이번 범위에서 제외):
//   1) 활성 매물 1개 이상 (building_active_listing_count > 0 캐시필드로 판정, 매물 재쿼리 없음)
//   2) 최근 확인일 내림차순 (building_last_verified_at, 활성 매물 verified_at의 최댓값)
//   3) 세부 지역 다양성 (한 동이 결과의 절반을 넘지 않도록 - 데이터가 부족하면 강제하지 않음)
//   4) 최근 수정일 내림차순
//
// 성능: 권역당 쿼리 1번(+term 캐시 프라임 1번)만 쓴다. 카드 렌더 시 매물 재쿼리가 발생하지 않도록
// building의 집계 캐시 필드만 사용하는 것이 전제(building-cache.php가 채움).
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Home 캐시 무효화용 버전 카운터.
 * 어떤 term의 캐시를 지워야 하는지 일일이 추적하지 않는다 - 빌딩이 권역을 옮기면 "이전 권역"과
 * "새 권역" 양쪽이 동시에 바뀌고, term 삭제/이동까지 고려하면 개별 무효화는 놓치기 쉽다.
 * 카운터를 1 올리면 모든 권역 캐시 키가 한 번에 무효가 되므로 정확하고 비용도 거의 없다.
 */
function ol_home_cache_version() {
    return (int) get_option('ol_home_cache_version', 1);
}

function ol_bump_home_cache_version() {
    update_option('ol_home_cache_version', ol_home_cache_version() + 1);
}

// 무효화 트리거: 빌딩/매물 저장·삭제·상태변경, 권역(term) 변경·삭제, 빌딩의 권역 재배정.
// TTL만으로 최신성을 보장하지 않는다(TTL은 안전망일 뿐).
add_action('save_post_building', 'ol_bump_home_cache_version');
add_action('save_post_listing', 'ol_bump_home_cache_version');
add_action('edited_office_region', 'ol_bump_home_cache_version');
add_action('created_office_region', 'ol_bump_home_cache_version');
add_action('delete_office_region', 'ol_bump_home_cache_version');

// 삭제/휴지통/복원은 모든 post_type에서 발화하므로 building/listing일 때만 버전을 올린다.
add_action('trashed_post', 'ol_bump_home_cache_on_post_change');
add_action('untrashed_post', 'ol_bump_home_cache_on_post_change');
add_action('before_delete_post', 'ol_bump_home_cache_on_post_change');
function ol_bump_home_cache_on_post_change($post_id) {
    if (in_array(get_post_type($post_id), ['building', 'listing'], true)) {
        ol_bump_home_cache_version();
    }
}

// 빌딩의 권역 재배정(term 관계 변경). set_object_terms는 모든 taxonomy에서 발화하므로 office_region만 걸러낸다.
add_action('set_object_terms', 'ol_bump_home_cache_on_term_change', 10, 4);
function ol_bump_home_cache_on_term_change($object_id, $terms, $tt_ids, $taxonomy) {
    if ('office_region' === $taxonomy) {
        ol_bump_home_cache_version();
    }
}

/**
 * 권역(부모 term) 하나에 대해 Home에 노출할 빌딩 ID 배열을 반환.
 *
 * @param int   $term_id office_region 부모 term ID
 * @param int   $limit   최대 개수 (Home V1은 8)
 * @param array $args    확장 슬롯. 지원: 'use_cache' => bool
 *                       (향후 'featured_first' => true 같은 옵션을 여기에 추가해 인터페이스를 깨지 않고 확장)
 * @return int[] building post ID 배열
 */
function ol_get_home_region_buildings($term_id, $limit = 8, array $args = []) {
    $term_id = (int) $term_id;
    $limit = max(1, (int) $limit);
    if (!$term_id) {
        return [];
    }

    $use_cache = !isset($args['use_cache']) || $args['use_cache'];
    $cache_key = 'ol_home_bld_' . ol_home_cache_version() . '_' . $term_id . '_' . $limit;

    if ($use_cache) {
        $cached = get_transient($cache_key);
        // 빈 배열도 유효한 결과이므로 false(=미스)와 구분해야 한다.
        if (is_array($cached)) {
            return $cached;
        }
    }

    $ids = ol_query_home_region_buildings($term_id, $limit);

    if ($use_cache) {
        // 캐시 실패(transient 저장 불가)여도 위에서 이미 실시간 결과를 얻었으므로 그대로 반환된다.
        set_transient($cache_key, $ids, 12 * HOUR_IN_SECONDS);
    }
    return $ids;
}

/**
 * 실제 쿼리 + 정렬 + 지역 다양성 적용. 캐시 계층 없이 항상 실시간 조회한다.
 * ol_get_home_region_buildings()의 캐시 미스 경로이자, 캐시를 우회하고 싶을 때의 fallback.
 */
function ol_query_home_region_buildings($term_id, $limit = 8) {
    $term_id = (int) $term_id;
    $limit = max(1, (int) $limit);

    // 다양성 조정을 하려면 limit보다 넉넉한 후보 풀이 필요하다(한 동에 몰린 걸 뒤 순위 빌딩으로 대체).
    // 상한을 둬서 데이터가 많아져도 쿼리 비용이 선형으로 늘지 않게 한다.
    $pool_size = min($limit * 3, 40);

    // post_modified 정렬은 WP가 SQL에서 처리(meta orderby를 쓰지 않는 이유는 아래 주석 참고).
    $candidates = get_posts([
        'post_type' => 'building',
        'post_status' => 'publish',
        'posts_per_page' => $pool_size,
        'orderby' => 'modified',
        'order' => 'DESC',
        'no_found_rows' => true,
        'ignore_sticky_posts' => true,
        'tax_query' => [[
            'taxonomy' => 'office_region',
            'field' => 'term_id',
            'terms' => $term_id,
            'include_children' => true,
        ]],
        'meta_query' => [[
            'key' => 'building_active_listing_count',
            'value' => 0,
            'compare' => '>',
            'type' => 'NUMERIC',
        ]],
    ]);

    if (empty($candidates)) {
        return [];
    }

    // 최근 확인일 정렬을 meta orderby로 하지 않는 이유: building_last_verified_at이 아직 비어있는
    // 빌딩(검수 워크플로우 미가동)이 많아 meta orderby가 그 빌딩들을 통째로 밀어내거나 순서를
    // 뒤섞을 수 있다. 후보 풀이 40개 이하로 한정돼 있으니 PHP에서 정렬하는 편이 정확하고 안전하다.
    $ids = wp_list_pluck($candidates, 'ID');
    // WP_Query가 update_post_term_cache 기본값(true)으로 이미 term 캐시를 채우지만, get_posts 인자를
    // 나중에 손대다가 그게 꺼지면 아래 지역 판정과 카드 렌더링이 곧바로 N+1이 된다. 이미 캐시에 있으면
    // 이 호출은 쿼리를 만들지 않으므로(캐시된 ID는 건너뜀) 안전망으로 명시해 둔다.
    update_object_term_cache($ids, 'building');

    $rows = [];
    foreach ($candidates as $post) {
        $rows[] = [
            'id' => (int) $post->ID,
            'verified' => (int) get_post_meta($post->ID, 'building_last_verified_at', true),
            'modified' => (string) $post->post_modified_gmt,
            'district' => ol_get_building_district_term_id($post->ID),
        ];
    }

    usort($rows, function ($a, $b) {
        if ($a['verified'] !== $b['verified']) {
            return $b['verified'] <=> $a['verified']; // 최근 확인일 내림차순
        }
        return strcmp($b['modified'], $a['modified']); // 동률이면 최근 수정일 내림차순
    });

    return ol_apply_district_diversity($rows, $limit);
}

/**
 * 빌딩이 속한 "자식" office_region term ID (동/구). 부모만 배정된 경우 0.
 * update_object_term_cache()가 선행 호출되어 있으면 추가 쿼리 없이 캐시에서 읽는다.
 */
function ol_get_building_district_term_id($building_id) {
    $terms = get_the_terms($building_id, 'office_region');
    if (is_wp_error($terms) || empty($terms)) {
        return 0;
    }
    foreach ($terms as $term) {
        if ($term->parent) {
            return (int) $term->term_id;
        }
    }
    return 0;
}

/**
 * 한 세부 지역이 결과의 절반을 넘지 않게 조정한다.
 * 1차 통과에서 동별 상한(ceil(limit/2))까지만 담고, 자리가 남으면 2차 통과에서 넘친 빌딩을 순서대로
 * 채운다 - 즉 해당 권역에 실제 데이터가 부족하면 상한을 강제하지 않는다(빈 카드를 만들지 않는 원칙).
 * 추가 쿼리 없이 이미 확보한 배열만 재배치하므로 비용이 없다.
 */
function ol_apply_district_diversity(array $rows, $limit) {
    $cap = (int) ceil($limit / 2);
    $picked = [];
    $per_district = [];
    $overflow = [];

    foreach ($rows as $row) {
        if (count($picked) >= $limit) {
            break;
        }
        $d = $row['district'];
        // district 0(세부지역 미배정)은 서로 다른 지역으로 취급하지 않고 상한을 적용하지 않는다.
        if ($d) {
            $used = isset($per_district[$d]) ? $per_district[$d] : 0;
            if ($used >= $cap) {
                $overflow[] = $row;
                continue;
            }
            $per_district[$d] = $used + 1;
        }
        $picked[] = (int) $row['id'];
    }

    foreach ($overflow as $row) {
        if (count($picked) >= $limit) {
            break;
        }
        $picked[] = (int) $row['id'];
    }

    return array_values(array_unique($picked));
}
