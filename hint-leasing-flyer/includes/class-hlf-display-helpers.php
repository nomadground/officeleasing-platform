<?php
/**
 * 공개 화면 전용 표시 헬퍼(주소 포맷, 매물별 accent color). HLF_Calculations와 마찬가지로 순수
 * 함수만 정의하며 워드프레스에 의존하지 않는다 — 단독 실행 테스트가 가능하다.
 *
 * 주소: DB/Snapshot 원본(road_address/lot_address)은 그대로 두고, 화면 출력 시점에만 지번주소를
 * 메인으로, 도로명주소를 보조로 뒤집고 "서울시 강남구"/"서울특별시 강남구" 접두어를 제거한다.
 * List/Detail 카드뿐 아니라 NOC 차트·지도 tooltip에 쓰이는 address 필드도 반드시 이 함수를 거쳐야
 * 화면 표시 주소와 tooltip 주소가 어긋나지 않는다.
 *
 * Accent color: 팔레트 배열은 assets/js/public-flyer.js의 ACCENT_PALETTE와 값이 완전히 같아야
 * 한다(리스트 배지는 서버 렌더링, NOC 차트/지도 마커는 클라이언트 렌더링이라 각자 계산하지만 입력값
 * order와 팔레트가 같으면 결과 색이 항상 일치한다) — 이 배열을 바꾸면 JS 쪽도 함께 바꿀 것.
 */

if ( ! function_exists( 'hlf_format_address' ) ) {
	/**
	 * @return array{main:string, sub:string} main은 항상 채워짐(지번주소 없으면 도로명주소로 대체).
	 *         sub는 main과 다를 때만 채워짐(둘 다 값이 같거나 하나만 있으면 빈 문자열).
	 */
	function hlf_format_address( $road_address, $lot_address ): array {
		$strip_prefix = static function ( $address ) {
			$address = trim( (string) $address );
			foreach ( array( '서울특별시 강남구 ', '서울시 강남구 ' ) as $prefix ) {
				if ( 0 === strpos( $address, $prefix ) ) {
					return substr( $address, strlen( $prefix ) );
				}
			}
			return $address;
		};

		$lot  = $strip_prefix( $lot_address );
		$road = $strip_prefix( $road_address );

		$main = '' !== $lot ? $lot : $road;
		$sub  = ( '' !== $road && $road !== $main ) ? $road : '';

		return array(
			'main' => $main,
			'sub'  => $sub,
		);
	}
}

if ( ! function_exists( 'hlf_item_accent_color' ) ) {
	/** 매물 순번(0-based, 화면 표시 순서와 동일) 기반 고정 팔레트 accent color. */
	function hlf_item_accent_color( int $order ): string {
		static $palette = array( '#355c73', '#a8582c', '#3d7a4f', '#7a4a9c', '#b8862e', '#3d6e8a', '#8a3d4a', '#4a7a3d' );
		return $palette[ $order % count( $palette ) ];
	}
}
