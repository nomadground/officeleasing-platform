<?php
/**
 * wp-admin "Leasing Flyer" 메뉴 + 두 화면(목록/편집)의 껍데기.
 *
 * 데이터 입출력은 전부 hlf/v1 REST(HLF_REST_Controller)를 JS fetch로 호출한다 — 이 클래스는
 * 메뉴 등록, 스크립트/스타일 enqueue, 컨테이너 마크업(빈 <div>)만 담당하고 비즈니스 로직은
 * 전혀 갖지 않는다(Flyer/Item CRUD 로직은 HLF_Flyer_Repository/HLF_Item_Repository/
 * HLF_REST_Controller에 이미 있으므로 여기서 다시 구현하지 않는다).
 *
 * 편집 화면(EDIT_SLUG)은 목록에서만 진입하도록 관리자 메뉴에서 숨기되(URL로는 계속 접근 가능),
 * remove_submenu_page()는 쓰지 않는다 — CSS로만 숨긴다. 이유(실제로 겪은 버그):
 *
 * remove_submenu_page()는 $submenu 전역 배열에서 그 항목을 지워버리는데, 그 순간 부모(LIST_SLUG)
 * 아래 submenu가 "자기 자신을 가리키는 항목 1개"만 남게 된다. 워드프레스 코어
 * (wp-admin/includes/menu.php)는 admin_menu 액션이 끝난 직후 "submenu가 1개뿐이고 그 항목이
 * 부모와 같은 슬러그를 가리키면 그 submenu 배열 자체를 통째로 지운다"는 후처리를 한다 — 그러면
 * EDIT_SLUG는 $submenu 어디에도 남지 않는다.
 *
 * 문제는 워드프레스가 각 관리자 페이지의 접근 권한을 검사할 때(user_can_access_admin_page())
 * get_admin_page_parent()로 "이 페이지의 부모가 뭐였는지"를 $submenu를 훑어서 역추적한다는
 * 것이다. $submenu에 흔적이 없으면 부모를 못 찾아 빈 문자열로 취급하고, 그 상태로 계산한
 * hookname("admin_page_{$slug}")이 add_submenu_page() 등록 시점에 실제 부모(LIST_SLUG)로 계산해
 * $_registered_pages에 저장해 둔 hookname("{$page_hook}_page_{$slug}", 부모의 sanitize_title
 * 기반)과 달라진다. 결과: $_registered_pages에서 못 찾음 → user_can_access_admin_page()가 false →
 * "Sorry, you are not allowed to access this page."(권한 없음) — capability는 정확히 맞아도
 * 이 오류가 뜬다. 목록(LIST_SLUG)은 최상위 메뉴 슬러그 자체라 $menu 배열만으로 부모를 바로 찾을 수
 * 있어 이 버그를 안 타서, "목록은 되는데 편집만 권한 오류"로 보이는 것이었다.
 *
 * 따라서 $submenu 등록은 그대로 두고(부모 추적이 항상 정상 동작하도록), 화면에서만 CSS로 숨긴다.
 */
defined( 'ABSPATH' ) || exit;

final class HLF_Admin_UI {

	const LIST_SLUG   = 'hlf-flyers';
	const EDIT_SLUG   = 'hlf-flyer-edit';
	const LISTUP_SLUG = 'hlf-listup';

	private static string $list_hook   = '';
	private static string $edit_hook   = '';
	private static string $listup_hook = '';

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_head', array( __CLASS__, 'hide_edit_submenu' ) );
		// 원본 매물 고객용 미리보기 — 공개 상세 템플릿을 그대로 재사용해 관리자 화면 밖(admin-post)에서
		// 전체 문서로 출력한다(관리자 크롬 안에 <!doctype> 문서를 중첩하지 않기 위함).
		add_action( 'admin_post_hlf_source_preview', array( __CLASS__, 'render_source_preview' ) );
	}

	public static function register_menu(): void {
		// HINT List Up — 통합 4탭 관리 화면(신규 기본 진입점).
		self::$listup_hook = (string) add_menu_page(
			'HINT List Up',
			'HINT List Up',
			'edit_leasing_flyers',
			self::LISTUP_SLUG,
			array( __CLASS__, 'render_listup_page' ),
			'dashicons-screenoptions',
			21
		);

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
	}

	/**
	 * "Flyer 편집" 항목을 좌측 메뉴에서 시각적으로만 숨긴다(목록의 "추가"/"수정" 버튼으로만 들어오게
	 * 하려는 목적). remove_submenu_page()를 쓰지 않는 이유는 클래스 docblock 참고 — $submenu 등록
	 * 자체는 그대로 둬야 워드프레스의 페이지 접근 권한 판정(get_admin_page_parent 기반 hookname
	 * 역추적)이 정상 동작한다.
	 */
	public static function hide_edit_submenu(): void {
		printf(
			'<style>#adminmenu a[href="admin.php?page=%s"]{display:none;}</style>',
			esc_attr( self::EDIT_SLUG )
		);
	}

	public static function render_list_page(): void {
		include HLF_DIR . 'templates/admin/admin-flyer-list.php';
	}

	public static function render_edit_page(): void {
		include HLF_DIR . 'templates/admin/admin-flyer-edit.php';
	}

	public static function render_listup_page(): void {
		include HLF_DIR . 'templates/admin/admin-listup.php';
	}

	/**
	 * 원본 매물(hlf_source_listing) 하나를 공개 상세 템플릿으로 미리보기한다. 별도 미리보기 화면을
	 * 새로 만들지 않고 templates/public/public-flyer-detail.php를 그대로 include한다 — 미리보기용으로
	 * flyer/item 컨텍스트를 합성해 넘긴다(실제 발행 URL이 아니라 임시 렌더). 편집 권한이 있어야 열 수
	 * 있다(비공개 데이터).
	 */
	public static function render_source_preview(): void {
		if ( ! current_user_can( 'edit_leasing_flyers' ) ) {
			wp_die( '이 매물을 미리볼 권한이 없습니다.', '', array( 'response' => 403 ) );
		}
		$source_id = isset( $_GET['source_id'] ) ? absint( wp_unslash( $_GET['source_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- 읽기 전용 미리보기.
		$source    = HLF_Source_Listing_Repository::get( $source_id );
		if ( ! $source ) {
			wp_die( '매물을 찾을 수 없습니다.', '', array( 'response' => 404 ) );
		}

		$item                 = HLF_Source_Listing_Repository::to_array( $source );
		$item['item_number']  = 'PREVIEW';
		$item['display_order'] = 0;

		// 공개 템플릿이 기대하는 컨텍스트를 합성한다(미리보기 전용 값 — 실제 발행 데이터 아님).
		$hlf_context = array(
			'flyer'  => array(
				'id'            => 0,
				'flyer_number'  => 'PREVIEW',
				'title'         => '매물 미리보기',
				'url'           => '#',
				'status'        => 'draft',
				'contact_name'  => (string) $item['contact_name'],
				'contact_phone' => (string) $item['contact_phone'],
			),
			'status' => 'draft',
			'item'   => $item,
			'items'  => array( $item ),
		);

		include HLF_DIR . 'templates/public/public-flyer-detail.php';
		exit;
	}

	public static function enqueue_assets( string $hook ): void {
		if ( ! in_array( $hook, array( self::$list_hook, self::$edit_hook, self::$listup_hook ), true ) ) {
			return;
		}

		wp_enqueue_style( 'hlf-admin', HLF_URL . 'assets/css/admin.css', array(), HLF_VERSION );

		$shared = array(
			'restUrl'              => esc_url_raw( rest_url( HLF_REST_Controller::NS . '/' ) ),
			'nonce'                => wp_create_nonce( 'wp_rest' ),
			'listUrl'              => admin_url( 'admin.php?page=' . self::LIST_SLUG ),
			'editUrlBase'          => admin_url( 'admin.php?page=' . self::EDIT_SLUG . '&flyer_id=' ),
			'sourcePreviewUrlBase' => admin_url( 'admin-post.php?action=hlf_source_preview&source_id=' ),
			'maxItems'             => HLF_Flyer_Item_Service::MAX_ITEMS_PER_FLYER,
			'defaultPhone'         => HLF_Flyer_Repository::DEFAULT_PHONE,
			'kakaoJsKey'           => defined( 'HLF_KAKAO_JS_KEY' ) ? HLF_KAKAO_JS_KEY : '',
		);

		wp_enqueue_script( 'hlf-admin-common', HLF_URL . 'assets/js/admin-common.js', array(), HLF_VERSION, true );

		// List Up 통합 화면: 전체 매물 폼이 OCR·wp.media·카카오 주소검색을 모두 쓴다.
		if ( self::$listup_hook === $hook ) {
			wp_enqueue_media();
			wp_enqueue_script( 'hlf-admin-ocr', HLF_URL . 'assets/js/admin-ocr.js', array(), HLF_VERSION, true );
			wp_enqueue_script( 'hlf-admin-listup', HLF_URL . 'assets/js/admin-listup.js', array( 'hlf-admin-common', 'hlf-admin-ocr' ), HLF_VERSION, true );
			wp_localize_script( 'hlf-admin-common', 'HLF_ADMIN', $shared );
			return;
		}

		if ( self::$list_hook === $hook ) {
			wp_enqueue_script( 'hlf-admin-flyer-list', HLF_URL . 'assets/js/admin-flyer-list.js', array( 'hlf-admin-common' ), HLF_VERSION, true );
			wp_localize_script( 'hlf-admin-common', 'HLF_ADMIN', $shared );
			return;
		}

		// Item 편집 화면에서만 wp.media 모달을 쓴다(매물 이미지 선택) — 다른 화면까지 전역으로
		// 로드할 필요는 없다.
		wp_enqueue_media();

		$flyer_id          = isset( $_GET['flyer_id'] ) ? absint( wp_unslash( $_GET['flyer_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- 읽기 전용 화면 진입 파라미터, 상태변경 없음.
		$shared['flyerId'] = $flyer_id > 0 ? $flyer_id : null;
		// 지도 미리보기(요청서 3-7)는 공개 화면과 동일하게 JS Key만 쓴다 — 도메인 제한 방식의 공개
		// 키라 브라우저에 노출돼도 안전하다(REST 키와 달리 서버 프록시를 거칠 필요가 없다).
		$shared['kakaoJsKey'] = defined( 'HLF_KAKAO_JS_KEY' ) ? HLF_KAKAO_JS_KEY : '';
		// OCR 파싱/섹션은 공용 모듈(admin-ocr.js)로 분리돼 있어 편집 화면 스크립트가 이를 의존한다.
		wp_enqueue_script( 'hlf-admin-ocr', HLF_URL . 'assets/js/admin-ocr.js', array(), HLF_VERSION, true );
		wp_enqueue_script( 'hlf-admin-flyer-edit', HLF_URL . 'assets/js/admin-flyer-edit.js', array( 'hlf-admin-common', 'hlf-admin-ocr' ), HLF_VERSION, true );
		wp_localize_script( 'hlf-admin-common', 'HLF_ADMIN', $shared );
	}
}
