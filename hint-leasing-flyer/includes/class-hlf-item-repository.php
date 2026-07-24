<?php
/**
 * leasing_flyer_item CRUD + 식별자.
 *
 * 식별자 분리(요청서 1-D, 번호 포맷은 요청서 7로 변경):
 * - item_number  : Flyer 내부에서 한 번 생성되면 절대 안 바뀌는 URL 식별자. 요청서 7 이후 새 item은
 *   "1", "2"…처럼 순수 숫자만 쓴다(예 /list/26072301/3/). 이 필드가 생기기 전(옛 "I0001" 포맷)에
 *   만들어진 item은 저장된 값 그대로 유지된다 — item_number는 애초에 저장값이라 이 포맷 변경이
 *   기존 값을 절대 건드리지 않는다.
 * - display_order: 화면 표시 순번. reorder로 언제든 바뀐다.
 * 브라우저 index/배열 순서를 영구 ID로 쓰지 않는다.
 *
 * item_number 생성은 부모 flyer의 시퀀스 메타(_hlf_next_item_seq)를 원자적으로 증가시켜 얻는다.
 * MySQL의 LAST_INSERT_ID(expr) 관용구로 "증가 + 증가된 값 읽기"를 커넥션 단위 원자연산으로 처리한다
 * (동시 저장에도 같은 번호가 두 번 나오지 않는다). item은 post_parent로 flyer에 연결한다.
 */
defined( 'ABSPATH' ) || exit;

final class HLF_Item_Repository {

	// 요청서: 갤러리 사진은 대표 1장 + 아래 슬라이드 3장(대표 포함 4장)까지만 허용한다 — 그 이상은
	// 갤러리 카드/슬라이드 폭을 고정 크기로 유지하기 어렵다(assets/css/public.css).
	const MAX_IMAGES = 4;

	public static function format_item_number( int $seq ): string {
		return (string) $seq;
	}

	/** 부모 flyer 소속 item들을 display_order 오름차순으로 반환. */
	public static function get_items( int $flyer_id ): array {
		return get_posts( array(
			'post_type'      => HLF_Post_Types::ITEM,
			'post_parent'    => $flyer_id,
			'post_status'    => array( 'publish', 'inherit', 'draft' ),
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'orderby'        => array( 'meta_value_num' => 'ASC', 'ID' => 'ASC' ),
			'meta_key'       => 'display_order',
		) );
	}

	/**
	 * item 개수만 필요할 때(단건 Flyer 조회 등) get_items()보다 가볍게 센다 — get_items()는
	 * display_order 정렬을 위해 postmeta JOIN + 전체 포스트 객체 hydration이 필요하지만, 개수만 셀
	 * 때는 정렬도 postmeta도 필요 없다(fields=>ids로 ID만 가져옴). Flyer 여러 개를 한 번에 나열할
	 * 때(목록 페이지네이션)는 이 메서드를 Flyer 수만큼 반복 호출하지 말고 count_items_batch()를
	 * 쓴다 — 그게 N+1을 피하는 지점이다.
	 */
	public static function count_items( int $flyer_id ): int {
		return count( get_posts( array(
			'post_type'      => HLF_Post_Types::ITEM,
			'post_parent'    => $flyer_id,
			'post_status'    => array( 'publish', 'inherit', 'draft' ),
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'fields'         => 'ids',
		) ) );
	}

	/**
	 * 여러 Flyer의 item 개수를 단 한 번의 쿼리로 계산한다(HLF_Flyer_Repository::list()가 페이지당
	 * Flyer 수만큼 count_items()를 따로 부르던 N+1을 없앤다). post_parent__in + fields=>id=>parent로
	 * 포스트 객체 hydration 없이 부모 ID만 받아와 PHP에서 집계한다 — HLF_Source_Listing_Repository::
	 * source_link_map()의 배치 계산과 같은 패턴이다.
	 *
	 * @param int[] $flyer_ids
	 * @return array<int,int> flyer_id => item 개수(해당 flyer에 item이 없으면 0).
	 */
	public static function count_items_batch( array $flyer_ids ): array {
		$counts = array_fill_keys( $flyer_ids, 0 );
		if ( empty( $flyer_ids ) ) {
			return $counts;
		}
		$pairs = get_posts( array(
			'post_type'       => HLF_Post_Types::ITEM,
			'post_parent__in' => $flyer_ids,
			'post_status'     => array( 'publish', 'inherit', 'draft' ),
			'posts_per_page'  => -1,
			'no_found_rows'   => true,
			'fields'          => 'id=>parent',
		) );
		foreach ( $pairs as $parent_id ) {
			$parent_id = (int) $parent_id;
			if ( isset( $counts[ $parent_id ] ) ) {
				$counts[ $parent_id ]++;
			}
		}
		return $counts;
	}

	public static function get_item_by_number( int $flyer_id, string $item_number ): ?WP_Post {
		$items = get_posts( array(
			'post_type'      => HLF_Post_Types::ITEM,
			'post_parent'    => $flyer_id,
			'post_status'    => array( 'publish', 'inherit', 'draft' ),
			'posts_per_page' => 1,
			'no_found_rows'  => true,
			'meta_key'       => 'item_number',
			'meta_value'     => $item_number,
		) );
		return $items ? $items[0] : null;
	}

	/**
	 * 부모 flyer의 시퀀스를 원자적으로 +1 하고 그 값을 반환.
	 * 시드 행이 없으면(가져오기/구버전 데이터) 기존 item_number 최댓값 기준으로 복구 시드한다.
	 */
	private static function next_sequence( int $flyer_id ): int {
		global $wpdb;
		$meta_key = HLF_Meta_Schema::FLYER_ITEM_SEQ;

		$rows = $wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->postmeta} SET meta_value = LAST_INSERT_ID(CAST(meta_value AS UNSIGNED) + 1) WHERE post_id = %d AND meta_key = %s",
			$flyer_id,
			$meta_key
		) );

		if ( $rows ) {
			return (int) $wpdb->get_var( 'SELECT LAST_INSERT_ID()' );
		}

		// 시드 행이 없음 → 기존 item_number 최댓값에서 복구. 옛 포맷("I0001")과 요청서 7의 새 포맷
		// (순수 숫자, 예 "3") 둘 다 인식한다 — "I" 접두사는 있어도 없어도 그만이다.
		$max = 0;
		foreach ( self::get_items( $flyer_id ) as $item ) {
			$num = (string) get_post_meta( $item->ID, 'item_number', true );
			if ( preg_match( '/^I?0*(\d+)$/', $num, $m ) ) {
				$max = max( $max, (int) $m[1] );
			}
		}
		$next = $max + 1;
		// 다음 호출을 위해 시퀀스 행을 현재 값으로 남긴다.
		delete_post_meta( $flyer_id, $meta_key );
		add_post_meta( $flyer_id, $meta_key, $next, true );
		return $next;
	}

	public static function create_item( int $flyer_id, array $fields ): int|WP_Error {
		// "이 Flyer가 새 Item을 받아도 되는가"(보관 가드 + 10개 상한)와 display_order 계산은
		// HLF_Flyer_Item_Service가 조정한다 — Item_Repository는 실제 포스트 생성/필드 저장만 맡는다.
		$prep = HLF_Flyer_Item_Service::prepare_item_creation( $flyer_id );
		if ( is_wp_error( $prep ) ) {
			return $prep;
		}
		$next_display_order = $prep['next_display_order'];

		$item_id = wp_insert_post( array(
			'post_type'   => HLF_Post_Types::ITEM,
			'post_parent' => $flyer_id,
			'post_status' => 'publish',
			'post_title'  => sanitize_text_field( $fields['road_address'] ?? $fields['lot_address'] ?? ( 'Flyer ' . $flyer_id . ' 항목' ) ),
		), true );

		if ( is_wp_error( $item_id ) ) {
			return $item_id;
		}
		$item_id = (int) $item_id;

		// 불변 item_number(원자적) + display_order(맨 뒤, 위에서 미리 계산한 값).
		$seq = self::next_sequence( $flyer_id );
		update_post_meta( $item_id, 'item_number', self::format_item_number( $seq ) );
		update_post_meta( $item_id, 'display_order', $next_display_order );

		self::apply_fields( $item_id, $fields );
		return $item_id;
	}

	public static function update_item( int $flyer_id, int $item_id, array $fields ): int|WP_Error {
		$guard = HLF_Flyer_Item_Service::assert_not_archived( $flyer_id );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}
		$item = get_post( $item_id );
		if ( ! $item || HLF_Post_Types::ITEM !== $item->post_type || (int) $item->post_parent !== $flyer_id ) {
			return new WP_Error( 'hlf_item_not_found', '해당 Flyer에 속한 매물이 아닙니다.', array( 'status' => 404 ) );
		}
		self::apply_fields( $item_id, $fields );
		return $item_id;
	}

	public static function delete_item( int $flyer_id, int $item_id ): bool|WP_Error {
		$guard = HLF_Flyer_Item_Service::assert_not_archived( $flyer_id );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}
		$item = get_post( $item_id );
		if ( ! $item || HLF_Post_Types::ITEM !== $item->post_type || (int) $item->post_parent !== $flyer_id ) {
			return new WP_Error( 'hlf_item_not_found', '해당 Flyer에 속한 매물이 아닙니다.', array( 'status' => 404 ) );
		}
		return (bool) wp_delete_post( $item_id, true );
	}

	/**
	 * 표시 순서 재정렬. $ordered_item_ids 순서대로 display_order를 0..n-1로 부여한다.
	 * item_number는 건드리지 않는다(상세 URL 불변).
	 *
	 * 저장 전에 요청 배열 전체를 검증한다 — 중복 ID가 없어야 하고, ID 집합이 이 Flyer의
	 * 현재 item ID 집합과 정확히 일치해야 한다(누락도, 타 Flyer item 포함도 거부).
	 * 검증에 실패하면 아무 것도 저장하지 않고 즉시 400을 반환한다(부분 반영 금지).
	 */
	public static function reorder( int $flyer_id, array $ordered_item_ids ): bool|WP_Error {
		$guard = HLF_Flyer_Item_Service::assert_not_archived( $flyer_id );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$requested_ids = array_map( 'intval', $ordered_item_ids );

		if ( count( $requested_ids ) !== count( array_unique( $requested_ids ) ) ) {
			return new WP_Error( 'hlf_reorder_invalid', '순서 목록에 같은 매물이 중복으로 들어 있습니다.', array( 'status' => 400 ) );
		}

		$valid_ids = array_map( 'intval', wp_list_pluck( self::get_items( $flyer_id ), 'ID' ) );
		sort( $requested_ids );
		$sorted_valid_ids = $valid_ids;
		sort( $sorted_valid_ids );
		if ( $requested_ids !== $sorted_valid_ids ) {
			return new WP_Error(
				'hlf_reorder_invalid',
				'순서 목록이 이 Flyer의 현재 매물 구성과 일치하지 않습니다. 새로고침 후 다시 시도해 주세요.',
				array( 'status' => 400 )
			);
		}

		$order = 0;
		foreach ( $ordered_item_ids as $item_id ) {
			update_post_meta( (int) $item_id, 'display_order', $order++ );
		}
		return true;
	}

	/**
	 * officeleasing import(Phase 2-2)의 provenance 필드를 기록하는 서버 전용 경로.
	 * apply_fields()/writable_fields() 화이트리스트를 거치지 않는다 — item_number를 create_item()
	 * 내부에서 update_post_meta()로 직접 쓰는 것과 정확히 같은 패턴이다(일반 클라이언트 입력 경로가
	 * 아니라 서버 로직만 호출하는 전용 setter). HLF_OfficeLeasing_Import_Service만 이 메서드를 부른다.
	 */
	public static function set_snapshot_metadata(
		int $item_id,
		int $source_listing_id,
		int $source_building_id,
		string $snapshot_created_at,
		string $snapshot_refreshed_at,
		int $snapshot_version
	): bool|WP_Error {
		$item = get_post( $item_id );
		if ( ! $item || HLF_Post_Types::ITEM !== $item->post_type ) {
			return new WP_Error( 'hlf_item_not_found', '매물을 찾을 수 없습니다.', array( 'status' => 404 ) );
		}

		// 각 값을 쓴 뒤 실제로 읽어서 검증한다 — update_post_meta()의 반환값(bool)은 "행이 바뀌었는지"만
		// 알려줄 뿐이라(같은 값이면 false) 성공 여부 판정에 쓸 수 없어, 대신 get_post_meta()로 읽어
		// 되돌아온 값을 비교한다. 워드프레스는 숫자 postmeta를 문자열로 반환하므로 숫자 필드는
		// (int) 캐스팅 후, 문자열 필드는 그대로 비교해야 정상 값을 오탐(false positive)으로 실패
		// 처리하지 않는다. snapshot_refreshed_at은 최초 Import 시 빈 문자열('')이 정상값이다.
		$fields = array(
			'source_listing_id'     => array( $source_listing_id, 'int' ),
			'source_building_id'    => array( $source_building_id, 'int' ),
			'snapshot_created_at'   => array( $snapshot_created_at, 'string' ),
			'snapshot_refreshed_at' => array( $snapshot_refreshed_at, 'string' ),
			'snapshot_version'      => array( $snapshot_version, 'int' ),
		);

		foreach ( $fields as $meta_key => $expected ) {
			list( $expected_value, $type ) = $expected;
			update_post_meta( $item_id, $meta_key, $expected_value );

			$stored = get_post_meta( $item_id, $meta_key, true );
			$stored = 'int' === $type ? (int) $stored : (string) $stored;
			$expected_value = 'int' === $type ? (int) $expected_value : (string) $expected_value;

			if ( $stored !== $expected_value ) {
				// 원인 파악용 필드명은 message가 아니라 data에만 담는다 — 화면에는 직원이 이해할 수
				// 있는 문구만 노출한다.
				return new WP_Error(
					'hlf_snapshot_metadata_write_failed',
					'매물 가져오기 중 데이터 저장을 확인하지 못했습니다. 다시 시도해 주세요.',
					array( 'status' => 500, 'field' => $meta_key )
				);
			}
		}

		return true;
	}

	/**
	 * "원본 매물(hlf_source_listing) → Flyer 포함"으로 만들어진 Item에 출처(source_listing_id)를
	 * 기록하는 서버 전용 경로. officeleasing import의 set_snapshot_metadata()와 같은 부류지만(둘 다
	 * apply_fields()/writable_fields()를 우회하는 서버 전용 setter), 이쪽은 building/version 같은
	 * officeleasing 전용 provenance 없이 source_listing_id 하나만 기록한다 — 나중에 "이 원본 매물이
	 * 어느 Flyer들에 포함돼 있나"를 역참조(HLF_Source_Listing_Repository)하는 데 이 값만 쓰인다.
	 * set_snapshot_metadata()와 동일하게 쓴 값을 다시 읽어 저장을 검증한다.
	 */
	public static function set_source_listing_id( int $item_id, int $source_listing_id ): bool|WP_Error {
		$item = get_post( $item_id );
		if ( ! $item || HLF_Post_Types::ITEM !== $item->post_type ) {
			return new WP_Error( 'hlf_item_not_found', '매물을 찾을 수 없습니다.', array( 'status' => 404 ) );
		}
		update_post_meta( $item_id, 'source_listing_id', $source_listing_id );
		if ( (int) get_post_meta( $item_id, 'source_listing_id', true ) !== $source_listing_id ) {
			return new WP_Error( 'hlf_source_link_write_failed', '매물 포함 중 데이터 저장을 확인하지 못했습니다. 다시 시도해 주세요.', array( 'status' => 500 ) );
		}
		return true;
	}

	/**
	 * 이미지 목록 저장. $exterior_image_id/$interior_image_ids는 관리자가 WordPress Media
	 * Library(wp.media)에서 고른 attachment ID다 — 새로 업로드했거나, 사이트에 이미 있던(post_parent가
	 * 이 item_id가 아닐 수 있는) 미디어를 그대로 선택할 수도 있다. 그래서 소유권(post_parent) 검증은
	 * 하지 않고 "실제로 존재하는 attachment 포스트인지"만 확인한다. post_parent를 이 item_id로
	 * 재설정(reparent)하지도 않는다 — 공유 중인 첨부의 소속을 바꾸면 다른 곳(다른 글/다른 매물)에
	 * 영향을 줄 수 있으므로 ID만 그대로 저장한다.
	 */
	public static function set_images( int $flyer_id, int $item_id, int $exterior_image_id, array $interior_image_ids ): bool|WP_Error {
		$guard = HLF_Flyer_Item_Service::assert_not_archived( $flyer_id );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$item = get_post( $item_id );
		if ( ! $item || HLF_Post_Types::ITEM !== $item->post_type || (int) $item->post_parent !== $flyer_id ) {
			return new WP_Error( 'hlf_item_not_found', '해당 Flyer에 속한 매물이 아닙니다.', array( 'status' => 404 ) );
		}

		$interior_image_ids = array_values( array_unique( array_map( 'intval', $interior_image_ids ) ) );
		$requested          = $exterior_image_id > 0 ? array_merge( array( $exterior_image_id ), $interior_image_ids ) : $interior_image_ids;

		// 요청서: 대표 1장 + 아래 슬라이드 3장(대표 포함 4장)으로 제한한다 — 갤러리 카드/슬라이드 폭을
		// 그 이상 스크롤 없이 고정 크기로 보여주기 위한 전제(assets/css/public.css).
		if ( count( $requested ) > self::MAX_IMAGES ) {
			return new WP_Error(
				'hlf_image_limit',
				'사진은 대표 이미지를 포함해 최대 ' . self::MAX_IMAGES . '장까지 등록할 수 있습니다.',
				array( 'status' => 400 )
			);
		}

		// delete_image()는 "지금 이미 저장된 목록에서 하나 뺀 나머지"를 그대로 이 메서드에 다시
		// 넘긴다 — 그 이미 저장돼 있던 나머지까지 매번 읽기 권한을 재확인하면, 다른 사람이 원래
		// 정상적으로 붙여 둔 이미지가 하나 섞여 있다는 이유만으로 "빼기" 작업 자체가 막혀버린다.
		// 그래서 권한 확인은 이번 요청에서 "새로 추가되는" ID에만 적용한다 — 이미 붙어 있던 ID를
		// 그대로 유지/제거하는 것은 이전에 이미 검증을 통과했으므로 다시 물을 필요가 없다.
		$existing = HLF_Meta_Schema::read_item( $item_id );
		$current_ids = array();
		if ( ! empty( $existing['exterior_image_id'] ) ) { $current_ids[] = (int) $existing['exterior_image_id']; }
		foreach ( $existing['interior_image_ids'] as $existing_id ) { $current_ids[] = (int) $existing_id; }

		foreach ( $requested as $id ) {
			$attachment = get_post( $id );
			if ( ! $attachment || 'attachment' !== $attachment->post_type || ! wp_attachment_is_image( $id ) ) {
				return new WP_Error(
					'hlf_image_invalid',
					'선택한 항목 중 유효하지 않은 이미지가 있습니다.',
					array( 'status' => 400 )
				);
			}
			// attachment 존재/타입만 확인하고 넘어가면, 낮은 권한 사용자가 자신이 볼 수 없는(다른
			// 사람의 비공개) attachment ID를 그대로 넣어 공개 Flyer에 새로 바인딩할 수 있다 — 새로
			// 추가되는 ID에 한해 읽기 권한을 확인한다.
			if ( ! in_array( $id, $current_ids, true ) && ! current_user_can( 'read_post', $id ) ) {
				return new WP_Error(
					'hlf_image_forbidden',
					'선택한 이미지 중 접근 권한이 없는 항목이 있습니다.',
					array( 'status' => 403 )
				);
			}
		}

		// 이미 등록돼 있던 사진의 원본 첨부 파일은 이 플러그인이 add_image_size로 등록한 hlf-item-photo/
		// hlf-item-thumb 크기를 아직 안 가지고 있을 수 있다(그 사이즈가 생기기 전에 업로드됐거나, 이
		// 매물에 처음 붙는 경우) — 새로 추가되는 ID에 한해 그 자리에서 생성해 둔다("최적 사이즈로
		// 리사이징 출력" 요청서). 실패해도(예: 파일 손상) 저장 자체는 계속 진행한다 — 화면에는
		// wp_get_attachment_image()가 사이즈 없는 attachment에 자동으로 원본을 대신 써 주므로 안전하다.
		self::ensure_image_sizes( array_diff( $requested, $current_ids ) );

		update_post_meta( $item_id, 'exterior_image_id', $exterior_image_id );
		update_post_meta( $item_id, 'interior_image_ids', $interior_image_ids );

		// set_snapshot_metadata()와 같은 이유로 쓴 값을 다시 읽어 검증한다 — update_post_meta()의
		// 반환값(bool)은 "행이 실제로 바뀌었는지"만 알려줄 뿐이라 성공 여부 판정에 못 쓴다.
		$stored_exterior = (int) get_post_meta( $item_id, 'exterior_image_id', true );
		$stored_interior = get_post_meta( $item_id, 'interior_image_ids', true );
		$stored_interior = is_array( $stored_interior ) ? array_values( array_map( 'intval', $stored_interior ) ) : array();

		if ( $stored_exterior !== $exterior_image_id || $stored_interior !== $interior_image_ids ) {
			return new WP_Error( 'hlf_image_save_failed', '이미지 정보를 저장하지 못했습니다. 다시 시도해 주세요.', array( 'status' => 500 ) );
		}

		return true;
	}

	/**
	 * wp_generate_attachment_metadata()는 admin(wp-admin/includes/image.php)에서만 자동으로
	 * 로드되는데, 이 메서드는 REST 요청(공개 화면 쪽) 컨텍스트에서도 호출되므로 필요하면 직접
	 * require한다. 실패(파일 손상 등)해도 조용히 넘어간다 — 사이즈가 없으면 화면에서 원본으로
	 * 대체될 뿐 저장 자체를 막을 이유는 아니다.
	 */
	private static function ensure_image_sizes( array $attachment_ids ): void {
		if ( ! $attachment_ids ) {
			return;
		}
		foreach ( $attachment_ids as $attachment_id ) {
			// 이미 두 사이즈가 다 있으면(전에 이 매물이든 다른 매물이든 한 번 붙었던 사진이면 흔함)
			// 다시 인코딩할 필요가 없다 — wp_generate_attachment_metadata()는 이미지 파일을 열어
			// 등록된 사이즈 전부를 다시 만드는 무거운 작업이라, 매번 새로 하면 사진마다 매번 이 비용이
			// 들어 매물 저장이 느려진다.
			$existing = wp_get_attachment_metadata( $attachment_id );
			$sizes    = is_array( $existing ) ? ( $existing['sizes'] ?? array() ) : array();
			if ( isset( $sizes['hlf-item-photo'] ) && isset( $sizes['hlf-item-thumb'] ) ) {
				continue;
			}
			if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}
			$file = get_attached_file( $attachment_id );
			if ( ! $file ) {
				continue;
			}
			$metadata = wp_generate_attachment_metadata( $attachment_id, $file );
			if ( $metadata ) {
				wp_update_attachment_metadata( $attachment_id, $metadata );
			}
		}
	}

	/**
	 * 이미지 하나를 이 Item의 exterior_image_id/interior_image_ids에서만 떼어낸다(detach) —
	 * Attachment 자체(파일)는 절대 지우지 않는다. Media Library에서 고른 이미지는 사이트 다른
	 * 곳(다른 글/다른 매물)에서도 쓰이고 있을 수 있어, 여기서 파일까지 삭제하면 그쪽까지 함께
	 * 사라지는 사고가 난다.
	 */
	public static function delete_image( int $flyer_id, int $item_id, int $attachment_id ): bool|WP_Error {
		$guard = HLF_Flyer_Item_Service::assert_not_archived( $flyer_id );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$item = get_post( $item_id );
		if ( ! $item || HLF_Post_Types::ITEM !== $item->post_type || (int) $item->post_parent !== $flyer_id ) {
			return new WP_Error( 'hlf_item_not_found', '해당 Flyer에 속한 매물이 아닙니다.', array( 'status' => 404 ) );
		}

		$current  = HLF_Meta_Schema::read_item( $item_id );
		$is_exterior = ( (int) $current['exterior_image_id'] === $attachment_id );
		$is_interior = in_array( $attachment_id, $current['interior_image_ids'], true );
		if ( ! $is_exterior && ! $is_interior ) {
			return new WP_Error( 'hlf_image_not_found', '이 매물에 지정된 이미지가 아닙니다.', array( 'status' => 404 ) );
		}

		$exterior = $is_exterior ? 0 : (int) $current['exterior_image_id'];
		$interior = array_values( array_diff( $current['interior_image_ids'], array( $attachment_id ) ) );

		return self::set_images( $flyer_id, $item_id, $exterior, $interior );
	}

	/** 화이트리스트 필드만 정규화해 저장. item_number/display_order 등 서버관리 필드는 무시된다. */
	private static function apply_fields( int $item_id, array $fields ): void {
		$schema   = HLF_Meta_Schema::item_fields();
		$writable = HLF_Meta_Schema::writable_fields();
		foreach ( $fields as $key => $value ) {
			if ( ! in_array( $key, $writable, true ) ) {
				continue;
			}
			$type = $schema[ $key ]['type'];
			update_post_meta( $item_id, $key, HLF_Meta_Schema::sanitize( $type, $value ) );
		}
	}

	/**
	 * item 하나를 계산 지표까지 붙여 직렬화(공개 템플릿/REST 공용).
	 *
	 * $include_image_previews는 기본 true(REST 응답 — 관리자 편집 화면의 wp.media 썸네일 표시에
	 * 실제로 쓰인다, admin-flyer-edit.js의 item.image_previews 참고). 공개 목록/상세 페이지
	 * (HLF_Routes::render())는 이 맵을 전혀 쓰지 않는데도(공개 템플릿은 exterior_image_id/
	 * interior_image_ids로 wp_get_attachment_image()를 직접 호출) 항상 계산되고 있었다 —
	 * image_previews()가 매물마다 사진 개수만큼 wp_get_attachment_image_url()(내부적으로
	 * get_post()+postmeta 조회)을 호출해, 리스트↔상세 페이지를 오갈 때마다(둘 다 매번 매물
	 * 전체를 다시 조회하는 완전한 서버 렌더링이라) 불필요한 DB 조회가 매물 수 × 사진 수만큼
	 * 반복되고 있었다. 공개 라우트에서는 false로 호출해 이 계산을 아예 건너뛴다.
	 */
	public static function to_array( WP_Post $item, bool $include_image_previews = true ): array {
		$data            = HLF_Meta_Schema::read_item( $item->ID );
		$data['metrics'] = hlf_calculate_item_metrics( $data );
		if ( $include_image_previews ) {
			$data['image_previews'] = self::image_previews( $data );
			$data['image_blur']     = self::image_blur( $data );
		}
		return $data;
	}

	/**
	 * 관리자 화면 미리보기 전용 — { attachment_id: 썸네일 URL } 맵. 브라우저(관리자 JS)는
	 * wp_get_attachment_image_url()을 직접 호출할 수 없으므로 REST 응답에 같이 실어 보낸다.
	 * exterior_image_id/interior_image_ids 원본 필드는 그대로 두고 이건 추가 정보일 뿐이다.
	 */
	private static function image_previews( array $data ): array {
		$ids = array();
		if ( ! empty( $data['exterior_image_id'] ) ) {
			$ids[] = (int) $data['exterior_image_id'];
		}
		foreach ( $data['interior_image_ids'] as $id ) {
			$ids[] = (int) $id;
		}

		$previews = array();
		foreach ( array_unique( $ids ) as $id ) {
			$url = wp_get_attachment_image_url( $id, 'thumbnail' );
			if ( $url ) {
				$previews[ $id ] = $url;
			}
		}
		return $previews;
	}

	/**
	 * { attachment_id: bool } 맵(관리자 편집 화면의 블러 체크박스 초기 상태 표시 전용, 요청서).
	 * 실제 저장은 관리자 JS가 REST 코어 미디어 엔드포인트(/wp/v2/media/{id})로 직접 하므로
	 * 여기서는 읽기만 한다 — HLF_Meta_Schema::PHOTO_BLUR 참고.
	 */
	private static function image_blur( array $data ): array {
		$ids = array();
		if ( ! empty( $data['exterior_image_id'] ) ) {
			$ids[] = (int) $data['exterior_image_id'];
		}
		foreach ( $data['interior_image_ids'] as $id ) {
			$ids[] = (int) $id;
		}
		$blur = array();
		foreach ( array_unique( $ids ) as $id ) {
			$blur[ $id ] = (bool) get_post_meta( $id, HLF_Meta_Schema::PHOTO_BLUR, true );
		}
		return $blur;
	}
}
