<?php
/**
 * wp-admin "Leasing Flyer" 메뉴 + 두 화면(목록/편집)의 껍데기.
 *
 * 데이터 입출력은 전부 hlf/v1 REST(HLF_REST_Controller)를 JS fetch로 호출한다 — 이 클래스는
 * 메뉴 등록, 스크립트/스타일 enqueue, 컨테이너 마크업(빈 <div>)만 담당하고 비즈니스 로직은
 * 전혀 갖지 않는다(Flyer/Item CRUD 로직은 HLF_Flyer_Repository/HLF_Item_Repository/
 * HLF_REST_Controller에 이미 있으므로 여기서 다시 구현하지 않는다).
 *
 * 편집 화면(EDIT_SLUG)은 목록에서만 진입하도록 관리자 메뉴에서 숨긴다(URL로는 계속 접근 가능) —
 * add_submenu_page로 등록해 hook suffix를 얻은 뒤 remove_submenu_page로 메뉴 노출만 제거하는
 * 워드프레스 표준 관용구를 쓴다.
 */
defined( 'ABSPATH' ) || exit;

final class HLF_Admin_UI {

	const LIST_SLUG = 'hlf-flyers';
	const EDIT_SLUG = 'hlf-flyer-edit';

	private static string $list_hook = '';
	private static string $edit_hook = '';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function register_menu(): void {
		self::$list_hook = (string) add_menu_page(
			'Leasing Flyer',
			'Leasing Flyer',
			'edit_leasing_flyers',
			self::LIST_SLUG,
			array( __CLASS__, 'render_list_page' ),
			'dashicons-media-spreadsheet',
			22
		);

		add_submenu_page(
			self::LIST_SLUG,
			'전체 Flyer',
			'전체 Flyer',
			'edit_leasing_flyers',
			self::LIST_SLUG,
			array( __CLASS__, 'render_list_page' )
		);

		self::$edit_hook = (string) add_submenu_page(
			self::LIST_SLUG,
			'Flyer 편집',
			'Flyer 편집',
			'edit_leasing_flyers',
			self::EDIT_SLUG,
			array( __CLASS__, 'render_edit_page' )
		);

		// 목록에서 "추가"/"수정" 버튼으로만 들어오게 하고, 좌측 메뉴에는 노출하지 않는다.
		remove_submenu_page( self::LIST_SLUG, self::EDIT_SLUG );
	}

	public static function render_list_page(): void {
		include HLF_DIR . 'templates/admin/admin-flyer-list.php';
	}

	public static function render_edit_page(): void {
		include HLF_DIR . 'templates/admin/admin-flyer-edit.php';
	}

	public static function enqueue_assets( string $hook ): void {
		if ( ! in_array( $hook, array( self::$list_hook, self::$edit_hook ), true ) ) {
			return;
		}

		wp_enqueue_style( 'hlf-admin', HLF_URL . 'assets/css/admin.css', array(), HLF_VERSION );

		$shared = array(
			'restUrl'      => esc_url_raw( rest_url( HLF_REST_Controller::NS . '/' ) ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'listUrl'      => admin_url( 'admin.php?page=' . self::LIST_SLUG ),
			'editUrlBase'  => admin_url( 'admin.php?page=' . self::EDIT_SLUG . '&flyer_id=' ),
			'maxItems'     => HLF_Item_Repository::MAX_ITEMS_PER_FLYER,
			'defaultPhone' => HLF_Flyer_Repository::DEFAULT_PHONE,
		);

		wp_enqueue_script( 'hlf-admin-common', HLF_URL . 'assets/js/admin-common.js', array(), HLF_VERSION, true );

		if ( self::$list_hook === $hook ) {
			wp_enqueue_script( 'hlf-admin-flyer-list', HLF_URL . 'assets/js/admin-flyer-list.js', array( 'hlf-admin-common' ), HLF_VERSION, true );
			wp_localize_script( 'hlf-admin-common', 'HLF_ADMIN', $shared );
			return;
		}

		$flyer_id          = isset( $_GET['flyer_id'] ) ? absint( wp_unslash( $_GET['flyer_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- 읽기 전용 화면 진입 파라미터, 상태변경 없음.
		$shared['flyerId'] = $flyer_id > 0 ? $flyer_id : null;
		wp_enqueue_script( 'hlf-admin-flyer-edit', HLF_URL . 'assets/js/admin-flyer-edit.js', array( 'hlf-admin-common' ), HLF_VERSION, true );
		wp_localize_script( 'hlf-admin-common', 'HLF_ADMIN', $shared );
	}
}
