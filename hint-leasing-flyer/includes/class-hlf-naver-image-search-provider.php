<?php
/**
 * 네이버 이미지 검색 API 연동(요청서 4). 인증정보는 코드에 하드코딩하지 않고 wp-config.php의
 * define() 상수(HLF_NAVER_CLIENT_ID / HLF_NAVER_CLIENT_SECRET)로만 받는다 — 기존 OL_KAKAO_JS_KEY와
 * 같은 패턴으로 통일(옵션 테이블 방식은 쓰지 않는다). Client ID/Secret은 여기(서버)에서만 쓰고
 * 관리자 JS에는 절대 넘기지 않는다.
 *
 * 원본 API 응답 구조(title/link/thumbnail/sizewidth/sizeheight 등)를 그대로 넘기지 않고
 * HLF_Image_Search_Service가 요구하는 공통 형식으로 이 클래스 안에서 정규화한다.
 */
defined( 'ABSPATH' ) || exit;

final class HLF_Naver_Image_Search_Provider implements HLF_Image_Search_Provider_Interface {

	const ENDPOINT = 'https://openapi.naver.com/v1/search/image';

	public function is_configured(): bool {
		return defined( 'HLF_NAVER_CLIENT_ID' ) && defined( 'HLF_NAVER_CLIENT_SECRET' )
			&& '' !== HLF_NAVER_CLIENT_ID && '' !== HLF_NAVER_CLIENT_SECRET;
	}

	public function search( string $query, int $limit ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error(
				'hlf_naver_not_configured',
				'네이버 이미지 검색 설정이 필요합니다.',
				array( 'status' => 503 )
			);
		}

		$url = add_query_arg(
			array(
				'query'   => rawurlencode( $query ),
				'display' => max( 1, min( 100, $limit ) ),
				'start'   => 1,
				'sort'    => 'sim',
			),
			self::ENDPOINT
		);

		$response = wp_remote_get( $url, array(
			'timeout' => 8,
			'headers' => array(
				'X-Naver-Client-Id'     => HLF_NAVER_CLIENT_ID,
				'X-Naver-Client-Secret' => HLF_NAVER_CLIENT_SECRET,
			),
		) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'hlf_image_search_failed',
				'이미지 검색에 실패했습니다. 잠시 후 다시 시도해 주세요.',
				array( 'status' => 502 )
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error(
				'hlf_image_search_failed',
				'이미지 검색에 실패했습니다. 잠시 후 다시 시도해 주세요.',
				array( 'status' => 502 )
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || ! isset( $body['items'] ) || ! is_array( $body['items'] ) ) {
			return new WP_Error(
				'hlf_image_search_malformed',
				'이미지 검색 결과 형식이 올바르지 않습니다.',
				array( 'status' => 502 )
			);
		}

		$results = array();
		foreach ( $body['items'] as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}
			$link = (string) ( $raw['link'] ?? '' );
			$results[] = array(
				'source'        => 'naver',
				'title'         => wp_strip_all_tags( (string) ( $raw['title'] ?? '' ) ),
				'thumbnail_url' => (string) ( $raw['thumbnail'] ?? '' ),
				'image_url'     => $link,
				// 네이버 이미지 검색 API는 원본 웹페이지 링크를 별도로 주지 않는다 — 이미지 자체의
				// link를 출처 URL로도 함께 쓴다(없는 값을 지어내지 않는다).
				'source_url'    => $link,
				'width'         => (int) ( $raw['sizewidth'] ?? 0 ),
				'height'        => (int) ( $raw['sizeheight'] ?? 0 ),
			);
		}

		return $results;
	}
}
