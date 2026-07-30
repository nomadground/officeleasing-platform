<?php
// WP 부트스트랩 없이 순수 PHP로 실행: php tests/test-calculations.php
// helpers.php의 ol_calc_*/ol_format_* 함수가 워드프레스에 의존하지 않기 때문에 가능하다.
require __DIR__ . '/../includes/helpers.php';

function ol_assert($label, $actual, $expected, $tolerance = 0.5) {
    $ok = is_numeric($expected) ? abs($actual - $expected) <= $tolerance : $actual === $expected;
    printf("[%s] %s -> got=%s expected=%s\n", $ok ? 'PASS' : 'FAIL', $label, $actual, $expected);
    return $ok;
}

$fail = 0;

// 파르나스타워 프로토타입 실측값 기준 (기존 handoff HTML과 대조)
$fail += !ol_assert('전용 327평 -> ㎡', ol_calc_sqm_from_pyeong(327), 1081.0, 1) ? 1 : 0;
$fail += !ol_assert('공급 616평 -> ㎡', ol_calc_sqm_from_pyeong(616), 2036.4, 1) ? 1 : 0;

// 건물 연면적/기준층면적: ㎡가 원본 입력, 평은 역산
$fail += !ol_assert('연면적 1081㎡ -> 평', ol_calc_pyeong_from_sqm(1081.0), 327.0, 0.5) ? 1 : 0;
$fail += !ol_assert('기준층면적 2036.4㎡ -> 평', ol_calc_pyeong_from_sqm(2036.4), 616.0, 0.5) ? 1 : 0;

// 보증금/임대료/관리비: 만원 단위 단일 입력 -> 원
$deposit = ol_calc_won_from_manwon(156838); // 15억 6,838만원 = 156,838만원
$fail += !ol_assert('보증금 156838만원 -> 원', $deposit, 1568380000, 0) ? 1 : 0;

$rent = ol_calc_won_from_manwon(13070); // 1억 3,070만원 = 13,070만원
$fail += !ol_assert('임대료 13070만원 -> 원', $rent, 130700000, 0) ? 1 : 0;

$fee = ol_calc_won_from_manwon(3638);
$fail += !ol_assert('관리비 3638만원 -> 원', $fee, 36380000, 0) ? 1 : 0;

$total = ol_calc_monthly_total_cost($rent, $fee);
$fail += !ol_assert('월 총비용', $total, 167080000, 0) ? 1 : 0;

$fail += !ol_assert('공급평당 임대료(원)', ol_calc_per_pyeong($rent, 616), 212175, 100) ? 1 : 0;
$fail += !ol_assert('공급평당 관리비(원)', ol_calc_per_pyeong($fee, 616), 59058, 50) ? 1 : 0;
$fail += !ol_assert('전용평당 NOC(원)', ol_calc_per_pyeong($total, 327), 510948, 200) ? 1 : 0;
$fail += !ol_assert('전용평당 보증금(원)', ol_calc_per_pyeong($deposit, 327), 4796269, 0) ? 1 : 0;

// 방어 로직: 0/음수/비정상값
$fail += !ol_assert('0평 나눗셈 방어', ol_calc_per_pyeong(1000000, 0), 0, 0) ? 1 : 0;
$fail += !ol_assert('음수 평 나눗셈 방어', ol_calc_per_pyeong(1000000, -10), 0, 0) ? 1 : 0;
$fail += !ol_assert('음수 만원 방어(0으로 처리)', ol_calc_won_from_manwon(-100), 0, 0) ? 1 : 0;
$fail += !ol_assert('음수 평 -> sqm 방어', ol_calc_sqm_from_pyeong(-50), 0, 0) ? 1 : 0;
$fail += !ol_assert('음수 sqm -> 평 방어', ol_calc_pyeong_from_sqm(-50), 0, 0) ? 1 : 0;
$fail += !ol_assert('safe_divide 0분모', ol_safe_divide(100, 0), 0, 0) ? 1 : 0;
$fail += !ol_assert('safe_divide 음수분모', ol_safe_divide(100, -5), 0, 0) ? 1 : 0;

// 화면 표기 포맷 함수 - 억 단위로 안 쪼개고 항상 만원 콤마표기로 통일
$fail += !ol_assert('보증금 표기(만원 통일)', ol_format_manwon($deposit), '156,838만원', 0) ? 1 : 0;
$fail += !ol_assert('임대료 표기(만원 통일)', ol_format_manwon($rent), '13,070만원', 0) ? 1 : 0;
$fail += !ol_assert('관리비 표기(만원 통일)', ol_format_manwon($fee), '3,638만원', 0) ? 1 : 0;
$fail += !ol_assert('작은 금액 표기 예시', ol_format_manwon(15320000), '1,532만원', 0) ? 1 : 0;
$fail += !ol_assert('전용평당 보증금 표기', ol_format_krw_per_pyeong(ol_calc_per_pyeong($deposit, 327)), '479.6만원', 0) ? 1 : 0;
$fail += !ol_assert('공급평당 임대료 표기', ol_format_krw_per_pyeong(ol_calc_per_pyeong($rent, 616)), '21.2만원', 0) ? 1 : 0;
$fail += !ol_assert('전용평당 NOC 표기', ol_format_krw_per_pyeong(ol_calc_per_pyeong($total, 327)), '51.1만원', 0) ? 1 : 0;

echo $fail === 0 ? "\n전체 통과\n" : "\n실패 {$fail}건\n";
exit($fail === 0 ? 0 : 1);
