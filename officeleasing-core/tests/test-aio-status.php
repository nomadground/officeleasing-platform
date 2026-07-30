<?php
/**
 * ol_sync_aio_status()의 상태 전이(생성방식/검수상태 분리, Sprint 01.5 3-6)를 WP 없이 검증한다.
 * 실제 calculations.php를 include하고, get_field/update_field/get_post_meta/update_post_meta를
 * 이 파일 안의 인메모리 스텁으로 대체해 진짜 함수 로직을 그대로 실행시킨다.
 *
 * 실행: php tests/test-aio-status.php
 */

define('ABSPATH', __DIR__ . '/');

$GLOBALS['__fields'] = [];
$GLOBALS['__meta'] = [];
$GLOBALS['__titles'] = ['1' => '파르나스타워'];

function get_field($name, $post_id) {
    return $GLOBALS['__fields'][$post_id][$name] ?? '';
}
function update_field($name, $value, $post_id) {
    $GLOBALS['__fields'][$post_id][$name] = $value;
}
function get_post_meta($post_id, $key, $single = false) {
    return $GLOBALS['__meta'][$post_id][$key] ?? '';
}
function update_post_meta($post_id, $key, $value) {
    $GLOBALS['__meta'][$post_id][$key] = $value;
}
function get_the_title($post_id) {
    return $GLOBALS['__titles'][$post_id] ?? '';
}

require __DIR__ . '/../includes/calculations.php';

$pass = 0;
$fail = 0;
function check($label, $got, $expected) {
    global $pass, $fail;
    if ($got === $expected) {
        $pass++;
        echo "[PASS] {$label}\n";
    } else {
        $fail++;
        echo "[FAIL] {$label}\n  got:      " . json_encode($got, JSON_UNESCAPED_UNICODE) . "\n  expected: " . json_encode($expected, JSON_UNESCAPED_UNICODE) . "\n";
    }
}

function f($id, $name) {
    return $GLOBALS['__fields'][$id][$name] ?? '';
}

// ── 1. 빈 상태에서 최초 저장 -> 초안 자동 삽입 + auto_generated/pending ──
ol_sync_aio_status(1);
check('최초 저장: building_location_summary가 채워짐', !empty(f(1, 'building_location_summary')), true);
check('최초 저장: 생성방식 auto_generated', f(1, 'aio_generation_status'), 'auto_generated');
check('최초 저장: 검수상태 pending', f(1, 'aio_review_status'), 'pending');
$draft_text = f(1, 'building_location_summary');

// ── 2. 아무것도 안 건드리고 재저장(예: 다른 필드 수정) -> 상태 유지, pending 그대로 ──
ol_sync_aio_status(1);
check('재저장(미편집): 생성방식 여전히 auto_generated', f(1, 'aio_generation_status'), 'auto_generated');
check('재저장(미편집): 검수상태 여전히 pending(강제로 안 건드림)', f(1, 'aio_review_status'), 'pending');

// ── 3. [3-6 핵심] 관리자가 텍스트는 그대로 두고 검수상태만 select에서 '검수완료'로 바꿈 ──
// (실제 화면에서는 ACF select 필드를 직접 조작하는 것과 동일 - update_field를 직접 호출해 시뮬레이션)
update_field('aio_review_status', 'reviewed', 1);
ol_sync_aio_status(1); // 텍스트는 안 바꾼 채 다시 저장(다른 필드 수정 등)
check('초안 그대로 + 검수완료 승인: 생성방식 auto_generated 유지', f(1, 'aio_generation_status'), 'auto_generated');
check('초안 그대로 + 검수완료 승인: 검수상태가 되돌아가지 않음(pending으로 리셋 안 됨)', f(1, 'aio_review_status'), 'reviewed');

// ── 4. 관리자가 실제로 문장을 수정 -> human_written + reviewed로 전환, 검수완료 유지 여부와 무관 ──
update_field('building_location_summary', '완전히 새로 쓴 입지 설명입니다.', 1);
ol_sync_aio_status(1);
check('직접 수정 후: 생성방식 human_written', f(1, 'aio_generation_status'), 'human_written');
check('직접 수정 후: 검수상태 reviewed(작성 자체가 검수)', f(1, 'aio_review_status'), 'reviewed');

// ── 5. 신규 빌딩, 관리자가 처음부터 직접 텍스트를 입력(자동초안을 거치지 않음) ──
update_field('building_location_summary', '처음부터 관리자가 쓴 문장.', 2);
$GLOBALS['__titles'][2] = '테스트빌딩2';
ol_sync_aio_status(2);
check('자동초안 없이 직접 입력: human_written', f(2, 'aio_generation_status'), 'human_written');
check('자동초안 없이 직접 입력: reviewed', f(2, 'aio_review_status'), 'reviewed');

printf("\n%d passed, %d failed\n", $pass, $fail);
if ($fail > 0) {
    exit(1);
}
