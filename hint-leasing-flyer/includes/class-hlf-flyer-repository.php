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
		// item 시퀀스 시드(0). item 추가 전에 행이 존재해야 원자적 증가가 안전하다.
		add_post_meta( $flyer_id, HLF_Meta_Schema::FLYER_ITEM_SEQ, 0, true );
		return (int) $flyer_id;
	}

	public static function update( int $flyer_id, array $data ): int|WP_Error {
		if ( HLF_Post_Types::FLYER !== get_post_type( $flyer_id ) ) {
			return new WP_Error( 'hlf_not_flyer', '대상이 Flyer가 아닙니다.', array( 'status' => 404 ) );
		}
		$postarr = array( 'ID' => $flyer_id );
		if ( isset( $data['title'] ) ) {
			$postarr['post_title'] = sanitize_text_field( $data['title'] );
		}
		if ( count( $postarr ) === 1 ) {
			return $flyer_id;
		}
		$result = wp_update_post( $postarr, true );
		return is_wp_error( $result ) ? $result : (int) $flyer_id;
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

	/** 상태 전이: draft|published|archived. */
	public static function set_status( int $flyer_id, string $status ): int|WP_Error {
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
		return array(
			'id'           => $flyer->ID,
			'flyer_number' => self::format_number( $flyer->ID ),
			'title'        => get_the_title( $flyer ),
			'status'       => self::status_label( $flyer->post_status ),
			'author'       => (int) $flyer->post_author,
			'created'      => $flyer->post_date_gmt,
			'modified'     => $flyer->post_modified_gmt,
			'url'          => HLF_Routes::flyer_url( $flyer->ID ),
			'item_count'   => count( HLF_Item_Repository::get_items( $flyer->ID ) ),
		);
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
