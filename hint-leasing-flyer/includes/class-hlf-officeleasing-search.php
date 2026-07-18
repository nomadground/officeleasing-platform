<?php
/**
 * officeleasing listing 검색 — 관리자가 Import할 매물을 찾기 위한 전용 조회 계층.
 *
 * HLF_OfficeLeasing_Mapper와 책임을 섞지 않는다: Mapper는 "이 listing 하나가 어떤 Flyer 필드
 * 값을 갖는지"만 알고, 이 클래스는 "여러 listing 중에서 어떻게 찾는지"만 안다.
 *
 * 실제 데이터 구조(officeleasing-core acf-json 대조 완료):
 *   - listing/building 둘 다 'title'을 지원하는 CPT(officeleasing-core/includes/post-types.php).
 *     listing에는 "매물번호" 같은 별도 필드가 없다 — article_no는 Flyer 쪽에만 있는 신규 필드이며
 *     officeleasing 원본에는 대응 필드가 없다(추측하지 않고 실제 ACF JSON을 확인해 그렇다는 것을
 *     확인함). 따라서 "매물 제목/매물번호" 검색은 listing의 post_title로 처리한다.
 *   - building_address_road/building_address_jibun: 텍스트 필드, meta LIKE 검색 가능.
 *   - related_building: post_object(return_format=id) — postmeta에 building post ID가 그대로 저장.
 *   - listing_status: select(available/reserved/contract_pending/leased/temporarily_hidden/expired).
 *
 * WP_Query가 "제목 's' 검색"과 "meta_query"를 OR로 묶어주지 않으므로, 검색어가 있을 때는
 * (a) building 제목/주소로 먼저 매칭되는 building_id들을 구하고
 * (b) "listing 제목 검색으로 나온 것" ∪ "related_building이 (a)에 속하는 것"
 * 두 결과를 합쳐서 최종 목록을 만든다. officeleasing-core를 건드리지 않고 커스텀 SQL/JOIN도
 * 쓰지 않는, WP 표준 쿼리만으로 가능한 범위의 절충이다(region 필터처럼 더 복잡한 조합은 이번
 * phase에서 제외).
 */
defined( 'ABSPATH' ) || exit;

final class HLF_OfficeLeasing_Search {

	const DEFAULT_PER_PAGE = 20;
	const MAX_PER_PAGE     = 50;

	/**
	 * @param array{search?: string, status?: string, page?: int, per_page?: int} $args
	 * @return array{items: array<int, array>, page: int, per_page: int, total: int}|WP_Error
	 */
	public static function search( array $args ) {
		if ( ! HLF_OfficeLeasing_Mapper::is_core_available() ) {
			return new WP_Error(
				'hlf_core_unavailable',
				'officeleasing-core(또는 ACF)가 활성화되어 있지 않아 원본 매물을 검색할 수 없습니다.',
				array( 'status' => 503 )
			);
		}

		$search   = trim( (string) ( $args['search'] ?? '' ) );
		$status   = trim( (string) ( $args['status'] ?? '' ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = min( self::MAX_PER_PAGE, max( 1, (int) ( $args['per_page'] ?? self::DEFAULT_PER_PAGE ) ) );

		$meta_query = array();
		if ( '' !== $status ) {
			$meta_query[] = array( 'key' => 'listing_status', 'value' => $status );
		}

		$query_args = array(
			'post_type'      => 'listing',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);
		if ( ! empty( $meta_query ) ) {
			$query_args['meta_query'] = $meta_query;
		}

		if ( '' !== $search ) {
			$matched_ids = self::find_matching_listing_ids( $search, $meta_query );
			if ( empty( $matched_ids ) ) {
				return array( 'items' => array(), 'page' => $page, 'per_page' => $per_page, 'total' => 0 );
			}
			$query_args['post__in'] = $matched_ids;
			$query_args['orderby']  = 'post__in'; // 관련도(제목 일치 우선) 순서 보존.
			unset( $query_args['order'] );
		}

		$query = new WP_Query( $query_args );

		$items = array();
		foreach ( $query->posts as $listing ) {
			$items[] = self::summarize_listing( $listing );
		}

		return array(
			'items'    => $items,
			'page'     => $page,
			'per_page' => $per_page,
			'total'    => (int) $query->found_posts,
		);
	}

	/**
	 * 검색어와 일치하는 listing id 전체(페이지네이션 전, post__in 구성을 위한 후보 집합).
	 * listing 제목 일치 ∪ (related_building이 제목/주소 일치 building에 속하는 listing).
	 */
	private static function find_matching_listing_ids( string $search, array $base_meta_query ): array {
		$by_title = get_posts( array(
			'post_type'      => 'listing',
			'post_status'    => 'publish',
			's'              => $search,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => $base_meta_query,
		) );

		$building_ids = self::find_matching_building_ids( $search );
		$by_building  = array();
		if ( ! empty( $building_ids ) ) {
			$meta_query = array_merge(
				$base_meta_query,
				array( array( 'key' => 'related_building', 'value' => $building_ids, 'compare' => 'IN' ) )
			);
			$by_building = get_posts( array(
				'post_type'      => 'listing',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => $meta_query,
			) );
		}

		return array_values( array_unique( array_map( 'intval', array_merge( $by_title, $by_building ) ) ) );
	}

	/** building 제목 또는 도로명/지번주소가 검색어와 일치하는 building id 목록. */
	private static function find_matching_building_ids( string $search ): array {
		$by_title = get_posts( array(
			'post_type'      => 'building',
			'post_status'    => 'publish',
			's'              => $search,
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );

		$by_address = get_posts( array(
			'post_type'      => 'building',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				'relation' => 'OR',
				array( 'key' => 'building_address_road', 'value' => $search, 'compare' => 'LIKE' ),
				array( 'key' => 'building_address_jibun', 'value' => $search, 'compare' => 'LIKE' ),
			),
		) );

		return array_values( array_unique( array_map( 'intval', array_merge( $by_title, $by_address ) ) ) );
	}

	/** 검색 결과 한 행 — Import 버튼이 미리보기로 쓸 최소 정보만. */
	private static function summarize_listing( WP_Post $listing ): array {
		$building_id = (int) get_field( 'related_building', $listing->ID );
		$building    = $building_id ? get_post( $building_id ) : null;

		return array(
			'listing_id'             => $listing->ID,
			'listing_title'          => get_the_title( $listing ),
			'listing_status'         => (string) get_field( 'listing_status', $listing->ID ),
			'building_id'            => $building_id,
			'building_title'         => $building ? get_the_title( $building ) : '',
			'road_address'           => $building_id ? (string) get_field( 'building_address_road', $building_id ) : '',
			'lot_address'            => $building_id ? (string) get_field( 'building_address_jibun', $building_id ) : '',
			'floor'                  => (string) get_field( 'floor_display', $listing->ID ),
			'lease_area_sqm'         => (float) get_field( 'lease_area_sqm', $listing->ID ),
			'exclusive_area_sqm'     => (float) get_field( 'exclusive_area_sqm', $listing->ID ),
			'deposit_manwon'         => (float) get_field( 'deposit_manwon', $listing->ID ),
			'monthly_rent_manwon'    => (float) get_field( 'monthly_rent_manwon', $listing->ID ),
			'maintenance_fee_manwon' => (float) get_field( 'maintenance_fee_manwon', $listing->ID ),
		);
	}
}
