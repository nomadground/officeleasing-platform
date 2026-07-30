<?php
/**
 * Office Leasing GeneratePress Child Theme
 *
 * 화면 템플릿과 프론트엔드 자산만 담당합니다.
 * CPT, ACF, 계산, 검색, 지도 데이터 로직은 officeleasing-core 플러그인에 둡니다.
 * 테마 함수 프리픽스는 olt_ (플러그인 ol_ 과 충돌 방지).
 */
defined( 'ABSPATH' ) || exit;

define( 'OL_CHILD_VERSION', '0.2.0' );
define( 'OL_CHILD_PATH', get_stylesheet_directory() );
define( 'OL_CHILD_URL', get_stylesheet_directory_uri() );

require_once OL_CHILD_PATH . '/inc/theme-helpers.php';
require_once OL_CHILD_PATH . '/inc/class-gnb-walker.php';

/**
 * OfficeLeasing v1 디자인 시스템 CSS 로드.
 * 목업의 확정 CSS를 그대로 담은 파일. 모든 CPT 템플릿이 이 하나를 공유한다(컴포넌트 재사용).
 * GeneratePress가 부모 style.css를 자동 로드하므로 부모 스타일 재-enqueue는 하지 않는다.
 */
function olt_enqueue_design_system(): void {
	$css = OL_CHILD_PATH . '/assets/css/officeleasing.css';
	wp_enqueue_style(
		'officeleasing-design',
		OL_CHILD_URL . '/assets/css/officeleasing.css',
		array(),
		file_exists( $css ) ? (string) filemtime( $css ) : OL_CHILD_VERSION
	);

	// 목록/허브 전용 CSS는 해당 페이지에서만 로드 (성능: 상세/일반 페이지엔 불필요).
	// Home도 이 파일에 포함된 빌딩 카드 컴포넌트(.olx-bcard*, .olx-grid)를 재사용하므로 같이 로드한다
	// — 카드 CSS를 home CSS에 복사하지 않기 위한 의도적 선택(단일 소스 유지).
	if ( is_post_type_archive( 'building' ) || is_tax( 'office_region' ) || is_front_page() ) {
		$archive_css = OL_CHILD_PATH . '/assets/css/officeleasing-archive.css';
		wp_enqueue_style(
			'officeleasing-archive',
			OL_CHILD_URL . '/assets/css/officeleasing-archive.css',
			array( 'officeleasing-design' ),
			file_exists( $archive_css ) ? (string) filemtime( $archive_css ) : OL_CHILD_VERSION
		);
	}

	// Home 전용 CSS/JS는 프론트 페이지에서만 로드.
	if ( is_front_page() ) {
		$home_css = OL_CHILD_PATH . '/assets/css/officeleasing-home.css';
		if ( file_exists( $home_css ) ) {
			wp_enqueue_style(
				'officeleasing-home',
				OL_CHILD_URL . '/assets/css/officeleasing-home.css',
				array( 'officeleasing-design', 'officeleasing-archive' ),
				(string) filemtime( $home_css )
			);
		}

		// 슬라이더 JS는 순수 개선용 - 없거나 실패해도 CSS 가로 스크롤로 모든 카드에 접근 가능하다.
		$home_js = OL_CHILD_PATH . '/assets/js/home-slider.js';
		if ( file_exists( $home_js ) ) {
			wp_enqueue_script(
				'officeleasing-home-slider',
				OL_CHILD_URL . '/assets/js/home-slider.js',
				array(),
				(string) filemtime( $home_js ),
				true
			);
		}
	}
}
add_action( 'wp_enqueue_scripts', 'olt_enqueue_design_system', 20 );

/**
 * 매물/빌딩 전용 인터랙션 JS(갤러리 썸네일 전환 등)는
 * 실제 파일을 만들었을 때 조건부로 로드한다. 지금은 파일이 없으면 스킵.
 */
function olt_enqueue_template_assets(): void {
	if ( ! is_singular( array( 'building', 'listing' ) ) ) {
		return;
	}
	$js = OL_CHILD_PATH . '/assets/js/single.js';
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'officeleasing-single', OL_CHILD_URL . '/assets/js/single.js', array(), (string) filemtime( $js ), true );
	}

	// 카카오맵 SDK는 플러그인(includes/maps.php)이 "앱키가 설정된 경우에만" 먼저 enqueue한다.
	// SDK가 실제로 큐에 있을 때만 초기화 스크립트를 붙여서, 키 미설정 상태에서 죽은 스크립트가 로드되지 않게 한다.
	if ( wp_script_is( 'kakao-maps-sdk', 'enqueued' ) ) {
		$map_js = OL_CHILD_PATH . '/assets/js/kakao-map.js';
		if ( file_exists( $map_js ) ) {
			wp_enqueue_script( 'officeleasing-kakao-map', OL_CHILD_URL . '/assets/js/kakao-map.js', array( 'kakao-maps-sdk' ), (string) filemtime( $map_js ), true );
		}
	}
}
add_action( 'wp_enqueue_scripts', 'olt_enqueue_template_assets', 21 );

/** 테마 셋업: 네비게이션 메뉴, 썸네일, 타이틀 태그. */
function olt_theme_setup(): void {
	register_nav_menus( array(
		'primary' => '주요 메뉴 (GNB)',
	) );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'title-tag' );
}
add_action( 'after_setup_theme', 'olt_theme_setup' );

/**
 * officeleasing-core 플러그인 의존성 안내.
 * 플러그인이 없으면 get_field()/ol_format_* 가 없어 템플릿이 fallback으로만 동작하므로 관리자에게 알린다.
 */
function olt_check_core_plugin(): void {
	if ( ! function_exists( 'get_field' ) ) {
		echo '<div class="notice notice-warning"><p><strong>Office Leasing 테마</strong>: '
			. 'officeleasing-core 플러그인과 ACF가 활성화되어야 매물 데이터가 정상 출력됩니다.</p></div>';
	}
}
add_action( 'admin_notices', 'olt_check_core_plugin' );
