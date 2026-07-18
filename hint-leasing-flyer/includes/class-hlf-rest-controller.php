<?php
/**
 * 관리자 전용 REST API (요청서 1-G). 네임스페이스 hlf/v1.
 *
 * 모든 엔드포인트는:
 *   - permission_callback 로 capability 검사(비로그인/권한없음 차단)
 *   - 쿠키 인증 시 워드프레스 REST가 X-WP-Nonce 를 자동 검증(nonce 경계)
 *   - 쓰기 필드는 HLF_Meta_Schema 화이트리스트로만 반영(sanitize 포함)
 * 공개 열람은 이 REST가 아니라 template_include(HLF_Routes)로만 제공한다.
 *
 * refresh-source / images 는 Phase 2 범위이므로 라우트만 등록하고 501을 반환한다(골격).
 */
defined( 'ABSPATH' ) || exit;

final class HLF_REST_Controller {

	const NS = 'hlf/v1';

	public static function register_routes(): void {
		register_rest_route( self::NS, '/flyers', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'list_flyers' ),
				'permission_callback' => array( __CLASS__, 'can_edit_flyers' ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_flyer' ),
				'permission_callback' => array( __CLASS__, 'can_edit_flyers' ),
			),
		) );

		register_rest_route( self::NS, '/flyers/(?P<id>\d+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_flyer' ),
				'permission_callback' => array( __CLASS__, 'can_edit_this_flyer' ),
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'update_flyer' ),
				'permission_callback' => array( __CLASS__, 'can_edit_this_flyer' ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'delete_flyer' ),
				'permission_callback' => array( __CLASS__, 'can_delete_this_flyer' ),
			),
		) );

		register_rest_route( self::NS, '/flyers/(?P<id>\d+)/items', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'create_item' ),
			'permission_callback' => array( __CLASS__, 'can_edit_this_flyer' ),
		) );

		register_rest_route( self::NS, '/flyers/(?P<id>\d+)/items/reorder', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'reorder_items' ),
			'permission_callback' => array( __CLASS__, 'can_edit_this_flyer' ),
		) );

		register_rest_route( self::NS, '/flyers/(?P<id>\d+)/items/(?P<item_id>\d+)', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'update_item' ),
				'permission_callback' => array( __CLASS__, 'can_edit_this_flyer' ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'delete_item' ),
				'permission_callback' => array( __CLASS__, 'can_edit_this_flyer' ),
			),
		) );

		// 이름은 "publish"가 아니라 "status"다 — draft/published/archived 중 어떤 상태로도
		// 전이할 수 있는 범용 상태변경 엔드포인트이며, publish 전용이 아니다.
		register_rest_route( self::NS, '/flyers/(?P<id>\d+)/status', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'set_status' ),
			'permission_callback' => array( __CLASS__, 'can_set_status_this_flyer' ),
		) );

		// officeleasing listing → 새 Flyer item 가져오기(Phase 2-2). flyer_id는 URL이 아니라
		// body(JSON)로 온다 — 기존 /flyers/{id}/... 라우트들과 형태가 달라 별도 permission_callback을 쓴다.
		register_rest_route( self::NS, '/import-officeleasing', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'import_officeleasing' ),
			'permission_callback' => array( __CLASS__, 'can_import_officeleasing' ),
		) );

		// --- Phase 2 범위: 라우트만 등록, 지금은 501 ---
		register_rest_route( self::NS, '/items/(?P<item_id>\d+)/refresh-source', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'phase2_stub' ),
			'permission_callback' => array( __CLASS__, 'can_edit_flyers' ),
		) );
		register_rest_route( self::NS, '/images', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'phase2_stub' ),
			'permission_callback' => array( __CLASS__, 'can_edit_flyers' ),
		) );
	}

	/* ---------------- permission callbacks ---------------- */

	public static function can_edit_flyers(): bool {
		return current_user_can( 'edit_leasing_flyers' );
	}

	public static function can_edit_this_flyer( WP_REST_Request $request ): bool {
		$id = (int) $request['id'];
		return current_user_can( 'edit_post', $id );
	}

	public static function can_delete_this_flyer( WP_REST_Request $request ): bool {
		$id = (int) $request['id'];
		return current_user_can( 'delete_post', $id );
	}

	/** publish_leasing_flyers는 draft/published/archived 어느 방향 전이든 동일하게 요구한다(범용 상태변경). */
	public static function can_set_status_this_flyer( WP_REST_Request $request ): bool {
		$id = (int) $request['id'];
		return current_user_can( 'publish_leasing_flyers' ) && current_user_can( 'edit_post', $id );
	}

	/** flyer_id가 URL이 아니라 body에 있으므로 can_edit_this_flyer(URL의 id 기준)와 별도로 둔다. */
	public static function can_import_officeleasing( WP_REST_Request $request ): bool {
		$flyer_id = (int) $request['flyer_id'];
		return current_user_can( 'edit_post', $flyer_id );
	}

	/* ---------------- flyer handlers ---------------- */

	public static function list_flyers( WP_REST_Request $request ) {
		$flyers = HLF_Flyer_Repository::list( array(
			'per_page' => min( 100, max( 1, (int) ( $request['per_page'] ?? 20 ) ) ),
			'page'     => max( 1, (int) ( $request['page'] ?? 1 ) ),
		) );
		return rest_ensure_response( $flyers );
	}

	public static function create_flyer( WP_REST_Request $request ) {
		$flyer_id = HLF_Flyer_Repository::create( self::flyer_input( $request ) );
		if ( is_wp_error( $flyer_id ) ) {
			return $flyer_id;
		}
		return self::respond_flyer( $flyer_id, 201 );
	}

	public static function get_flyer( WP_REST_Request $request ) {
		$flyer = get_post( (int) $request['id'] );
		if ( ! $flyer || HLF_Post_Types::FLYER !== $flyer->post_type ) {
			return new WP_Error( 'hlf_not_found', 'Flyer를 찾을 수 없습니다.', array( 'status' => 404 ) );
		}
		$data          = HLF_Flyer_Repository::to_array( $flyer );
		$data['items'] = array_map( array( 'HLF_Item_Repository', 'to_array' ), HLF_Item_Repository::get_items( $flyer->ID ) );
		return rest_ensure_response( $data );
	}

	public static function update_flyer( WP_REST_Request $request ) {
		$result = HLF_Flyer_Repository::update( (int) $request['id'], self::flyer_input( $request ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return self::respond_flyer( (int) $request['id'] );
	}

	public static function delete_flyer( WP_REST_Request $request ) {
		$result = HLF_Flyer_Repository::delete( (int) $request['id'], true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array( 'deleted' => (bool) $result ) );
	}

	/** draft|published|archived 중 하나로 상태를 바꾼다(publish 전용 엔드포인트가 아님). */
	public static function set_status( WP_REST_Request $request ) {
		$status = sanitize_key( (string) $request['status'] );
		$result = HLF_Flyer_Repository::set_status( (int) $request['id'], $status );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return self::respond_flyer( (int) $request['id'] );
	}

	/* ---------------- item handlers ---------------- */

	public static function create_item( WP_REST_Request $request ) {
		$item_id = HLF_Item_Repository::create_item( (int) $request['id'], self::item_input( $request ) );
		if ( is_wp_error( $item_id ) ) {
			return $item_id;
		}
		return rest_ensure_response( HLF_Item_Repository::to_array( get_post( $item_id ) ) );
	}

	public static function update_item( WP_REST_Request $request ) {
		$result = HLF_Item_Repository::update_item( (int) $request['id'], (int) $request['item_id'], self::item_input( $request ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( HLF_Item_Repository::to_array( get_post( (int) $request['item_id'] ) ) );
	}

	public static function delete_item( WP_REST_Request $request ) {
		$result = HLF_Item_Repository::delete_item( (int) $request['id'], (int) $request['item_id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array( 'deleted' => (bool) $result ) );
	}

	public static function reorder_items( WP_REST_Request $request ) {
		$order = $request['order'];
		if ( ! is_array( $order ) ) {
			return new WP_Error( 'hlf_bad_order', 'order 는 item_id 배열이어야 합니다.', array( 'status' => 400 ) );
		}
		$result = HLF_Item_Repository::reorder( (int) $request['id'], $order );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$items = array_map( array( 'HLF_Item_Repository', 'to_array' ), HLF_Item_Repository::get_items( (int) $request['id'] ) );
		return rest_ensure_response( array( 'items' => $items ) );
	}

	/**
	 * officeleasing listing → 새 Flyer item. body: { flyer_id, listing_id, building_id? }.
	 * 실제 매핑/저장은 HLF_Snapshot_Import_Service(Mapper + 기존 create_item()) 몫 — 여기서는
	 * 입력을 뽑아 넘기고 응답을 만들 뿐이다(계산 지표 포함 응답은 기존 create_item 핸들러와 동일 패턴).
	 */
	public static function import_officeleasing( WP_REST_Request $request ) {
		$params      = self::request_params( $request );
		$flyer_id    = (int) ( $params['flyer_id'] ?? 0 );
		$listing_id  = (int) ( $params['listing_id'] ?? 0 );
		$building_id = ! empty( $params['building_id'] ) ? (int) $params['building_id'] : null;

		if ( ! $listing_id ) {
			return new WP_Error( 'hlf_missing_listing_id', 'listing_id가 필요합니다.', array( 'status' => 400 ) );
		}

		$item_id = HLF_Snapshot_Import_Service::import( $flyer_id, $listing_id, $building_id );
		if ( is_wp_error( $item_id ) ) {
			return $item_id;
		}

		$response = rest_ensure_response( HLF_Item_Repository::to_array( get_post( $item_id ) ) );
		$response->set_status( 201 );
		return $response;
	}

	/* ---------------- helpers ---------------- */

	/** 요청 본문에서 화이트리스트 필드만 뽑아 넘긴다(정규화는 repository/apply_fields가 재확인). */
	private static function item_input( WP_REST_Request $request ): array {
		return self::pluck_params( $request, HLF_Meta_Schema::writable_fields() );
	}

	/** Flyer 생성/수정 입력. title은 메타가 아니라 post_title이므로 별도로 항상 포함한다. */
	private static function flyer_input( WP_REST_Request $request ): array {
		$out = self::pluck_params( $request, HLF_Meta_Schema::flyer_writable_fields() );
		$params = self::request_params( $request );
		if ( array_key_exists( 'title', $params ) ) {
			$out['title'] = (string) $params['title'];
		}
		return $out;
	}

	private static function request_params( WP_REST_Request $request ): array {
		$params = $request->get_json_params();
		return is_array( $params ) ? $params : $request->get_params();
	}

	private static function pluck_params( WP_REST_Request $request, array $whitelist ): array {
		$params = self::request_params( $request );
		$out    = array();
		foreach ( $whitelist as $field ) {
			if ( array_key_exists( $field, $params ) ) {
				$out[ $field ] = $params[ $field ];
			}
		}
		return $out;
	}

	private static function respond_flyer( int $flyer_id, int $status = 200 ) {
		$response = rest_ensure_response( HLF_Flyer_Repository::to_array( get_post( $flyer_id ) ) );
		$response->set_status( $status );
		return $response;
	}

	public static function phase2_stub() {
		return new WP_Error( 'hlf_phase2', '이 엔드포인트는 Phase 2에서 구현됩니다.', array( 'status' => 501 ) );
	}
}
