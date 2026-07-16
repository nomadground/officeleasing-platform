<?php
/**
 * hlf-calculations 단독 실행 테스트 (WP 부트스트랩 불필요).
 * 실행: php hint-leasing-flyer/tests/test-calculations.php
 * officeleasing-core의 tests/test-calculations.php와 같은 방식(순수 함수 검증).
 */
require_once __DIR__ . '/../includes/class-hlf-calculations.php';

$failures = 0;
function hlf_assert( $label, $actual, $expected, $eps = 0.05 ) {
	global $failures;
	$ok = is_numeric( $expected ) ? ( abs( (float) $actual - (float) $expected ) <= $eps ) : ( $actual === $expected );
	printf( "[%s] %s  (got=%s, expected=%s)\n", $ok ? 'PASS' : 'FAIL', $label, var_export( $actual, true ), var_export( $expected, true ) );
	if ( ! $ok ) {
		$failures++;
	}
}

// ㎡ → 평
hlf_assert( 'sqm_to_pyeong 132.2㎡', hlf_sqm_to_pyeong( 132.2 ), 39.99 );
hlf_assert( 'sqm_to_pyeong 0', hlf_sqm_to_pyeong( 0 ), 0.0 );
hlf_assert( 'sqm_to_pyeong negative', hlf_sqm_to_pyeong( -5 ), 0.0 );

// NOC = ((보증금 × 0.035 ÷ 12) + 임대료 + 관리비) ÷ 전용평
// 보증금 3000만, 임대료 310만, 관리비 60만, 전용 132.2㎡(=39.99평)
// 이자 = 3000*0.035/12 = 8.75 ; 월실효 = 8.75+310+60 = 378.75 ; /39.99 ≈ 9.47
$exclusive_pyeong = hlf_sqm_to_pyeong( 132.2 );
hlf_assert( 'noc 3000/310/60', hlf_calculate_noc( 3000, 310, 60, $exclusive_pyeong ), 9.47, 0.1 );
hlf_assert( 'noc zero exclusive', hlf_calculate_noc( 3000, 310, 60, 0 ), 0.0 );

// 보증금 1억(10000만), 임대료 1000만, 관리비 150만, 전용 257.42㎡
$ep2 = hlf_sqm_to_pyeong( 257.42 ); // ≈ 77.87평
// 이자 = 10000*0.035/12 = 29.1667 ; 월실효 = 29.1667+1000+150 = 1179.1667 ; /77.87 ≈ 15.14
hlf_assert( 'noc 10000/1000/150', hlf_calculate_noc( 10000, 1000, 150, $ep2 ), 15.14, 0.15 );

// 공급평당 지표
$lease_pyeong = hlf_sqm_to_pyeong( 168.6 ); // ≈ 51.0평
hlf_assert( 'rent_per_lease_pyeong 310', hlf_calculate_rent_per_lease_pyeong( 310, $lease_pyeong ), round( 310 / $lease_pyeong, 1 ) );
hlf_assert( 'deposit_per_lease_pyeong 3000', hlf_calculate_deposit_per_lease_pyeong( 3000, $lease_pyeong ), round( 3000 / $lease_pyeong, 1 ) );

// item 묶음 계산
$metrics = hlf_calculate_item_metrics( array(
	'deposit_manwon'         => 3000,
	'monthly_rent_manwon'    => 310,
	'maintenance_fee_manwon' => 60,
	'lease_area_sqm'         => 168.6,
	'exclusive_area_sqm'     => 132.2,
) );
hlf_assert( 'item metrics noc', $metrics['noc'], 9.47, 0.1 );
hlf_assert( 'item metrics lease_pyeong', $metrics['lease_pyeong'], 51.0, 0.1 );

// 주차 정규화
hlf_assert( 'parking "자주식 10대"', hlf_normalize_parking_available( '자주식 10대' ), true );
hlf_assert( 'parking "주차 가능"', hlf_normalize_parking_available( '주차 가능' ), true );
hlf_assert( 'parking "기계식"', hlf_normalize_parking_available( '기계식' ), true );
hlf_assert( 'parking "주차 불가"', hlf_normalize_parking_available( '주차 불가' ), false );
hlf_assert( 'parking "없음"', hlf_normalize_parking_available( '없음' ), false );
hlf_assert( 'parking empty', hlf_normalize_parking_available( '' ), false );

echo "\n";
if ( $failures ) {
	printf( "%d test(s) FAILED\n", $failures );
	exit( 1 );
}
echo "ALL TESTS PASSED\n";
exit( 0 );
