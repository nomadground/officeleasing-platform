<?php
// office_region(계층형, building+listing 공용) / listing_feature(비계층형, listing 전용) 등록.
// listing 화면에서는 office_region이 자동 동기화 전용이므로 입력 메타박스를 숨긴다.
if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'ol_register_taxonomies', 5);
function ol_register_taxonomies() {
    register_taxonomy('office_region', ['building', 'listing'], [
        'label' => '권역',
        'labels' => [
            'name' => '권역',
            'singular_name' => '권역',
        ],
        'hierarchical' => true,
        'public' => true,
        'show_in_rest' => true,
        'show_admin_column' => false, // 관리자 목록 컬럼은 admin-columns.php에서 별도 구성
        // URL은 permalinks.php의 term_link 필터 + 커스텀 rewrite rule이 전담한다.
        // 여기서 자동 rewrite를 켜두면 /office-region/{slug}/ 형태가 별도로 살아남아
        // 같은 term에 대해 두 개의 URL(중복 콘텐츠)이 생기므로 반드시 false로 끈다.
        'rewrite' => false,
    ]);

    register_taxonomy('listing_feature', ['listing'], [
        'label' => '매물 특징',
        'labels' => [
            'name' => '매물 특징',
            'singular_name' => '특징',
        ],
        'hierarchical' => false,
        'public' => true,
        'show_in_rest' => true,
        'rewrite' => ['slug' => 'feature'],
    ]);
}

// listing 편집화면에서 office_region 입력 메타박스 숨김 (건물 저장 시 자동 세팅되므로 관리자 직접 입력 금지)
add_action('add_meta_boxes', function () {
    remove_meta_box('office_regiondiv', 'listing', 'side');
}, 20);

// URL Foundation(3-1) 적용 전 이미 'GBD'/'CBD'/'YBD'/'ETC'라는 영문 코드로 부모 term이 심어져
// 있던 사이트를 위한 1회성 이름/슬러그 마이그레이션. 신규 설치는 ol_seed_office_regions()가
// 처음부터 한글 이름으로 심으므로 이 함수는 아무것도 찾지 못하고 조용히 종료된다.
// 반드시 시딩(아래, priority 21)보다 먼저 실행해야 한다 - 순서가 바뀌면 시딩이 먼저 새 한글
// 이름으로 term을 만들어버리고, 마이그레이션이 그 다음 레거시 term을 같은 이름으로 바꾸려다
// 슬러그 중복(자동 -2 접미사)으로 두 개의 "같은" 부모 term이 생기는 사고로 이어진다.
add_action('init', 'ol_maybe_migrate_office_region_names', 20);
function ol_maybe_migrate_office_region_names() {
    if (get_option('ol_office_region_names_migrated')) {
        return;
    }
    ol_migrate_office_region_names();
    update_option('ol_office_region_names_migrated', 1);
}

// 권역 부모/자식 초기 term 시딩. 옵션 플래그로 1회만 실행 (활성화 훅 타이밍 이슈 회피, term_exists로도 이중 방지)
add_action('init', 'ol_maybe_seed_office_regions', 21);
function ol_maybe_seed_office_regions() {
    if (get_option('ol_office_region_seeded')) {
        return;
    }
    ol_seed_office_regions();
    update_option('ol_office_region_seeded', 1);
}

function ol_office_region_parent_name_map() {
    return [
        'GBD' => '강남사무실임대',
        'CBD' => '도심권사무실임대',
        'YBD' => '여의도사무실임대',
        'ETC' => '기타권역사무실임대',
    ];
}

function ol_migrate_office_region_names() {
    foreach (ol_office_region_parent_name_map() as $old_name => $new_name) {
        $term = get_term_by('name', $old_name, 'office_region');
        if (!$term || is_wp_error($term) || $term->parent) {
            continue; // 이미 마이그레이션됐거나, 동명의 자식 term(있을 수 없지만 방어)이면 건너뜀
        }
        wp_update_term($term->term_id, 'office_region', [
            'name' => $new_name,
            'slug' => $new_name,
        ]);
    }
}

function ol_seed_office_regions() {
    $tree = [
        '강남사무실임대' => ['역삼동', '논현동', '삼성동', '대치동', '신사동', '청담동'],
        '도심권사무실임대' => ['종로구', '중구'],
        '여의도사무실임대' => ['여의도', '영등포'],
        '기타권역사무실임대' => ['서초구', '성수동', '송파구', '용산구'],
    ];
    foreach ($tree as $parent_name => $children) {
        $existing = term_exists($parent_name, 'office_region');
        $parent_id = $existing ? (int) $existing['term_id'] : 0;
        if (!$parent_id) {
            $inserted = wp_insert_term($parent_name, 'office_region');
            if (is_wp_error($inserted)) {
                continue;
            }
            $parent_id = (int) $inserted['term_id'];
        }
        foreach ($children as $child_name) {
            if (!term_exists($child_name, 'office_region', $parent_id)) {
                wp_insert_term($child_name, 'office_region', ['parent' => $parent_id]);
            }
        }
    }
}
