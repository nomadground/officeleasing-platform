<?php
/**
 * 직원 전용 포털(/listad/) — 요청서: "직원 포털 기능 통합".
 *
 * 설계 원칙(중요):
 * - Repository/REST Controller/Snapshot/OCR/카카오 주소검색/공개 /listup/ 등 기존 로직은 전혀
 *   건드리지 않는다. 이 클래스는 "새 진입점 하나(/listad/)" + "로그인 게이트" + "wp-admin 리디렉션"
 *   만 담당하고, 실제 데이터 CRUD는 화면(assets/js/portal.js)이 기존 hlf/v1 REST를 그대로 호출한다.
 * - 로그인은 반드시 워드프레스 네이티브 인증(wp_signon → wp_authenticate → 인증 쿠키)만 쓴다.
 *   별도 회원가입/계정 시스템을 두지 않는다 — 로그인 실패 시에도 다른 보안 플러그인(로그인 시도
 *   제한 등)이 걸어둔 authenticate/wp_login_failed 필터가 그대로 동작한다(wp_signon()을 우회하지
 *   않고 그대로 호출하기 때문).
 * - 담당자(직원 실명) 개념은 이미 있는 HLF_Contact_Directory(설정 탭 "담당자 디렉터리")를 그대로
 *   재사용한다 — 새 데이터 모델을 만들지 않는다. "현재 로그인한 직원이 어떤 담당자인지"만
 *   포털 화면(localStorage)에 저장하고, 신규 매물/안내문 생성 시 그 담당자의 이름·연락처를
 *   자동으로 채워 넣는다(REST 자체는 기존 그대로 — 서버는 "누가 등록했는지"를 몰라도 된다).
 */
defined( 'ABSPATH' ) || exit;

final class HLF_Portal {

	const QUERY_VAR          = 'hlf_portal';
	const LOGIN_NONCE_ACTION = 'hlf_portal_login';
	const LOGIN_FIELD        = 'hlf_portal_login_submit';

	/** 이번 요청에서 로그인 시도가 실패했을 때 표시할 메시지(요청 단위 상태라 static으로 충분). */
	private static string $login_error = '';

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ), 10 );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'dispatch' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect_staff_from_wp_admin' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_assets' ) );
		// 포털은 독립된 화면이라 워드프레스 관리자 툴바를 띄우지 않는다(wp_head()/wp_footer()를
		// 직접 호출하는 portal.php에서 그대로 두면 툴바가 함께 렌더링되므로 여기서 끈다).
		add_filter( 'show_admin_bar', array( __CLASS__, 'maybe_hide_admin_bar' ) );
	}

	public static function maybe_hide_admin_bar( $show ) {
		return self::is_portal_request() ? false : $show;
	}

	public static function add_rewrite_rules(): void {
		// tab/flyer_id는 경로가 아니라 일반 쿼리스트링(예: /listad/?tab=flyers)이라 별도 rewrite
		// 태그가 필요 없다 — 워드프레스는 경로(listad/)만 매칭하면 나머지 ?key=value는 그대로
		// $_GET에 남긴다. 화면 전환은 JS(portal.js)가 location.search를 읽어 처리한다(요청서: 기본
		// 진입 주소는 항상 /listad/로 유지).
		add_rewrite_rule( '^listad/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	public static function register_query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public static function portal_url(): string {
		return home_url( user_trailingslashit( 'listad' ) );
	}

	private static function is_portal_request(): bool {
		return '1' === (string) get_query_var( self::QUERY_VAR );
	}

	/** 포털 접근 capability 게이트(요청서 3항 그대로). */
	public static function current_user_can_access(): bool {
		return current_user_can( 'edit_leasing_flyers' ) || current_user_can( 'manage_options' );
	}

	public static function login_error(): string {
		return self::$login_error;
	}

	/**
	 * /listad/ 요청 전체를 여기서 가로챈다:
	 *   미로그인            → 로그인 처리(POST) 또는 로그인 화면
	 *   로그인 O, 권한 X    → 403
	 *   로그인 O, 권한 O    → 포털 화면
	 * 검색엔진 색인/캐시 방지(요청서 보안 항목: noindex/nofollow/noarchive/no-cache)는 여기서
	 * nocache_headers()로 HTTP 헤더를, 각 템플릿에서 <meta name="robots">를 담당한다.
	 */
	public static function dispatch(): void {
		if ( ! self::is_portal_request() ) {
			return;
		}

		nocache_headers();
		status_header( 200 );

		if ( ! is_user_logged_in() ) {
			self::handle_login_request();
			include HLF_DIR . 'templates/portal/login.php';
			exit;
		}

		if ( ! self::current_user_can_access() ) {
			wp_die(
				esc_html__( '이 화면에 접근할 권한이 없습니다.', 'hint-leasing-flyer' ),
				'',
				array( 'response' => 403 )
			);
		}

		include HLF_DIR . 'templates/portal/portal.php';
		exit;
	}

	/**
	 * 로그인 폼 POST 처리. 반드시 워드프레스 네이티브 인증만 쓴다(wp_signon → 인증 쿠키/세션).
	 * 성공 시 항상 /listad/로 이동(요청서: "로그인 성공 후에는 /listad/로 이동한다").
	 */
	private static function handle_login_request(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! isset( $_POST[ self::LOGIN_FIELD ] ) ) {
			return;
		}

		$nonce = isset( $_POST['_wpnonce'] ) ? (string) wp_unslash( $_POST['_wpnonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, self::LOGIN_NONCE_ACTION ) ) {
			self::$login_error = '로그인 요청이 만료되었습니다. 다시 시도해 주세요.';
			return;
		}

		// wp-login.php와 동일하게 sanitize_user()로 미리 걸러내지 않는다 — 이메일 로그인 등 워드
		// 프레스 네이티브 인증이 지원하는 입력 형식을 그대로 wp_signon()/wp_authenticate()에 넘겨야
		// 한다(가공은 표시할 때만 esc_attr로 한다).
		$username = isset( $_POST['log'] ) ? trim( (string) wp_unslash( $_POST['log'] ) ) : '';
		$password = isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : '';

		if ( '' === $username || '' === $password ) {
			self::$login_error = '아이디와 비밀번호를 모두 입력해 주세요.';
			return;
		}

		$user = wp_signon(
			array(
				'user_login'    => $username,
				'user_password' => $password,
				'remember'      => true,
			),
			is_ssl()
		);

		if ( is_wp_error( $user ) ) {
			self::$login_error = '아이디 또는 비밀번호가 올바르지 않습니다.';
			return;
		}

		wp_safe_redirect( self::portal_url() );
		exit;
	}

	/**
	 * edit_leasing_flyers 권한 직원이 wp-admin에 들어오면 포털로 되돌린다(요청서 4항). 관리자
	 * (manage_options)와 REST/ajax/admin-post/async-upload/cron 요청은 제외한다 — 이 요청들은
	 * wp.media 업로드, heartbeat, REST 저장 등 포털 화면 자체가 내부적으로 걸어 두는 호출이라
	 * 리디렉션하면 포털 기능 자체가 깨진다.
	 */
	public static function maybe_redirect_staff_from_wp_admin(): void {
		if ( wp_doing_ajax() || wp_doing_cron() || defined( 'REST_REQUEST' ) ) {
			return;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_leasing_flyers' ) ) {
			return; // 이 플러그인과 무관한 사용자는 손대지 않는다.
		}

		global $pagenow;
		$excluded_pagenows = array( 'admin-ajax.php', 'admin-post.php', 'async-upload.php' );
		if ( in_array( $pagenow, $excluded_pagenows, true ) ) {
			return;
		}

		wp_safe_redirect( self::portal_url() );
		exit;
	}

	/**
	 * 포털 화면 자산은 /listad/ 요청에서만 로드한다(요청서 검증 항목: "관리자 화면과 Public 화면의
	 * asset 분리"). wp.media는 프론트엔드에서도 wp_footer 훅으로 미디어 템플릿을 출력하므로
	 * (코어 wp_enqueue_media()가 admin_footer/wp_footer 둘 다에 wp_print_media_templates를 건다)
	 * portal.php가 wp_head()/wp_footer()를 호출하는 한 정상 동작한다.
	 */
	public static function maybe_enqueue_assets(): void {
		if ( ! self::is_portal_request() || ! is_user_logged_in() || ! self::current_user_can_access() ) {
			return;
		}

		wp_enqueue_media();
		// admin.css의 ".hlf-admin .xyz" 컴포넌트 스타일(카드/테이블/필드/통계카드 등)을 그대로
		// 재사용한다(portal.php가 <body class="hlf-portal hlf-admin">를 쓴다) — portal.css는 그
		// 위에 포털 전용 상단바/nav/로그인 화면과, admin.css가 기대하는 wp-admin 기본 버튼 스킨
		// (여기서는 없음)만 추가로 채운다.
		wp_enqueue_style( 'hlf-admin', HLF_URL . 'assets/css/admin.css', array(), HLF_VERSION );
		wp_enqueue_style( 'hlf-portal', HLF_URL . 'assets/css/portal.css', array( 'hlf-admin' ), HLF_VERSION );

		wp_enqueue_script( 'hlf-admin-common', HLF_URL . 'assets/js/admin-common.js', array(), HLF_VERSION, true );
		wp_enqueue_script( 'hlf-admin-ocr', HLF_URL . 'assets/js/admin-ocr.js', array(), HLF_VERSION, true );
		wp_enqueue_script( 'hlf-portal', HLF_URL . 'assets/js/portal.js', array( 'hlf-admin-common', 'hlf-admin-ocr' ), HLF_VERSION, true );

		$current_user = wp_get_current_user();
		$shared       = array(
			'restUrl'              => esc_url_raw( rest_url( HLF_REST_Controller::NS . '/' ) ),
			'nonce'                => wp_create_nonce( 'wp_rest' ),
			'portalUrl'            => self::portal_url(),
			'sourcePreviewUrlBase' => admin_url( 'admin-post.php?action=hlf_source_preview&source_id=' ),
			'maxItems'             => HLF_Flyer_Item_Service::MAX_ITEMS_PER_FLYER,
			'defaultPhone'         => HLF_Flyer_Repository::DEFAULT_PHONE,
			'user'                 => array(
				'id'          => (int) $current_user->ID,
				'displayName' => $current_user->display_name,
			),
			'isAdmin'              => current_user_can( 'manage_options' ),
		);
		wp_localize_script( 'hlf-portal', 'HLF_PORTAL', $shared );
	}
}
