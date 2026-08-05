<?php
/**
 * theme-helpers.php의 순수 포맷 함수(범위/층수 표기)를 WP 없이 검증한다.
 * theme-helpers.php 자체는 defined('ABSPATH') || exit; 가드가 있어 WP 밖에서 그냥 require하면
 * 아무 것도 안 하고 즉시 끝나버린다 - 이 테스트 파일이 ABSPATH를 먼저 정의해 우회한다
 * (officeleasing-core/tests/test-calculations.php가 helpers.php를 직접 require하는 것과
 * 같은 취지 - 다만 그쪽 helpers.php는 애초에 ABSPATH 가드 자체를 안 건다는 차이가 있다).
 *
 * olt_won()/olt_pyeong() 등 이 파일이 감싸는 플러그인 함수(ol_format_manwon 등)는 실제 WP+플러그인
 * 없이는 로드되지 않으므로, 아래 테스트는 그 함수들이 폴백 포맷(간단한 number_format)으로
 * 동작한다는 전제로 결과를 검증한다 - 실제 서식(만원 단위 등)은 officeleasing-core 쪽 테스트가 커버한다.
 *
 * 실행: php tests/test-theme-helpers.php
 */

define('ABSPATH', __DIR__ . '/');
require __DIR__ . '/../inc/theme-helpers.php';

$pass = 0;
$fail = 0;
function check($label, $actual, $expected) {
    global $pass, $fail;
    $ok = $actual === $expected;
    if ($ok) {
        $pass++;
        echo "[PASS] $label\n";
    } else {
        $fail++;
        printf("[FAIL] %s -> got=%s expected=%s\n", $label, var_export($actual, true), var_export($expected, true));
    }
}

// ── olt_format_range() - 면적 전용(0 = 데이터 없음이 실제로 성립하는 필드) ──
$pyeong = function ($v) { return number_format((float) $v) . '평'; };
check('area range - 둘 다 0(데이터 없음)', olt_format_range(0, 0, $pyeong), '');
check('area range - 단일값(min==max)', olt_format_range(327, 327, $pyeong), '327평');
check('area range - min만 있음(max 0)', olt_format_range(298, 0, $pyeong), '298평');
check('area range - 정상 범위(포맷 콜백을 값마다 그대로 적용)', olt_format_range(298, 342, $pyeong), '298평 ~ 342평');

// ── olt_format_money_range() - 금액 전용(3차 리뷰: null/false/''를 -1보다 먼저 걸러야 함) ──
$won = function ($v) { return number_format((float) $v / 10000) . '만원'; };
check('money range - null/null -> 데이터 없음', olt_format_money_range(null, null, $won), '');
check('money range - false/false -> 데이터 없음', olt_format_money_range(false, false, $won), '');
check("money range - ''/'' -> 데이터 없음", olt_format_money_range('', '', $won), '');
check('money range - -1/-1(캐시 sentinel) -> 데이터 없음', olt_format_money_range(-1, -1, $won), '');
check("money range - 문자열 '0'/'0' -> 유효한 0원", olt_format_money_range('0', '0', $won), '0만원');
check('money range - 0/0(숫자) -> 유효한 0원', olt_format_money_range(0, 0, $won), '0만원');
check('money range - 0/500000 -> 범위에 0원 포함', olt_format_money_range(0, 500000, $won), '0만원 ~ 50만원');
check('money range - 500000/500000 -> 단일값', olt_format_money_range(500000, 500000, $won), '50만원');
check('money range - 500000/1000000 -> 정상 범위', olt_format_money_range(500000, 1000000, $won), '50만원 ~ 100만원');
check('money range - min만 없음(하나만 결측이어도 데이터 없음 처리)', olt_format_money_range(null, 500000, $won), '');

// ── olt_floor_range() / olt_floor_label() ──
check('floor - 단일 지상층', olt_floor_range(17, 17), '17층');
check('floor - 단일 지하층', olt_floor_range(-2, -2), '지하2층');
check('floor - 지상 범위(단위 한 번만)', olt_floor_range(3, 7), '3~7층');
check('floor - 지하/지상 혼합', olt_floor_range(-1, 7), '지하1층~7층');
check('floor - 지하끼리(얕은 지하 먼저)', olt_floor_range(-3, -1), '지하1층~지하3층');
check('floor - 둘 다 0(데이터 없음)', olt_floor_range(0, 0), '');

printf("\n%d passed, %d failed\n", $pass, $fail);
if ($fail > 0) {
    exit(1);
}
