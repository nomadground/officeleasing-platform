<?php
// Home(front-page) 전용 JSON-LD: WebSite + WebPage + RealEstateAgent.
//
// @id 규칙(향후 빌딩/매물 스키마도 이 규칙을 따른다):
//   WebSite         : {home}/#website
//   RealEstateAgent : {home}/#organization
//   WebPage         : {home}/#webpage
// 빌딩/매물 페이지의 seller 노드는 회사 정보를 다시 통째로 선언하지 말고
// {home}/#organization 을 @id로 참조한다(중복 엔티티 방지).
//
// [중복 출력 방지] Rank Math가 활성화되어 있으면 같은 엔티티(WebSite/WebPage/Organization)를
// 이미 출력하므로 이 파일은 아무것도 렌더하지 않는다. Rank Math가 없을 때만 우리가 출력한다.
// 강제로 켜고 싶으면 'ol_output_home_schema' 필터로 true를 반환하면 된다.
if (!defined('ABSPATH')) {
    exit;
}

/** Rank Math 등 SEO 플러그인이 동일 엔티티를 이미 출력하는지 판정. */
function ol_seo_plugin_outputs_schema() {
    // Rank Math (무료/Pro 공통 상수·클래스)
    if (defined('RANK_MATH_VERSION') || class_exists('RankMath\\Helper')) {
        return true;
    }
    // Yoast SEO
    if (defined('WPSEO_VERSION')) {
        return true;
    }
    return false;
}

function ol_should_output_home_schema() {
    return (bool) apply_filters('ol_output_home_schema', !ol_seo_plugin_outputs_schema());
}

/**
 * 사이트 홈 URL(트레일링 슬래시 포함). 모든 스키마 파일이 @id를 조립할 때 이 값을 공유해야
 * 조직/웹사이트 @id가 페이지마다 어긋나지 않는다(빌딩 페이지의 schema.php도 이 함수를 그대로 쓴다).
 */
function ol_schema_home_url() {
    return trailingslashit(home_url('/'));
}

/** RealEstateAgent(회사) 노드의 @id. 빌딩/매물 페이지는 이 값을 seller로 참조만 하고 재선언하지 않는다. */
function ol_schema_organization_id() {
    return ol_schema_home_url() . '#organization';
}

add_action('wp_head', 'ol_render_home_schema', 20);
function ol_render_home_schema() {
    if (!is_front_page() || is_paged()) {
        return;
    }
    if (!ol_should_output_home_schema()) {
        return;
    }

    $home = ol_schema_home_url();
    $company = ol_company_info();

    $graph = [
        [
            '@type' => 'WebSite',
            '@id' => $home . '#website',
            'url' => $home,
            'name' => $company['brand'],
            'inLanguage' => 'ko-KR',
            'publisher' => ['@id' => ol_schema_organization_id()],
        ],
        [
            '@type' => 'RealEstateAgent',
            '@id' => ol_schema_organization_id(),
            'name' => $company['legal_name'],
            'alternateName' => $company['brand'],
            'url' => $home,
            'telephone' => $company['phone'],
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $company['address_street'],
                'addressLocality' => $company['address_region'],
                'addressRegion' => $company['address_city'],
                'addressCountry' => $company['address_country'],
            ],
            // 중개업 등록번호 - 법적 고지이자 E-E-A-T 신뢰 신호. 화면(Footer/Why 섹션)에도 동일하게 노출된다.
            'identifier' => [
                '@type' => 'PropertyValue',
                'name' => '중개업 등록번호',
                'value' => $company['license_number'],
            ],
            'areaServed' => [
                '@type' => 'City',
                'name' => '서울특별시',
            ],
        ],
        [
            '@type' => 'WebPage',
            '@id' => $home . '#webpage',
            'url' => $home,
            'name' => get_bloginfo('name'),
            'isPartOf' => ['@id' => $home . '#website'],
            'about' => ['@id' => ol_schema_organization_id()],
            'inLanguage' => 'ko-KR',
        ],
    ];

    $payload = [
        '@context' => 'https://schema.org',
        '@graph' => $graph,
    ];

    echo "\n<!-- OfficeLeasing Home Schema -->\n";
    echo '<script type="application/ld+json">'
        . wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        . "</script>\n";
}
