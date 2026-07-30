<?php
/**
 * home-query.php의 순수 로직(지역 다양성 조정)과 회사정보 헬퍼를 WP 없이 검증한다.
 *
 * 복사본을 테스트하지 않고 실제 파일을 include한다 - include 시점에 필요한 WP 함수(add_action)만
 * 스텁으로 채우면 되고, 검증 대상 함수들(ol_apply_district_diversity 등)은 DB를 쓰지 않는다.
 * DB를 쓰는 ol_query_home_region_buildings()는 여기서 검증할 수 없다(실제 WP 필요).
 *
 * 실행: php tests/test-home-query.php
 */

define('ABSPATH', __DIR__ . '/');
// include 시점에 훅 등록만 통과시키기 위한 최소 스텁.
function add_action() { return true; }
function apply_filters($tag, $value) { return $value; }

require __DIR__ . '/../includes/home-query.php';
require __DIR__ . '/../includes/helpers.php';

$pass = 0;
$fail = 0;
function check($label, $got, $expected) {
    global $pass, $fail;
    if ($got === $expected) {
        $pass++;
        echo "[PASS] {$label}\n";
    } else {
        $fail++;
        echo "[FAIL] {$label}\n  got:      " . json_encode($got, JSON_UNESCAPED_UNICODE)
            . "\n  expected: " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n";
    }
}

// 헬퍼: 정렬된 행 배열 생성 (id => district)
function rows(array $pairs) {
    $out = [];
    foreach ($pairs as $id => $district) {
        $out[] = ['id' => $id, 'district' => $district, 'verified' => 0, 'modified' => ''];
    }
    return $out;
}

// ── 1. 한 동에 전부 몰린 경우: 상한(ceil(8/2)=4)까지만 앞으로 오고 나머지는 뒤로 밀린다 ──
// 삼성동(=1) 6개 + 역삼동(=2) 2개. 8개를 뽑으면 결국 전부 들어가지만 "순서"가 바뀌어야 한다.
$r = rows([101 => 1, 102 => 1, 103 => 1, 104 => 1, 105 => 1, 106 => 1, 201 => 2, 202 => 2]);
$got = ol_apply_district_diversity($r, 8);
check(
    '동일 지역 6개 + 다른 지역 2개 -> 상한 4개 이후 다른 지역이 먼저 배치',
    $got,
    [101, 102, 103, 104, 201, 202, 105, 106]
);

// ── 2. limit이 결과 수보다 작으면 다양성이 실제로 결과를 바꾼다 ──
$r = rows([101 => 1, 102 => 1, 103 => 1, 104 => 1, 105 => 1, 201 => 2]);
$got = ol_apply_district_diversity($r, 4);
check(
    'limit 4 - 한 지역이 절반(2)을 넘지 않고 다른 지역이 포함됨',
    $got,
    [101, 102, 201, 103]
);

// ── 3. 데이터가 부족하면 상한을 강제하지 않는다(빈 자리를 만들지 않음) ──
$r = rows([101 => 1, 102 => 1, 103 => 1]);
$got = ol_apply_district_diversity($r, 8);
check('한 지역에 3개뿐이면 상한을 강제하지 않고 3개 모두 반환', $got, [101, 102, 103]);

// ── 4. 세부지역 미배정(0)은 상한 대상이 아니다 ──
$r = rows([101 => 0, 102 => 0, 103 => 0, 104 => 0, 105 => 0, 106 => 0]);
$got = ol_apply_district_diversity($r, 4);
check('district 0(미배정)은 상한을 적용하지 않고 순서대로 채움', $got, [101, 102, 103, 104]);

// ── 5. limit 초과분은 잘린다 ──
$r = rows([101 => 1, 201 => 2, 301 => 3, 401 => 4, 501 => 5]);
$got = ol_apply_district_diversity($r, 3);
check('limit 3 - 정확히 3개만 반환', $got, [101, 201, 301]);

// ── 6. 중복 ID가 들어와도 한 번만 나간다(동일 빌딩 중복 금지) ──
$r = rows([101 => 1]);
$r[] = ['id' => 101, 'district' => 2, 'verified' => 0, 'modified' => ''];
$got = ol_apply_district_diversity($r, 8);
check('중복 ID 제거', $got, [101]);

// ── 7. 빈 입력 ──
check('빈 입력 -> 빈 배열', ol_apply_district_diversity([], 8), []);

// ── 8. 회사정보 단일 소스 ──
check('회사 등록번호', ol_company('license_number'), '11680-2026-00163');
check('회사 대표전화', ol_company('phone'), '02-553-5988');
check('회사 전체주소', ol_company('address_full'), '서울 강남구 언주로 550 청광빌딩 2층');
check('tel: 링크 정규화', ol_tel_href('02-553-5988'), '025535988');
check('없는 키는 빈 문자열', ol_company('nope'), '');

printf("\n%d passed, %d failed\n", $pass, $fail);
if ($fail > 0) {
    exit(1);
}
