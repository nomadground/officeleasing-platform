<?php
/**
 * Plugin Name: OfficeLeasing Core
 * Description: building/listing 데이터 모델, ACF 필드 동기화, 계산·검증 로직. WPCode 스니펫을 대체하는 단일 플러그인.
 * Version: 0.1.0
 * Requires Plugins: advanced-custom-fields
 */
if (!defined('ABSPATH')) {
    exit;
}

define('OL_CORE_DIR', plugin_dir_path(__FILE__));
define('OL_CORE_URL', plugin_dir_url(__FILE__));
define('OL_CORE_FILE', __FILE__);

add_action('plugins_loaded', 'ol_bootstrap');
function ol_bootstrap() {
    // CPT/Taxonomy는 ACF 유무와 무관하게 항상 등록 (콘텐츠 구조는 ACF에 의존하지 않음)
    require_once OL_CORE_DIR . 'includes/post-types.php';
    require_once OL_CORE_DIR . 'includes/taxonomies.php';

    if (!function_exists('get_field')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>OfficeLeasing Core</strong>: '
                . 'Advanced Custom Fields 플러그인이 활성화되어야 정상 동작합니다.</p></div>';
        });
        return;
    }

    require_once OL_CORE_DIR . 'includes/acf-json.php';
    require_once OL_CORE_DIR . 'includes/permalinks.php';
    require_once OL_CORE_DIR . 'includes/helpers.php';
    require_once OL_CORE_DIR . 'includes/maps.php';
    require_once OL_CORE_DIR . 'includes/admin-map-picker.php';
    require_once OL_CORE_DIR . 'includes/calculations.php';
    require_once OL_CORE_DIR . 'includes/region-sync.php';
    require_once OL_CORE_DIR . 'includes/building-cache.php';
    require_once OL_CORE_DIR . 'includes/cache-rebuild.php';
    require_once OL_CORE_DIR . 'includes/save-hooks.php';
    require_once OL_CORE_DIR . 'includes/save-api.php';
    require_once OL_CORE_DIR . 'includes/archive-query.php';
    require_once OL_CORE_DIR . 'includes/home-query.php';
    require_once OL_CORE_DIR . 'includes/schema-home.php';
    require_once OL_CORE_DIR . 'includes/schema.php';
    require_once OL_CORE_DIR . 'includes/schema-hub.php';
    require_once OL_CORE_DIR . 'includes/validation.php';
    require_once OL_CORE_DIR . 'includes/admin-columns.php';
    require_once OL_CORE_DIR . 'includes/admin-hidden-fields.php';
    require_once OL_CORE_DIR . 'includes/admin-summary-box.php';
}

register_activation_hook(__FILE__, function () {
    require_once OL_CORE_DIR . 'includes/post-types.php';
    require_once OL_CORE_DIR . 'includes/taxonomies.php';
    ol_register_post_types();
    ol_register_taxonomies();
    // 마이그레이션을 먼저 실행해 기존 'GBD' 등 영문 term이 있으면 한글 이름으로 바꾸고,
    // 그 다음 시딩해야 seed가 같은 부모를 중복 생성하지 않는다(seed는 한글 이름 기준으로 존재 여부를 확인함).
    ol_migrate_office_region_names();
    update_option('ol_office_region_names_migrated', 1);
    ol_seed_office_regions();
    update_option('ol_office_region_seeded', 1);
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});
