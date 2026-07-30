<?php
// URL Foundation (Sprint 01.5 3-1): building/office_region의 최종 한글 계층 URL 구성.
//
// 목표 형태:
//   권역 허브(부모)          : /강남사무실임대/
//   지역 허브(자식)          : /강남사무실임대/삼성동/
//   빌딩(부모+자식 모두 있음) : /강남사무실임대/삼성동/파르나스타워/
//   빌딩(부모만 있음)        : /강남사무실임대/파르나스타워/
//   빌딩(권역 미지정)        : /building/파르나스타워/ (기존 fallback, CPT 자체 rewrite가 그대로 처리)
//
// 설계 원칙:
// - post_type_link/term_link 필터만 새로 추가하고, get_permalink()/get_term_link()를 쓰는
//   기존 테마 템플릿 코드는 전혀 건드리지 않는다(WP가 내부적으로 이 필터를 거쳐가므로).
// - 오래된 URL(예: 권역 지정 전에 만들어진 /building/{slug}/, 또는 자식 term 배정 전 2단 URL)은
//   워드프레스 코어의 redirect_canonical()이 get_permalink()로 계산한 현재 canonical과 비교해
//   자동으로 301 리다이렉트한다 - 별도의 template_redirect 코드를 추가하지 않는다(중복 구현 방지).
// - rewrite rule은 실제 존재하는 부모/자식 slug만으로 정규식을 한정해, 사이트의 다른 라우트와
//   충돌할 위험을 최소화한다(범용 3단 와일드카드 rewrite를 쓰지 않음).
if (!defined('ABSPATH')) {
    exit;
}

/**
 * office_region 부모(최상위) term들의 slug 목록.
 * generate_rewrite_rules 훅(=flush 시점)에서만 쓰이므로 매 요청마다 쿼리하지 않는다.
 */
function ol_office_region_parent_slugs() {
    $terms = get_terms([
        'taxonomy' => 'office_region',
        'parent' => 0,
        'hide_empty' => false,
        'fields' => 'slugs',
    ]);
    return is_wp_error($terms) ? [] : array_filter(array_map('strval', $terms));
}

/**
 * office_region 자식(하위) term들의 slug 목록 (부모 구분 없이 평탄화).
 * 시딩 데이터상 자식 slug가 서로 겹치지 않는다는 전제.
 */
function ol_office_region_child_slugs() {
    $terms = get_terms([
        'taxonomy' => 'office_region',
        'hide_empty' => false,
        'fields' => 'id=>parent',
    ]);
    if (is_wp_error($terms)) {
        return [];
    }
    $child_ids = array_keys(array_filter($terms, function ($parent_id) {
        return (int) $parent_id !== 0;
    }));
    if (empty($child_ids)) {
        return [];
    }
    $child_terms = get_terms([
        'taxonomy' => 'office_region',
        'hide_empty' => false,
        'include' => $child_ids,
        'fields' => 'slugs',
    ]);
    return is_wp_error($child_terms) ? [] : array_filter(array_map('strval', $child_terms));
}

/**
 * building 포스트의 office_region term(부모/자식)을 반환.
 * @return array{parent: WP_Term|null, child: WP_Term|null}
 */
function ol_get_building_region_terms($building_id) {
    $terms = wp_get_object_terms($building_id, 'office_region');
    $out = ['parent' => null, 'child' => null];
    if (is_wp_error($terms) || empty($terms)) {
        return $out;
    }
    foreach ($terms as $term) {
        if ($term->parent) {
            $out['child'] = $term;
        } else {
            $out['parent'] = $term;
        }
    }
    if ($out['child'] && !$out['parent']) {
        $parent = get_term($out['child']->parent, 'office_region');
        if ($parent && !is_wp_error($parent)) {
            $out['parent'] = $parent;
        }
    }
    return $out;
}

// building 포스트의 permalink를 office_region 배정 상태에 따라 동적으로 구성.
// 권역이 전혀 배정되지 않은 빌딩은 $link를 그대로 반환 -> CPT 기본 rewrite(/building/{slug}/)로 폴백.
add_filter('post_type_link', 'ol_building_permalink', 10, 2);
function ol_building_permalink($link, $post) {
    if (!$post || 'building' !== $post->post_type) {
        return $link;
    }
    $regions = ol_get_building_region_terms($post->ID);
    if (!$regions['parent']) {
        return $link;
    }
    $segments = [$regions['parent']->slug];
    if ($regions['child']) {
        $segments[] = $regions['child']->slug;
    }
    $segments[] = $post->post_name;
    return home_url(user_trailingslashit(implode('/', $segments)));
}

// office_region term의 link를 부모/자식 계층에 맞는 한글 세그먼트로 구성.
add_filter('term_link', 'ol_region_term_link', 10, 3);
function ol_region_term_link($url, $term, $taxonomy) {
    if ('office_region' !== $taxonomy) {
        return $url;
    }
    if ($term->parent) {
        $parent = get_term($term->parent, 'office_region');
        if (!$parent || is_wp_error($parent)) {
            return $url;
        }
        return home_url(user_trailingslashit($parent->slug . '/' . $term->slug));
    }
    return home_url(user_trailingslashit($term->slug));
}

// 커스텀 rewrite rule 등록. flush_rewrite_rules() 실행 시(활성화/버전업 시에만) 딱 한 번씩 호출된다.
// 배열 순서 = 매칭 우선순위(먼저 등록된 것부터 시도) - 반드시 구체적인 규칙을 먼저 둔다:
//   1) 부모/자식/빌딩(3단)
//   2) 부모/자식(2단, 자식이 실제 office_region 자식 term slug일 때만) -> 지역 허브
//   3) 부모/빌딩(2단, 위에서 안 걸린 나머지) -> 자식 미배정 빌딩
//   4) 부모(1단) -> 권역 허브
add_action('generate_rewrite_rules', 'ol_generate_office_region_rewrite_rules');
function ol_generate_office_region_rewrite_rules($wp_rewrite) {
    $parents = ol_office_region_parent_slugs();
    if (empty($parents)) {
        return;
    }
    $parent_alt = implode('|', array_map(function ($slug) {
        return preg_quote($slug, '#');
    }, $parents));

    $new_rules = [];

    $new_rules['^(' . $parent_alt . ')/([^/]+)/([^/]+)/?$'] = 'index.php?building=$matches[3]';

    $children = ol_office_region_child_slugs();
    if (!empty($children)) {
        $child_alt = implode('|', array_map(function ($slug) {
            return preg_quote($slug, '#');
        }, $children));
        $new_rules['^(' . $parent_alt . ')/(' . $child_alt . ')/?$'] = 'index.php?office_region=$matches[2]';
    }

    $new_rules['^(' . $parent_alt . ')/([^/]+)/?$'] = 'index.php?building=$matches[2]';
    $new_rules['^(' . $parent_alt . ')/?$'] = 'index.php?office_region=$matches[1]';

    $wp_rewrite->rules = $new_rules + $wp_rewrite->rules;
}

// rewrite 로직 자체를 바꿀 때마다 이 버전을 올리면, 다음 요청에서 1회만 자동으로
// flush_rewrite_rules()가 실행된다(매 요청 flush는 매우 비용이 크므로 금지 - 버전 비교로만 실행).
define('OL_PERMALINKS_VERSION', 1);
add_action('init', 'ol_maybe_flush_permalinks', 30);
function ol_maybe_flush_permalinks() {
    if ((int) get_option('ol_permalinks_version') === OL_PERMALINKS_VERSION) {
        return;
    }
    flush_rewrite_rules();
    update_option('ol_permalinks_version', OL_PERMALINKS_VERSION);
}
