<?php
// 아카이브/허브 메인쿼리 보정.
// - office_region 아카이브(허브)는 building만 노출하고(taxonomy가 listing에도 붙어있어 기본은 둘 다 나옴),
//   상위 권역 term에서는 하위 동(자식 term)의 building까지 포함한다(include_children).
// - building 아카이브(/사무실임대/)와 office_region 아카이브의 페이지당 개수를 통일한다.
if (!defined('ABSPATH')) {
    exit;
}

const OL_ARCHIVE_PER_PAGE = 12;

add_action('pre_get_posts', 'ol_adjust_archive_queries');
function ol_adjust_archive_queries($query) {
    if (is_admin() || !$query->is_main_query()) {
        return;
    }

    // 전체 building 아카이브
    if ($query->is_post_type_archive('building')) {
        $query->set('posts_per_page', OL_ARCHIVE_PER_PAGE);
        return;
    }

    // office_region 허브 (권역/동)
    if ($query->is_tax('office_region')) {
        $query->set('post_type', 'building');
        $query->set('posts_per_page', OL_ARCHIVE_PER_PAGE);

        // 상위 권역 term이면 하위 동 term의 building까지 포함
        $term = $query->get_queried_object();
        if ($term && !is_wp_error($term) && (int) $term->parent === 0) {
            $query->set('tax_query', [[
                'taxonomy'         => 'office_region',
                'field'            => 'term_id',
                'terms'            => (int) $term->term_id,
                'include_children' => true,
            ]]);
        }
    }
}
