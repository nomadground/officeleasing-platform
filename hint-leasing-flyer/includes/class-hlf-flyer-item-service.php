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
		// 소속 item도 함께 제거. 이 Item들이 원본 매물을 출처로 갖고 있었다면 그 원본의 "연결됨"
		// 상태가 바뀌므로($linked_source_ids), 삭제 후 대시보드 통계 캐시를 무효화해야 한다 —
		// 안 하면 이미 사라진 연결이 통계에 최대 TTL만큼 남는다(외부 코드 감사 P1).
		$had_source_link = false;
		foreach ( HLF_Item_Repository::get_items( $flyer_id ) as $item ) {
			if ( ! $had_source_link && (int) get_post_meta( $item->ID, 'source_listing_id', true ) > 0 ) {
				$had_source_link = true;
			}
			wp_delete_post( $item->ID, true );
		}
		$result = wp_delete_post( $flyer_id, $force );
		if ( $had_source_link ) {
			HLF_Source_Listing_Repository::invalidate_stats_cache();
		}
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

	/** 같은 PHP 요청 안에서 이미 이 Flyer의 락을 들고 있는지(중첩 호출 재진입 감지, 아래 참고). */
	private static array $held_locks = array();

	/**
	 * 동시성 안정화 감사 대응: 이 Flyer(flyer_id) 하나에 대한 쓰기 경쟁 조건을 좁힌다.
	 *
	 * 문제: HLF_Item_Repository::create_item()은 "현재 item 개수 < 10개" 확인과 display_order
	 * 최댓값 계산(prepare_item_creation())을 먼저 SELECT로 읽은 뒤 나중에 wp_insert_post()로 쓴다 —
	 * 이 두 단계 사이에 잠금이 없으면, 같은 Flyer에 거의 동시에 두 번 "매물 추가"가 들어왔을 때(더블
	 * 클릭, 여러 탭/기기) 둘 다 "9개(<10)"를 보고 통과해 11개가 되거나, 둘 다 같은 최댓값+1을 계산해
	 * 두 매물이 같은 display_order를 갖는 경합이 생길 수 있다. HLF_Source_Listing_Repository::
	 * include_in_flyer()의 "이미 포함됐는지" 확인도 같은 구조(확인 → create_item)라 같은 원본을
	 * 동시에 두 번 포함시키면 Item이 중복 생성될 수 있다.
	 *
	 * 해결: MySQL/MariaDB의 이름 붙은 락(GET_LOCK/RELEASE_LOCK, "advisory lock")으로 이 Flyer에 대한
	 * 위 두 종류의 쓰기를 한 번에 하나씩만 지나가게 한다. 트랜잭션이 아니라 이 락을 쓰는 이유: 포스트/
	 * 포스트메타는 각각 독립적으로 커밋되는 워드프레스 쓰기 API(wp_insert_post/update_post_meta)라
	 * DB 트랜잭션으로 통째로 감쌀 수 없다 — "이 Flyer에 대한 이 종류의 쓰기는 순서대로"라는 보장만
	 * 있으면 충분하다.
	 *
	 * - 재진입 안전: 같은 PHP 요청(=같은 DB 커넥션) 안에서 중첩 호출되면(예: include_in_flyer()가
	 *   이미 이 락을 잡은 채로 그 안에서 create_item()이 다시 이 메서드를 호출) 이미 들고 있는 락이므로
	 *   재획득을 시도하지 않고 콜백만 실행한다 — MySQL 버전별 GET_LOCK() 재진입 동작 차이(5.7.5 이전/
	 *   이후 "세션당 락 1개"와 "세션당 여러 락" 정책 차이)에 기대지 않기 위해 PHP 쪽에서 명시적으로
	 *   추적한다. 한 요청은 항상 Flyer 하나만 잠그므로(서로 다른 두 Flyer를 동시에 잠그는 경로가 없다)
	 *   nested lock deadlock 시나리오 자체가 없다.
	 * - 반드시 해제: try/finally로 감싸 콜백에서 예외가 나거나 WP_Error를 반환해도 락은 항상 풀린다.
	 * - 락 획득 자체가 타임아웃(다른 요청이 지금 이 Flyer를 쓰고 있음)되면 진행하지 않고 바로 409를
	 *   반환한다 — 여기서 조용히 통과시키면 락을 두는 의미가 없어진다. 반면 GET_LOCK 자체를 쓸 수 없는
	 *   환경(권한 제한된 DB 계정, $wpdb 테스트 더블 등, get_var()가 '0'/'1'이 아닌 값을 돌려주는 경우)은
	 *   fail-open으로 락 없이 그대로 진행한다 — 이 락은 실사용 경합 창을 좁히는 방어이지 유일한 정합성
	 *   보증은 아니며(각 메서드 자신의 검증이 최종 기준), GET_LOCK을 못 쓴다고 기능 전체가 멈추는 것보다
	 *   낫다.
	 *
	 * 실행 환경 제약: 이 저장소의 개발/CI 환경에는 워드프레스·MySQL 런타임이 없어 GET_LOCK 경로 자체를
	 * 여기서 직접 기동해 검증하지는 못했다(정적 코드 검토만 수행) — 실제 워드프레스+MySQL/MariaDB
	 * 배포 환경에서의 동시 요청 테스트가 필요하다.
	 *
	 * @return mixed $callback의 반환값, 또는 락이 타임아웃되면 WP_Error.
	 */
	public static function with_flyer_lock( int $flyer_id, callable $callback ) {
		$name = 'hlf_flyer_items_' . $flyer_id;
		if ( isset( self::$held_locks[ $name ] ) ) {
			return $callback();
		}

		global $wpdb;
		$acquired = false;
		if ( $wpdb instanceof wpdb ) {
			// 3초: 관리자 UI의 단발성 클릭 충돌을 가르는 용도라 길게 기다릴 이유가 없다 — 그 이상
			// 걸리면 다른 요청이 지금 이 Flyer를 쓰고 있다는 뜻이므로 즉시 409로 알려 다시 시도하게 한다.
			$got = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $name, 3 ) );
			if ( '0' === (string) $got ) {
				return new WP_Error(
					'hlf_flyer_busy',
					'이 안내문을 다른 요청이 처리하고 있습니다. 잠시 후 다시 시도해 주세요.',
					array( 'status' => 409 )
				);
			}
			$acquired = '1' === (string) $got;
		}

		if ( $acquired ) {
			self::$held_locks[ $name ] = true;
		}
		try {
			return $callback();
		} finally {
			if ( $acquired ) {
				unset( self::$held_locks[ $name ] );
				$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
			}
		}
	}
}
