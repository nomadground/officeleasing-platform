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
		// 시/도+구/군 접두어를 통째로 지운다(동/번지만 남김) — 예: "서울 강남구 역삼동 825" ->
		// "역삼동 825". 이전에는 "서울특별시 강남구 "/"서울시 강남구 " 두 문자열만 하드코딩해
		// 지웠는데, 실제 데이터(카카오 API 응답)는 "서울 강남구 "처럼 "시/특별시" 표기 없이 내려와
		// 지워지지 않는 경우가 있었다(강남구 매물만 다루던 초기 데이터로는 안 드러났던 격차).
		// "구/군"으로 끝나는 마지막 행정구역 단어까지를 통째로 지우는 방식으로 일반화해, 강남구가
		// 아닌 다른 구/군(서초구·분당구 등)이나 "경기도 성남시 분당구"처럼 시/도 사이에 다른 시가
		// 끼는 경우까지도 별도 하드코딩 없이 동일하게 처리한다. 구/군이 아예 없는 주소(드묾)는
		// 매치되지 않아 원문 그대로 남는다(안전한 기본값).
		$strip_prefix = static function ( $address ) {
			$address = trim( (string) $address );
			$stripped = preg_replace( '/^.*?[가-힣]+(?:구|군)\s+/u', '', $address, 1 );
			return null !== $stripped ? $stripped : $address;
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
		// 5번째 색(#b8862e)은 흰색 텍스트 대비 3.24:1로 WCAG AA 본문 텍스트 기준(4.5:1) 미달이었다 —
		// 같은 색상 계열을 유지한 채 명도만 낮춰 4.81:1로 교체(#936b25). assets/js/public-flyer.js의
		// ACCENT_PALETTE와 값이 완전히 같아야 하므로 그쪽도 함께 바꿨다(파일 상단 주석 규칙).
		static $palette = array( '#355c73', '#a8582c', '#3d7a4f', '#7a4a9c', '#936b25', '#3d6e8a', '#8a3d4a', '#4a7a3d' );
		return $palette[ $order % count( $palette ) ];
	}
}
