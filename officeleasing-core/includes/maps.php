<?php
// 카카오맵 JS SDK 조건부 로드 - Building 상세페이지(single-building) 전용.
//
// 아래 조건을 전부 만족할 때만 SDK를 로드한다:
//   1) 현재 페이지가 single-building
//   2) wp-config.php에 OL_KAKAO_JS_KEY가 정의되어 있고 비어있지 않음
//   3) 해당 빌딩의 위경도(building_lat/building_lng)가 유효함
// 하나라도 안 맞으면 조용히 스킵한다 - 화면은 테마의 정적 .olx-map-fallback이 그대로 유지된다
// (SDK 자체를 안 실으면 불필요한 외부 네트워크 요청도 안 생긴다).
//
// 앱키는 여기 하드코딩하지 않는다. wp-config.php에 아래처럼 정의한다:
//   define('OL_KAKAO_JS_KEY', '발급받은_JavaScript_키');
// 도메인 제한은 카카오 디벨로퍼스 콘솔의 "Web 플랫폼 등록"에서 걸어야 한다 -
// JS 키는 브라우저 소스에 노출될 수밖에 없는 구조라, 도메인 화이트리스트가 유일한 보안 경계다.
if (!defined('ABSPATH')) {
    exit;
}

function ol_get_kakao_js_key() {
    if (defined('OL_KAKAO_JS_KEY') && OL_KAKAO_JS_KEY) {
        return OL_KAKAO_JS_KEY;
    }
    return '';
}

/** 문자열/숫자 혼재 입력을 안전하게 float로 정규화. 비정상/범위밖 값은 null. */
function ol_kakao_normalize_coordinate($value, $min, $max) {
    if ($value === null || $value === '' || !is_numeric($value)) {
        return null;
    }
    $float = (float) $value;
    if (!is_finite($float) || $float < $min || $float > $max) {
        return null;
    }
    return $float;
}

/** 빌딩의 유효 좌표를 반환. 하나라도 없거나 (0,0)이면 null (미입력으로 간주). */
function ol_kakao_get_building_coords($building_id) {
    $lat = ol_kakao_normalize_coordinate(get_field('building_lat', $building_id), -90, 90);
    $lng = ol_kakao_normalize_coordinate(get_field('building_lng', $building_id), -180, 180);
    if (null === $lat || null === $lng) {
        return null;
    }
    if (0.0 === $lat && 0.0 === $lng) {
        return null;
    }
    return ['lat' => $lat, 'lng' => $lng];
}

add_action('wp_enqueue_scripts', 'ol_enqueue_kakao_map_sdk');
function ol_enqueue_kakao_map_sdk() {
    if (!is_singular('building')) {
        return;
    }
    $app_key = ol_get_kakao_js_key();
    if (!$app_key) {
        return;
    }
    $building_id = get_queried_object_id();
    if (!ol_kakao_get_building_coords($building_id)) {
        return;
    }

    // autoload=false: 테마 측 kakao.maps.load() 콜백 안에서만 초기화 (SDK 파싱 완료 전 API 호출 방지)
    // wp_enqueue_script는 핸들 기준으로 자동 중복제거되므로 여러 곳에서 호출돼도 script 태그는 하나만 출력된다.
    wp_enqueue_script(
        'kakao-maps-sdk',
        'https://dapi.kakao.com/v2/maps/sdk.js?appkey=' . rawurlencode($app_key) . '&autoload=false',
        [],
        null,
        true
    );
}
