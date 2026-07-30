<?php
/**
 * 공개 URL 라우팅 (요청서 1-E, 이후 요청서 7로 프리픽스 변경).
 *   목록: /list/{flyer-number}/            예 /list/26072301/
 *   상세: /list/{flyer-number}/{item-number}/  예 /list/26072301/3/
 *
 * 프리픽스 변경 이력: 원래 공개 프리픽스는 /listup/였다(직원 포털이 /listad/였을 때). 요청서 7에서
 * 직원 포털이 /listup/으로 옮겨가면서 공개 프리픽스를 /list/로 바꿨다 — 그래서 이미 실제로 공유된
 * 옛 /listup/{flyer}/{item}?/ 링크(옛 "LF-000123" 형식 번호 포함)를 절대 깨뜨리면 안 된다. 아래
 * add_rewrite_rules()가 새 /list/ 규칙과 옛 /listup/ 규칙을 동시에 등록해 둘 다 공개 템플릿으로
 * 라우팅한다 — 리다이렉트가 아니라 진짜 별칭(alias)이라 URL도 그대로 유지된다. 직원 포털의
 * /listup/은 세그먼트 없는 정확한 경로(^listup/?$)만 쓰므로(HLF_Portal), 매물 세그먼트가 항상
 * 붙는 이 옛 공개 규칙(^listup/([^/]+)/...)과 절대 겹치지 않는다.
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

	/** 새로 발급하는 링크에만 쓰는 프리픽스(요청서 7). 옛 프리픽스는 LEGACY_PREFIX로 계속 라우팅만. */
	const PREFIX        = 'list';
	const LEGACY_PREFIX = 'listup';

	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ), 10 );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush' ), 30 );
		add_action( 'template_redirect', array( __CLASS__, 'dispatch' ) );
	}

	public static function add_rewrite_rules(): void {
		add_rewrite_tag( '%hlf_flyer_number%', '([^/]+)' );
		add_rewrite_tag( '%hlf_item_number%', '([^/]+)' );

		foreach ( array( self::PREFIX, self::LEGACY_PREFIX ) as $prefix ) {
			add_rewrite_rule(
				'^' . $prefix . '/([^/]+)/([^/]+)/?$',
				'index.php?hlf_flyer_number=$matches[1]&hlf_item_number=$matches[2]',
				'top'
			);
			add_rewrite_rule(
				'^' . $prefix . '/([^/]+)/?$',
				'index.php?hlf_flyer_number=$matches[1]',
				'top'
			);
		}
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
		return home_url( user_trailingslashit( self::PREFIX . '/' . HLF_Flyer_Repository::format_number( $flyer_id ) ) );
	}

	public static function item_url( int $flyer_id, string $item_number ): string {
		return home_url( user_trailingslashit( self::PREFIX . '/' . HLF_Flyer_Repository::format_number( $flyer_id ) . '/' . $item_number ) );
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
		// 요청서(실사용 버그): 매물 사진을 추가/수정한 직후에도 이 공개 페이지가 예전 내용 그대로
		// 보이는 경우가 있었다 — HLF_Cache_Purge는 알려진 캐시 플러그인 API만 직접 호출하므로, 그
		// 목록에 없는 호스팅사 엣지 캐시/CDN/프록시는 건드리지 못한다(class-hlf-cache-purge.php 참고).
		// 이 페이지는 404 응답에는 이미 nocache_headers()를 쓰고 있었는데(send_404) 정작 정상 200
		// 응답에는 빠져 있었다 — 표준 Cache-Control/Expires 헤더를 지키는 캐시 계층이라면(대부분의
		// CDN·리버스 프록시 포함) 여기서 막아야 우리가 모르는 캐시 기술이어도 최신 내용이 나간다.
		nocache_headers();

		// 템플릿에서 참조할 컨텍스트.
		$hlf_context = array(
			// 공개 화면은 item_count를 쓰지 않는다(관리자 Flyer 목록 화면 전용 데이터) — false로 넘겨
			// count_items()의 별도 쿼리를 건너뛴다. 바로 아래에서 items를 어차피 직접 조회하므로
			// item_count는 이 페이지에서 완전히 중복 쿼리였다.
			'flyer'  => HLF_Flyer_Repository::to_array( $flyer, false ),
			'status' => HLF_Flyer_Repository::status_label( $flyer->post_status ),
			// 공개 화면은 image_previews를 쓰지 않는다(관리자 편집 화면 전용 데이터) — false로 넘겨
			// 매물마다 사진 개수만큼 반복되는 불필요한 attachment 조회를 건너뛴다.
			'items'  => array_map( static function ( $post ) {
				return HLF_Item_Repository::to_array( $post, false );
			}, HLF_Item_Repository::get_items( $flyer->ID ) ),
		);

		if ( $item ) {
			$hlf_context['item']  = HLF_Item_Repository::to_array( $item, false );
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
