<?php
/**
 * 이미지 검색 오케스트레이션(요청서 3). "검색어 검증 → provider에 위임 → 결과 개수 제한"만 한다.
 * provider 선택은 필터 하나로 열어두되(향후 다중 provider 대비), 지금은 실제로 Naver 하나만
 * 구현한다 — 과도한 추상화(레지스트리, 팩토리 등)는 만들지 않는다.
 */
defined( 'ABSPATH' ) || exit;

final class HLF_Image_Search_Service {

	const MAX_RESULTS     = 20;
	const MIN_QUERY_CHARS = 2;

	/**
	 * @return array<int, array>|WP_Error
	 */
	public static function search( string $query ) {
		$query = trim( sanitize_text_field( $query ) );
		if ( mb_strlen( preg_replace( '/\s+/u', '', $query ) ) < self::MIN_QUERY_CHARS ) {
			return new WP_Error(
				'hlf_image_query_too_short',
				'검색어를 두 글자 이상 입력해 주세요.',
				array( 'status' => 400 )
			);
		}

		$provider = self::provider();
		if ( ! $provider->is_configured() ) {
			return new WP_Error(
				'hlf_naver_not_configured',
				'네이버 이미지 검색 설정이 필요합니다.',
				array( 'status' => 503 )
			);
		}

		$results = $provider->search( $query, self::MAX_RESULTS );
		if ( is_wp_error( $results ) ) {
			return $results;
		}

		return array_slice( array_values( $results ), 0, self::MAX_RESULTS );
	}

	/** 관리자 UI가 검색 폼을 아예 숨길지 판단할 때 쓰는 얕은 확인(REST 호출 없이). */
	public static function is_configured(): bool {
		return self::provider()->is_configured();
	}

	private static function provider(): HLF_Image_Search_Provider_Interface {
		/**
		 * 다른 provider로 교체/추가할 수 있는 유일한 확장점(이번 phase는 Naver 고정 구현만 제공).
		 *
		 * @param HLF_Image_Search_Provider_Interface $provider
		 */
		return apply_filters( 'hlf_image_search_provider', new HLF_Naver_Image_Search_Provider() );
	}
}
