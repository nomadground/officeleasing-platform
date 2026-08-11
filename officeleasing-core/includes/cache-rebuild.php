<?php
// building-cache.php는 매물 저장/삭제 "시점"부터 캐시를 채운다. 플러그인을 처음 설치했거나
// 이미 매물이 쌓여있는 상태에서 이 기능을 추가한 경우, 기존 빌딩들의 캐시 필드는 0으로
// 남아있을 수 있다. 이 파일은 전체 빌딩을 순회하며 캐시를 일괄 재계산하는 진입점 2개를 제공한다:
// (1) WP-CLI 명령 (2) 관리자 화면 수동 트리거(SSH/WP-CLI 접근이 없는 호스팅 환경 대비).
if (!defined('ABSPATH')) {
    exit;
}

function ol_rebuild_all_building_cache() {
    $building_ids = get_posts([
        'post_type' => 'building',
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
    ]);
    foreach ($building_ids as $id) {
        ol_recount_building_cache($id);
    }
    return count($building_ids);
}

// [listing-detail-ux-pass2 리뷰 지적, 실제 갭 확인] ol_calculate_listing_fields()(계산.php)는
// save_post_listing 훅으로만 실행된다 - 새 파생 필드(예: deposit_per_lease_pyeong)를 추가해도
// 배포 이전부터 있던 매물은 다시 저장(수정)되기 전까지 그 필드가 비어있는 채로 남는다.
// 위 rebuild-cache는 building 집계만 재계산할 뿐 이 문제를 해결하지 못한다 - listing 자체의
// 파생 필드를 다시 계산하는 별도 진입점이 필요하다.
function ol_rebuild_all_listing_fields() {
    $listing_ids = get_posts([
        'post_type' => 'listing',
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
    ]);
    foreach ($listing_ids as $id) {
        ol_calculate_listing_fields($id);
    }
    return count($listing_ids);
}

// WP-CLI: wp officeleasing rebuild-cache
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('officeleasing rebuild-cache', function () {
        $count = ol_rebuild_all_building_cache();
        WP_CLI::success("{$count}개 빌딩의 집계 캐시를 재생성했습니다.");
    });
    // WP-CLI: wp officeleasing rebuild-listing-fields
    // 새 파생 필드를 추가한 배포 직후 1회 실행 권장(이번 deposit_per_lease_pyeong이 첫 사례).
    // building 캐시는 원본 raw 필드(deposit_amount/monthly_rent 등)만 읽으므로, 이 명령이 그 값
    // 자체를 바꾸는 경우는 거의 없지만 두 명령을 함께 실행해도 안전하다(멱등 - 여러 번 실행해도 결과 동일).
    WP_CLI::add_command('officeleasing rebuild-listing-fields', function () {
        $count = ol_rebuild_all_listing_fields();
        WP_CLI::success("{$count}개 매물의 파생 필드(평 환산·평당가 등)를 재계산했습니다.");
    });
}

// 관리자 화면 수동 트리거 - 빌딩 목록 상단에 안내 + 버튼
add_action('admin_post_ol_rebuild_cache', 'ol_handle_rebuild_cache_request');
function ol_handle_rebuild_cache_request() {
    if (!current_user_can('manage_options')) {
        wp_die('권한이 없습니다.', 403);
    }
    check_admin_referer('ol_rebuild_cache');

    $count = ol_rebuild_all_building_cache();

    wp_safe_redirect(add_query_arg(
        'ol_cache_rebuilt',
        $count,
        admin_url('edit.php?post_type=building')
    ));
    exit;
}

add_action('admin_notices', 'ol_render_rebuild_cache_notice');
function ol_render_rebuild_cache_notice() {
    $screen = get_current_screen();
    if (!$screen || 'building' !== $screen->post_type || 'edit' !== $screen->base) {
        return;
    }
    if (!current_user_can('manage_options')) {
        return;
    }
    if (isset($_GET['ol_cache_rebuilt'])) {
        printf(
            '<div class="notice notice-success is-dismissible"><p>%d개 빌딩의 매물 집계 캐시를 재생성했습니다.</p></div>',
            (int) $_GET['ol_cache_rebuilt']
        );
        return;
    }
    $url = wp_nonce_url(admin_url('admin-post.php?action=ol_rebuild_cache'), 'ol_rebuild_cache');
    printf(
        '<div class="notice notice-info"><p>매물 집계 캐시(활성매물수·층수/면적/보증금/임대료/관리비 범위)가 실제 데이터와 안 맞아 보이면 '
            . '<a href="%s">지금 전체 재생성</a>할 수 있습니다. 플러그인 설치 직후나 대량 데이터 입력 후 1회 실행을 권장합니다.</p></div>',
        esc_url($url)
    );
}

// 관리자 화면 수동 트리거 - 매물 목록 상단에 안내 + 버튼 (SSH/WP-CLI 접근이 없는 호스팅 환경 대비,
// 위 building 캐시 트리거와 동일한 패턴). 새 파생 필드를 추가한 배포 직후 사용을 염두에 둔다.
add_action('admin_post_ol_rebuild_listing_fields', 'ol_handle_rebuild_listing_fields_request');
function ol_handle_rebuild_listing_fields_request() {
    if (!current_user_can('manage_options')) {
        wp_die('권한이 없습니다.', 403);
    }
    check_admin_referer('ol_rebuild_listing_fields');

    $count = ol_rebuild_all_listing_fields();

    wp_safe_redirect(add_query_arg(
        'ol_listing_fields_rebuilt',
        $count,
        admin_url('edit.php?post_type=listing')
    ));
    exit;
}

add_action('admin_notices', 'ol_render_rebuild_listing_fields_notice');
function ol_render_rebuild_listing_fields_notice() {
    $screen = get_current_screen();
    if (!$screen || 'listing' !== $screen->post_type || 'edit' !== $screen->base) {
        return;
    }
    if (!current_user_can('manage_options')) {
        return;
    }
    if (isset($_GET['ol_listing_fields_rebuilt'])) {
        printf(
            '<div class="notice notice-success is-dismissible"><p>%d개 매물의 파생 필드를 재계산했습니다.</p></div>',
            (int) $_GET['ol_listing_fields_rebuilt']
        );
        return;
    }
    $url = wp_nonce_url(admin_url('admin-post.php?action=ol_rebuild_listing_fields'), 'ol_rebuild_listing_fields');
    printf(
        '<div class="notice notice-info"><p>매물 파생 필드(평 환산·평당가 등)가 실제 값과 안 맞아 보이면 '
            . '<a href="%s">지금 전체 재계산</a>할 수 있습니다. 새 자동계산 필드를 추가한 배포 직후 1회 실행을 권장합니다.</p></div>',
        esc_url($url)
    );
}
