<?php
/**
 * Plugin Name: HINT Leasing Flyer
 * Description: 임대매물 전달용 Leasing Flyer. 발행 시점 조건을 스냅샷으로 저장하고 /listup/ 공개 URL로 공유한다. officeleasing-core에 의존하지 않고 단독 동작한다.
 * Version: 0.1.0
 * Author: HINT
 * Text Domain: hint-leasing-flyer
 *
 * 설계 근거:
 * - officeleasing-core.php의 부트스트랩 패턴(plugins_loaded → require, 활성화 훅에서 CPT 등록 + flush)을 참고했다.
 * - 단, HLF는 officeleasing-core / ACF가 없어도 활성화·동작해야 하므로 데이터 접근은 전부 워드프레스
 *   네이티브 함수(get_post_meta/register_post_meta)로만 처리한다. ACF는 있으면 편집 UI로만 얹힌다(acf-json).
 * - 함수/클래스 프리픽스는 hlf_ / HLF_ (core의 ol_, 테마의 olt_ 와 충돌하지 않음).
 */

defined( 'ABSPATH' ) || exit;

define( 'HLF_VERSION', '0.1.0' );
define( 'HLF_FILE', __FILE__ );
define( 'HLF_DIR', plugin_dir_path( __FILE__ ) );
define( 'HLF_URL', plugin_dir_url( __FILE__ ) );

// rewrite 규칙을 바꿀 때마다 올린다 → 다음 요청에서 1회만 자동 flush (permalinks.php의 버전비교 패턴과 동일).
define( 'HLF_REWRITE_VERSION', 1 );

require_once HLF_DIR . 'includes/class-hlf-calculations.php';
require_once HLF_DIR . 'includes/class-hlf-post-types.php';
require_once HLF_DIR . 'includes/class-hlf-capabilities.php';
require_once HLF_DIR . 'includes/class-hlf-meta-schema.php';
require_once HLF_DIR . 'includes/class-hlf-flyer-repository.php';
require_once HLF_DIR . 'includes/class-hlf-item-repository.php';
require_once HLF_DIR . 'includes/class-hlf-officeleasing-mapper.php';
require_once HLF_DIR . 'includes/class-hlf-routes.php';
require_once HLF_DIR . 'includes/class-hlf-rest-controller.php';
require_once HLF_DIR . 'includes/class-hlf-plugin.php';

add_action( 'plugins_loaded', array( 'HLF_Plugin', 'boot' ) );

/**
 * 활성화: CPT/상태/capability를 등록한 뒤 rewrite를 flush한다.
 * (officeleasing-core.php의 register_activation_hook 흐름과 동일한 순서.)
 */
register_activation_hook( __FILE__, function () {
	HLF_Post_Types::register();
	HLF_Routes::add_rewrite_rules();
	HLF_Capabilities::add_caps();
	update_option( 'hlf_rewrite_version', HLF_REWRITE_VERSION );
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function () {
	flush_rewrite_rules();
} );
