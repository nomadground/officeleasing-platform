<?php
/**
 * leasing_flyer CRUD + 식별자.
 *
 * Flyer 번호:
 * - 요청서 7 이후(새 Flyer): 생성 시각 날짜 6자리 + 그날 순번 2자리(예 "26072301"). 생성 시점에
 *   HLF_Meta_Schema::FLYER_NUMBER로 한 번만 저장되고 이후 절대 바뀌지 않는다.
 * - 요청서 1-D(이 필드가 생기기 전에 만들어진 옛 Flyer): post ID 기반 표시 포맷. 예 123 →
 *   "LF-000123". 별도 sequence table/option 없이 post ID 자체를 쓰므로 동시성에 안전하다 — 옛
 *   Flyer는 이 계산 방식을 계속 쓴다(format_number() 참고, 절대 새 형식으로 바뀌지 않는다).
 */
defined( 'ABSPATH' ) || exit;

final class HLF_Flyer_Repository {

	const NUMBER_PREFIX = 'LF-';

	/** Flyer contact_phone이 비어있을 때 공개 템플릿·관리자 UI가 공통으로 쓰는 대표번호(단일 출처). */
	const DEFAULT_PHONE = '02-553-5988';

	/**
	 * 공개 화면 문의처. 우선순위: item 레벨 override(있으면) → flyer 레벨 기본값(있으면) → 대표번호.
	 * $item 을 넘기지 않으면 목록 화면처럼 flyer 레벨만으로 계산한다.
	 */
	public static function public_contact( array $flyer, ?array $item = null ): array {
		$name  = ( $item['contact_name'] ?? '' ) ?: ( $flyer['contact_name'] ?? '' );
		$phone = ( $item['contact_phone'] ?? '' ) ?: ( ( $flyer['contact_phone'] ?? '' ) ?: self::DEFAULT_PHONE );
		return array( 'name' => $name, 'phone' => $phone );
	}

	/** 이 Flyer에 저장된 번호가 있으면 그대로, 없으면(옛 Flyer) post ID 기반 옛 포맷으로 계산. */
	public static function format_number( int $flyer_id ): string {
		$stored = get_post_meta( $flyer_id, HLF_Meta_Schema::FLYER_NUMBER, true );
		if ( $stored ) {
			return (string) $stored;
		}
		return self::NUMBER_PREFIX . sprintf( '%06d', $flyer_id );
	}

	/**
	 * 새 Flyer 생성 시 한 번만 호출한다(create() 참고) — 오늘 날짜(사이트 로컬 시간) 6자리 + 그날
	 * 이미 발급된 개수+1(2자리)을 조합해 저장한다. 동시 생성 시 아주 드물게 같은 순번이 나올 수
	 * 있는 카운트 기반 계산이지만(원자적 증가 아님), 이 플러그인의 다른 저사용량 내부 운영 전제와
	 * 같은 수준의 위험으로 판단해 별도 원자적 카운터 없이 단순하게 둔다.
	 */
	private static function assign_flyer_number( int $flyer_id ): void {
		global $wpdb;
		$date_prefix = current_time( 'ymd' );
		$count       = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s",
			HLF_Meta_Schema::FLYER_NUMBER,
			$wpdb->esc_like( $date_prefix ) . '%'
		) );
		update_post_meta( $flyer_id, HLF_Meta_Schema::FLYER_NUMBER, $date_prefix . sprintf( '%02d', $count + 1 ) );
	}

	/** "LF-000123" → 123. 형식이 안 맞으면 0. 새 형식(순수 숫자 8자리) 번호는 get_by_number()가 별도 처리. */
	public static function parse_number( string $flyer_number ): int {
		if ( ! preg_match( '/^LF-0*(\d+)$/', trim( $flyer_number ), $m ) ) {
			return 0;
		}
		return (int) $m[1];
	}

	/**
	 * Flyer 번호로 포스트를 찾는다. 타입 불일치/미존재는 null. 새 형식(날짜+순번, 숫자만 8자리)은
	 * FLYER_NUMBER 메타로 직접 조회하고, 옛 형식("LF-000123")은 그 안의 post ID를 그대로 쓴다 — 두
	 * 경로 모두 마지막에 get_post()로 상태와 무관하게 포스트를 가져온다(HLF_Routes::dispatch()가
	 * draft/archived 등 상태별 접근 정책을 이미 별도로 검사하므로 여기서 상태를 미리 거르지 않는다).
	 */
	public static function get_by_number( string $flyer_number ): ?WP_Post {
		$flyer_number = trim( $flyer_number );
		if ( preg_match( '/^\d{8}$/', $flyer_number ) ) {
			global $wpdb;
			$id = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
				HLF_Meta_Schema::FLYER_NUMBER,
				$flyer_number
			) );
		} else {
			$id = self::parse_number( $flyer_number );
		}
		if ( $id <= 0 ) {
			return null;
		}
		$post = get_post( $id );
		if ( ! $post || HLF_Post_Types::FLYER !== $post->post_type ) {
			return null;
		}
		return $post;
	}

	/**
	 * 새 Flyer는 생성 즉시 발행(publish) 상태로 저장한다 — 별도 "발행하기" 클릭 없이 생성 직후
	 * 공개 URL이 바로 유효해야 한다는 운영 방침(draft/publish 이분법 폐지)에 따른 것. archived로의
	 * 전환(보관 처리)은 이 방침과 무관한 별도 워크플로우라 그대로 남아 있다.
	 */
	public static function create( array $data ): int|WP_Error {
		$postarr = array(
			'post_type'   => HLF_Post_Types::FLYER,
			'post_title'  => isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '제목 없는 Flyer',
			'post_status' => 'publish',
			'post_author' => get_current_user_id(),
		);
		$flyer_id = wp_insert_post( $postarr, true );
		if ( is_wp_error( $flyer_id ) ) {
			return $flyer_id;
		}
		$flyer_id = (int) $flyer_id;
		// item 시퀀스 시드(0). item 추가 전에 행이 존재해야 원자적 증가가 안전하다.
		add_post_meta( $flyer_id, HLF_Meta_Schema::FLYER_ITEM_SEQ, 0, true );
		// 요청서 7: 새 형식 Flyer 번호는 생성 시점에 한 번만 배정하고 이후 절대 바뀌지 않는다.
		self::assign_flyer_number( $flyer_id );
		self::apply_meta_fields( $flyer_id, $data );
		return $flyer_id;
	}

	public static function update( int $flyer_id, array $data ): int|WP_Error {
		$guard = HLF_Flyer_Item_Service::assert_not_archived( $flyer_id );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}
		if ( isset( $data['title'] ) ) {
			$result = wp_update_post( array( 'ID' => $flyer_id, 'post_title' => sanitize_text_field( $data['title'] ) ), true );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		self::apply_meta_fields( $flyer_id, $data );
		return $flyer_id;
	}

	/** contact_name/contact_phone 등 flyer 레벨 메타를 화이트리스트로만 반영(제목/상태는 여기서 다루지 않음). */
	private static function apply_meta_fields( int $flyer_id, array $data ): void {
		$schema   = HLF_Meta_Schema::flyer_fields();
		$writable = HLF_Meta_Schema::flyer_writable_fields();
		foreach ( $data as $key => $value ) {
			if ( ! in_array( $key, $writable, true ) ) {
				continue;
			}
			update_post_meta( $flyer_id, $key, HLF_Meta_Schema::sanitize( $schema[ $key ]['type'], $value ) );
		}
	}

	/**
	 * Flyer 삭제(소속 item cascade 포함)는 HLF_Flyer_Item_Service가 조정한다 — Flyer_Repository는
	 * Item_Repository를 몰라도 되게 하기 위함(과거엔 여기서 직접 HLF_Item_Repository::get_items()를
	 * 불러 자식을 지웠다). REST 엔드포인트(DELETE /flyers/{id})의 요청/응답은 그대로다.
	 */
	public static function delete( int $flyer_id, bool $force = false ): bool|WP_Error {
		return HLF_Flyer_Item_Service::delete_flyer_with_items( $flyer_id, $force );
	}

	/** 상태 전이: draft|published|archived. archived 상태에서도 이 메서드 자체는 허용한다(되돌리기 가능). */
	public static function set_status( int $flyer_id, string $status ): int|WP_Error {
		if ( HLF_Post_Types::FLYER !== get_post_type( $flyer_id ) ) {
			return new WP_Error( 'hlf_not_flyer', '대상이 Flyer가 아닙니다.', array( 'status' => 404 ) );
		}
		$map = array(
			'draft'     => 'draft',
			'published' => 'publish',
			'archived'  => HLF_Post_Types::STATUS_ARCHIVED,
		);
		if ( ! isset( $map[ $status ] ) ) {
			return new WP_Error( 'hlf_bad_status', '알 수 없는 상태입니다.', array( 'status' => 400 ) );
		}
		$result = wp_update_post( array( 'ID' => $flyer_id, 'post_status' => $map[ $status ] ), true );
		return is_wp_error( $result ) ? $result : (int) $flyer_id;
	}

	public static function status_label( string $wp_status ): string {
		switch ( $wp_status ) {
			case 'publish':
				return 'published';
			case HLF_Post_Types::STATUS_ARCHIVED:
				return 'archived';
			default:
				return 'draft';
		}
	}

	/**
	 * REST/템플릿 공용 직렬화. $include_item_count은 관리자 Flyer 목록 화면 전용 데이터라 공개
	 * list/detail 페이지(HLF_Routes::render)에서는 false로 넘겨, 그 화면에서는 어차피 쓰지 않는
	 * item_count 계산을 건너뛴다 — 그 페이지는 어차피 items를 따로 조회해 가져오므로 중복 쿼리였다.
	 *
	 * $item_count_override를 넘기면 그 값을 그대로 쓰고 쿼리하지 않는다 — list()가 여러 Flyer의
	 * 개수를 미리 한 번에 배치 계산(count_items_batch())해 넘겨주는 경로다(N+1 방지). 넘기지 않으면
	 * (단건 조회 등) 이 Flyer 하나만을 위해 count_items()를 호출한다.
	 */
	public static function to_array( WP_Post $flyer, bool $include_item_count = true, ?int $item_count_override = null ): array {
		$base = array(
			'id'           => $flyer->ID,
			'flyer_number' => self::format_number( $flyer->ID ),
			'title'        => get_the_title( $flyer ),
			'status'       => self::status_label( $flyer->post_status ),
			'author'       => (int) $flyer->post_author,
			'created'      => $flyer->post_date_gmt,
			'modified'     => $flyer->post_modified_gmt,
			'url'          => HLF_Routes::flyer_url( $flyer->ID ),
		);
		if ( $include_item_count ) {
			$base['item_count'] = null !== $item_count_override ? $item_count_override : HLF_Item_Repository::count_items( $flyer->ID );
		}
		return array_merge( $base, HLF_Meta_Schema::read_flyer( $flyer->ID ) );
	}

	public static function list( array $args = array() ): array {
		$query = array(
			'post_type'      => HLF_Post_Types::FLYER,
			'post_status'    => array( 'draft', 'publish', HLF_Post_Types::STATUS_ARCHIVED ),
			'posts_per_page' => $args['per_page'] ?? 20,
			'paged'          => $args['page'] ?? 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		$posts = get_posts( $query );
		// 페이지당 Flyer 수만큼 count_items()를 따로 부르는 대신(N+1) 한 번에 배치 계산한다.
		$counts = HLF_Item_Repository::count_items_batch( wp_list_pluck( $posts, 'ID' ) );
		return array_map( function ( $post ) use ( $counts ) {
			return self::to_array( $post, true, $counts[ $post->ID ] ?? 0 );
		}, $posts );
	}
}
