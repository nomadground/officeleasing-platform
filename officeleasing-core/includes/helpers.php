<?php
// 범용 헬퍼. 순수 계산 함수(ol_calc_*, ol_format_*)는 워드프레스에 의존하지 않아
// tests/test-calculations.php에서 WP 부트스트랩 없이 단독 실행 검증이 가능하다.
// 그래서 이 파일은 의도적으로 ABSPATH 가드를 걸지 않는다 (직접 로드해도 함수 정의만 하고
// 아무것도 실행하지 않으므로 안전하다). ol_is_real_save()만 워드프레스 함수에 의존한다.

if (!defined('OL_PYEONG_TO_SQM')) {
    define('OL_PYEONG_TO_SQM', 3.3058);
}

/**
 * get_field()로 읽은 보증금/임대료/관리비 원시값에서 "저장된 적 없음"과 "0으로 저장됨"을 구분한다.
 * group_ol_listing.json 기준 deposit_manwon/monthly_rent_manwon/maintenance_fee_manwon은 전부
 * required=1 + min=0 - 즉 0은 관리자가 실제로 고를 수 있는 유효한 값이다(관리비 없음/보증금 없음
 * 조건 등). ACF는 값이 한 번도 저장된 적 없는 필드는 null/false/''를 반환하므로, 그 경우에만 null을
 * 반환해 "집계에서 제외"로 처리하고 그 외에는 0을 포함한 실제 숫자를 그대로 반환한다.
 * ol_calc_*류처럼 워드프레스 함수를 쓰지 않으므로 WP 없이도 단독 테스트 가능하다.
 */
function ol_money_field_value($raw) {
    if ($raw === null || $raw === false || $raw === '') {
        return null;
    }
    return (float) $raw;
}

function ol_calc_sqm_from_pyeong($pyeong) {
    $pyeong = (float) $pyeong;
    if ($pyeong <= 0) {
        return 0;
    }
    return round($pyeong * OL_PYEONG_TO_SQM, 1);
}

// sqm이 원본 입력인 필드(건물 연면적/기준층면적)에서 평을 역산할 때 사용
function ol_calc_pyeong_from_sqm($sqm) {
    $sqm = (float) $sqm;
    if ($sqm <= 0) {
        return 0;
    }
    return round($sqm / OL_PYEONG_TO_SQM, 2);
}

function ol_calc_won_from_manwon($manwon) {
    $manwon = max(0, (float) $manwon);
    return (int) round($manwon * 10000);
}

function ol_safe_divide($numerator, $denominator) {
    $numerator = (float) $numerator;
    $denominator = (float) $denominator;
    if ($denominator <= 0) {
        return 0;
    }
    return $numerator / $denominator;
}

function ol_calc_monthly_total_cost($monthly_rent, $maintenance_fee) {
    return max(0, (float) $monthly_rent) + max(0, (float) $maintenance_fee);
}

function ol_calc_per_pyeong($amount, $pyeong) {
    $amount = max(0, (float) $amount);
    return (int) round(ol_safe_divide($amount, $pyeong));
}

// 15320000 -> "1,532만원". 억 단위로 쪼개지 않고 항상 만원 단위 콤마표기로 통일한다.
function ol_format_manwon($won) {
    $won = max(0, (float) $won);
    return number_format(round($won / 10000)) . '만원';
}

// 평당가처럼 만원 미만 단수가 있는 값 표기: 4796269 -> "479.6만원"
function ol_format_krw_per_pyeong($won) {
    $won = max(0, (float) $won);
    return number_format($won / 10000, 1) . '만원';
}

/**
 * floor_display(group_ol_listing.json 기준 required text 필드, "해당층 표기" - ACF 정의에 형식 제약은
 * 없지만 실제 사용례(tests/test-schema.php의 "21층"/"22층"/"23층", single-building.php의 "해당층/총층"
 * 단수 표기)는 전부 매물 1건당 대표층 하나만 담는다는 것을 전제로 한다. 범위 표기("3~5층" 등)는
 * 이 필드의 실제 용도가 아니라고 보고 - 지원하지 않는다(파싱 실패로 null, 캐시 min/max 계산에서 제외).
 *
 * 여러 매물의 floor_display를 min/max로 묶어 building 카드에 "3~7층" 범위를 캐시하기 위한 용도.
 * 지하는 음수로 취급(지하1층 -> -1)해 지상/지하가 섞인 범위도 min/max 비교가 자연스럽게 성립한다.
 *
 * 인식하는 형식(공백 무시, 대소문자 무시, 전체 문자열이 아래 패턴 중 하나와 정확히 일치해야 함 - 부분
 * 매치 금지. "3~5층"처럼 패턴에 없는 여분 문자가 있으면 매치 자체가 안 되어 null을 반환한다):
 *  - "17층", "17"           -> 17  (지상)
 *  - "지하1층", "B1"        -> -1  (지하)
 *  - "-2층", "-2"           -> -2  (지하, 마이너스 부호 표기)
 *  - "B동 3층"처럼 앞에 "…동" 접두어가 붙으면 그 접두어는 지하/지상 판정에서 먼저 떼어낸다 -
 *    "B동"의 B는 지하가 아니라 건물 동(棟) 이름이므로, 이 접두어를 실수로 지하 표기로 오인하면 안 된다.
 */
function ol_extract_floor_number($floor_display) {
    $s = trim((string) $floor_display);
    if ($s === '') {
        return null;
    }

    // "A동"/"B동" 등 건물 동 접두어는 이후 지하/지상 판정과 무관하므로 먼저 제거한다.
    $s = preg_replace('/^[0-9a-z가-힣]+동\s*/iu', '', $s);

    if (preg_match('/^(지하|b)\s*(\d+)\s*층?$/iu', $s, $m)) {
        return -1 * (int) $m[2];
    }
    if (preg_match('/^-(\d+)\s*층?$/u', $s, $m)) {
        return -1 * (int) $m[1];
    }
    if (preg_match('/^(\d+)\s*층?$/u', $s, $m)) {
        return (int) $m[1];
    }
    return null;
}

// save_post_{type} 훅에서 리비전/자동저장/타입불일치를 걸러내는 공통 가드
function ol_is_real_save($post_id, $post_type) {
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return false;
    }
    if (get_post_type($post_id) !== $post_type) {
        return false;
    }
    return true;
}

/**
 * 회사 공통정보 단일 소스.
 * Footer / Contact CTA / JSON-LD Organization이 각자 하드코딩하지 않고 전부 이 값을 참조한다.
 * (이 함수는 WP 함수를 쓰지 않는 순수 배열 반환이므로 helpers.php에 두고 단독 테스트도 가능하다.)
 *
 * 값을 바꿀 일이 생기면 이 한 곳만 고치면 된다. 필요하면 'ol_company_info' 필터로 덮어쓸 수 있게 두되,
 * 필터는 WP가 있을 때만 적용한다(테스트 환경에서 apply_filters가 없어도 동작해야 하므로).
 */
function ol_company_info() {
    $info = [
        'legal_name'      => '힌트부동산중개법인',
        'brand'           => 'OFFICE LEASING',
        'tagline'         => '서울 프라임 오피스 임대 플랫폼',
        'phone'           => '02-553-5988',
        'address_full'    => '서울 강남구 언주로 550 청광빌딩 2층',
        'address_street'  => '언주로 550 청광빌딩 2층',
        'address_city'    => '서울특별시',
        'address_region'  => '강남구',
        'address_country' => 'KR',
        'license_number'  => '11680-2026-00163',
        'hours'           => '평일 09:00 – 18:00',
        // 카카오톡 채널 URL. 실제 채널 주소가 확정되면 여기(또는 ol_company_info 필터)에만 넣으면
        // Contact CTA가 자동으로 버튼을 노출한다. 비어있으면 임의의 외부 URL을 만들지 않고 버튼을 숨긴다.
        'kakao_url'       => '',
    ];
    if (function_exists('apply_filters')) {
        $info = apply_filters('ol_company_info', $info);
    }
    return $info;
}

/** 회사정보 단일 값 조회. 없는 키는 빈 문자열. */
function ol_company($key) {
    $info = ol_company_info();
    return isset($info[$key]) ? $info[$key] : '';
}

/** 전화번호를 tel: 링크용으로 정규화 (02-553-5988 -> 025535988) */
function ol_tel_href($phone = null) {
    $phone = ($phone === null) ? ol_company('phone') : $phone;
    return preg_replace('/[^0-9+]/', '', (string) $phone);
}

/**
 * 아카이브(/사무실임대/) 상단 SEO 인트로의 단일 소스.
 * 테마의 olt_archive_seo_intro()가 이 함수를 우선 호출하고(플러그인 비활성 시엔 자체 사본으로 폴백),
 * schema-hub.php도 화면과 동일한 문장을 쓰기 위해 이 함수를 직접 부른다 - 문구가 두 곳에서 따로
 * 관리되며 갈라지는 것(화면 문구 수정 후 스키마엔 반영 안 되는 사고)을 막기 위한 단일 출처.
 */
function ol_default_archive_intro() {
    $text = '서울 프라임 오피스 임대 매물을 권역별로 확인하세요. 강남(GBD)·도심권(CBD)·여의도(YBD)를 중심으로 검증된 빌딩 정보와 실시간 공실 현황을 제공합니다.';
    return function_exists('apply_filters') ? apply_filters('ol_archive_intro', $text) : $text;
}

/** 아카이브 기본 FAQ (지역 컨텍스트 없는 전체 목록/폴백용) - 화면(olt_archive_faqs)과 스키마(FAQPage)가 공유하는 단일 소스. */
function ol_default_archive_faqs() {
    $faqs = [
        ['q' => '오피스 임대 상담은 어떻게 진행되나요?', 'a' => '관심 빌딩의 문의 버튼으로 연락 주시면, 담당 중개사가 공실 현황과 임대 조건을 확인해 안내해 드립니다.'],
        ['q' => '표시된 임대 조건은 확정 금액인가요?', 'a' => '임대료·관리비는 시장 상황과 공실 현황에 따라 변동될 수 있어, 상담 시 최신 조건을 다시 확인해 드립니다. 부가세는 모두 별도입니다.'],
        ['q' => '원하는 지역의 매물이 목록에 없으면 어떻게 하나요?', 'a' => '희망 지역·면적·예산을 알려주시면 등록되지 않은 매물까지 포함해 확인 가능한 범위에서 찾아 안내해 드립니다.'],
    ];
    return function_exists('apply_filters') ? apply_filters('ol_archive_faqs', $faqs) : $faqs;
}

/**
 * 권역/동 term에 region_intro가 비어있을 때 쓰는 자동 문구의 단일 소스.
 * $label은 화면 표시용 스타일(예: "강남(GBD)")이 아니라 term의 실제 이름을 그대로 받는다 -
 * Core는 테마의 스타일링 함수(olt_region_label)를 호출하지 않는다는 원칙 때문에, 자동생성
 * 문구는 원본 term 이름을 쓴다. region_intro를 실제로 채워두면 화면과 스키마 모두 그 값을 그대로 쓰므로
 * 이 폴백 문구가 노출되는 건 관리자가 아직 입력하지 않은 과도기뿐이다.
 */
function ol_default_region_intro($label) {
    return sprintf('%s 오피스 임대 매물을 확인하세요. 검증된 빌딩 정보와 실시간 공실 현황을 제공합니다.', $label);
}
