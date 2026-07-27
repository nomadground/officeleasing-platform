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
 * - 별도 관계 테이블을 만들지 않는다 — 부모-자식(post_parent) + source_listing_id 메타만 쓴다.
 * - 요청서(실사용): 처음 포함될 때만 값을 복사하고 그 뒤로는 서로 무관한 완전한 스냅샷이었다 —
 *   원본을 나중에 수정해도 이미 포함된 Item에는 반영되지 않아, 반영하려면 매번 빼고 다시 넣어야
 *   했다. 이제 update()가 텍스트 필드를 저장할 때마다 이 원본을 출처로 둔 모든 Item(여러 Flyer에
 *   걸쳐 있을 수 있다)에도 같은 값을 다시 써서 자동으로 맞춘다(sync_included_items()). 보관
 *   (archived) 상태인 Flyer의 Item만 예외로 그대로 얼어붙는다(update_item()의 기존 보관 가드를
 *   그대로 활용). 사진(exterior_image_id/interior_image_ids)은 이 동기화 대상이 아니다 — 사진은
 *   Item 화면에서 그대로 개별 관리한다(대표 지정이 Item마다 다를 수 있어 원본과 항상 같을 필요가
 *   없다).
 */
defined( 'ABSPATH' ) || exit;

final class HLF_Source_Listing_Repository {

	/**
	 * 원본 매물 하나를 필드 배열로 만든다(계산 지표 + 관리자 썸네일 미리보기 포함).
	 * $included_count는 목록에서 소스별로 한 번씩 셀 때 N+1을 피하려고 외부(source_link_map)에서
	 * 미리 계산해 넘겨주는 값 — 넘기지 않으면 이 원본 하나에 대해서만 즉석에서 센다.
	 *
	 * $include_previews는 기본 true(단건 조회·폼 편집 화면은 썸네일 미리보기를 실제로 쓴다). 목록
	 * 테이블은 썸네일을 표시하지 않으므로 list()에서 false로 넘겨, 원본마다 사진 개수만큼 반복되는
	 * wp_get_attachment_image_url() 조회를 통째로 건너뛴다(Item to_array의 image_previews 최적화와 동일).
	 */
	public static function to_array( WP_Post $source, ?int $included_count = null, bool $include_previews = true, ?array $linked_items = null ): array {
		$data                        = HLF_Meta_Schema::read_source( $source->ID );
		$data['title']               = get_the_title( $source );
		$data['metrics']             = hlf_calculate_item_metrics( $data );
		if ( $include_previews ) {
			$data['image_previews'] = self::image_previews( $data );
			$data['image_blur']     = self::image_blur( $data );
		}
		$data['included_flyer_count'] = null === $included_count ? self::included_flyer_count( $source->ID ) : $included_count;
		// "링크 복사" 버튼(전체 매물 목록)용 — 이 원본이 포함된 각 Flyer의 개별 매물 공개 URL.
		$data['linked_items'] = null === $linked_items ? self::linked_items_for_source( $source->ID ) : $linked_items;
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

	/** { attachment_id: bool } 맵(관리자 편집 화면의 블러 체크박스 초기 상태, HLF_Item_Repository::image_blur와 동일 규칙). */
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

	public static function get( int $source_id ): ?WP_Post {
		$post = get_post( $source_id );
		if ( ! $post || HLF_Post_Types::SOURCE !== $post->post_type ) {
			return null;
		}
		return $post;
	}

	/**
	 * 목록/검색. $args: search(주소·키워드), linked('linked'|'unlinked'|'', Dashboard 카드 클릭용
	 * 연결 여부 필터), page, per_page.
	 * 반환: array( 'items' => [...to_array], 'total' => int, 'page' => int, 'per_page' => int ).
	 */
	public static function list( array $args = array() ): array {
		$per_page = min( 100, max( 1, (int) ( $args['per_page'] ?? 20 ) ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$search   = trim( (string) ( $args['search'] ?? '' ) );
		$linked   = (string) ( $args['linked'] ?? '' );
		$contact  = trim( (string) ( $args['contact'] ?? '' ) );

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
		// 담당자(contact) 필터는 검색어와 별개 조건이라 AND로 묶는다 — 검색어의 OR 그룹을 top-level에
		// 바로 두면 담당자 조건까지 OR로 풀려버리므로 반드시 하위 그룹으로 감싼다.
		$meta_query = array();
		if ( '' !== $search ) {
			$meta_query[] = array(
				'relation' => 'OR',
				array( 'key' => 'lot_address', 'value' => $search, 'compare' => 'LIKE' ),
				array( 'key' => 'road_address', 'value' => $search, 'compare' => 'LIKE' ),
				array( 'key' => 'features', 'value' => $search, 'compare' => 'LIKE' ),
			);
		}
		if ( '' !== $contact ) {
			$meta_query[] = array( 'key' => 'contact_name', 'value' => $contact, 'compare' => '=' );
		}
		if ( $meta_query ) {
			$query_args['meta_query'] = count( $meta_query ) > 1 ? array_merge( array( 'relation' => 'AND' ), $meta_query ) : $meta_query;
		}

		// "연결"은 Item 쪽 메타(source_link_map)로만 알 수 있어 source 자체의 meta_query로는 표현할 수
		// 없다 — linked/unlinked 필터가 걸린 경우에만 전체 맵이 필요하다(어떤 source가 걸리는지 DB
		// 쿼리 전에 알아야 post__in/post__not_in을 만들 수 있으므로). 필터가 없는 기본 목록에서는
		// 전체 Item을 훑을 필요 없이, 쿼리 실행 후 이번 페이지에 뽑힌 source_id들만으로 범위를 좁혀
		// 링크 맵을 구한다(요청서: 매물 몇천 건이 돼도 목록 로딩이 전체 Item 수에 비례해 느려지지
		// 않도록).
		$needs_full_map = 'linked' === $linked || 'unlinked' === $linked;
		if ( $needs_full_map ) {
			$link_map = self::source_link_map();
			// 빈 배열은 WP_Query에서 "필터 없음"과 동일하게 취급되므로, linked인데 아무것도 안 걸린
			// 경우 존재하지 않는 ID(0)로 안전하게 0건 처리한다.
			$linked_ids = array_map( 'intval', array_keys( $link_map ) );
			if ( 'linked' === $linked ) {
				$query_args['post__in'] = $linked_ids ?: array( 0 );
			} else {
				$query_args['post__not_in'] = $linked_ids;
			}
		}

		$query = new WP_Query( $query_args );
		$link_map = $needs_full_map ? $link_map : self::source_link_map_for( wp_list_pluck( $query->posts, 'ID' ) );
		$items = array_map(
			static function ( $post ) use ( $link_map ) {
				$flyer_item_map = $link_map[ $post->ID ] ?? array();
				// 목록 테이블은 썸네일을 그리지 않으므로 image_previews 계산은 건너뛴다(false). linked_items는
				// 이미 배치로 구한 $link_map에서 바로 만들어("링크 복사" 버튼용) 원본마다 추가 쿼리가 없다.
				return self::to_array( $post, count( $flyer_item_map ), false, self::linked_item_urls( $flyer_item_map ) );
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
		// 전체 개수(total)와 미연결 수(unlinked)가 즉시 바뀐다 — 대시보드가 바로 반영하도록 무효화.
		self::invalidate_stats_cache();
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
		self::sync_included_items( $source_id );
		return $source_id;
	}

	/**
	 * 요청서: 이 원본을 출처로 이미 포함된 모든 Item(여러 Flyer에 걸쳐 있을 수 있다)에 방금 저장한
	 * 값을 그대로 다시 써서, "빼고 다시 넣기" 없이도 수정 내용이 바로 반영되게 한다. 위에서 저장한
	 * 값을 그대로 다시 읽어(부분 수정으로 호출됐어도 항상 완전한 최신 값 기준) 쓰기 가능 필드만
	 * 넘긴다 — include_in_flyer()가 최초 포함 시 복사하는 필드 집합과 완전히 같은 규칙이다.
	 */
	private static function sync_included_items( int $source_id ): void {
		$source_data = HLF_Meta_Schema::read_source( $source_id );
		$fields      = array();
		foreach ( HLF_Meta_Schema::source_writable_fields() as $key ) {
			if ( array_key_exists( $key, $source_data ) ) {
				$fields[ $key ] = $source_data[ $key ];
			}
		}

		$items = get_posts( array(
			'post_type'      => HLF_Post_Types::ITEM,
			'post_status'    => array( 'publish', 'inherit', 'draft' ),
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'meta_key'       => 'source_listing_id',
			'meta_value'     => $source_id,
		) );
		foreach ( $items as $item ) {
			// 보관된 Flyer의 Item은 update_item()의 기존 보관 가드(assert_not_archived)가 WP_Error로
			// 거부한다 — 원본 저장 자체를 실패시킬 이유는 아니므로 그 Item만 조용히 건너뛴다.
			HLF_Item_Repository::update_item( (int) $item->post_parent, $item->ID, $fields );
		}
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
		$deleted = (bool) wp_delete_post( $source_id, true );
		if ( $deleted ) {
			// 전체 개수(total)가 즉시 바뀐다 — 대시보드가 바로 반영하도록 무효화.
			self::invalidate_stats_cache();
		}
		return $deleted;
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
		// 반환값을 반드시 확인한다 — 예전에는 실패해도 무시하고 넘어가, 사진이 있는 원본을 포함했는데도
		// 새 Item에 사진이 하나도 안 들어간 채 "포함 성공"으로 응답이 나가는 문제가 있었다(예: 새로
		// 생성된 Item은 기존 이미지가 없어 set_images()가 모든 요청 ID를 "새로 추가되는" 것으로 보고
		// read_post 권한을 검사하는데, 이게 실패해도 여기서 조용히 삼켜졌다). 실패하면 방금 만든 Item을
		// 정리하고(아래 source_listing_id 기록 실패와 동일한 orphan cleanup) 에러를 그대로 올린다.
		$exterior = (int) ( $source_data['exterior_image_id'] ?? 0 );
		$interior = is_array( $source_data['interior_image_ids'] ?? null ) ? $source_data['interior_image_ids'] : array();
		if ( $exterior > 0 || ! empty( $interior ) ) {
			$images_result = HLF_Item_Repository::set_images( $flyer_id, $item_id, $exterior, $interior );
			if ( is_wp_error( $images_result ) ) {
				HLF_Item_Repository::delete_item( $flyer_id, $item_id );
				return $images_result;
			}
		}

		// 출처 기록(역참조용). 실패하면 방금 만든 Item을 정리해 반쪽짜리 상태를 남기지 않는다
		// (officeleasing import service와 동일한 orphan cleanup).
		$linked = HLF_Item_Repository::set_source_listing_id( $item_id, $source_id );
		if ( is_wp_error( $linked ) ) {
			HLF_Item_Repository::delete_item( $flyer_id, $item_id );
			return $linked;
		}

		self::invalidate_stats_cache();
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
		if ( $deleted > 0 ) {
			self::invalidate_stats_cache();
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

	/**
	 * 이 원본 매물이 포함된 서로 다른 Flyer 개수(단건). 목록에서는 source_link_map() 배치 계산을
	 * 쓰지만, 단건 조회에서는 전체 Item을 훑을 필요 없이 이 원본을 출처로 갖는 Item만 뽑아
	 * 부모(Flyer)의 distinct 개수를 센다(fields=id=>parent — 포스트 객체 hydration도 생략).
	 */
	public static function included_flyer_count( int $source_id ): int {
		$pairs = get_posts( array(
			'post_type'      => HLF_Post_Types::ITEM,
			'post_status'    => array( 'publish', 'inherit', 'draft' ),
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'fields'         => 'id=>parent',
			'meta_key'       => 'source_listing_id',
			'meta_value'     => $source_id,
		) );
		return count( array_unique( array_map( 'intval', array_values( (array) $pairs ) ) ) );
	}

	/**
	 * 단건 조회용 linked_item_urls() — included_flyer_count()와 같은 원리로, 이 원본을 출처로 갖는
	 * Item만 뽑아 {flyer_id => item_number} 맵을 만든 뒤 URL을 붙인다("링크복사" 버튼용).
	 */
	public static function linked_items_for_source( int $source_id ): array {
		$items = get_posts( array(
			'post_type'      => HLF_Post_Types::ITEM,
			'post_status'    => array( 'publish', 'inherit', 'draft' ),
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'meta_key'       => 'source_listing_id',
			'meta_value'     => $source_id,
		) );
		$flyer_item_map = array();
		foreach ( $items as $item ) {
			$flyer_item_map[ (int) $item->post_parent ] = (string) get_post_meta( $item->ID, 'item_number', true );
		}
		return self::linked_item_urls( $flyer_item_map );
	}

	/**
	 * 모든 Item을 한 번 훑어 source_listing_id → {flyer_id => item_number} 맵을 만든다. 목록 화면에서
	 * 원본마다 카운트/링크 쿼리를 따로 날리는 N+1을 피하기 위한 배치 계산이다. get_posts가 postmeta
	 * 캐시를 프라이밍하므로 아래 get_post_meta는 추가 쿼리가 아니라 캐시 조회다. 값은 단순 bool이
	 * 아니라 item_number까지 담아, 목록의 "링크복사" 버튼이 원본마다 다시 쿼리하지 않고 이 맵만으로
	 * 공개 URL(HLF_Routes::item_url)을 만들 수 있게 한다.
	 *
	 * @return array<int, array<int,string>> source_id => (flyer_id => item_number) 집합.
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
			$map[ $sid ][ $fid ] = (string) get_post_meta( $item->ID, 'item_number', true );
		}
		return $map;
	}

	/**
	 * source_link_map()의 범위 제한판 — 전체 Item이 아니라 주어진 source_id들에 연결된 Item만
	 * 훑는다(GPT 코드 감사 P1#3: 목록 화면에서 linked/unlinked 필터가 없을 때는 이번 페이지에 뽑힌
	 * source_id 수십 개만 알면 되므로, 전체 Item 수에 비례해 느려지는 source_link_map()을 쓸 이유가
	 * 없다). 빈 배열이면 쿼리 자체를 건너뛴다(0건 페이지 등).
	 *
	 * @param array<int,int> $source_ids
	 * @return array<int, array<int,string>>
	 */
	private static function source_link_map_for( array $source_ids ): array {
		$source_ids = array_values( array_unique( array_map( 'intval', $source_ids ) ) );
		if ( ! $source_ids ) {
			return array();
		}

		$items = get_posts( array(
			'post_type'      => HLF_Post_Types::ITEM,
			'post_status'    => array( 'publish', 'inherit', 'draft' ),
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'meta_query'     => array(
				array( 'key' => 'source_listing_id', 'value' => $source_ids, 'compare' => 'IN' ),
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
			$map[ $sid ][ $fid ] = (string) get_post_meta( $item->ID, 'item_number', true );
		}
		return $map;
	}

	/**
	 * 이 원본이 포함된 Flyer들의 개별 매물 공개 페이지 URL 목록(하나의 원본이 여러 Flyer에 동시에
	 * 포함될 수 있어 배열이다). 목록/단건 조회 둘 다 $link_map(source_link_map의 부분집합, 이미
	 * 배치로 계산됨)을 그대로 넘겨받아 쓰므로 원본마다 추가 쿼리가 생기지 않는다.
	 *
	 * @param array<int,string> $flyer_item_map flyer_id => item_number.
	 * @return array<int, array{flyer_id:int, item_number:string, url:string}>
	 */
	private static function linked_item_urls( array $flyer_item_map ): array {
		$links = array();
		foreach ( $flyer_item_map as $flyer_id => $item_number ) {
			if ( '' === $item_number ) {
				continue;
			}
			$links[] = array(
				'flyer_id'    => (int) $flyer_id,
				'item_number' => $item_number,
				'url'         => HLF_Routes::item_url( (int) $flyer_id, $item_number ),
			);
		}
		return $links;
	}

	const STATS_TRANSIENT = 'hlf_source_stats_v1';
	// 초. 예전에는 30초였다 — 통계를 바꾸는 모든 경로(원본 생성/삭제, 포함/해제, Flyer 삭제 cascade)가
	// 이제 전부 invalidate_stats_cache()로 즉시 무효화하므로, "혹시 놓친 경로 때문에 오래 묵는 것"을
	// 걱정해 TTL을 짧게 유지할 이유가 없어졌다. 짧은 TTL은 대시보드를 열 때마다 전체 Source+Item을
	// 다시 훑게 만드는 비용이라(외부 코드 감사 P1), 정확성은 명시적 무효화로 보장하고 TTL은
	// 안전망으로만 남긴다.
	const STATS_TTL       = 300;

	/**
	 * 대시보드용 원본 매물 통계: 전체 / 연결(1개 이상 Flyer에 포함) / 미연결.
	 * "연결"은 source_link_map의 키 중 실제 hlf_source_listing인 것만 센다(officeleasing import로
	 * 생긴 Item의 source_listing_id는 officeleasing 글을 가리키므로 여기 카탈로그 카운트에서 제외된다).
	 *
	 * 이 계산은 전체 Source + 전체 Item을 훑어야 해서(연결 여부는 Item 쪽 메타로만 알 수 있음)
	 * 데이터가 많아질수록 느려진다(GPT 코드 감사 P1#4) — 완전히 없앨 수는 없지만(별도 카운터 메타를
	 * 새로 도입하는 건 더 큰 구조 변경이라 이번 범위 밖으로 미룬다), 대시보드를 열 때마다 매번 다시
	 * 계산할 필요는 없으므로 짧은 TTL(30초) transient로 캐시한다. include_in_flyer/exclude_from_flyer가
	 * 캐시를 즉시 무효화하므로 직접 조작한 경우는 바로 반영되고, 그 외 경로(Flyer 삭제 cascade 등)로
	 * 바뀐 경우는 최대 30초 뒤에 반영된다 — 대시보드 통계 표시라 이 정도 지연은 무해하다고 판단.
	 * @return array{total:int,linked:int,unlinked:int}
	 */
	public static function stats(): array {
		$cached = get_transient( self::STATS_TRANSIENT );
		if ( is_array( $cached ) && isset( $cached['total'], $cached['linked'], $cached['unlinked'] ) ) {
			return $cached;
		}

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
		$stats    = array( 'total' => $total, 'linked' => $linked, 'unlinked' => max( 0, $total - $linked ) );

		set_transient( self::STATS_TRANSIENT, $stats, self::STATS_TTL );
		return $stats;
	}

	/**
	 * 통계(total/linked/unlinked)를 바꾸는 모든 경로에서 호출해 다음 stats()가 즉시 새로 계산하게 한다.
	 * 호출 지점: 원본 생성/삭제(total 변화), Flyer 포함/해제(linked 변화), Flyer 삭제 cascade로 Item이
	 * 통째로 사라지는 경우(linked 변화 — HLF_Flyer_Item_Service::delete_flyer_with_items). 이 목록이
	 * 완전해야 TTL을 안전하게 늘릴 수 있다(STATS_TTL 주석 참고). public인 이유는 Flyer 쪽 서비스가
	 * 자기 cascade 삭제 뒤에 직접 불러야 하기 때문이다.
	 */
	public static function invalidate_stats_cache(): void {
		delete_transient( self::STATS_TRANSIENT );
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

		// delete_image()가 "이미 저장된 목록에서 하나 뺀 나머지"를 그대로 이 메서드에 다시 넘기므로,
		// 이미 붙어 있던 나머지까지 매번 재확인하면 그중 하나를 다른 사람이 붙였다는 이유만으로
		// "빼기" 자체가 막혀버린다 — 권한 확인은 이번 요청에서 새로 추가되는 ID에만 적용한다.
		$existing = HLF_Meta_Schema::read_source( $source_id );
		$current_ids = array();
		if ( ! empty( $existing['exterior_image_id'] ) ) { $current_ids[] = (int) $existing['exterior_image_id']; }
		foreach ( $existing['interior_image_ids'] as $existing_id ) { $current_ids[] = (int) $existing_id; }

		// 요청서: 대표 1장 + 아래 슬라이드 3장(대표 포함 4장)으로 제한한다 — HLF_Item_Repository와
		// 같은 상한(MAX_IMAGES). 단, HLF_Item_Repository::set_images와 같은 이유로 "이미 갖고 있던
		// 개수보다 늘리지만 않으면" 통과시킨다 — 그렇지 않으면 상한이 생기기 전에 이미 4장을 넘게
		// 등록된 원본 매물은 delete_image()로 한 장씩 빼도 계속 이 검사에 걸려 사진을 하나도 못 뗀다.
		$ceiling = max( HLF_Item_Repository::MAX_IMAGES, count( $current_ids ) );
		if ( count( $requested ) > $ceiling ) {
			return new WP_Error(
				'hlf_image_limit',
				'사진은 대표 이미지를 포함해 최대 ' . HLF_Item_Repository::MAX_IMAGES . '장까지 등록할 수 있습니다.',
				array( 'status' => 400 )
			);
		}

		foreach ( $requested as $id ) {
			$attachment = get_post( $id );
			if ( ! $attachment || 'attachment' !== $attachment->post_type || ! wp_attachment_is_image( $id ) ) {
				return new WP_Error( 'hlf_image_invalid', '선택한 항목 중 유효하지 않은 이미지가 있습니다.', array( 'status' => 400 ) );
			}
			// attachment 존재/타입만 확인하고 넘어가면, 낮은 권한 사용자가 자신이 볼 수 없는(다른
			// 사람의 비공개) attachment ID를 그대로 넣어 매물에 새로 바인딩할 수 있다 — 새로 추가되는
			// ID에 한해 읽을 권한을 확인한다.
			if ( ! in_array( $id, $current_ids, true ) && ! current_user_can( 'read_post', $id ) ) {
				return new WP_Error( 'hlf_image_forbidden', '선택한 이미지 중 접근 권한이 없는 항목이 있습니다.', array( 'status' => 403 ) );
			}
		}

		// 새로 추가되는 ID만 hlf-item-photo/hlf-item-thumb 사이즈를 생성해 둔다("최적 사이즈로 리사이징
		// 출력" 요청서) — HLF_Item_Repository::ensure_image_sizes와 같은 이유·같은 안전성(실패해도
		// 조용히 넘어감).
		self::ensure_image_sizes( array_diff( $requested, $current_ids ) );

		update_post_meta( $source_id, 'exterior_image_id', $exterior_image_id );
		update_post_meta( $source_id, 'interior_image_ids', $interior_image_ids );

		$stored_exterior = (int) get_post_meta( $source_id, 'exterior_image_id', true );
		$stored_interior = get_post_meta( $source_id, 'interior_image_ids', true );
		$stored_interior = is_array( $stored_interior ) ? array_values( array_map( 'intval', $stored_interior ) ) : array();
		if ( $stored_exterior !== $exterior_image_id || $stored_interior !== $interior_image_ids ) {
			return new WP_Error( 'hlf_image_save_failed', '이미지 정보를 저장하지 못했습니다. 다시 시도해 주세요.', array( 'status' => 500 ) );
		}
		self::sync_included_item_images( $source_id, $exterior_image_id, $interior_image_ids );
		return true;
	}

	/**
	 * 요청서(실사용 버그): 원본 매물 사진을 추가/교체/삭제해도 이미 포함된 임대안내문에는 반영되지
	 * 않아, 빼서 다시 넣어야만(그러면 순서도 맨 뒤로 밀림) 보였다 — sync_included_items()가 텍스트
	 * 필드에 이미 적용하는 것과 같은 "즉시 반영" 원칙을 사진에도 그대로 적용한다. 예전에는 사진을
	 * 일부러 이 동기화에서 제외했었다("임대안내문마다 독립적으로 사진 관리", v0.4.0-beta.14) — 그런데
	 * 실사용에서는 그 독립성보다 "원본에 사진을 추가하면 이미 포함된 안내문에도 바로 보여야 한다"는
	 * 쪽이 실제 업무 흐름이라는 재요청이 들어와 텍스트 필드와 같은 정책으로 통일한다.
	 * HLF_Item_Repository::set_images()가 내부적으로 이미 하는 보관(archived) Flyer 가드가 그 경우만
	 * 조용히 건너뛴다 — 원본 저장 자체를 실패시킬 이유는 아니므로 개별 Item 실패는 무시한다.
	 */
	private static function sync_included_item_images( int $source_id, int $exterior_image_id, array $interior_image_ids ): void {
		$items = get_posts( array(
			'post_type'      => HLF_Post_Types::ITEM,
			'post_status'    => array( 'publish', 'inherit', 'draft' ),
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'meta_key'       => 'source_listing_id',
			'meta_value'     => $source_id,
		) );
		foreach ( $items as $item ) {
			HLF_Item_Repository::set_images( (int) $item->post_parent, $item->ID, $exterior_image_id, $interior_image_ids );
		}
	}

	/** HLF_Item_Repository::ensure_image_sizes와 동일 — 원본 매물도 같은 두 사이즈를 공유해 쓴다. */
	private static function ensure_image_sizes( array $attachment_ids ): void {
		if ( ! $attachment_ids ) {
			return;
		}
		foreach ( $attachment_ids as $attachment_id ) {
			// 이미 두 사이즈가 다 있으면 다시 인코딩할 필요가 없다 — HLF_Item_Repository::
			// ensure_image_sizes와 같은 이유(무거운 재생성 비용을 아낀다).
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
