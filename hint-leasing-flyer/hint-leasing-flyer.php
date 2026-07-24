<?php
/**
 * Plugin Name: HINT Leasing Flyer
 * Description: 임대매물 전달용 Leasing Flyer. 발행 시점 조건을 스냅샷으로 저장하고 /list/ 공개 URL로 공유한다. officeleasing-core에 의존하지 않고 단독 동작한다.
 * Version: 0.4.0-beta.8
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: HINT
 * Text Domain: hint-leasing-flyer
 *
 * Requires PHP 8.0 근거: includes/ 전체에 union return type(int|WP_Error, bool|WP_Error 등,
 * class-hlf-flyer-repository.php/class-hlf-item-repository.php)을 실제로 쓰고 있어 PHP 8.0
 * 미만에서는 파싱 자체가 실패한다 — 이 헤더가 있으면 그 전에 워드프레스가 활성화를 막고 안내를
 * 띄운다(치명적 에러 대신). Requires at least는 officeleasing-core.php에 별도 명시가 없어
 * 이 플러그인이 실제로 쓰는 API(register_post_meta 콜백 배열, WP_REST_Server, 커스텀
 * post status) 기준으로 넉넉히 잡은 보수적 하한이다.
 *
 * 설계 근거:
 * - officeleasing-core.php의 부트스트랩 패턴(plugins_loaded → require, 활성화 훅에서 CPT 등록 + flush)을 참고했다.
 * - 단, HLF는 officeleasing-core / ACF가 없어도 활성화·동작해야 하므로 데이터 접근은 전부 워드프레스
 *   네이티브 함수(get_post_meta/register_post_meta)로만 처리한다. ACF는 있으면 편집 UI로만 얹힌다(acf-json).
 * - 함수/클래스 프리픽스는 hlf_ / HLF_ (core의 ol_, 테마의 olt_ 와 충돌하지 않음).
 */

defined( 'ABSPATH' ) || exit;

define( 'HLF_VERSION', '0.4.0-beta.8' );
define( 'HLF_FILE', __FILE__ );
define( 'HLF_DIR', plugin_dir_path( __FILE__ ) );
define( 'HLF_URL', plugin_dir_url( __FILE__ ) );

// rewrite 규칙을 바꿀 때마다 올린다 → 다음 요청에서 1회만 자동 flush (permalinks.php의 버전비교 패턴과 동일).
// 2: /listad/ 직원 포털 rewrite 규칙 추가(HLF_Portal).
// 3: 요청서 7 — 공개 프리픽스 /listup/ -> /list/(옛 /listup/ 링크는 별칭으로 계속 라우팅,
//    HLF_Routes 참고), 직원 포털 /listad/ -> /listup/(HLF_Portal).
define( 'HLF_REWRITE_VERSION', 3 );

require_once HLF_DIR . 'includes/class-hlf-calculations.php';
require_once HLF_DIR . 'includes/class-hlf-display-helpers.php';
require_once HLF_DIR . 'includes/class-hlf-post-types.php';
require_once HLF_DIR . 'includes/class-hlf-capabilities.php';
require_once HLF_DIR . 'includes/class-hlf-meta-schema.php';
require_once HLF_DIR . 'includes/class-hlf-flyer-item-service.php';
require_once HLF_DIR . 'includes/class-hlf-flyer-repository.php';
require_once HLF_DIR . 'includes/class-hlf-item-repository.php';
require_once HLF_DIR . 'includes/class-hlf-source-listing-repository.php';
require_once HLF_DIR . 'includes/class-hlf-contact-directory.php';
require_once HLF_DIR . 'includes/class-hlf-officeleasing-mapper.php';
require_once HLF_DIR . 'includes/class-hlf-officeleasing-search.php';
require_once HLF_DIR . 'includes/class-hlf-officeleasing-import-service.php';
require_once HLF_DIR . 'includes/class-hlf-image-pipeline.php';
require_once HLF_DIR . 'includes/class-hlf-routes.php';
require_once HLF_DIR . 'includes/class-hlf-rest-controller.php';
require_once HLF_DIR . 'includes/class-hlf-admin-ui.php';
require_once HLF_DIR . 'includes/class-hlf-portal.php';
require_once HLF_DIR . 'includes/class-hlf-plugin.php';

add_action( 'plugins_loaded', array( 'HLF_Plugin', 'boot' ) );

/**
 * 활성화: CPT/상태/capability를 등록한 뒤 rewrite를 flush한다.
 * (officeleasing-core.php의 register_activation_hook 흐름과 동일한 순서.)
 */
register_activation_hook( __FILE__, function () {
	HLF_Post_Types::register();
	HLF_Meta_Schema::register();
	HLF_Routes::add_rewrite_rules();
	HLF_Portal::add_rewrite_rules();
	HLF_Capabilities::add_caps();
	update_option( 'hlf_rewrite_version', HLF_REWRITE_VERSION );
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function () {
	flush_rewrite_rules();
} );
