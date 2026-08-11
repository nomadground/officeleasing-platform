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
// olt_won_html()이 WP 코어 함수 esc_html()을 쓰는데, 이 테스트는 WP 부트스트랩 없이 theme-helpers.php를
// 단독 로드하므로 최소 폴백을 직접 정의한다(실제 사이트에선 항상 진짜 esc_html()이 로드되어 있다 -
// 여기 htmlspecialchars(ENT_QUOTES)는 이 파일에서 검증하는 순수 숫자/한글 문자열엔 동작이 동일하다).
if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES);
    }
}
// olt_area_slider()가 esc_attr()도 쓴다(data-listing-index) - 같은 이유로 최소 폴백을 둔다.
if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES);
    }
}
// olt_format_area_sqm_pyeong()이 Core의 ol_calc_sqm_from_pyeong()(순수 계산 함수, ABSPATH 가드 없음)을
// 호출하므로 theme-helpers.php보다 먼저 requires - test-schema.php가 schema.php를 위해 하는 것과 동일 이유.
require __DIR__ . '/../../officeleasing-core/includes/helpers.php';
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

// ── olt_format_area_sqm_pyeong() - building-card.php 전용, 괄호 없는 sqm/pyeong 한 쌍 (주/보조는 호출부 책임) ──
$r = olt_format_area_sqm_pyeong(0, 0);
check('area sqm+pyeong - 둘 다 0(데이터 없음) - sqm', $r['sqm'], '');
check('area sqm+pyeong - 둘 다 0(데이터 없음) - pyeong', $r['pyeong'], '');
$r = olt_format_area_sqm_pyeong(327, 327);
check('area sqm+pyeong - 단일값 - sqm(327평→㎡ 환산)', $r['sqm'], '1,081.0㎡');
check('area sqm+pyeong - 단일값 - pyeong(괄호 없음)', $r['pyeong'], '327평');
$r = olt_format_area_sqm_pyeong(298, 342);
check('area sqm+pyeong - 범위 - sqm(괄호 없음)', $r['sqm'], '985.1㎡ ~ 1,130.6㎡');
check('area sqm+pyeong - 범위 - pyeong(괄호 없음, min~max)', $r['pyeong'], '298평 ~ 342평');

// ── olt_won_html() - building-card.php 전용, 숫자/단위 span 분리 (각 부분 esc_html 처리됨) ──
check(
    'won html - 단일값 - 숫자와 만원을 별도 span으로, pair로 감쌈(줄바꿈은 ~에서만)',
    olt_won_html('252,250만원'),
    '<span class="olx-money-pair"><span class="olx-money-num">252,250</span><span class="olx-money-unit">만원</span></span>'
);
check(
    'won html - 범위 - 양쪽 다 pair로 분리, 구분자는 그대로(pair 밖에 있어야 그 자리서 줄바꿈 가능)',
    olt_won_html('50만원 ~ 100만원'),
    '<span class="olx-money-pair"><span class="olx-money-num">50</span><span class="olx-money-unit">만원</span></span> ~ <span class="olx-money-pair"><span class="olx-money-num">100</span><span class="olx-money-unit">만원</span></span>'
);
check('won html - 빈 문자열(데이터 없음) - 그대로 빈 문자열', olt_won_html(''), '');

// ── olt_floor_range() / olt_floor_label() ──
check('floor - 단일 지상층', olt_floor_range(17, 17), '17층');
check('floor - 단일 지하층', olt_floor_range(-2, -2), '지하2층');
check('floor - 지상 범위(단위 한 번만)', olt_floor_range(3, 7), '3~7층');
check('floor - 지하/지상 혼합', olt_floor_range(-1, 7), '지하1층~7층');
check('floor - 지하끼리(얕은 지하 먼저)', olt_floor_range(-3, -1), '지하1층~지하3층');
check('floor - 둘 다 0(데이터 없음)', olt_floor_range(0, 0), '');

// ── olt_floor_tier() - 총 층수 대비 상/중/하위 1/3로 단순화 ──
check('floor tier - 상위 1/3(40층 중 35층)', olt_floor_tier('35층', 40), '고층');
check('floor tier - 경계값(40층 중 27층, 27/40=0.675>2/3)', olt_floor_tier('27층', 40), '고층');
check('floor tier - 중간(40층 중 20층)', olt_floor_tier('20층', 40), '중층');
check('floor tier - 하위 1/3(40층 중 10층)', olt_floor_tier('10층', 40), '저층');
check('floor tier - 지하는 항상 지하(등급 없음)', olt_floor_tier('지하2층', 40), '지하');
check('floor tier - 파싱 불가(범위 표기)는 원문 그대로', olt_floor_tier('3~5층', 40), '3~5층');
check('floor tier - 총 층수 모르면(0) 원문 그대로', olt_floor_tier('17층', 0), '17층');

// ── olt_pyeong() - [listing-detail-ux-pass4] 괄호 제거 요청("자꾸 면적에 평을 괄호안에 넣는데") ──
check('pyeong - 정상값은 괄호 없이', olt_pyeong(327), '327평');
check('pyeong - 소수는 반올림(number_format 기본)', olt_pyeong(226.7), '227평');
check('pyeong - 0/빈값은 빈 문자열', olt_pyeong(0), '');
check('pyeong - null도 빈 문자열', olt_pyeong(null), '');

// ── olt_area_slider() - [listing-detail-ux-pass4] "면적 슬라이더" 점-선 UI ──
check('area slider - 빈 배열이면 빈 문자열(마크업 자체를 안 만듦)', olt_area_slider(array(), 0), '');

$single_stop = array( array(
	'index' => 0, 'lease_pyeong' => '363평', 'lease_sqm' => '1,200.0㎡',
	'exclusive_pyeong' => '227평', 'exclusive_sqm' => '750.4㎡',
) );
$single_html = olt_area_slider($single_stop, 0);
check('area slider - 매물 1건은 --single 수정자 클래스', str_contains($single_html, 'olx-area-slider--single'), true);
check('area slider - 매물 1건은 최소/최대 라벨 없음(비교 대상 없음)', str_contains($single_html, 'olx-area-slider-end'), false);
check('area slider - 매물 1건짜리 점도 기본 active', str_contains($single_html, 'olx-area-slider-dot is-active'), true);
check('area slider - 임대면적 값 포함', str_contains($single_html, '363평'), true);
check('area slider - 전용면적 값 포함', str_contains($single_html, '227평'), true);
// [listing-detail-ux-pass5] "좌측 상단 임대면적, 좌측하단 전용면적" 축 라벨 요청 - 매물 1건이어도(비교
// 대상이 없어 최소/최대 라벨은 빠지지만) 축 라벨은 항상 나온다.
check('area slider - 축 라벨(임대면적) 항상 포함', str_contains($single_html, 'olx-area-slider-axis-lease">임대면적'), true);
check('area slider - 축 라벨(전용면적) 항상 포함', str_contains($single_html, 'olx-area-slider-axis-exclusive">전용면적'), true);

$multi_stops = array(
	array('index' => 2, 'lease_pyeong' => '200평', 'lease_sqm' => '661.2㎡', 'exclusive_pyeong' => '121평', 'exclusive_sqm' => '400.0㎡'),
	array('index' => 1, 'lease_pyeong' => '280평', 'lease_sqm' => '925.6㎡', 'exclusive_pyeong' => '170평', 'exclusive_sqm' => '562.0㎡'),
	array('index' => 0, 'lease_pyeong' => '363평', 'lease_sqm' => '1,200.0㎡', 'exclusive_pyeong' => '227평', 'exclusive_sqm' => '750.4㎡'),
);
$multi_html = olt_area_slider($multi_stops, 1);
check('area slider - 매물 2건 이상은 최소/최대 라벨 있음', substr_count($multi_html, 'olx-area-slider-end'), 2);
check('area slider - --single 클래스 없음', str_contains($multi_html, 'olx-area-slider--single'), false);
// 정렬은 호출부 책임(이 함수는 넘겨받은 순서 그대로 그린다) - index=1(두 번째 넘긴 stop)이 기본 활성.
check('area slider - 넘겨받은 순서 그대로 렌더(정렬은 호출부 책임) - 첫 번째 점의 인덱스',
	strpos($multi_html, 'data-listing-index="2"') < strpos($multi_html, 'data-listing-index="1"'), true);
// 마크업은 항상 class="olx-area-slider-dot[ is-active]" 다음에 data-listing-index가 오므로
// (olt_area_slider() 내부 순서 고정) 이 순서를 그대로 정규식으로 확인한다.
check('area slider - default_index=1인 점만 is-active',
	preg_match('/class="olx-area-slider-dot is-active"[^>]*data-listing-index="1"/', $multi_html) === 1, true);
check('area slider - default_index=1이 아닌 점(index=2)은 is-active 없음',
	preg_match('/class="olx-area-slider-dot"[^>]*data-listing-index="2"/', $multi_html) === 1, true);

printf("\n%d passed, %d failed\n", $pass, $fail);
if ($fail > 0) {
    exit(1);
}
