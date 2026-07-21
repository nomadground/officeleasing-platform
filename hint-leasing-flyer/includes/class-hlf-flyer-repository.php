<?php
/**
 * leasing_flyer CRUD + 식별자.
 *
 * Flyer 번호(요청서 1-D): post ID 기반 표시 포맷. 예 123 → "LF-000123".
 * 별도 sequence table/option 없이 post ID 자체를 쓰므로 동시성에 안전하다.
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

	public static function format_number( int $flyer_id ): string {
		return self::NUMBER_PREFIX . sprintf( '%06d', $flyer_id );
	}

	/** "LF-000123" → 123. 형식이 안 맞으면 0. */
	public static function parse_number( string $flyer_number ): int {
		if ( ! preg_match( '/^LF-0*(\d+)$/', trim( $flyer_number ), $m ) ) {
			return 0;
		}
		return (int) $m[1];
	}

	/** Flyer 번호로 포스트를 찾는다. 타입 불일치/미존재는 null. */
	public static function get_by_number( string $flyer_number ): ?WP_Post {
		$id = self::parse_number( $flyer_number );
		if ( $id <= 0 ) {
			return null;
		}
		$post = get_post( $id );
		if ( ! $post || HLF_Post_Types::FLYER !== $post->post_type ) {
			return null;
		}
		return $post;
	}

	public static function create( array $data ): int|WP_Error {
		$postarr = array(
			'post_type'   => HLF_Post_Types::FLYER,
			'post_title'  => isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '제목 없는 Flyer',
			'post_status' => 'draft',
			'post_author' => get_current_user_id(),
		);
		$flyer_id = wp_insert_post( $postarr, true );
		if ( is_wp_error( $flyer_id ) ) {
			return $flyer_id;
		}
		$flyer_id = (int) $flyer_id;
		// item 시퀀스 시드(0). item 추가 전에 행이 존재해야 원자적 증가가 안전하다.
		add_post_meta( $flyer_id, HLF_Meta_Schema::FLYER_ITEM_SEQ, 0, true );
		self::apply_meta_fields( $flyer_id, $data );
		return $flyer_id;
	}

	public static function update( int $flyer_id, array $data ): int|WP_Error {
		$guard = self::assert_not_archived( $flyer_id );
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

	public static function delete( int $flyer_id, bool $force = false ): bool|WP_Error {
		if ( HLF_Post_Types::FLYER !== get_post_type( $flyer_id ) ) {
			return new WP_Error( 'hlf_not_flyer', '대상이 Flyer가 아닙니다.', array( 'status' => 404 ) );
		}
		// 소속 item도 함께 제거.
		foreach ( HLF_Item_Repository::get_items( $flyer_id ) as $item ) {
			wp_delete_post( $item->ID, true );
		}
		$result = wp_delete_post( $flyer_id, $force );
		return (bool) $result;
	}

	/**
	 * archived Flyer는 읽기 전용이다(기존 공유 링크는 유지, 신규 쓰기만 차단) — Flyer 자체 수정,
	 * Item 추가/수정/삭제/재정렬, officeleasing import 등 이 Flyer에 속한 모든 변경 동작이 이
	 * 게이트를 공유한다(중복 구현 방지). 상태 변경(set_status) 자체와 읽기(GET)는 이 게이트를
	 * 거치지 않는다 — archived에서 draft/published로 되돌리는 것 자체가 막히면 안 되기 때문이다.
	 *
	 * @return true|WP_Error
	 */
	public static function assert_not_archived( int $flyer_id ) {
		$flyer = get_post( $flyer_id );
		if ( ! $flyer || HLF_Post_Types::FLYER !== $flyer->post_type ) {
			return new WP_Error( 'hlf_not_flyer', '대상이 Flyer가 아닙니다.', array( 'status' => 404 ) );
		}
		if ( HLF_Post_Types::STATUS_ARCHIVED === $flyer->post_status ) {
			return new WP_Error( 'hlf_flyer_archived', '보관된 Flyer는 읽기 전용입니다.', array( 'status' => 409 ) );
		}
		return true;
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

	/** REST/템플릿 공용 직렬화. */
	public static function to_array( WP_Post $flyer ): array {
		$base = array(
			'id'           => $flyer->ID,
			'flyer_number' => self::format_number( $flyer->ID ),
			'title'        => get_the_title( $flyer ),
			'status'       => self::status_label( $flyer->post_status ),
			'author'       => (int) $flyer->post_author,
			'created'      => $flyer->post_date_gmt,
			'modified'     => $flyer->post_modified_gmt,
			'url'          => HLF_Routes::flyer_url( $flyer->ID ),
			'item_count'   => HLF_Item_Repository::count_items( $flyer->ID ),
		);
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
		return array_map( array( __CLASS__, 'to_array' ), $posts );
	}
}
