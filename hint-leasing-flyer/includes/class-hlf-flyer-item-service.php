<?php
/**
 * Flyer ↔ Item 관계 조정 서비스.
 *
 * HLF_Flyer_Repository/HLF_Item_Repository는 각자 자기 CPT(leasing_flyer/leasing_flyer_item)의
 * 단일 CRUD만 맡고, "Flyer와 Item이 서로에게 미치는 영향"(보관 상태 가드, 삭제 cascade, 매물 개수
 * 상한 정책)은 이 서비스가 조정한다.
 *
 * 리팩토링 전 구조: Flyer_Repository::delete()가 HLF_Item_Repository::get_items()를 불러 자식을
 * 지우고, 반대로 Item_Repository의 모든 쓰기 메서드가 HLF_Flyer_Repository::assert_not_archived()를
 * 불렀다 — 두 Repository가 서로를 참조하는 순환 의존이었다. 이 서비스를 상위에 두어 의존 방향을
 * "서비스 → 두 Repository" 단방향으로 정리한다(Repository는 이 서비스를 몰라도 된다).
 *
 * 리팩토링 원칙: 이 파일이 생기기 전과 REST 엔드포인트 요청/응답이 100% 동일해야 한다 — 여기서
 * 하는 일은 기존 로직을 있는 그대로 옮기는 것뿐, 새 검증/필드/응답을 추가하지 않는다.
 */
defined( 'ABSPATH' ) || exit;

final class HLF_Flyer_Item_Service {

	/** Flyer 하나에 담을 수 있는 최대 항목 수. (구 HLF_Item_Repository::MAX_ITEMS_PER_FLYER) */
	const MAX_ITEMS_PER_FLYER = 10;

	/**
	 * archived Flyer는 읽기 전용이다(기존 공유 링크는 유지, 신규 쓰기만 차단) — Flyer 자체 수정,
	 * Item 추가/수정/삭제/재정렬, officeleasing import 등 이 Flyer에 속한 모든 변경 동작이 이
	 * 게이트를 공유한다(중복 구현 방지). 상태 변경(set_status) 자체와 읽기(GET)는 이 게이트를
	 * 거치지 않는다 — archived에서 draft/published로 되돌리는 것 자체가 막히면 안 되기 때문이다.
	 * (구 HLF_Flyer_Repository::assert_not_archived().)
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

	/**
	 * Flyer 삭제 + 소속 Item cascade 삭제. (구 HLF_Flyer_Repository::delete() 본문 그대로.)
	 * HLF_Flyer_Repository::delete()가 이 메서드로 위임하므로 REST 엔드포인트(DELETE /flyers/{id})의
	 * 요청/응답은 이 리팩토링 전후로 동일하다.
	 */
	public static function delete_flyer_with_items( int $flyer_id, bool $force = false ): bool|WP_Error {
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
	 * 새 Item을 만들기 전에 확인해야 할 것(보관 가드 + 10개 상한)과, 새 Item에 매길 display_order를
	 * 한 번에 계산해 돌려준다. (구 HLF_Item_Repository::create_item() 앞부분 그대로.) Item_Repository는
	 * 이 결과만 받아 실제 포스트 생성(wp_insert_post)과 필드 저장만 맡는다 — "이 Flyer가 새 Item을
	 * 받아도 되는가"라는 정책 판단은 여기 있다.
	 *
	 * @return array{next_display_order:int}|WP_Error
	 */
	public static function prepare_item_creation( int $flyer_id ) {
		$guard = self::assert_not_archived( $flyer_id );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}

		// 10개 제한 체크와 display_order 최댓값 계산 둘 다 "이 Flyer의 현재 item 목록"이 필요하므로
		// get_items()를 한 번만 불러 재사용한다(전에는 이 두 용도로 같은 쿼리를 두 번 날렸다).
		$existing_items = HLF_Item_Repository::get_items( $flyer_id );

		// 클라이언트 우회(직접 REST 호출 등) 방지 — 서버가 최종 기준. UI는 안내만 표시한다.
		if ( count( $existing_items ) >= self::MAX_ITEMS_PER_FLYER ) {
			return new WP_Error(
				'hlf_item_limit_reached',
				sprintf( 'Flyer 하나에는 최대 %d개의 매물만 담을 수 있습니다.', self::MAX_ITEMS_PER_FLYER ),
				array( 'status' => 400 )
			);
		}

		// wp_insert_post() 이전에 기존 item들의 display_order 최댓값을 구한다 — 삭제로 중간 번호가
		// 빈 상태에서 "전체 개수"로 새 순번을 매기면 남아있는 item과 값이 겹칠 수 있다(예: 0,1,2 중
		// 1번 삭제 후 재추가 시 "개수-1"=1이 남아있는 2번과 충돌). 최댓값+1이면 항상 유일하다.
		$max_existing_order = -1;
		foreach ( $existing_items as $existing_item ) {
			$max_existing_order = max( $max_existing_order, (int) get_post_meta( $existing_item->ID, 'display_order', true ) );
		}

		return array( 'next_display_order' => $max_existing_order + 1 );
	}
}
