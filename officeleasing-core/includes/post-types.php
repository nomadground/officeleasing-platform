<?php
// building/listing CPT 등록. URL rewrite는 임시 슬러그 - office_region 계층 경로를 반영하는
// 커스텀 rewrite/템플릿 분기는 다음 "URL" 단계에서 별도로 구현한다.
if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'ol_register_post_types');
function ol_register_post_types() {
    register_post_type('building', [
        'label' => '빌딩',
        'labels' => [
            'name' => '빌딩',
            'singular_name' => '빌딩',
            'add_new_item' => '빌딩 추가',
            'edit_item' => '빌딩 수정',
            'all_items' => '전체 빌딩',
            'search_items' => '빌딩 검색',
            'not_found' => '등록된 빌딩이 없습니다',
        ],
        'public' => true,
        'show_in_rest' => true,
        'supports' => ['title', 'thumbnail'],
        // 전체 목록(archive-building.php)을 /사무실임대/에서 노출한다.
        // (한글 계층 URL /강남사무실임대/삼성동/…은 별도 rewrite 라운드에서 처리)
        'has_archive' => '사무실임대',
        'rewrite' => ['slug' => 'building', 'with_front' => false],
        'taxonomies' => ['office_region'],
        'menu_icon' => 'dashicons-building',
        'menu_position' => 20,
    ]);

    register_post_type('listing', [
        'label' => '매물',
        'labels' => [
            'name' => '매물',
            'singular_name' => '매물',
            'add_new_item' => '매물 추가',
            'edit_item' => '매물 수정',
            'all_items' => '전체 매물',
            'search_items' => '매물 검색',
            'not_found' => '등록된 매물이 없습니다',
        ],
        'public' => true,
        'show_in_rest' => true,
        'supports' => ['title'],
        'has_archive' => false,
        'rewrite' => ['slug' => 'listing', 'with_front' => false],
        'taxonomies' => ['office_region', 'listing_feature'],
        'menu_icon' => 'dashicons-media-document',
        'menu_position' => 21,
    ]);
}
