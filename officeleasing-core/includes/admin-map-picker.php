<?php
// Building 편집화면 전용 위치 선택기. ACF 무료엔 Pro의 "Google Map" 필드가 없어서
// 카카오맵 JS SDK(services 라이브러리)로 같은 기능(주소검색/지도클릭 -> 좌표 자동입력)을 직접 만든다.
// REST 키는 필요 없다 - services 라이브러리의 Geocoder가 JS 키 하나로 브라우저에서 동작한다.
// building_lat/building_lng 필드 자체(acf-json)는 손대지 않는다 - 그 옆에 보조 UI만 얹는 방식.
if (!defined('ABSPATH')) {
    exit;
}

// building_map_picker_slot(message 필드, 필드목록 맨 앞)에 선택기 UI를 붙인다.
// 이 슬롯을 맨 앞에 둔 이유: 도로명주소/지번주소/위도/경도가 전부 readonly라
// 검색 UI가 그 필드들보다 먼저 나와야 자연스러운 입력 순서가 된다 (ACF 표준 확장 포인트).
add_action('acf/render_field/key=field_ol_bld_map_picker_slot', 'ol_render_map_picker_ui');
function ol_render_map_picker_ui($field) {
    echo '<div id="ol-map-picker" style="margin-top:6px;border:1px solid #dcdcde;padding:12px;background:#fff;max-width:640px;">'
        . '<div style="display:flex;gap:8px;margin-bottom:10px;">'
        . '<input type="text" id="ol-map-picker-address" class="regular-text" placeholder="주소를 입력하세요 (예: 테헤란로 521)" style="flex:1;" />'
        . '<button type="button" id="ol-map-picker-search" class="button">주소·좌표 찾기</button>'
        . '</div>'
        . '<div id="ol-map-picker-status" style="font-size:12px;color:#666;margin-bottom:6px;display:none;"></div>'
        . '<div id="ol-map-picker-canvas" style="width:100%;height:280px;background:#eef1f2;"></div>'
        . '<p style="margin:8px 0 0;color:#666;font-size:12px;">검색하면 아래 도로명주소·지번주소·위도·경도가 한번에 채워집니다(직접 입력 불가). 지도를 직접 클릭하면 좌표만 갱신됩니다.</p>'
        . '</div>';
}

add_action('admin_enqueue_scripts', 'ol_enqueue_map_picker_assets');
function ol_enqueue_map_picker_assets($hook) {
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }
    $screen_post_type = get_current_screen() ? get_current_screen()->post_type : '';
    if ('building' !== $screen_post_type) {
        return;
    }

    $app_key = function_exists('ol_get_kakao_js_key') ? ol_get_kakao_js_key() : '';
    if (!$app_key) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-warning"><p><strong>위치 선택기</strong>: '
                . 'wp-config.php에 OL_KAKAO_JS_KEY가 설정돼야 지도가 표시됩니다. 지금은 위경도를 직접 입력해 주세요.</p></div>';
        });
        return;
    }

    wp_enqueue_script(
        'kakao-maps-sdk-admin',
        'https://dapi.kakao.com/v2/maps/sdk.js?appkey=' . rawurlencode($app_key) . '&libraries=services&autoload=false',
        [],
        null,
        true
    );

    $js_path = OL_CORE_DIR . 'assets/admin-map-picker.js';
    wp_enqueue_script(
        'ol-admin-map-picker',
        OL_CORE_URL . 'assets/admin-map-picker.js',
        ['kakao-maps-sdk-admin'],
        file_exists($js_path) ? (string) filemtime($js_path) : '0.1.0',
        true
    );
}
