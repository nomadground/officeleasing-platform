<?php
/**
 * 부트스트랩 오케스트레이션. plugins_loaded 이후 각 서브시스템을 init에 연결한다.
 * officeleasing-core의 ol_bootstrap()과 같은 역할이되, ACF 유무와 무관하게 전부 동작한다.
 */
defined( 'ABSPATH' ) || exit;

final class HLF_Plugin {

	public static function boot(): void {
		// CPT/상태/메타/capability는 ACF 없이도 항상 등록한다.
		add_action( 'init', array( 'HLF_Post_Types', 'register' ), 5 );
		add_action( 'init', array( 'HLF_Meta_Schema', 'register' ), 6 );

		// URL(rewrite) + 공개 템플릿 라우팅.
		HLF_Routes::init();

		// 관리자 전용 REST API.
		add_action( 'rest_api_init', array( 'HLF_REST_Controller', 'register_routes' ) );

		// wp-admin "Leasing Flyer" 메뉴(목록/편집 화면). 데이터는 위 REST를 그대로 호출한다.
		HLF_Admin_UI::init();

		// ACF가 있으면 flyer item 편집용 필드그룹 json을 추가 로드(선택적, 의존 아님).
		add_filter( 'acf/settings/load_json', array( __CLASS__, 'maybe_add_acf_json_path' ) );

		load_plugin_textdomain( 'hint-leasing-flyer', false, dirname( plugin_basename( HLF_FILE ) ) . '/languages' );
	}

	/**
	 * ACF가 활성화된 경우에만 acf-json 경로를 등록한다. ACF가 없으면 필터 자체가 호출되지 않으므로 안전.
	 */
	public static function maybe_add_acf_json_path( array $paths ): array {
		$paths[] = HLF_DIR . 'acf-json';
		return $paths;
	}
}
