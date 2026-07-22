<?php
/**
 * hlf-display-helpers 단독 실행 테스트 (WP 부트스트랩 불필요).
 * 실행: php hint-leasing-flyer/tests/test-display-helpers.php
 */
require_once __DIR__ . '/../includes/class-hlf-display-helpers.php';

$failures = 0;
function hlf_dh_assert( $label, $actual, $expected ) {
	global $failures;
	$ok = ( $actual === $expected );
	printf( "[%s] %s  (got=%s, expected=%s)\n", $ok ? 'PASS' : 'FAIL', $label, var_export( $actual, true ), var_export( $expected, true ) );
	if ( ! $ok ) {
		$failures++;
	}
}

// 지번주소가 메인, 도로명주소가 보조.
$r = hlf_format_address( '테헤란로 521', '삼성동 159-9' );
hlf_dh_assert( 'main is lot_address', $r['main'], '삼성동 159-9' );
hlf_dh_assert( 'sub is road_address', $r['sub'], '테헤란로 521' );

// "서울시 강남구"/"서울특별시 강남구" 접두어 제거.
$r = hlf_format_address( '서울특별시 강남구 테헤란로 521', '서울특별시 강남구 삼성동 159-9' );
hlf_dh_assert( 'strips 서울특별시 강남구 from main', $r['main'], '삼성동 159-9' );
hlf_dh_assert( 'strips 서울특별시 강남구 from sub', $r['sub'], '테헤란로 521' );

$r = hlf_format_address( '서울시 강남구 테헤란로 521', '서울시 강남구 삼성동 159-9' );
hlf_dh_assert( 'strips 서울시 강남구 from main', $r['main'], '삼성동 159-9' );

// 카카오 API가 실제로 내려주는 "서울 강남구"(특별시/시 없음) 형태 — 이전에는 하드코딩된 두
// 문자열("서울특별시 강남구 "/"서울시 강남구 ")만 지워서 이 형태는 안 지워지던 실제 버그였다.
$r = hlf_format_address( '역삼로 220', '서울 강남구 역삼동 825' );
hlf_dh_assert( 'strips 서울 강남구(no 시/특별시) from main', $r['main'], '역삼동 825' );

// 강남구가 아닌 다른 구/군도 하드코딩 없이 일반적으로 제거되어야 한다.
$r = hlf_format_address( '서초대로 1', '서울 서초구 서초동 1' );
hlf_dh_assert( 'strips 서초구(다른 구) from main', $r['main'], '서초동 1' );

// "시/도 시 구"처럼 중간에 다른 시가 끼는 경우(예: 경기도 성남시 분당구)도 마지막 구/군까지
// 통째로 지운다.
$r = hlf_format_address( '정자로 1', '경기도 성남시 분당구 정자동 100' );
hlf_dh_assert( 'strips multi-level 시/도+시+구 prefix', $r['main'], '정자동 100' );

// 구/군이 아예 없는 주소는 원문 그대로 남는다(안전한 기본값 — 잘못 잘리는 것보다 안전).
$r = hlf_format_address( '', '세종특별자치시 도담동 100' );
hlf_dh_assert( 'no 구/군 present -> address left unchanged', $r['main'], '세종특별자치시 도담동 100' );

// 지번주소가 없으면 도로명주소로 대체(sub는 비움).
$r = hlf_format_address( '테헤란로 521', '' );
hlf_dh_assert( 'falls back to road_address when lot_address empty', $r['main'], '테헤란로 521' );
hlf_dh_assert( 'sub empty when only one address present', $r['sub'], '' );

// 둘 다 없으면 빈 문자열.
$r = hlf_format_address( '', '' );
hlf_dh_assert( 'both empty -> main empty', $r['main'], '' );

// 둘이 같으면 sub는 비움(중복 표시 방지).
$r = hlf_format_address( '동일주소', '동일주소' );
hlf_dh_assert( 'identical road/lot -> sub empty', $r['sub'], '' );

// Accent color: 팔레트 순환, 0-based order.
hlf_dh_assert( 'order 0 color', hlf_item_accent_color( 0 ), '#355c73' );
hlf_dh_assert( 'order 1 color', hlf_item_accent_color( 1 ), '#a8582c' );
hlf_dh_assert( 'palette wraps around (order 8 == order 0)', hlf_item_accent_color( 8 ), hlf_item_accent_color( 0 ) );
hlf_dh_assert( 'palette wraps around (order 9 == order 1)', hlf_item_accent_color( 9 ), hlf_item_accent_color( 1 ) );

echo "\n";
if ( $failures ) {
	echo "{$failures} assertion(s) FAILED\n";
	exit( 1 );
}
echo "ALL DISPLAY-HELPER TESTS PASSED\n";
