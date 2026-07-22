<?php
/**
 * 원본 매물(hlf_source_listing, "전체 매물") CRUD + Flyer 포함/해제 오케스트레이션.
 *
 * 설계(요청서 2-1):
 * - 원본 매물은 어떤 Flyer에도 속하지 않는 독립 카탈로그다.
 * - "Flyer에 포함" = 이 원본의 현재 값을 복사해 기존 HLF_Item_Repository::create_item()으로 그 Flyer의
 *   자식 Item(스냅샷)을 새로 만들고, 그 Item에 source_listing_id(=이 원본의 post ID)를 기록한다.
 *   10개 제한/item_number 발급/display_order 계산/archive 가드는 전부 create_item()이 이미 하므로
 *   여기서 다시 구현하지 않는다.
 * - "포함 해제" = 그 Flyer 안에서 이 원본을 출처로 갖는 Item만 삭제한다(기존 delete_item 로직).
 *   스냅샷 구조이므로 원본 매물과 다른 Flyer에 포함된 동일 원본의 별도 Item은 영향받지 않는다.
 * - 별도 관계 테이블을 만들지 않는다 — 부모-자식(post_parent) + source_listing_id 메타만 쓴다.
 */
defined( 'ABSPATH' ) || exit;

final class HLF_Source_Listing_Repository {

	/**
	 * 원본 매물 하나를 필드 배열로 만든다(계산 지표 + 관리자 썸네일 미리보기 포함).
	 * $included_count는 목록에서 소스별로 한 번씩 셀 때 N+1을 피하려고 외부(source_link_map)에서
	 * 미리 계산해 넘겨주는 값 — 넘기지 않으면 이 원본 하나에 대해서만 즉석에서 센다.
	 */
	public static function to_array( WP_Post $source, ?int $included_count = null ): array {
		$data                        = HLF_Meta_Schema::read_source( $source->ID );
		$data['title']               = get_the_title( $source );
		$data['metrics']             = hlf_calculate_item_metrics( $data );
		$data['image_previews']      = self::image_previews( $data );
		$data['included_flyer_count'] = null === $included_count ? self::included_flyer_count( $source->ID ) : $included_count;
		return $data;
	}

	/** { attachment_id: 썸네일 URL } 맵(관리자 미리보기 전용, HLF_Item_Repository::image_previews와 동일 규칙). */
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

	public static function get( int $source_id ): ?WP_Post {
		$post = get_post( $source_id );
		if ( ! $post || HLF_Post_Types::SOURCE !== $post->post_type ) {
			return null;
		}
		return $post;
	}

	/**
	 * 목록/검색. $args: search(주소·키워드), page, per_page.
	 * 반환: array( 'items' => [...to_array], 'total' => int, 'page' => int, 'per_page' => int ).
	 */
	public static function list( array $args = array() ): array {
		$per_page = min( 100, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$search   = trim( (string) ( $args['search'] ?? '' ) );

		$query_args = array(
			'post_type'      => HLF_Post_Types::SOURCE,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		// 주소는 title(등록 시 도로명/지번주소로 세팅)에도, lot_address/road_address 메타에도 있으므로
		// 세 곳 중 하나라도 매칭되게 한다. WP는 's'(제목/본문)와 meta_query를 AND로 묶으므로 여기서는
		// 메타 LIKE OR만 쓰고 제목은 등록 시 주소로 세팅되는 특성에 의존한다(둘 다 주소라 실질 동일).
		if ( '' !== $search ) {
			$query_args['meta_query'] = array(
				'relation' => 'OR',
				array( 'key' => 'lot_address', 'value' => $search, 'compare' => 'LIKE' ),
				array( 'key' => 'road_address', 'value' => $search, 'compare' => 'LIKE' ),
				array( 'key' => 'features', 'value' => $search, 'compare' => 'LIKE' ),
			);
		}

		$query = new WP_Query( $query_args );
		$link_map = self::source_link_map();
		$items = array_map(
			static function ( $post ) use ( $link_map ) {
				$count = isset( $link_map[ $post->ID ] ) ? count( $link_map[ $post->ID ] ) : 0;
				return self::to_array( $post, $count );
			},
			$query->posts
		);

		return array(
			'items'    => $items,
			'total'    => (int) $query->found_posts,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	public static function create( array $fields ): int|WP_Error {
		$source_id = wp_insert_post( array(
			'post_type'   => HLF_Post_Types::SOURCE,
			'post_status' => 'publish',
			'post_author' => get_current_user_id(),
			'post_title'  => self::title_from_fields( $fields ),
		), true );
		if ( is_wp_error( $source_id ) ) {
			return $source_id;
		}
		$source_id = (int) $source_id;
		self::apply_fields( $source_id, $fields );
		return $source_id;
	}

	public static function update( int $source_id, array $fields ): int|WP_Error {
		if ( ! self::get( $source_id ) ) {
			return new WP_Error( 'hlf_source_not_found', '매물을 찾을 수 없습니다.', array( 'status' => 404 ) );
		}
		self::apply_fields( $source_id, $fields );
		// 주소가 바뀌면 제목(목록 검색·표시용)도 최신 주소로 맞춰 준다.
		$title = self::title_from_fields( HLF_Meta_Schema::read_source( $source_id ) );
		wp_update_post( array( 'ID' => $source_id, 'post_title' => $title ) );
		return $source_id;
	}

	/**
	 * 원본 매물 삭제. 이미 각 Flyer에 포함되어 만들어진 Item(스냅샷)에는 영향을 주지 않는다 —
	 * 스냅샷 구조상 Item은 독립 복사본이라 원본이 사라져도 그대로 남는다(요청서 2-1, 3-1).
	 * 남은 Item의 source_listing_id는 더 이상 존재하지 않는 원본을 가리키는 값이 되지만, 그 값은
	 * 역참조 카운트에만 쓰이므로 무해하다(끊긴 링크일 뿐 데이터 손상 아님).
	 */
	public static function delete( int $source_id ): bool|WP_Error {
		if ( ! self::get( $source_id ) ) {
			return new WP_Error( 'hlf_source_not_found', '매물을 찾을 수 없습니다.', array( 'status' => 404 ) );
		}
		return (bool) wp_delete_post( $source_id, true );
	}

	/* ---------------- Flyer 포함/해제 ---------------- */

	/**
	 * 이 원본 매물을 Flyer에 포함한다(=스냅샷 Item 생성). 이미 포함돼 있으면 중복 생성하지 않고
	 * 기존 Item ID를 그대로 돌려준다(체크박스 semantics — 중복 클릭/재요청에 안전).
	 * @return int|WP_Error 생성(또는 기존) Item ID.
	 */
	public static function include_in_flyer( int $flyer_id, int $source_id ) {
		$source = self::get( $source_id );
		if ( ! $source ) {
			return new WP_Error( 'hlf_source_not_found', '매물을 찾을 수 없습니다.', array( 'status' => 404 ) );
		}

		$existing = self::items_for_source_in_flyer( $flyer_id, $source_id );
		if ( $existing ) {
			return (int) $existing[0];
		}

		// 원본의 현재 값(쓰기 가능 필드만) + 이미지 필드를 스냅샷으로 복사한다.
		$source_data = HLF_Meta_Schema::read_source( $source_id );
		$fields      = array();
		foreach ( HLF_Meta_Schema::source_writable_fields() as $key ) {
			if ( array_key_exists( $key, $source_data ) ) {
				$fields[ $key ] = $source_data[ $key ];
			}
		}

		$item_id = HLF_Item_Repository::create_item( $flyer_id, $fields );
		if ( is_wp_error( $item_id ) ) {
			return $item_id;
		}
		$item_id = (int) $item_id;

		// 이미지도 그대로 복사(같은 attachment ID를 참조 — 파일 복제 없음, Item 이미지 규칙과 동일).
		$exterior = (int) ( $source_data['exterior_image_id'] ?? 0 );
		$interior = is_array( $source_data['interior_image_ids'] ?? null ) ? $source_data['interior_image_ids'] : array();
		if ( $exterior > 0 || ! empty( $interior ) ) {
			HLF_Item_Repository::set_images( $flyer_id, $item_id, $exterior, $interior );
		}

		// 출처 기록(역참조용). 실패하면 방금 만든 Item을 정리해 반쪽짜리 상태를 남기지 않는다
		// (officeleasing import service와 동일한 orphan cleanup).
		$linked = HLF_Item_Repository::set_source_listing_id( $item_id, $source_id );
		if ( is_wp_error( $linked ) ) {
			HLF_Item_Repository::delete_item( $flyer_id, $item_id );
			return $linked;
		}

		return $item_id;
	}

	/**
	 * 이 원본 매물을 Flyer에서 포함 해제한다(=그 Flyer 안에서 이 원본을 출처로 갖는 Item 삭제).
	 * @return int|WP_Error 삭제한 Item 수(포함돼 있지 않았으면 0 — 무해한 no-op).
	 */
	public static function exclude_from_flyer( int $flyer_id, int $source_id ) {
		$item_ids = self::items_for_source_in_flyer( $flyer_id, $source_id );
		$deleted  = 0;
		foreach ( $item_ids as $item_id ) {
			$result = HLF_Item_Repository::delete_item( $flyer_id, (int) $item_id );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$deleted++;
		}
		return $deleted;
	}

	/** 이 Flyer가 이 원본 매물을 포함하고 있는지. */
	public static function is_included( int $flyer_id, int $source_id ): bool {
		return ! empty( self::items_for_source_in_flyer( $flyer_id, $source_id ) );
	}

	/** 특정 Flyer 안에서 이 원본을 출처(source_listing_id)로 갖는 Item ID 목록. */
	private static function items_for_source_in_flyer( int $flyer_id, int $source_id ): array {
		return get_posts( array(
			'post_type'      => HLF_Post_Types::ITEM,
			'post_parent'    => $flyer_id,
			'post_status'    => array( 'publish', 'inherit', 'draft' ),
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'fields'         => 'ids',
			'meta_key'       => 'source_listing_id',
			'meta_value'     => $source_id,
		) );
	}

	/** 이 원본 매물이 포함된 서로 다른 Flyer 개수. */
	public static function included_flyer_count( int $source_id ): int {
		$map = self::source_link_map();
		return isset( $map[ $source_id ] ) ? count( $map[ $source_id ] ) : 0;
	}

	/**
	 * 모든 Item을 한 번 훑어 source_listing_id → {포함한 Flyer ID 집합} 맵을 만든다. 목록 화면에서
	 * 원본마다 카운트 쿼리를 따로 날리는 N+1을 피하기 위한 배치 계산이다. get_posts가 postmeta
	 * 캐시를 프라이밍하므로 아래 get_post_meta는 추가 쿼리가 아니라 캐시 조회다.
	 *
	 * @return array<int, array<int,bool>> source_id => (flyer_id => true) 집합.
	 */
	private static function source_link_map(): array {
		$items = get_posts( array(
			'post_type'      => HLF_Post_Types::ITEM,
			'post_status'    => array( 'publish', 'inherit', 'draft' ),
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'meta_query'     => array(
				array( 'key' => 'source_listing_id', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ),
			),
		) );

		$map = array();
		foreach ( $items as $item ) {
			$sid = (int) get_post_meta( $item->ID, 'source_listing_id', true );
			if ( $sid <= 0 ) {
				continue;
			}
			$fid = (int) $item->post_parent;
			if ( ! isset( $map[ $sid ] ) ) {
				$map[ $sid ] = array();
			}
			$map[ $sid ][ $fid ] = true;
		}
		return $map;
	}

	/**
	 * 대시보드용 원본 매물 통계: 전체 / 연결(1개 이상 Flyer에 포함) / 미연결.
	 * "연결"은 source_link_map의 키 중 실제 hlf_source_listing인 것만 센다(officeleasing import로
	 * 생긴 Item의 source_listing_id는 officeleasing 글을 가리키므로 여기 카탈로그 카운트에서 제외된다).
	 * @return array{total:int,linked:int,unlinked:int}
	 */
	public static function stats(): array {
		$source_ids = get_posts( array(
			'post_type'      => HLF_Post_Types::SOURCE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'fields'         => 'ids',
		) );
		$total    = count( $source_ids );
		$link_map = self::source_link_map();
		$linked   = count( array_intersect( array_map( 'intval', $source_ids ), array_keys( $link_map ) ) );
		return array( 'total' => $total, 'linked' => $linked, 'unlinked' => max( 0, $total - $linked ) );
	}

	/* ---------------- 이미지(원본 매물) ---------------- */

	/**
	 * 원본 매물의 이미지 저장. HLF_Item_Repository::set_images와 같은 규칙(attachment 존재만 확인,
	 * post_parent 재설정 안 함)이되, archive 가드/부모 검증은 없다(원본은 독립 게시물).
	 */
	public static function set_images( int $source_id, int $exterior_image_id, array $interior_image_ids ): bool|WP_Error {
		if ( ! self::get( $source_id ) ) {
			return new WP_Error( 'hlf_source_not_found', '매물을 찾을 수 없습니다.', array( 'status' => 404 ) );
		}

		$interior_image_ids = array_values( array_unique( array_map( 'intval', $interior_image_ids ) ) );
		$requested          = $exterior_image_id > 0 ? array_merge( array( $exterior_image_id ), $interior_image_ids ) : $interior_image_ids;

		foreach ( $requested as $id ) {
			$attachment = get_post( $id );
			if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
				return new WP_Error( 'hlf_image_invalid', '선택한 항목 중 유효하지 않은 이미지가 있습니다.', array( 'status' => 400 ) );
			}
		}

		update_post_meta( $source_id, 'exterior_image_id', $exterior_image_id );
		update_post_meta( $source_id, 'interior_image_ids', $interior_image_ids );

		$stored_exterior = (int) get_post_meta( $source_id, 'exterior_image_id', true );
		$stored_interior = get_post_meta( $source_id, 'interior_image_ids', true );
		$stored_interior = is_array( $stored_interior ) ? array_values( array_map( 'intval', $stored_interior ) ) : array();
		if ( $stored_exterior !== $exterior_image_id || $stored_interior !== $interior_image_ids ) {
			return new WP_Error( 'hlf_image_save_failed', '이미지 정보를 저장하지 못했습니다. 다시 시도해 주세요.', array( 'status' => 500 ) );
		}
		return true;
	}

	/** 이미지 하나를 원본 매물에서 뗀다(Attachment 파일 자체는 삭제하지 않음 — Item과 동일 정책). */
	public static function delete_image( int $source_id, int $attachment_id ): bool|WP_Error {
		if ( ! self::get( $source_id ) ) {
			return new WP_Error( 'hlf_source_not_found', '매물을 찾을 수 없습니다.', array( 'status' => 404 ) );
		}
		$current     = HLF_Meta_Schema::read_source( $source_id );
		$is_exterior = ( (int) $current['exterior_image_id'] === $attachment_id );
		$is_interior = in_array( $attachment_id, $current['interior_image_ids'], true );
		if ( ! $is_exterior && ! $is_interior ) {
			return new WP_Error( 'hlf_image_not_found', '이 매물에 지정된 이미지가 아닙니다.', array( 'status' => 404 ) );
		}
		$exterior = $is_exterior ? 0 : (int) $current['exterior_image_id'];
		$interior = array_values( array_diff( $current['interior_image_ids'], array( $attachment_id ) ) );
		return self::set_images( $source_id, $exterior, $interior );
	}

	/* ---------------- helpers ---------------- */

	private static function title_from_fields( array $fields ): string {
		$title = (string) ( $fields['road_address'] ?? '' );
		if ( '' === trim( $title ) ) {
			$title = (string) ( $fields['lot_address'] ?? '' );
		}
		if ( '' === trim( $title ) ) {
			$title = '매물';
		}
		return sanitize_text_field( $title );
	}

	/** 화이트리스트(source_writable_fields) 필드만 정규화해 저장. */
	private static function apply_fields( int $source_id, array $fields ): void {
		$schema   = HLF_Meta_Schema::source_fields();
		$writable = HLF_Meta_Schema::source_writable_fields();
		foreach ( $fields as $key => $value ) {
			if ( ! in_array( $key, $writable, true ) ) {
				continue;
			}
			update_post_meta( $source_id, $key, HLF_Meta_Schema::sanitize( $schema[ $key ]['type'], $value ) );
		}
	}
}
