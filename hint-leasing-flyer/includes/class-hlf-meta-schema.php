<?php
/**
 * Flyer Item 메타 스키마 (요청서 1-B 전체 필드).
 *
 * 이 클래스가 필드의 단일 진실원천이다. 값 접근은 워드프레스 네이티브 get_post_meta/update_post_meta로만
 * 하며 ACF get_field에 의존하지 않는다 → officeleasing-core/ACF 비활성 상태에서도 동작한다.
 * (ACF가 있으면 acf-json 필드그룹이 같은 meta_key로 편집 UI를 얹지만, 읽기/쓰기 경로는 항상 네이티브다.)
 *
 * item_number는 서버가 관리하는 불변 식별자이므로 클라이언트 쓰기 화이트리스트(writable_fields)에서 제외한다.
 */
defined( 'ABSPATH' ) || exit;

final class HLF_Meta_Schema {

	/** 내부 관리용 flyer 메타: 다음 item 시퀀스(원자적 증가 대상). */
	const FLYER_ITEM_SEQ = '_hlf_next_item_seq';

	/**
	 * 요청서 7: 새 형식 Flyer 번호(생성 시각의 날짜 6자리 + 그날 순번 2자리, 예 "26072301"). 생성
	 * 시점에 한 번만 기록되고 이후 절대 바뀌지 않는다(HLF_Flyer_Repository::create() 참고). 이 메타가
	 * 없는 Flyer(이 필드가 생기기 전에 만들어짐)는 옛 방식("LF-000123", post ID 기반 실시간 계산)을
	 * 그대로 쓴다 — 기존에 이미 공유된 링크의 번호가 이 변경으로 바뀌는 일은 없다.
	 */
	const FLYER_NUMBER = '_hlf_flyer_number';

	/**
	 * item 필드 정의. key => [ 'type' => string|int|float|bool|int_array, 'default' => mixed ].
	 * 'source_*'는 원본 listing/building 연결(선택). 나머지는 발행 시점 snapshot 값.
	 */
	public static function item_fields(): array {
		return array(
			// 원본 연결(Phase 2-2: officeleasing import) — 전부 서버 전용(writable_fields() 제외).
			// HLF_Item_Repository::set_snapshot_metadata()를 통해서만 기록되며, 일반 Item
			// POST/PUT(apply_fields())으로는 절대 바뀌지 않는다. created_at은 최초 1회만 기록되고
			// 이후 절대 갱신되지 않는다 — refreshed_at만 재수입(Refresh, 향후 phase) 때 채워진다.
			// version은 이 스냅샷이 만들어질 당시의 HLF_OfficeLeasing_Mapper::MAPPING_CONTRACT_VERSION.
			'source_listing_id'      => array( 'type' => 'int' ),
			'source_building_id'     => array( 'type' => 'int' ),
			'snapshot_created_at'    => array( 'type' => 'string' ),
			'snapshot_refreshed_at'  => array( 'type' => 'string' ),
			'snapshot_version'       => array( 'type' => 'int' ),

			// 식별/표시 순서
			'item_number'            => array( 'type' => 'string' ), // 서버 관리(불변). writable 제외.
			'display_order'          => array( 'type' => 'int' ),

			// 주소/좌표 (building 스냅샷)
			'road_address'           => array( 'type' => 'string' ),
			'lot_address'            => array( 'type' => 'string' ),
			// 건물명(선택) — officeleasing 연동 없이 직접 입력하는 자유 텍스트. 비워두면 어디에도
			// 표시하지 않는다(요청서: "안쓰면 안나오게").
			'building_name'          => array( 'type' => 'string' ),
			'latitude'               => array( 'type' => 'geo' ),
			'longitude'              => array( 'type' => 'geo' ),

			// 층/면적
			'floor_current'          => array( 'type' => 'string' ), // "B1", "2~3" 등 자유표기 → string
			'floor_total'            => array( 'type' => 'string' ),
			'lease_area_sqm'         => array( 'type' => 'float' ),
			'exclusive_area_sqm'     => array( 'type' => 'float' ),

			// 금액(만원)
			'deposit_manwon'         => array( 'type' => 'float' ),
			'monthly_rent_manwon'    => array( 'type' => 'float' ),
			'maintenance_fee_manwon' => array( 'type' => 'float' ),

			// 편의/부대
			'parking_available'      => array( 'type' => 'bool' ),
			'total_parking'          => array( 'type' => 'string' ), // building_parking 원문 파생
			'elevator_available'     => array( 'type' => 'bool' ),
			'direction'              => array( 'type' => 'string' ),
			'available_date_text'    => array( 'type' => 'string' ),

			// Flyer 신규 필드 (officeleasing-core에 없음)
			'approval_date'          => array( 'type' => 'string' ), // 사용승인일(≠ building_completion_date=준공일)
			'building_use'           => array( 'type' => 'string' ),
			'article_no'             => array( 'type' => 'string' ),
			'contact_name'           => array( 'type' => 'string' ),
			'contact_phone'          => array( 'type' => 'string' ),

			'features'               => array( 'type' => 'string' ),

			// 이미지: Phase 2 파이프라인이 채운다. 스키마만 미리 등록.
			'exterior_image_id'      => array( 'type' => 'int' ),
			'interior_image_ids'     => array( 'type' => 'int_array' ),
		);
	}

	/**
	 * 원본 매물(hlf_source_listing) 필드 정의. Item 스키마에서 "특정 Flyer 소속"이라는 뜻을 갖는
	 * 필드만 뺀 것이다 — item_number/display_order(Flyer 내 식별·순서)와 snapshot_*·source_*
	 * (Item이 어느 원본/발행 시점에서 왔는지 추적하는 provenance)는 독립 카탈로그인 원본 매물에는
	 * 의미가 없다. 나머지(주소/층/면적/금액/담당자/이미지 등)는 Item과 동일한 key/type을 그대로
	 * 쓴다 — 그래야 "포함" 시 값을 그대로 복사해 Item을 만들 수 있다.
	 */
	public static function source_fields(): array {
		$exclude = array(
			'item_number', 'display_order',
			'source_listing_id', 'source_building_id',
			'snapshot_created_at', 'snapshot_refreshed_at', 'snapshot_version',
		);
		return array_diff_key( self::item_fields(), array_flip( $exclude ) );
	}

	/** 원본 매물에서 클라이언트가 직접 쓸 수 있는 필드(이미지는 Item과 마찬가지로 별도 엔드포인트). */
	public static function source_writable_fields(): array {
		$exclude = array( 'exterior_image_id', 'interior_image_ids' );
		return array_values( array_diff( array_keys( self::source_fields() ), $exclude ) );
	}

	/** 원본 매물 전체 필드를 정규화된 배열로 읽는다(read_item과 같은 규칙). */
	public static function read_source( int $source_id ): array {
		$out = array( 'id' => $source_id );
		foreach ( self::source_fields() as $key => $def ) {
			$raw = get_post_meta( $source_id, $key, true );
			if ( 'int_array' === $def['type'] ) {
				$out[ $key ] = is_array( $raw ) ? array_map( 'absint', $raw ) : array();
			} elseif ( 'bool' === $def['type'] ) {
				$out[ $key ] = self::to_bool( $raw );
			} elseif ( 'int' === $def['type'] ) {
				$out[ $key ] = (int) $raw;
			} elseif ( 'float' === $def['type'] ) {
				$out[ $key ] = $raw === '' ? null : (float) $raw;
			} else {
				$out[ $key ] = (string) $raw;
			}
		}
		return $out;
	}

	/** 클라이언트가 REST로 직접 쓸 수 있는 필드(서버관리/순서/스냅샷 provenance 필드 제외). */
	public static function writable_fields(): array {
		$exclude = array(
			'item_number', 'display_order', 'exterior_image_id', 'interior_image_ids',
			// Phase 2-2: officeleasing import provenance — HLF_Item_Repository::set_snapshot_metadata()
			// 를 통해서만 기록된다. 일반 Item 생성/수정 폼에서 절대 입력받지 않는다.
			'source_listing_id', 'source_building_id',
			'snapshot_created_at', 'snapshot_refreshed_at', 'snapshot_version',
		);
		return array_values( array_diff( array_keys( self::item_fields() ), $exclude ) );
	}

	/**
	 * Flyer(발행 단위) 레벨 메타. 요청서 Phase 2-1: 담당자 기본값.
	 *
	 * 용도: Flyer 전체의 기본 문의처(공개 템플릿 하단에 표시, 비어있으면 대표번호로 폴백).
	 * Item의 contact_name/contact_phone(item_fields 참고)은 그대로 유지되며, "이 매물만
	 * 다른 담당자면 개별 입력"하는 선택적 override다 — Flyer 레벨 값을 대체하는 게 아니라
	 * 항목 단위로 겹쳐 쓰는 구조. 우선순위(항목 override → flyer 기본값 → 대표번호 폴백)는
	 * 공개 템플릿(templates/public/*.php)에서 처리한다.
	 */
	public static function flyer_fields(): array {
		return array(
			'contact_name'  => array( 'type' => 'string' ),
			'contact_phone' => array( 'type' => 'string' ),
		);
	}

	/** Flyer 필드는 전부 클라이언트가 직접 쓸 수 있다(서버관리 필드가 없음). */
	public static function flyer_writable_fields(): array {
		return array_keys( self::flyer_fields() );
	}

	public static function register(): void {
		$auth = static function ( $allowed, $meta_key, $post_id ) {
			return current_user_can( 'edit_post', $post_id );
		};

		foreach ( self::item_fields() as $key => $def ) {
			$rest_type = self::rest_type( $def['type'] );
			register_post_meta( HLF_Post_Types::ITEM, $key, array(
				'single'            => true,
				'type'              => $rest_type,
				'show_in_rest'      => false, // 커스텀 컨트롤러(HLF_REST_Controller)로만 노출.
				'sanitize_callback' => static function ( $value ) use ( $def ) {
					return HLF_Meta_Schema::sanitize( $def['type'], $value );
				},
				'auth_callback'     => $auth,
			) );
		}

		foreach ( self::flyer_fields() as $key => $def ) {
			$rest_type = self::rest_type( $def['type'] );
			register_post_meta( HLF_Post_Types::FLYER, $key, array(
				'single'            => true,
				'type'              => $rest_type,
				'show_in_rest'      => false,
				'sanitize_callback' => static function ( $value ) use ( $def ) {
					return HLF_Meta_Schema::sanitize( $def['type'], $value );
				},
				'auth_callback'     => $auth,
			) );
		}

		foreach ( self::source_fields() as $key => $def ) {
			$rest_type = self::rest_type( $def['type'] );
			register_post_meta( HLF_Post_Types::SOURCE, $key, array(
				'single'            => true,
				'type'              => $rest_type,
				'show_in_rest'      => false,
				'sanitize_callback' => static function ( $value ) use ( $def ) {
					return HLF_Meta_Schema::sanitize( $def['type'], $value );
				},
				'auth_callback'     => $auth,
			) );
		}

		register_post_meta( HLF_Post_Types::FLYER, self::FLYER_ITEM_SEQ, array(
			'single'        => true,
			'type'          => 'integer',
			'show_in_rest'  => false,
			'auth_callback' => $auth,
		) );

		register_post_meta( HLF_Post_Types::FLYER, self::FLYER_NUMBER, array(
			'single'        => true,
			'type'          => 'string',
			'show_in_rest'  => false,
			'auth_callback' => $auth,
		) );
	}

	private static function rest_type( string $type ): string {
		switch ( $type ) {
			case 'int':
				return 'integer';
			case 'float':
			case 'geo':
				return 'number';
			case 'bool':
				return 'boolean';
			case 'int_array':
				return 'array';
			default:
				return 'string';
		}
	}

	/** 타입별 정규화/살균. REST 저장과 register_post_meta sanitize 양쪽에서 공용으로 쓴다. */
	public static function sanitize( string $type, $value ) {
		switch ( $type ) {
			case 'int':
				return (int) $value;
			case 'float':
				return $value === '' || $value === null ? '' : (float) $value;
			case 'geo':
				// 좌표는 정밀도 보존 위해 문자열로 저장하되 숫자만 허용.
				if ( $value === '' || $value === null || ! is_numeric( $value ) ) {
					return '';
				}
				return (string) $value;
			case 'bool':
				return self::to_bool( $value ) ? 1 : 0;
			case 'int_array':
				$arr = is_array( $value ) ? $value : array();
				return array_values( array_filter( array_map( 'absint', $arr ) ) );
			case 'string':
			default:
				return sanitize_text_field( (string) $value );
		}
	}

	public static function to_bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( is_numeric( $value ) ) {
			return (float) $value > 0;
		}
		$v = strtolower( trim( (string) $value ) );
		return in_array( $v, array( 'true', '1', 'yes', 'y', 'on', '가능', 'true' ), true );
	}

	/** item 전체 필드를 정규화된 배열로 읽는다(공개 템플릿/REST 응답 공용). */
	public static function read_item( int $item_id ): array {
		$out = array( 'id' => $item_id );
		foreach ( self::item_fields() as $key => $def ) {
			$raw = get_post_meta( $item_id, $key, true );
			if ( 'int_array' === $def['type'] ) {
				$out[ $key ] = is_array( $raw ) ? array_map( 'absint', $raw ) : array();
			} elseif ( 'bool' === $def['type'] ) {
				$out[ $key ] = self::to_bool( $raw );
			} elseif ( 'int' === $def['type'] ) {
				$out[ $key ] = (int) $raw;
			} elseif ( 'float' === $def['type'] ) {
				$out[ $key ] = $raw === '' ? null : (float) $raw;
			} else {
				$out[ $key ] = (string) $raw;
			}
		}
		return $out;
	}

	/** Flyer 레벨 메타(contact_name/contact_phone)를 정규화된 배열로 읽는다. */
	public static function read_flyer( int $flyer_id ): array {
		$out = array();
		foreach ( self::flyer_fields() as $key => $def ) {
			$out[ $key ] = self::sanitize( $def['type'], get_post_meta( $flyer_id, $key, true ) );
		}
		return $out;
	}
}
