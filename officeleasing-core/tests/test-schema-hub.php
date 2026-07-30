<?php
/**
 * schema-hub.php의 노드 조립 함수를 WP 없이 검증한다. add_action/wp_head 배관은 제외하고,
 * 실제 그래프 조립 함수(ol_schema_hub_collection_node 등)를 직접 호출해 archive/parent-hub/child-hub
 * 세 가지 컨텍스트에서 CollectionPage/ItemList/BreadcrumbList/FAQPage가 올바르게 구성되는지 확인한다.
 *
 * 실행: php tests/test-schema-hub.php
 */

define('ABSPATH', __DIR__ . '/');

$GLOBALS['__is_tax'] = false;
$GLOBALS['__queried_object'] = null;
$GLOBALS['__fields'] = [];      // [term_id or object][field_name] => value
$GLOBALS['__wp_query_posts'] = [];
$GLOBALS['__found_posts'] = 0;

class WP_Post_Stub {
    public $ID;
    public $post_type;
    public $post_title;
    public function __construct($id, $post_type, $title) {
        $this->ID = $id;
        $this->post_type = $post_type;
        $this->post_title = $title;
    }
}
class WP_Term_Stub {
    public $term_id;
    public $parent;
    public $name;
    public function __construct($id, $parent, $name) {
        $this->term_id = $id;
        $this->parent = $parent;
        $this->name = $name;
    }
}
// PHP의 실제 WP_Post 클래스가 없으므로 instanceof 체크를 대체할 별칭을 만든다.
class_alias('WP_Post_Stub', 'WP_Post');

function is_post_type_archive($pt) {
    return !$GLOBALS['__is_tax'];
}
function is_tax($tax) {
    return $GLOBALS['__is_tax'];
}
function is_paged() {
    return false;
}
function apply_filters($tag, $value) {
    return $value;
}
function add_action() {
    return true;
}
function get_query_var($key) {
    return 0;
}
function get_pagenum_link($paged, $escape = true) {
    return 'https://officeleasing.co.kr/' . $GLOBALS['__current_path'] . '/';
}
function home_url($path = '/') {
    return 'https://officeleasing.co.kr' . $path;
}
function trailingslashit($s) {
    return rtrim($s, '/') . '/';
}
function ol_schema_home_url() {
    return trailingslashit(home_url('/'));
}
function get_queried_object() {
    return $GLOBALS['__queried_object'];
}
function is_wp_error($thing) {
    return false;
}
function get_field($name, $context) {
    $key = is_object($context) ? $context->term_id : $context;
    return $GLOBALS['__fields'][$key][$name] ?? '';
}
function get_term($id, $tax) {
    return $GLOBALS['__terms_by_id'][$id] ?? null;
}
function get_term_link($term) {
    return 'https://officeleasing.co.kr/' . $term->name . '/';
}
function get_post_type_archive_link($pt) {
    return 'https://officeleasing.co.kr/사무실임대/';
}
function get_permalink($post) {
    return 'https://officeleasing.co.kr/building/' . $post->ID . '/';
}
function get_the_title($post) {
    return $post->post_title;
}
function ol_default_archive_intro() {
    return '기본 아카이브 인트로 문장';
}
function ol_default_archive_faqs() {
    return [['q' => '기본 질문1', 'a' => '기본 답변1']];
}
function ol_default_region_intro($label) {
    return $label . ' 기본 지역 인트로';
}

require __DIR__ . '/../includes/schema-hub.php';

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

// ══════════════════════════ 1. 전체 아카이브(/사무실임대/) ══════════════════════════
$GLOBALS['__is_tax'] = false;
$current_url = 'https://officeleasing.co.kr/사무실임대/';

$collection = ol_schema_hub_collection_node($current_url, false);
check('아카이브 CollectionPage @type', $collection['@type'], 'CollectionPage');
check('아카이브 CollectionPage description은 ol_default_archive_intro', $collection['description'], '기본 아카이브 인트로 문장');
check('아카이브 CollectionPage mainEntity가 itemlist 참조', $collection['mainEntity'], ['@id' => $current_url . '#itemlist']);

$breadcrumb = ol_schema_hub_breadcrumb_node($current_url, false);
check('아카이브 breadcrumb 2단계(홈>사무실 임대)', count($breadcrumb['itemListElement']), 2);
check('아카이브 breadcrumb 첫 항목은 홈', $breadcrumb['itemListElement'][0]['name'], '홈');
check('아카이브 breadcrumb 마지막 항목은 사무실 임대', $breadcrumb['itemListElement'][1]['name'], '사무실 임대');

$faq = ol_schema_hub_faq_node($current_url, false);
check('아카이브 FAQPage는 기본 FAQ 사용', $faq['mainEntity'][0]['name'], '기본 질문1');

// ItemList: $wp_query->posts 시뮬레이션
global $wp_query;
$wp_query = new stdClass();
$wp_query->posts = [
    new WP_Post_Stub(101, 'building', '파르나스타워'),
    new WP_Post_Stub(102, 'building', '테헤란로빌딩'),
];
$wp_query->found_posts = 27; // 페이지네이션 전체 개수(현재 페이지엔 2개만 있어도 됨)
$item_list = ol_schema_hub_item_list_node($current_url);
check('ItemList @type', $item_list['@type'], 'ItemList');
check('ItemList numberOfItems는 전체 개수(found_posts)', $item_list['numberOfItems'], 27);
check('ItemList itemListElement는 현재 페이지 것만(2개)', count($item_list['itemListElement']), 2);
check('ItemList 첫 항목 position', $item_list['itemListElement'][0]['position'], 1);
check('ItemList 첫 항목 url', $item_list['itemListElement'][0]['url'], 'https://officeleasing.co.kr/building/101/');

// building이 아닌 post가 섞여 있어도 제외되어야 한다(방어적 필터)
$wp_query->posts[] = (function () {
    $p = new WP_Post_Stub(999, 'post', '무관한글');
    return $p;
})();
$item_list2 = ol_schema_hub_item_list_node($current_url);
check('post_type이 building이 아닌 항목은 ItemList에서 제외', count($item_list2['itemListElement']), 2);

// posts가 비어있으면 ItemList 자체를 만들지 않는다(빈 목록을 스키마로 지어내지 않음)
$wp_query->posts = [];
check('posts 없으면 ItemList 없음', ol_schema_hub_item_list_node($current_url), null);

// ══════════════════════════ 2. 권역 허브(부모 term, 예: 강남사무실임대) ══════════════════════════
$GLOBALS['__is_tax'] = true;
$parent_term = new WP_Term_Stub(1, 0, '강남사무실임대');
$GLOBALS['__queried_object'] = $parent_term;
$GLOBALS['__terms_by_id'] = [1 => $parent_term];
$current_url_parent = 'https://officeleasing.co.kr/강남사무실임대/';

$collection_parent = ol_schema_hub_collection_node($current_url_parent, true);
check('상위 권역 CollectionPage name은 원본 term 이름 그대로(접미사 없음)', $collection_parent['name'], '강남사무실임대');
check('region_intro 없으면 ol_default_region_intro 폴백', $collection_parent['description'], '강남사무실임대 기본 지역 인트로');

$GLOBALS['__fields'][1]['region_intro'] = '강남 권역 실제 소개 문장';
$collection_parent2 = ol_schema_hub_collection_node($current_url_parent, true);
check('region_intro ACF 필드가 있으면 그것을 사용', $collection_parent2['description'], '강남 권역 실제 소개 문장');

$breadcrumb_parent = ol_schema_hub_breadcrumb_node($current_url_parent, true);
check('상위 권역 breadcrumb 3단계(홈>사무실 임대>강남)', count($breadcrumb_parent['itemListElement']), 3);
check('상위 권역 breadcrumb 마지막 항목', $breadcrumb_parent['itemListElement'][2]['name'], '강남사무실임대');

// ══════════════════════════ 3. 동 허브(자식 term, 예: 삼성동) ══════════════════════════
$child_term = new WP_Term_Stub(2, 1, '삼성동');
$GLOBALS['__queried_object'] = $child_term;
$GLOBALS['__terms_by_id'][2] = $child_term;
$current_url_child = 'https://officeleasing.co.kr/강남사무실임대/삼성동/';

$collection_child = ol_schema_hub_collection_node($current_url_child, true);
check('자식(동) CollectionPage name에 "사무실 임대" 접미사 붙음', $collection_child['name'], '삼성동 사무실 임대');

$breadcrumb_child = ol_schema_hub_breadcrumb_node($current_url_child, true);
check('동 허브 breadcrumb 4단계(홈>사무실 임대>강남>삼성동)', count($breadcrumb_child['itemListElement']), 4);
check('동 허브 breadcrumb 3번째가 부모(강남)', $breadcrumb_child['itemListElement'][2]['name'], '강남사무실임대');
check('동 허브 breadcrumb 4번째가 본인(삼성동)', $breadcrumb_child['itemListElement'][3]['name'], '삼성동');

// region_faq_q/a ACF 필드가 있으면 그것을 쓰고, 없으면 아카이브 기본 FAQ로 폴백
$faq_child_default = ol_schema_hub_faq_node($current_url_child, true);
check('동 term에 FAQ 필드 없으면 기본 FAQ로 폴백', $faq_child_default['mainEntity'][0]['name'], '기본 질문1');

$GLOBALS['__fields'][2]['region_faq_q1'] = '삼성동 전용 질문';
$GLOBALS['__fields'][2]['region_faq_a1'] = '삼성동 전용 답변';
$faq_child_real = ol_schema_hub_faq_node($current_url_child, true);
check('동 term에 FAQ 필드 있으면 그것을 사용', $faq_child_real['mainEntity'][0]['name'], '삼성동 전용 질문');

printf("\n%d passed, %d failed\n", $pass, $fail);
if ($fail > 0) {
    exit(1);
}
