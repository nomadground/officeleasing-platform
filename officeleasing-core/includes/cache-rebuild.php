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

// WP-CLI: wp officeleasing rebuild-cache
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('officeleasing rebuild-cache', function () {
        $count = ol_rebuild_all_building_cache();
        WP_CLI::success("{$count}개 빌딩의 집계 캐시를 재생성했습니다.");
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
