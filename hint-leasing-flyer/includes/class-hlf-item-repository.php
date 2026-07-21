<?php
/**
 * leasing_flyer_item CRUD + 식별자.
 *
 * 식별자 분리(요청서 1-D):
 * - item_number  : Flyer 내부에서 한 번 생성되면 절대 안 바뀌는 URL 식별자. 포맷 "I0001".
 * - display_order: 화면 표시 순번. reorder로 언제든 바뀐다.
 * 브라우저 index/배열 순서를 영구 ID로 쓰지 않는다.
 *
 * item_number 생성은 부모 flyer의 시퀀스 메타(_hlf_next_item_seq)를 원자적으로 증가시켜 얻는다.
 * MySQL의 LAST_INSERT_ID(expr) 관용구로 "증가 + 증가된 값 읽기"를 커넥션 단위 원자연산으로 처리한다
 * (동시 저장에도 같은 번호가 두 번 나오지 않는다). item은 post_parent로 flyer에 연결한다.
 */
defined( 'ABSPATH' ) || exit;

final class HLF_Item_Repository {

	/** Flyer 하나에 담을 수 있는 최대 항목 수. 관리자 UI도 같은 값을 안내로 쓰되, 기준은 서버다. */
	const MAX_ITEMS_PER_FLYER = 10;

	public static function format_item_number( int $seq ): string {
		return 'I' . sprintf( '%04d', $seq );
	}

	/** 부모 flyer 소속 item들을 display_order 오름차순으로 반환. */
	public static function get_items( int $flyer_id ): array {
		return get_posts( array(
			'post_type'      => HLF_Post_Types::ITEM,
			'post_parent'    => $flyer_id,
			'post_status'    => array( 'publish', 'inherit', 'draft' ),
			'posts_per_page' => -1,
			'orderby'        => array( 'meta_value_num' => 'ASC', 'ID' => 'ASC' ),
			'meta_key'       => 'display_order',
		) );
	}

	public static function get_item_by_number( int $flyer_id, string $item_number ): ?WP_Post {
		$items = get_posts( array(
			'post_type'      => HLF_Post_Types::ITEM,
			'post_parent'    => $flyer_id,
			'post_status'    => array( 'publish', 'inherit', 'draft' ),
			'posts_per_page' => 1,
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

		// 시드 행이 없음 → 기존 item_number 최댓값에서 복구.
		$max = 0;
		foreach ( self::get_items( $flyer_id ) as $item ) {
			$num = (string) get_post_meta( $item->ID, 'item_number', true );
			if ( preg_match( '/^I0*(\d+)$/', $num, $m ) ) {
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
		$guard = HLF_Flyer_Repository::assert_not_archived( $flyer_id );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		// 10개 제한 체크와 display_order 최댓값 계산 둘 다 "이 Flyer의 현재 item 목록"이 필요하므로
		// get_items()를 한 번만 불러 재사용한다(전에는 이 두 용도로 같은 쿼리를 두 번 날렸다).
		$existing_items = self::get_items( $flyer_id );

		// 클라이언트 우회(직접 REST 호출 등) 방지 — 서버가 최종 기준. UI는 안내만 표시한다.
		if ( count( $existing_items ) >= self::MAX_ITEMS_PER_FLYER ) {
			return new WP_Error(
				'hlf_item_limit_reached',
				sprintf( 'Flyer 하나에는 최대 %d개의 매물만 담을 수 있습니다.', self::MAX_ITEMS_PER_FLYER ),
				array( 'status' => 400 )
			);
		}

		// wp_insert_post() 이전에 기존 item들의 display_order 최댓값을 구한다 — 삭제로 중간 번호가
		// 빈 상태에서 "전체 개수"로 새 순번을 매기면 남아있는 item과 값이 겹칠 수 있다(예: 0,1,2 중
		// 1번 삭제 후 재추가 시 "개수-1"=1이 남아있는 2번과 충돌). 최댓값+1이면 항상 유일하다.
		$max_existing_order = -1;
		foreach ( $existing_items as $existing_item ) {
			$max_existing_order = max( $max_existing_order, (int) get_post_meta( $existing_item->ID, 'display_order', true ) );
		}
		$next_display_order = $max_existing_order + 1; // 기존 item이 없으면 -1 + 1 = 0.

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
		$guard = HLF_Flyer_Repository::assert_not_archived( $flyer_id );
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
		$guard = HLF_Flyer_Repository::assert_not_archived( $flyer_id );
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
		$guard = HLF_Flyer_Repository::assert_not_archived( $flyer_id );
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
	 * 이미지 목록 저장. $exterior_image_id/$interior_image_ids는 관리자가 WordPress Media
	 * Library(wp.media)에서 고른 attachment ID다 — 새로 업로드했거나, 사이트에 이미 있던(post_parent가
	 * 이 item_id가 아닐 수 있는) 미디어를 그대로 선택할 수도 있다. 그래서 소유권(post_parent) 검증은
	 * 하지 않고 "실제로 존재하는 attachment 포스트인지"만 확인한다. post_parent를 이 item_id로
	 * 재설정(reparent)하지도 않는다 — 공유 중인 첨부의 소속을 바꾸면 다른 곳(다른 글/다른 매물)에
	 * 영향을 줄 수 있으므로 ID만 그대로 저장한다.
	 */
	public static function set_images( int $flyer_id, int $item_id, int $exterior_image_id, array $interior_image_ids ): bool|WP_Error {
		$guard = HLF_Flyer_Repository::assert_not_archived( $flyer_id );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		$item = get_post( $item_id );
		if ( ! $item || HLF_Post_Types::ITEM !== $item->post_type || (int) $item->post_parent !== $flyer_id ) {
			return new WP_Error( 'hlf_item_not_found', '해당 Flyer에 속한 매물이 아닙니다.', array( 'status' => 404 ) );
		}

		$interior_image_ids = array_values( array_unique( array_map( 'intval', $interior_image_ids ) ) );
		$requested          = $exterior_image_id > 0 ? array_merge( array( $exterior_image_id ), $interior_image_ids ) : $interior_image_ids;

		foreach ( $requested as $id ) {
			$attachment = get_post( $id );
			if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
				return new WP_Error(
					'hlf_image_invalid',
					'선택한 항목 중 유효하지 않은 이미지가 있습니다.',
					array( 'status' => 400 )
				);
			}
		}

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
	 * 이미지 하나를 이 Item의 exterior_image_id/interior_image_ids에서만 떼어낸다(detach) —
	 * Attachment 자체(파일)는 절대 지우지 않는다. Media Library에서 고른 이미지는 사이트 다른
	 * 곳(다른 글/다른 매물)에서도 쓰이고 있을 수 있어, 여기서 파일까지 삭제하면 그쪽까지 함께
	 * 사라지는 사고가 난다.
	 */
	public static function delete_image( int $flyer_id, int $item_id, int $attachment_id ): bool|WP_Error {
		$guard = HLF_Flyer_Repository::assert_not_archived( $flyer_id );
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

	/** item 하나를 계산 지표까지 붙여 직렬화(공개 템플릿/REST 공용). */
	public static function to_array( WP_Post $item ): array {
		$data                   = HLF_Meta_Schema::read_item( $item->ID );
		$data['metrics']        = hlf_calculate_item_metrics( $data );
		$data['image_previews'] = self::image_previews( $data );
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
}
