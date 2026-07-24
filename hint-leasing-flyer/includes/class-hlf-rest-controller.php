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
 * refresh-source는 아직 Phase 범위 밖이라 라우트만 등록하고 501을 반환한다(골격).
 * 매물 이미지는 WordPress 기본 Media Library(wp.media)에서 선택한 attachment ID를
 * HLF_Item_Repository::set_images()/delete_image()로 저장한다(외부 이미지 검색/다운로드 없음).
 */
defined( 'ABSPATH' ) || exit;

// mbstring은 필수 확장이 아니다 — 없는 환경에서 mb_strlen()/mb_strpos() 직접 호출은 치명적 오류로
// 이어진다. 여기서 쓰는 곳(검색어 길이 제한, 지번 문자열 포함 여부 확인)은 폴백 시 바이트 단위로
// 동작해 멀티바이트 문자열에서 정확도가 약간 떨어질 수 있지만 기능 자체가 깨지지는 않는다 — 정상
// 환경(mbstring 있음)에서는 지금과 완전히 동일하게 동작한다.
if ( ! function_exists( 'hlf_mb_strlen' ) ) {
	function hlf_mb_strlen( string $s ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $s, 'UTF-8' ) : strlen( $s );
	}
}
if ( ! function_exists( 'hlf_mb_strpos' ) ) {
	function hlf_mb_strpos( string $haystack, string $needle ) {
		return function_exists( 'mb_strpos' ) ? mb_strpos( $haystack, $needle, 0, 'UTF-8' ) : strpos( $haystack, $needle );
	}
}

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

		// officeleasing listing 검색(Phase 2-2) — 특정 Flyer에 종속되지 않으므로 can_edit_flyers 재사용.
		register_rest_route( self::NS, '/officeleasing/listings', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'search_officeleasing_listings' ),
			'permission_callback' => array( __CLASS__, 'can_edit_flyers' ),
			'args'                => array(
				// 검색어는 주소·건물명 수준이라 정상 입력이 이 길이를 넘을 일이 없다 — 비정상적으로 긴
				// 입력만 400으로 거른다(카카오 주소검색 q의 maxLength와 같은 방어). WP 코어가
				// register_rest_route args의 maxLength를 자동 검증한다.
				'search'   => array( 'type' => 'string', 'maxLength' => 200 ),
				'status'   => array(
					'type' => 'string',
					'enum' => array( '', 'available', 'reserved', 'contract_pending', 'leased', 'temporarily_hidden', 'expired' ),
				),
				'page'     => array( 'type' => 'integer', 'minimum' => 1 ),
				'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => HLF_OfficeLeasing_Search::MAX_PER_PAGE ),
			),
		) );

		// officeleasing listing → 이 Flyer에 새 item 가져오기(Phase 2-2). 기존 /flyers/{id}/items
		// 라우트는 그대로 두고(수동 생성용), 이건 별도 라우트 — 같은 permission_callback을 재사용한다.
		register_rest_route( self::NS, '/flyers/(?P<id>\d+)/items/import', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'import_officeleasing_item' ),
			'permission_callback' => array( __CLASS__, 'can_edit_this_flyer' ),
			'args'                => array(
				'listing_id' => array(
					'type'     => 'integer',
					'required' => true,
					'minimum'  => 1,
				),
			),
		) );

		// --- 아직 범위 밖: 라우트만 등록, 지금은 501 ---
		register_rest_route( self::NS, '/items/(?P<item_id>\d+)/refresh-source', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'phase2_stub' ),
			'permission_callback' => array( __CLASS__, 'can_edit_flyers' ),
		) );

		// 매물 이미지 저장 — WordPress Media Library(wp.media)에서 선택한 attachment ID들을
		// 저장한다(대표 지정 포함). 최종 상태 전체를 클라이언트가 계산해 보낸다(reorder_items와 같은 설계).
		register_rest_route( self::NS, '/flyers/(?P<id>\d+)/items/(?P<item_id>\d+)/images', array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => array( __CLASS__, 'update_item_images' ),
			'permission_callback' => array( __CLASS__, 'can_edit_this_flyer' ),
		) );

		register_rest_route( self::NS, '/flyers/(?P<id>\d+)/items/(?P<item_id>\d+)/images/(?P<attachment_id>\d+)', array(
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => array( __CLASS__, 'delete_item_image' ),
			'permission_callback' => array( __CLASS__, 'can_edit_this_flyer' ),
		) );

		// 지번주소 → 도로명주소/좌표 조회(카카오 Local API). 서버가 대신 호출한다 — REST API 키를
		// 브라우저에 노출하지 않기 위해서다(Authorization 헤더는 이 서버 사이드 호출에만 붙는다).
		// 특정 Flyer/Item에 종속되지 않는 조회라 officeleasing 검색과 같은 패턴(can_edit_flyers)만 요구한다.
		register_rest_route( self::NS, '/kakao/address-search', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'search_kakao_address' ),
			'permission_callback' => array( __CLASS__, 'can_edit_flyers' ),
			'args'                => array(
				'q' => array( 'type' => 'string', 'required' => true, 'maxLength' => 200 ),
			),
		) );

		/* ---------------- 원본 매물(전체 매물) ---------------- */
		// 원본 매물은 특정 Flyer에 종속되지 않는 독립 카탈로그라 flyer-scoped 권한이 아니라
		// can_edit_flyers(카탈로그 관리 권한)만 요구한다(officeleasing 검색/카카오 조회와 같은 패턴).
		register_rest_route( self::NS, '/source-listings', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'list_source_listings' ),
				'permission_callback' => array( __CLASS__, 'can_edit_flyers' ),
				'args'                => array(
					// 검색어/담당자명은 짧은 텍스트라 정상 입력이 이 길이를 넘지 않는다 — 비정상적으로
					// 긴 입력만 400으로 거른다(officeleasing 검색·카카오 주소검색과 같은 방어).
					'search'   => array( 'type' => 'string', 'maxLength' => 200 ),
					// Dashboard의 "연결된 매물"/"미연결 매물" 카드 클릭 시 이 목록으로 넘어와 필터링하는 데 쓴다.
					'linked'   => array( 'type' => 'string', 'enum' => array( '', 'linked', 'unlinked' ) ),
					// 요청서: 목록이 커지면 로딩이 느려지므로, 기본값으로 "내 매물"만 먼저 보여주고
					// "전체 보기"를 눌러야 전부 나오게 한다 — contact_name 정확히 일치(담당자 디렉터리에
					// 저장된 이름 그대로) 필터.
					'contact'  => array( 'type' => 'string', 'maxLength' => 100 ),
					'page'     => array( 'type' => 'integer', 'minimum' => 1 ),
					'per_page' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100 ),
				),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'create_source_listing' ),
				'permission_callback' => array( __CLASS__, 'can_edit_flyers' ),
			),
		) );

		// officeleasing listing → "전체 매물" 카탈로그로 가져오기(Flyer Item import와 같은 패턴,
		// 대상만 다르다). 특정 Flyer에 종속되지 않으므로 can_edit_flyers만 요구한다.
		register_rest_route( self::NS, '/source-listings/import-officeleasing', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'import_officeleasing_source' ),
			'permission_callback' => array( __CLASS__, 'can_edit_flyers' ),
			'args'                => array(
				'listing_id' => array( 'type' => 'integer', 'required' => true, 'minimum' => 1 ),
			),
		) );

		register_rest_route( self::NS, '/source-listings/(?P<source_id>\d+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_source_listing' ),
				'permission_callback' => array( __CLASS__, 'can_edit_flyers' ),
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'update_source_listing' ),
				'permission_callback' => array( __CLASS__, 'can_edit_flyers' ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'delete_source_listing' ),
				'permission_callback' => array( __CLASS__, 'can_edit_flyers' ),
			),
		) );

		register_rest_route( self::NS, '/source-listings/(?P<source_id>\d+)/images', array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => array( __CLASS__, 'update_source_images' ),
			'permission_callback' => array( __CLASS__, 'can_edit_flyers' ),
		) );

		register_rest_route( self::NS, '/source-listings/(?P<source_id>\d+)/images/(?P<attachment_id>\d+)', array(
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => array( __CLASS__, 'delete_source_image' ),
			'permission_callback' => array( __CLASS__, 'can_edit_flyers' ),
		) );

		// Flyer ↔ 원본 매물 포함/해제. PUT=포함(멱등), DELETE=해제. Flyer-scoped 권한을 요구한다.
		register_rest_route( self::NS, '/flyers/(?P<id>\d+)/source-listings/(?P<source_id>\d+)', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'include_source_in_flyer' ),
				'permission_callback' => array( __CLASS__, 'can_edit_this_flyer' ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'exclude_source_from_flyer' ),
				'permission_callback' => array( __CLASS__, 'can_edit_this_flyer' ),
			),
		) );

		/* ---------------- 담당자 디렉터리(설정) ---------------- */
		// 읽기는 폼 채우기용이라 staff(can_edit_flyers)에게 허용하고, 쓰기(추가/수정/삭제/기본지정)는
		// 설정 관리 권한(manage_leasing_flyer_settings)을 요구한다.
		register_rest_route( self::NS, '/contacts', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_contacts' ),
				'permission_callback' => array( __CLASS__, 'can_edit_flyers' ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'add_contact' ),
				'permission_callback' => array( __CLASS__, 'can_manage_settings' ),
			),
		) );

		register_rest_route( self::NS, '/contacts/(?P<index>\d+)', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'update_contact' ),
				'permission_callback' => array( __CLASS__, 'can_manage_settings' ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'remove_contact' ),
				'permission_callback' => array( __CLASS__, 'can_manage_settings' ),
			),
		) );

		register_rest_route( self::NS, '/contacts/(?P<index>\d+)/default', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( __CLASS__, 'set_default_contact' ),
			'permission_callback' => array( __CLASS__, 'can_manage_settings' ),
		) );

		/* ---------------- 대시보드 ---------------- */
		register_rest_route( self::NS, '/dashboard', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( __CLASS__, 'get_dashboard' ),
			'permission_callback' => array( __CLASS__, 'can_edit_flyers' ),
		) );
	}

	/* ---------------- permission callbacks ---------------- */

	public static function can_edit_flyers(): bool {
		return current_user_can( 'edit_leasing_flyers' );
	}

	/** 담당자 디렉터리 등 사이트 전역 설정 변경 — 담당자 role이 아니라 관리자급만(설정 cap). */
	public static function can_manage_settings(): bool {
		return current_user_can( 'manage_leasing_flyer_settings' );
	}

	public static function can_edit_this_flyer( WP_REST_Request $request ): bool {
		$id = (int) $request['id'];
		return current_user_can( 'edit_post', $id );
	}

	public static function can_delete_this_flyer( WP_REST_Request $request ): bool {
		$id = (int) $request['id'];
		return current_user_can( 'delete_post', $id );
	}

	/**
	 * publish_leasing_flyers는 draft/published/archived 어느 방향 전이든 동일하게 요구한다(범용 상태변경).
	 * 대상이 실제로 Flyer인지도 여기서 한 번 더 확인한다(defense-in-depth) — 권한 계층은 "이 사용자가
	 * 상태를 바꿀 수 있나"만 보고, 실제 대상 종류 검증은 HLF_Flyer_Repository::set_status()가 최종
	 * 기준이다.
	 */
	public static function can_set_status_this_flyer( WP_REST_Request $request ): bool {
		$id = (int) $request['id'];
		return HLF_Post_Types::FLYER === get_post_type( $id )
			&& current_user_can( 'publish_leasing_flyers' )
			&& current_user_can( 'edit_post', $id );
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
			return new WP_Error( 'hlf_bad_order', '순서 정보가 올바르지 않습니다.', array( 'status' => 400 ) );
		}
		$result = HLF_Item_Repository::reorder( (int) $request['id'], $order );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$items = array_map( array( 'HLF_Item_Repository', 'to_array' ), HLF_Item_Repository::get_items( (int) $request['id'] ) );
		return rest_ensure_response( array( 'items' => $items ) );
	}

	/**
	 * officeleasing listing 검색(관리자가 Import할 매물을 찾는 화면용). GET 쿼리 파라미터:
	 * search, status, page, per_page. 실제 쿼리는 HLF_OfficeLeasing_Search가 전담.
	 */
	public static function search_officeleasing_listings( WP_REST_Request $request ) {
		$params = self::request_params( $request );
		$result = HLF_OfficeLeasing_Search::search( array(
			'search'   => (string) ( $params['search'] ?? '' ),
			'status'   => (string) ( $params['status'] ?? '' ),
			'page'     => (int) ( $params['page'] ?? 1 ),
			'per_page' => (int) ( $params['per_page'] ?? HLF_OfficeLeasing_Search::DEFAULT_PER_PAGE ),
		) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	}

	/**
	 * officeleasing listing → 이 Flyer(URL의 id)에 새 item. body: { listing_id } — building_id는
	 * 받지 않는다(HLF_OfficeLeasing_Import_Service/Mapper가 항상 서버에서 확정).
	 * 실제 매핑/저장은 HLF_OfficeLeasing_Import_Service(Mapper + 기존 create_item()) 몫 — 여기서는
	 * 입력을 뽑아 넘기고 응답을 만들 뿐이다(계산 지표 포함 응답은 기존 create_item 핸들러와 동일 패턴).
	 */
	public static function import_officeleasing_item( WP_REST_Request $request ) {
		$flyer_id   = (int) $request['id'];
		$params     = self::request_params( $request );
		$listing_id = (int) ( $params['listing_id'] ?? 0 );

		if ( ! $listing_id ) {
			return new WP_Error( 'hlf_missing_listing_id', '가져올 매물을 선택해 주세요.', array( 'status' => 400 ) );
		}

		$item_id = HLF_OfficeLeasing_Import_Service::import( $flyer_id, $listing_id );
		if ( is_wp_error( $item_id ) ) {
			return $item_id;
		}

		$response = rest_ensure_response( HLF_Item_Repository::to_array( get_post( $item_id ) ) );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * officeleasing listing → "전체 매물" 카탈로그(hlf_source_listing)로 가져오기. 특정 Flyer에
	 * 종속되지 않는다는 점만 import_officeleasing_item()과 다르고, 나머지 규칙(building_id는 클라이언트가
	 * 못 정함, 실제 저장은 서비스 클래스 몫)은 동일하다.
	 */
	public static function import_officeleasing_source( WP_REST_Request $request ) {
		$params     = self::request_params( $request );
		$listing_id = (int) ( $params['listing_id'] ?? 0 );

		if ( ! $listing_id ) {
			return new WP_Error( 'hlf_missing_listing_id', '가져올 매물을 선택해 주세요.', array( 'status' => 400 ) );
		}

		$source_id = HLF_OfficeLeasing_Import_Service::import_to_catalog( $listing_id );
		if ( is_wp_error( $source_id ) ) {
			return $source_id;
		}

		$response = rest_ensure_response( HLF_Source_Listing_Repository::to_array( get_post( $source_id ) ) );
		$response->set_status( 201 );
		return $response;
	}

	/* ---------------- image handlers (Media Library) ---------------- */

	/** 이미지 순서/대표 지정. body: { exterior_image_id, interior_image_ids } — 둘 다 wp.media에서
	 *  선택한 attachment ID(신규 업로드 또는 기존 미디어 모두 가능). */
	public static function update_item_images( WP_REST_Request $request ) {
		$flyer_id = (int) $request['id'];
		$item_id  = (int) $request['item_id'];
		$params   = self::request_params( $request );
		$exterior = (int) ( $params['exterior_image_id'] ?? 0 );
		$interior = is_array( $params['interior_image_ids'] ?? null ) ? array_map( 'intval', $params['interior_image_ids'] ) : array();

		$result = HLF_Item_Repository::set_images( $flyer_id, $item_id, $exterior, $interior );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( HLF_Item_Repository::to_array( get_post( $item_id ) ) );
	}

	/** 이미지 하나를 이 Item에서 뗀다(Item 필드에서만 제거 — Attachment 자체는 삭제하지 않는다,
	 *  Media Library에서 고른 이미지는 사이트 다른 곳에서도 쓰이고 있을 수 있으므로). */
	public static function delete_item_image( WP_REST_Request $request ) {
		$flyer_id      = (int) $request['id'];
		$item_id       = (int) $request['item_id'];
		$attachment_id = (int) $request['attachment_id'];

		$result = HLF_Item_Repository::delete_image( $flyer_id, $item_id, $attachment_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( HLF_Item_Repository::to_array( get_post( $item_id ) ) );
	}

	/* ---------------- 카카오 주소 검색 ---------------- */

	/** 한 번에 클라이언트로 내려주는 주소 후보 최대 개수(카카오 응답 그대로 다 주지 않고 상위 N개만). */
	const KAKAO_ADDRESS_MAX_CANDIDATES = 10;

	/**
	 * 지번주소로 도로명주소·좌표 후보 목록을 조회한다(카카오 Local API,
	 * https://dapi.kakao.com/v2/local/search/address.json). REST API 키는 wp-config.php의
	 * define('HLF_KAKAO_REST_API_KEY', ...)로만 받는다(Naver 자격증명과 같은 패턴 — 옵션 테이블 방식
	 * 채택 안 함). 키가 없으면 501을 반환해 "주소 검색 버튼만 비활성화되고 나머지 관리자 화면은 그대로
	 * 동작"하도록 한다(Item 위도/경도 수동 입력은 이 기능과 무관하게 항상 가능).
	 *
	 * 결과가 1건이든 여러 건이든 서버가 자동으로 확정하지 않는다 — 관리자 화면이 항상 후보 목록을
	 * 보여주고 사용자가 클릭으로 확정한다(회원가입 폼의 주소검색 팝업과 같은 패턴, 오탐으로 엉뚱한
	 * 좌표가 저장되는 것을 막기 위함). 이 엔드포인트는 정렬만 책임지고, 확정 클릭은 클라이언트가
	 * 이미 받은 목록 중 하나를 그대로 골라 폼에 채우는 것뿐이라 별도 확정 API가 필요 없다.
	 */
	public static function search_kakao_address( WP_REST_Request $request ) {
		if ( ! defined( 'HLF_KAKAO_REST_API_KEY' ) || ! HLF_KAKAO_REST_API_KEY ) {
			return new WP_Error( 'hlf_kakao_not_configured', '카카오 REST API 키가 설정되지 않았습니다.', array( 'status' => 501 ) );
		}

		$query = trim( (string) ( $request['q'] ?? '' ) );
		if ( '' === $query ) {
			return new WP_Error( 'hlf_kakao_query_required', '검색할 주소를 입력해 주세요.', array( 'status' => 400 ) );
		}
		if ( hlf_mb_strlen( $query ) > 200 ) {
			return new WP_Error( 'hlf_kakao_query_too_long', '검색어가 너무 깁니다.', array( 'status' => 400 ) );
		}

		$response = wp_remote_get(
			'https://dapi.kakao.com/v2/local/search/address.json?query=' . rawurlencode( $query ),
			array(
				'headers' => array( 'Authorization' => 'KakaoAK ' . HLF_KAKAO_REST_API_KEY ),
				'timeout' => 5,
			)
		);
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'hlf_kakao_request_failed', '주소 검색 중 오류가 발생했습니다.', array( 'status' => 502 ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error( 'hlf_kakao_request_failed', '주소 검색 중 오류가 발생했습니다.', array( 'status' => 502 ) );
		}

		$body      = json_decode( wp_remote_retrieve_body( $response ), true );
		$documents = is_array( $body['documents'] ?? null ) ? $body['documents'] : array();
		if ( empty( $documents ) ) {
			return new WP_Error( 'hlf_kakao_no_result', '주소 검색 결과를 찾지 못했습니다.', array( 'status' => 404 ) );
		}

		$sorted   = self::sort_kakao_candidates( $documents, $query );
		$results  = array_map( array( __CLASS__, 'map_kakao_document' ), array_slice( $sorted, 0, self::KAKAO_ADDRESS_MAX_CANDIDATES ) );

		return rest_ensure_response( array( 'results' => $results ) );
	}

	/** 카카오 문서(document) 1건을 관리자 폼이 바로 쓰는 필드 이름(road_address/lot_address/latitude/longitude)으로 변환. */
	private static function map_kakao_document( array $doc ): array {
		return array(
			'road_address'      => (string) ( $doc['road_address']['address_name'] ?? '' ),
			'lot_address'       => (string) ( $doc['address']['address_name'] ?? $doc['address_name'] ?? '' ),
			'latitude'          => (string) ( $doc['y'] ?? '' ),
			'longitude'         => (string) ( $doc['x'] ?? '' ),
			'region_1depth_name' => (string) ( $doc['address']['region_1depth_name'] ?? '' ),
			'region_2depth_name' => (string) ( $doc['address']['region_2depth_name'] ?? '' ),
			'region_3depth_name' => (string) ( $doc['address']['region_3depth_name'] ?? '' ),
		);
	}

	/**
	 * 우선순위 4단계(요청서 3-5): ① 입력 지번주소와 정확히 일치(법정동+본번·부번이 일치하고, 그 뒤에
	 * 다른 텍스트가 붙지 않는 "깨끗한" 지번주소) → ② 동일 법정동+본번·부번 일치(뒤에 참고용 텍스트가
	 * 더 붙어 있어도 인정) → ③ 지번(address) 타입이지만 본번·부번이 다른 결과 → ④ 나머지(지번 정보
	 * 자체가 없는 도로명 전용 결과 등). "정확히 일치"를 전체 문자열 완전 일치로 판정하지 않는 이유:
	 * 카카오는 항상 "시/도 + 구/군 + 동 + 번지" 풀네임으로 응답하는데, 사용자는 보통 "삼성동 159-8"처럼
	 * 짧게만 입력하므로 전체 문자열 일치는 사실상 절대 만족되지 않는다 — 법정동+번지 일치 여부와
	 * "번지 뒤에 잡음이 붙어있는지"로 ①/②를 가른다.
	 *
	 * usort는 안정 정렬이 아니므로(PHP 명세상 보장 안 됨) 원본 인덱스를 tie-breaker로 함께 비교해
	 * 같은 순위 안에서는 카카오 응답 순서를 그대로 보존한다.
	 */
	private static function sort_kakao_candidates( array $documents, string $query ): array {
		list( $query_main_no, $query_sub_no ) = self::extract_bunji( $query );

		$scored = array();
		foreach ( $documents as $index => $doc ) {
			$address = $doc['address'] ?? null;
			$score   = 4; // ④ 나머지(지번 정보 없음).

			if ( is_array( $address ) ) {
				$score = 3; // ③ 지번 타입이지만 본번·부번 불일치.

				$region_3 = (string) ( $address['region_3depth_name'] ?? '' );
				$main_no  = (string) ( $address['main_address_no'] ?? '' );
				$sub_no   = (string) ( $address['sub_address_no'] ?? '' );
				$same_bunji = $query_main_no !== '' && $main_no === $query_main_no
					&& ( $query_sub_no === '' ? true : $sub_no === $query_sub_no )
					&& ( '' === $region_3 || false !== hlf_mb_strpos( $query, $region_3 ) );

				if ( $same_bunji ) {
					$address_name = rtrim( (string) ( $address['address_name'] ?? '' ) );
					$bunji_text   = $sub_no !== '' ? ( $main_no . '-' . $sub_no ) : $main_no;
					// 번지 숫자 뒤에 다른 텍스트가 붙어 있지 않으면(문자열이 정확히 그 번지로 끝나면)
					// "깨끗한" 지번주소로 보고 ①, 붙어 있으면(예: "…159-8번지 인근") ②로 내린다.
					$score = str_ends_with( $address_name, $bunji_text ) ? 1 : 2;
				}
			}

			$scored[] = array( 'score' => $score, 'index' => $index, 'doc' => $doc );
		}

		usort( $scored, static function ( $a, $b ) {
			return $a['score'] <=> $b['score'] ?: $a['index'] <=> $b['index'];
		} );

		return array_map( static function ( $s ) { return $s['doc']; }, $scored );
	}

	/** 입력 지번주소 문자열에서 "본번-부번"(예: "159-8" -> ['159','8'], "159" -> ['159','']) 추출. */
	private static function extract_bunji( string $query ): array {
		if ( preg_match( '/(\d+)(?:-(\d+))?(?!.*\d)/u', $query, $m ) ) {
			return array( $m[1], $m[2] ?? '' );
		}
		return array( '', '' );
	}

	/* ---------------- 원본 매물(전체 매물) handlers ---------------- */

	public static function list_source_listings( WP_REST_Request $request ) {
		$params = self::request_params( $request );
		return rest_ensure_response( HLF_Source_Listing_Repository::list( array(
			'search'   => (string) ( $params['search'] ?? '' ),
			'linked'   => (string) ( $params['linked'] ?? '' ),
			'contact'  => (string) ( $params['contact'] ?? '' ),
			'page'     => (int) ( $params['page'] ?? 1 ),
			'per_page' => (int) ( $params['per_page'] ?? 20 ),
		) ) );
	}

	public static function create_source_listing( WP_REST_Request $request ) {
		$source_id = HLF_Source_Listing_Repository::create( self::source_input( $request ) );
		if ( is_wp_error( $source_id ) ) {
			return $source_id;
		}
		$response = rest_ensure_response( HLF_Source_Listing_Repository::to_array( get_post( $source_id ) ) );
		$response->set_status( 201 );
		return $response;
	}

	public static function get_source_listing( WP_REST_Request $request ) {
		$source = HLF_Source_Listing_Repository::get( (int) $request['source_id'] );
		if ( ! $source ) {
			return new WP_Error( 'hlf_source_not_found', '매물을 찾을 수 없습니다.', array( 'status' => 404 ) );
		}
		return rest_ensure_response( HLF_Source_Listing_Repository::to_array( $source ) );
	}

	public static function update_source_listing( WP_REST_Request $request ) {
		$result = HLF_Source_Listing_Repository::update( (int) $request['source_id'], self::source_input( $request ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( HLF_Source_Listing_Repository::to_array( get_post( (int) $request['source_id'] ) ) );
	}

	public static function delete_source_listing( WP_REST_Request $request ) {
		$result = HLF_Source_Listing_Repository::delete( (int) $request['source_id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array( 'deleted' => (bool) $result ) );
	}

	public static function update_source_images( WP_REST_Request $request ) {
		$source_id = (int) $request['source_id'];
		$params    = self::request_params( $request );
		$exterior  = (int) ( $params['exterior_image_id'] ?? 0 );
		$interior  = is_array( $params['interior_image_ids'] ?? null ) ? array_map( 'intval', $params['interior_image_ids'] ) : array();

		$result = HLF_Source_Listing_Repository::set_images( $source_id, $exterior, $interior );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( HLF_Source_Listing_Repository::to_array( get_post( $source_id ) ) );
	}

	public static function delete_source_image( WP_REST_Request $request ) {
		$source_id = (int) $request['source_id'];
		$result    = HLF_Source_Listing_Repository::delete_image( $source_id, (int) $request['attachment_id'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( HLF_Source_Listing_Repository::to_array( get_post( $source_id ) ) );
	}

	/* ---------------- Flyer ↔ 원본 매물 포함/해제 ---------------- */

	public static function include_source_in_flyer( WP_REST_Request $request ) {
		$flyer_id  = (int) $request['id'];
		$source_id = (int) $request['source_id'];
		$result    = HLF_Source_Listing_Repository::include_in_flyer( $flyer_id, $source_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array(
			'included' => true,
			'item'     => HLF_Item_Repository::to_array( get_post( (int) $result ) ),
			'source'   => HLF_Source_Listing_Repository::to_array( get_post( $source_id ) ),
		) );
	}

	public static function exclude_source_from_flyer( WP_REST_Request $request ) {
		$flyer_id  = (int) $request['id'];
		$source_id = (int) $request['source_id'];
		$result    = HLF_Source_Listing_Repository::exclude_from_flyer( $flyer_id, $source_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array(
			'included' => false,
			'removed'  => (int) $result,
			'source'   => HLF_Source_Listing_Repository::to_array( get_post( $source_id ) ),
		) );
	}

	/* ---------------- 담당자 디렉터리 handlers ---------------- */

	public static function get_contacts() {
		return rest_ensure_response( HLF_Contact_Directory::to_array() );
	}

	public static function add_contact( WP_REST_Request $request ) {
		$params = self::request_params( $request );
		$result = HLF_Contact_Directory::add( (string) ( $params['name'] ?? '' ), (string) ( $params['phone'] ?? '' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( HLF_Contact_Directory::to_array() );
	}

	public static function update_contact( WP_REST_Request $request ) {
		$params = self::request_params( $request );
		$result = HLF_Contact_Directory::update( (int) $request['index'], (string) ( $params['name'] ?? '' ), (string) ( $params['phone'] ?? '' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( HLF_Contact_Directory::to_array() );
	}

	public static function remove_contact( WP_REST_Request $request ) {
		$result = HLF_Contact_Directory::remove( (int) $request['index'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( HLF_Contact_Directory::to_array() );
	}

	public static function set_default_contact( WP_REST_Request $request ) {
		$result = HLF_Contact_Directory::set_default( (int) $request['index'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( HLF_Contact_Directory::to_array() );
	}

	/* ---------------- 대시보드 ---------------- */

	public static function get_dashboard() {
		$source_stats = HLF_Source_Listing_Repository::stats();
		// count(get_posts(...)) 방식은 Flyer가 늘어날수록 ID 전체를 매번 전송받아 세는 방식이라
		// Flyer 수에 비례해 느려진다 — wp_count_posts()는 상태별 개수를 단일 집계 쿼리(GROUP BY)로
		// 가져오므로 Flyer가 수백 개여도 비용이 그대로다.
		$counts      = wp_count_posts( HLF_Post_Types::FLYER );
		$flyer_count = (int) ( $counts->draft ?? 0 )
			+ (int) ( $counts->publish ?? 0 )
			+ (int) ( $counts->{HLF_Post_Types::STATUS_ARCHIVED} ?? 0 );
		return rest_ensure_response( array(
			'source_total'    => $source_stats['total'],
			'source_linked'   => $source_stats['linked'],
			'source_unlinked' => $source_stats['unlinked'],
			'flyer_total'     => $flyer_count,
		) );
	}

	/* ---------------- helpers ---------------- */

	/** 요청 본문에서 화이트리스트 필드만 뽑아 넘긴다(정규화는 repository/apply_fields가 재확인). */
	private static function item_input( WP_REST_Request $request ): array {
		return self::pluck_params( $request, HLF_Meta_Schema::writable_fields() );
	}

	/** 원본 매물 입력. source_writable_fields 화이트리스트만 뽑는다. */
	private static function source_input( WP_REST_Request $request ): array {
		return self::pluck_params( $request, HLF_Meta_Schema::source_writable_fields() );
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
		return new WP_Error( 'hlf_not_implemented', '이 기능은 아직 준비 중입니다.', array( 'status' => 501 ) );
	}
}
