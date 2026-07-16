<?php
/**
 * officeleasing-core(building/listing) → Flyer Item 스냅샷 매핑.
 *
 * [Phase 1: 골격만] 실제 필드 복사/이미지 파생/원본 재불러오기는 Phase 2에서 구현한다.
 * 이 클래스는 매핑 계약(어떤 core 필드가 어떤 Flyer 필드로 가는지)만 명시하고,
 * to_snapshot()은 아직 실제 값을 읽지 않는다(officeleasing-core 비활성 환경 보호).
 *
 * 검증된 매핑 근거(요청서 1-B). 소스 필드명은 실제 ACF 키와 대조 완료:
 *   building: building_address_road/jibun, building_lat/lng, building_ground_floors,
 *             building_parking(텍스트→parking_available/total_parking 파생),
 *             building_elevator_count(≥1 → true), building_orientation, building_image_1~8,
 *             building_completion_date(=준공일, ≠ 사용승인일 → Flyer approval_date는 신규 필드로 분리),
 *   listing:  floor_display, lease_area_sqm, exclusive_area_sqm,
 *             deposit_manwon/monthly_rent_manwon/maintenance_fee_manwon,
 *             move_in_type/move_in_date(→ available_date_text),
 *             listing_note + listing_key_point_1~3(→ features), listing_image_1~6.
 * core에 없는 Flyer 신규 필드: article_no, contact_name, contact_phone, building_use, approval_date.
 */
defined( 'ABSPATH' ) || exit;

final class HLF_OfficeLeasing_Mapper {

	/** officeleasing-core가 활성화되어 있고 get_field를 쓸 수 있는지. */
	public static function is_core_available(): bool {
		return function_exists( 'get_field' ) && post_type_exists( 'listing' ) && post_type_exists( 'building' );
	}

	/**
	 * building/listing 필드 → Flyer item 필드 매핑 계약(정적 정의).
	 * Phase 2 구현이 이 표를 그대로 소비한다. 'transform' 은 파생 규칙 라벨.
	 *
	 * @return array<string, array{source:string, from:string, transform?:string}>
	 */
	public static function mapping_contract(): array {
		return array(
			'road_address'           => array( 'from' => 'building', 'source' => 'building_address_road' ),
			'lot_address'            => array( 'from' => 'building', 'source' => 'building_address_jibun' ),
			'latitude'               => array( 'from' => 'building', 'source' => 'building_lat' ),
			'longitude'              => array( 'from' => 'building', 'source' => 'building_lng' ),
			'floor_current'          => array( 'from' => 'listing', 'source' => 'floor_display' ),
			'floor_total'            => array( 'from' => 'building', 'source' => 'building_ground_floors' ),
			'lease_area_sqm'         => array( 'from' => 'listing', 'source' => 'lease_area_sqm' ),
			'exclusive_area_sqm'     => array( 'from' => 'listing', 'source' => 'exclusive_area_sqm' ),
			'deposit_manwon'         => array( 'from' => 'listing', 'source' => 'deposit_manwon' ),
			'monthly_rent_manwon'    => array( 'from' => 'listing', 'source' => 'monthly_rent_manwon' ),
			'maintenance_fee_manwon' => array( 'from' => 'listing', 'source' => 'maintenance_fee_manwon' ),
			'parking_available'      => array( 'from' => 'building', 'source' => 'building_parking', 'transform' => 'parking_to_bool' ),
			'total_parking'          => array( 'from' => 'building', 'source' => 'building_parking', 'transform' => 'parking_raw_text' ),
			'elevator_available'     => array( 'from' => 'building', 'source' => 'building_elevator_count', 'transform' => 'count_to_bool' ),
			'direction'              => array( 'from' => 'building', 'source' => 'building_orientation' ),
			'available_date_text'    => array( 'from' => 'listing', 'source' => 'move_in_type', 'transform' => 'move_in_to_text' ),
			'features'               => array( 'from' => 'listing', 'source' => 'listing_note', 'transform' => 'features_compose' ),
			'exterior_image_id'      => array( 'from' => 'building', 'source' => 'building_image_1', 'transform' => 'image_derive' ),
			'interior_images'        => array( 'from' => 'listing', 'source' => 'listing_image_1..6', 'transform' => 'image_derive' ),
			// approval_date/building_use/article_no/contact_* 는 core 소스가 없음 → 관리자 입력(Flyer 신규).
		);
	}

	/**
	 * [Phase 2 예정] listing/building ID로부터 Flyer item 스냅샷 배열을 생성.
	 * Phase 1에서는 미구현임을 명시적으로 알린다(조용한 빈 스냅샷 방지).
	 */
	public static function to_snapshot( int $listing_id, ?int $building_id = null ): WP_Error {
		return new WP_Error(
			'hlf_mapper_phase2',
			'officeleasing 원본 → Flyer 스냅샷 매핑은 Phase 2에서 구현됩니다.',
			array( 'status' => 501 )
		);
	}
}
