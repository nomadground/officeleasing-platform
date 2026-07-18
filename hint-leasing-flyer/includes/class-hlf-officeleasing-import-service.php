<?php
/**
 * officeleasing listing → Flyer Item "가져오기" 오케스트레이션.
 *
 * 흐름은 딱 세 단계뿐이다:
 *   1) HLF_OfficeLeasing_Mapper::to_snapshot() 으로 데이터 필드 배열을 받는다(순수 변환, 저장 없음).
 *      building_id는 여기서 클라이언트로부터 절대 받지 않는다 — Mapper가 listing의 related_building
 *      연결에서 항상 서버 스스로 확인하므로, 클라이언트가 임의의 building_id를 넘겨 주소를 조작한
 *      스냅샷을 만들 수 없다.
 *   2) 데이터 필드만으로 HLF_Item_Repository::create_item()을 호출한다(10개 제한/item_number 발급/
 *      display_order 계산은 전부 create_item()이 이미 하는 일이므로 여기서 다시 구현하지 않는다).
 *   3) provenance(source_listing_id/source_building_id/snapshot_*)는 HLF_Item_Repository::
 *      set_snapshot_metadata()라는 서버 전용 경로로만 기록한다 — apply_fields()/writable_fields()
 *      화이트리스트를 거치지 않으며, 이 클래스도 update_post_meta()를 직접 호출하지 않는다.
 *
 * 2)가 성공하고 3)이 실패하면(이론상만 가능하지만) 방금 만든 Item을 그대로 남겨두지 않고 삭제해
 * "데이터는 없는데 껍데기만 있는" 고아 Item이 생기지 않게 한다.
 */
defined( 'ABSPATH' ) || exit;

final class HLF_OfficeLeasing_Import_Service {

	/**
	 * @return int|WP_Error 생성된 item의 post ID, 또는 실패 사유(Mapper/Repository가 준 그대로 전파).
	 */
	public static function import( int $flyer_id, int $listing_id ) {
		$snapshot = HLF_OfficeLeasing_Mapper::to_snapshot( $listing_id );
		if ( is_wp_error( $snapshot ) ) {
			return $snapshot;
		}

		$item_id = HLF_Item_Repository::create_item( $flyer_id, $snapshot );
		if ( is_wp_error( $item_id ) ) {
			return $item_id;
		}

		$building_id = (int) get_field( 'related_building', $listing_id );
		$now         = current_time( 'mysql', true ); // GMT — flyer.created/modified가 post_date_gmt를 쓰는 관례와 동일.

		$metadata_result = HLF_Item_Repository::set_snapshot_metadata(
			$item_id,
			$listing_id,
			$building_id,
			$now,
			'', // 최초 Import는 refreshed_at을 비워둔다 — Refresh(향후 phase)가 실제로 일어날 때만 채워진다.
			HLF_OfficeLeasing_Mapper::MAPPING_CONTRACT_VERSION
		);

		if ( is_wp_error( $metadata_result ) ) {
			// 고아 Item 정리: 데이터는 저장됐는데 출처 기록만 실패한 반쪽짜리 Item을 남기지 않는다.
			HLF_Item_Repository::delete_item( $flyer_id, $item_id );
			return $metadata_result;
		}

		return $item_id;
	}
}
