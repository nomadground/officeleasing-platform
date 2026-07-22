<?php
/**
 * officeleasing-core(building/listing) → Flyer Item 스냅샷 매핑.
 *
 * [Phase 2-2] to_snapshot()이 실제 값을 읽어 필드 배열을 만든다. 이 클래스는 오직 "listing_id
 * (+building_id) → 필드 배열"만 하는 순수 변환기다 — item_number/display_order/10개 제한 같은
 * Flyer/Item 저장 규칙은 전혀 모른다(그건 HLF_Item_Repository의 일이다). source_listing_id 등
 * "이 스냅샷이 어디서 왔는지"에 대한 기록(provenance)도 여기서 채우지 않는다 — 그건 이 스냅샷을
 * 실제로 저장할 오케스트레이션 계층(HLF_OfficeLeasing_Import_Service)의 일이다. 이 분리 덕분에
 * to_snapshot()은 최초 Import와 향후 Refresh(원본에서 다시 불러오기) 양쪽에서 그대로 재사용된다.
 *
 * 부작용 없음: get_field()로 "읽기"만 하고, update_post_meta()/wp_insert_post() 등 어떤 것도
 * 호출하지 않는다 — officeleasing 원본에도, Flyer 쪽에도 아무것도 쓰지 않는 순수 함수다.
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
 *
 * 이미지 처리 범위: exterior_image_id/interior_image_ids(플러그인 자체 가공본 슬롯, 워터마크·
 * 리사이즈 파이프라인 전용)는 mapping_contract() 문서에는 남아있지만 이번 phase의 to_snapshot()
 * 결과에는 아예 포함하지 않는다 — 그 파이프라인이 아직 없는 상태에서 원본을 그대로 채우면 미가공
 * 원본이 처리된 이미지인 것처럼 노출되기 때문이다. 원본 attachment ID의 사전 스냅샷(예:
 * source_exterior_image_id 같은 필드)도 이번 phase에서는 두지 않는다 — 지금 이 값을 실제로
 * 소비하는 곳이 없고, 이미지 파이프라인 phase가 확정되면 그때 필요에 맞게 스키마를 추가하는 편이
 * 낫다(쓰이지 않는 필드를 미리 만들어두지 않는다).
 */
defined( 'ABSPATH' ) || exit;

final class HLF_OfficeLeasing_Mapper {

	/** mapping_contract()가 바뀔 때마다 올린다. 스냅샷에 이 시점의 값을 함께 기록해두면(snapshot_version)
	 *  나중에 계약이 바뀌었을 때 "이 Item은 옛 버전으로 매핑됐다"를 코드로 판별할 수 있다. */
	const MAPPING_CONTRACT_VERSION = 1;

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
			'interior_image_ids'     => array( 'from' => 'listing', 'source' => 'listing_image_1..6', 'transform' => 'image_derive' ),
			// approval_date/building_use/article_no/contact_* 는 core 소스가 없음 → 관리자 입력(Flyer 신규).
		);
	}

	/**
	 * listing ID로부터 Flyer item 스냅샷 배열을 생성한다. building은 항상 listing의
	 * related_building에서 서버가 직접 구한다 — 호출자가 building_id를 지정할 수 있게 하면
	 * listing과 실제로 연결되지 않은 임의의 building을 스냅샷에 섞어넣을 수 있으므로 파라미터
	 * 자체를 받지 않는다.
	 * 반환 배열의 키는 HLF_Meta_Schema::item_fields()와 1:1로 대응하며, 그대로
	 * HLF_Item_Repository::create_item()/update_item()에 넘기면 된다.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function to_snapshot( int $listing_id ) {
		if ( ! self::is_core_available() ) {
			return new WP_Error(
				'hlf_core_unavailable',
				'원본 매물 연동 기능이 꺼져 있어 불러올 수 없습니다(officeleasing-core/ACF 비활성화). 관리자에게 문의해 주세요.',
				array( 'status' => 503 )
			);
		}
		if ( 'listing' !== get_post_type( $listing_id ) ) {
			return new WP_Error( 'hlf_invalid_listing', '선택한 매물 정보를 찾을 수 없습니다.', array( 'status' => 404 ) );
		}
		if ( ! self::is_readable_source( $listing_id ) ) {
			return new WP_Error(
				'hlf_source_not_readable',
				'이 매물은 열람 권한이 없거나 발행 상태가 아니어서 가져올 수 없습니다.',
				array( 'status' => 403 )
			);
		}

		$building_id = (int) get_field( 'related_building', $listing_id );
		if ( ! $building_id || 'building' !== get_post_type( $building_id ) ) {
			return new WP_Error(
				'hlf_no_building_linked',
				'이 매물에 연결된 빌딩 정보가 없어 가져올 수 없습니다.',
				array( 'status' => 422 )
			);
		}
		if ( ! self::is_readable_source( $building_id ) ) {
			return new WP_Error(
				'hlf_source_not_readable',
				'연결된 빌딩을 열람할 권한이 없거나 발행 상태가 아니어서 가져올 수 없습니다.',
				array( 'status' => 403 )
			);
		}

		// building_parking 원문은 parking_available(bool)/total_parking(원문) 양쪽의 공통 소스이므로
		// 한 번만 읽는다.
		$parking_raw = (string) get_field( 'building_parking', $building_id );

		return array(
			'road_address'              => (string) get_field( 'building_address_road', $building_id ),
			'lot_address'                => (string) get_field( 'building_address_jibun', $building_id ),
			'latitude'                   => (string) get_field( 'building_lat', $building_id ),
			'longitude'                  => (string) get_field( 'building_lng', $building_id ),
			'floor_current'              => (string) get_field( 'floor_display', $listing_id ),
			'floor_total'                => self::stringify_number( get_field( 'building_ground_floors', $building_id ) ),
			'lease_area_sqm'             => (float) get_field( 'lease_area_sqm', $listing_id ),
			'exclusive_area_sqm'         => (float) get_field( 'exclusive_area_sqm', $listing_id ),
			'deposit_manwon'             => (float) get_field( 'deposit_manwon', $listing_id ),
			'monthly_rent_manwon'        => (float) get_field( 'monthly_rent_manwon', $listing_id ),
			'maintenance_fee_manwon'     => (float) get_field( 'maintenance_fee_manwon', $listing_id ),
			'parking_available'          => hlf_normalize_parking_available( $parking_raw ),
			'total_parking'              => $parking_raw,
			'elevator_available'         => ( (int) get_field( 'building_elevator_count', $building_id ) ) >= 1,
			'direction'                  => (string) get_field( 'building_orientation', $building_id ),
			'available_date_text'        => self::format_move_in_text( $listing_id ),
			'features'                   => self::compose_features( $listing_id ),
		);
	}

	/**
	 * 워드프레스 네이티브 발행 상태(post_status === 'publish') + 열람 권한(read_post) 둘 다 확인한다.
	 * officeleasing의 listing_status(available/reserved/contract_pending/...) ACF 필드는 업무상
	 * 진행 상태일 뿐 이 게이트와 무관하다 — 그 필드로 차단하면 "협의중"인 정상 발행 매물도 못
	 * 가져오게 되어 정책과 어긋난다. 이 게이트는 오직 "검색을 우회해 draft/private listing_id를
	 * 직접 넘기는" 경로를 막기 위한 것이다(검색 자체는 이미 publish만 노출해 안전하다).
	 */
	// public: HLF_OfficeLeasing_Search::summarize_listing()도 검색 결과 미리보기에서 building
	// 노출 여부를 판단할 때 이 정의를 그대로 재사용한다(같은 "읽을 수 있는 원본" 기준을 두 곳에서
	// 따로 정의해 어긋나는 것을 막기 위함).
	public static function is_readable_source( int $post_id ): bool {
		return 'publish' === get_post_status( $post_id ) && current_user_can( 'read_post', $post_id );
	}

	/** "0"/빈 값은 빈 문자열로, 그 외엔 정수 문자열로. floor_total처럼 자유표기 문자열 필드에 채운다. */
	private static function stringify_number( $value ): string {
		$value = (float) $value;
		return $value > 0 ? (string) ( (int) $value ) : '';
	}

	/** move_in_type(select) + move_in_date(scheduled일 때만) → 사람이 읽는 한 줄 문구. */
	private static function format_move_in_text( int $listing_id ): string {
		$type = (string) get_field( 'move_in_type', $listing_id );
		switch ( $type ) {
			case 'immediate':
				return '즉시입주';
			case 'negotiable':
				return '협의가능';
			case 'scheduled':
				$formatted = self::format_ymd_dot( (string) get_field( 'move_in_date', $listing_id ) );
				return '' === $formatted ? '날짜협의' : $formatted . ' 입주가능';
			default:
				return '';
		}
	}

	/** ACF date_picker의 "Ymd"(예: 20260315) → "2026.03.15". 형식이 안 맞으면 빈 문자열. */
	private static function format_ymd_dot( string $ymd ): string {
		if ( ! preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $ymd, $m ) ) {
			return '';
		}
		return "{$m[1]}.{$m[2]}.{$m[3]}";
	}

	/**
	 * listing_key_point_1~3(그룹, kp{n}_title/kp{n}_desc) + listing_note를 MVP의
	 * "ACE | 인테리어 완비 | 컨디션 우수" 스타일 한 줄로 합친다.
	 */
	private static function compose_features( int $listing_id ): string {
		$parts = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$kp    = get_field( "listing_key_point_{$i}", $listing_id );
			$title = is_array( $kp ) ? trim( (string) ( $kp[ "kp{$i}_title" ] ?? '' ) ) : '';
			$desc  = is_array( $kp ) ? trim( (string) ( $kp[ "kp{$i}_desc" ] ?? '' ) ) : '';
			if ( '' !== $title && '' !== $desc ) {
				$parts[] = "{$title}({$desc})";
			} elseif ( '' !== $title ) {
				$parts[] = $title;
			} elseif ( '' !== $desc ) {
				$parts[] = $desc;
			}
		}
		$note = trim( (string) get_field( 'listing_note', $listing_id ) );
		if ( '' !== $note ) {
			$parts[] = $note;
		}
		return implode( ' | ', $parts );
	}

}
