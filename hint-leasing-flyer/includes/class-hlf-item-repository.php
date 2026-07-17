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
		if ( HLF_Post_Types::FLYER !== get_post_type( $flyer_id ) ) {
			return new WP_Error( 'hlf_not_flyer', '대상이 Flyer가 아닙니다.', array( 'status' => 404 ) );
		}

		// 클라이언트 우회(직접 REST 호출 등) 방지 — 서버가 최종 기준. UI는 안내만 표시한다.
		if ( count( self::get_items( $flyer_id ) ) >= self::MAX_ITEMS_PER_FLYER ) {
			return new WP_Error(
				'hlf_item_limit_reached',
				sprintf( 'Flyer 하나에는 최대 %d개의 매물만 담을 수 있습니다.', self::MAX_ITEMS_PER_FLYER ),
				array( 'status' => 400 )
			);
		}

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

		// 불변 item_number(원자적) + display_order(맨 뒤).
		$seq = self::next_sequence( $flyer_id );
		update_post_meta( $item_id, 'item_number', self::format_item_number( $seq ) );

		$existing_count = max( 0, count( self::get_items( $flyer_id ) ) - 1 );
		update_post_meta( $item_id, 'display_order', $existing_count );

		self::apply_fields( $item_id, $fields );
		return $item_id;
	}

	public static function update_item( int $flyer_id, int $item_id, array $fields ): int|WP_Error {
		$item = get_post( $item_id );
		if ( ! $item || HLF_Post_Types::ITEM !== $item->post_type || (int) $item->post_parent !== $flyer_id ) {
			return new WP_Error( 'hlf_item_not_found', '해당 Flyer의 항목이 아닙니다.', array( 'status' => 404 ) );
		}
		self::apply_fields( $item_id, $fields );
		return $item_id;
	}

	public static function delete_item( int $flyer_id, int $item_id ): bool|WP_Error {
		$item = get_post( $item_id );
		if ( ! $item || HLF_Post_Types::ITEM !== $item->post_type || (int) $item->post_parent !== $flyer_id ) {
			return new WP_Error( 'hlf_item_not_found', '해당 Flyer의 항목이 아닙니다.', array( 'status' => 404 ) );
		}
		return (bool) wp_delete_post( $item_id, true );
	}

	/**
	 * 표시 순서 재정렬. $ordered_item_ids 순서대로 display_order를 0..n-1로 부여한다.
	 * item_number는 건드리지 않는다(상세 URL 불변).
	 */
	public static function reorder( int $flyer_id, array $ordered_item_ids ): bool|WP_Error {
		$valid_ids = wp_list_pluck( self::get_items( $flyer_id ), 'ID' );
		$order     = 0;
		foreach ( $ordered_item_ids as $item_id ) {
			$item_id = (int) $item_id;
			if ( ! in_array( $item_id, $valid_ids, true ) ) {
				return new WP_Error( 'hlf_reorder_invalid', '순서 목록에 이 Flyer 소속이 아닌 항목이 있습니다.', array( 'status' => 400 ) );
			}
			update_post_meta( $item_id, 'display_order', $order++ );
		}
		return true;
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
		$data              = HLF_Meta_Schema::read_item( $item->ID );
		$data['metrics']   = hlf_calculate_item_metrics( $data );
		return $data;
	}
}
