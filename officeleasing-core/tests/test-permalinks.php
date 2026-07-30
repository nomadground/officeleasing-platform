<?php
/**
 * WP 없이 permalinks.php의 rewrite 정규식 "생성 로직"만 그대로 복제해 검증하는 오프라인 테스트.
 * 실제 WP_Rewrite 매칭 엔진 자체는 검증 못 하지만(그건 실제 WP 필요), 우리가 만든 정규식이
 * 의도한 샘플 경로에는 매칭되고, 무관한 경로는 안 건드리는지는 여기서 확인할 수 있다.
 *
 * 실행: php tests/test-permalinks.php
 */

function build_rules(array $parents, array $children) {
    $parent_alt = implode('|', array_map(function ($s) { return preg_quote($s, '#'); }, $parents));
    $rules = [];
    $rules['3seg'] = '#^(' . $parent_alt . ')/([^/]+)/([^/]+)/?$#u';
    if (!empty($children)) {
        $child_alt = implode('|', array_map(function ($s) { return preg_quote($s, '#'); }, $children));
        $rules['2seg_hub'] = '#^(' . $parent_alt . ')/(' . $child_alt . ')/?$#u';
    }
    $rules['2seg_building'] = '#^(' . $parent_alt . ')/([^/]+)/?$#u';
    $rules['1seg_hub'] = '#^(' . $parent_alt . ')/?$#u';
    return $rules;
}

// 매칭 우선순위 그대로 시뮬레이션: 순서대로 시도해서 첫 매칭을 채택.
function resolve(array $rules, $path) {
    $order = ['3seg', '2seg_hub', '2seg_building', '1seg_hub'];
    foreach ($order as $key) {
        if (!isset($rules[$key])) {
            continue;
        }
        if (preg_match($rules[$key], $path, $m)) {
            return [$key, $m];
        }
    }
    return [null, null];
}

$parents = ['강남사무실임대', '도심권사무실임대', '여의도사무실임대', '기타권역사무실임대'];
$children = ['역삼동', '논현동', '삼성동', '대치동', '신사동', '청담동', '종로구', '중구', '여의도', '영등포', '서초구', '성수동', '송파구', '용산구'];

$rules = build_rules($parents, $children);

$pass = 0;
$fail = 0;
function check($label, $condition) {
    global $pass, $fail;
    if ($condition) {
        $pass++;
    } else {
        $fail++;
        echo "FAIL: {$label}\n";
    }
}

// 1) 3단: 부모/자식/빌딩 -> building
[$type, $m] = resolve($rules, '강남사무실임대/삼성동/파르나스타워');
check('3-seg resolves to 3seg rule', '3seg' === $type);
check('3-seg building slug extracted', '파르나스타워' === ($m[3] ?? null));

// 2) 2단: 부모/자식(실제 동 이름) -> office_region 허브로 우선 해석
[$type, $m] = resolve($rules, '강남사무실임대/삼성동');
check('2-seg known child resolves to hub rule (not building)', '2seg_hub' === $type);
check('2-seg hub child slug extracted', '삼성동' === ($m[2] ?? null));

// 3) 2단: 부모/빌딩명(자식 term 아님) -> building fallback
[$type, $m] = resolve($rules, '강남사무실임대/파르나스타워');
check('2-seg unknown second segment resolves to building fallback', '2seg_building' === $type);
check('2-seg building slug extracted', '파르나스타워' === ($m[2] ?? null));

// 4) 1단: 부모만 -> office_region 허브
[$type, $m] = resolve($rules, '강남사무실임대');
check('1-seg resolves to parent hub rule', '1seg_hub' === $type);

// 5) 다른 권역의 자식 슬러그: 여의도사무실임대/영등포 -> hub
[$type, $m] = resolve($rules, '여의도사무실임대/영등포');
check('YBD child slug resolves to hub rule', '2seg_hub' === $type);

// 6) 무관한 경로는 전혀 매칭되지 않아야 함(다른 라우트를 침범하지 않음이 핵심 안전장치)
[$type] = resolve($rules, 'building/파르나스타워');
check('unrelated /building/{slug} path does not match any custom rule', null === $type);

[$type] = resolve($rules, '사무실임대');
check('archive slug alone does not match parent alternation', null === $type);

[$type] = resolve($rules, 'about-us');
check('unrelated static page slug does not match', null === $type);

// 7) 중첩된 3단(중간 세그먼트가 실제 term인지 여부와 무관하게 매물 slug만 추출) - 의도된 느슨한 매칭
[$type, $m] = resolve($rules, '강남사무실임대/논현동/삼성동');
check('3-seg ignores middle-segment validity, extracts trailing slug as building', '삼성동' === ($m[3] ?? null));

printf("\n%d passed, %d failed\n", $pass, $fail);
if ($fail > 0) {
    exit(1);
}
