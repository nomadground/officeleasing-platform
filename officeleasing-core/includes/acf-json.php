<?php
// acf-json 폴더를 필드그룹 원본 저장/로드 경로로 지정. ACF 관리자 화면에서 수정하면 이 폴더의 json이 자동 갱신된다.
if (!defined('ABSPATH')) {
    exit;
}

add_filter('acf/settings/save_json', function ($path) {
    return dirname(__DIR__) . '/acf-json';
});

add_filter('acf/settings/load_json', function ($paths) {
    $paths[] = dirname(__DIR__) . '/acf-json';
    return $paths;
});
