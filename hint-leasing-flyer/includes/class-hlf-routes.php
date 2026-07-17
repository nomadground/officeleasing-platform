<?php
/**
 * 공개 URL 라우팅 (요청서 1-E).
 *   목록: /listup/{flyer-number}/            예 /listup/LF-000123/
 *   상세: /listup/{flyer-number}/{item-number}/  예 /listup/LF-000123/I0003/
 *
 * 공개 페이지는 REST가 아니라 서버 렌더링(template_include 계열)으로 출력한다 — SEO/공유 안정성,
 * 공개 데이터 노출 최소화. 접근 정책:
 *   - Flyer 없음 → 404
 *   - draft     → 로그인 + edit 권한 있어야 미리보기, 아니면 404(존재를 드러내지 않음)
 *   - published → 공개
 *   - archived  → 기존 링크 읽기 전용 유지(공개 열람 허용, 신규 공유만 관리자 UI에서 중단)
 *   - item이 해당 Flyer 소속이 아니면 404
 *
 * rewrite flush는 permalinks.php와 동일한 버전비교 방식(HLF_REWRITE_VERSION)으로 1회만.
 */
defined( 'ABSPATH' ) || exit;

final class HLF_Routes {

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ), 10 );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush' ), 30 );
		add_action( 'template_redirect', array( __CLASS__, 'dispatch' ) );
	}

	public static function add_rewrite_rules(): void {
		add_rewrite_tag( '%hlf_flyer_number%', '([^/]+)' );
		add_rewrite_tag( '%hlf_item_number%', '([^/]+)' );

		add_rewrite_rule(
			'^listup/([^/]+)/([^/]+)/?$',
			'index.php?hlf_flyer_number=$matches[1]&hlf_item_number=$matches[2]',
			'top'
		);
		add_rewrite_rule(
			'^listup/([^/]+)/?$',
			'index.php?hlf_flyer_number=$matches[1]',
			'top'
		);
	}

	public static function register_query_vars( array $vars ): array {
		$vars[] = 'hlf_flyer_number';
		$vars[] = 'hlf_item_number';
		return $vars;
	}

	public static function maybe_flush(): void {
		if ( (int) get_option( 'hlf_rewrite_version' ) === HLF_REWRITE_VERSION ) {
			return;
		}
		self::add_rewrite_rules();
		flush_rewrite_rules();
		update_option( 'hlf_rewrite_version', HLF_REWRITE_VERSION );
	}

	public static function flyer_url( int $flyer_id ): string {
		return home_url( user_trailingslashit( 'listup/' . HLF_Flyer_Repository::format_number( $flyer_id ) ) );
	}

	public static function item_url( int $flyer_id, string $item_number ): string {
		return home_url( user_trailingslashit( 'listup/' . HLF_Flyer_Repository::format_number( $flyer_id ) . '/' . $item_number ) );
	}

	private static function send_404(): void {
		global $wp_query;
		if ( $wp_query instanceof WP_Query ) {
			$wp_query->set_404();
		}
		status_header( 404 );
		nocache_headers();
	}

	public static function dispatch(): void {
		$flyer_number = get_query_var( 'hlf_flyer_number' );
		if ( '' === $flyer_number || null === $flyer_number ) {
			return; // 우리 라우트가 아님.
		}

		$flyer = HLF_Flyer_Repository::get_by_number( (string) $flyer_number );
		if ( ! $flyer ) {
			self::send_404();
			return;
		}

		// fail-closed: publish/archived로 명시되지 않은 모든 상태(draft/pending/future/trash/
		// auto-draft 등)는 비공개로 간주하고 edit_post 권한이 있을 때만 미리보기를 허용한다.
		$is_public = in_array( $flyer->post_status, array( 'publish', HLF_Post_Types::STATUS_ARCHIVED ), true );
		if ( ! $is_public && ! current_user_can( 'edit_post', $flyer->ID ) ) {
			self::send_404();
			return;
		}

		$item_number = get_query_var( 'hlf_item_number' );
		$item        = null;
		if ( '' !== $item_number && null !== $item_number ) {
			$item = HLF_Item_Repository::get_item_by_number( $flyer->ID, (string) $item_number );
			if ( ! $item ) {
				self::send_404();
				return;
			}
		}

		self::render( $flyer, $item );
		exit;
	}

	private static function render( WP_Post $flyer, ?WP_Post $item ): void {
		status_header( 200 );

		// 템플릿에서 참조할 컨텍스트.
		$hlf_context = array(
			'flyer'  => HLF_Flyer_Repository::to_array( $flyer ),
			'status' => HLF_Flyer_Repository::status_label( $flyer->post_status ),
			'items'  => array_map( array( 'HLF_Item_Repository', 'to_array' ), HLF_Item_Repository::get_items( $flyer->ID ) ),
		);

		if ( $item ) {
			$hlf_context['item']  = HLF_Item_Repository::to_array( $item );
			$template             = HLF_DIR . 'templates/public/public-flyer-detail.php';
		} else {
			$template = HLF_DIR . 'templates/public/public-flyer-list.php';
		}

		// 테마가 오버라이드할 수 있게 하되, 없으면 플러그인 기본 템플릿을 쓴다.
		$override = locate_template( array( 'hlf/' . basename( $template ) ) );
		if ( $override ) {
			$template = $override;
		}

		include $template;
	}
}
