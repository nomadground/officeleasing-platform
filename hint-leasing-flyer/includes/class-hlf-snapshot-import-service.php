<?php
/**
 * officeleasing listing/building → Flyer Item "가져오기" 오케스트레이션.
 *
 * 이 클래스가 하는 일은 딱 두 단계뿐이다:
 *   1) HLF_OfficeLeasing_Mapper::to_snapshot() 으로 데이터 필드 배열을 받는다(순수 변환, 저장 없음).
 *   2) provenance(어디서 왔는지) 필드를 얹어서 HLF_Item_Repository::create_item()에 그대로 넘긴다.
 *
 * update_post_meta()를 직접 호출하지 않는다 — 실제 저장은 전부 create_item() 내부의 기존
 * apply_fields()가 처리한다(Repository 미수정). 10개 제한/item_number 발급/display_order 계산도
 * create_item()이 이미 하는 일이므로 여기서 다시 처리하지 않는다.
 *
 * provenance 필드(source_listing_id/source_building_id/snapshot_created_at/refreshed_at/version)는
 * Mapper의 관심사가 아니다 — Mapper는 "이 listing/building이 어떤 Flyer 필드 값을 갖는지"만 알고,
 * "이 스냅샷을 누가 언제 가져왔는지"는 이 오케스트레이션 계층의 책임이라 여기서만 채운다.
 */
defined( 'ABSPATH' ) || exit;

final class HLF_Snapshot_Import_Service {

	/**
	 * @return int|WP_Error 생성된 item의 post ID, 또는 실패 사유(Mapper/Repository가 준 그대로 전파).
	 */
	public static function import( int $flyer_id, int $listing_id, ?int $building_id = null ) {
		$snapshot = HLF_OfficeLeasing_Mapper::to_snapshot( $listing_id, $building_id );
		if ( is_wp_error( $snapshot ) ) {
			return $snapshot;
		}

		$resolved_building_id = $building_id ?: (int) get_field( 'related_building', $listing_id );
		$now = current_time( 'mysql', true ); // GMT — flyer.created/modified가 post_date_gmt를 쓰는 것과 동일 관례.

		$fields = array_merge( $snapshot, array(
			'source_listing_id'     => $listing_id,
			'source_building_id'    => $resolved_building_id,
			'snapshot_created_at'   => $now,
			'snapshot_refreshed_at' => $now,
			'snapshot_version'      => HLF_OfficeLeasing_Mapper::MAPPING_CONTRACT_VERSION,
		) );

		return HLF_Item_Repository::create_item( $flyer_id, $fields );
	}
}
