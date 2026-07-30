<?php
// 허브 페이지(전체 빌딩 아카이브 /사무실임대/, 권역·동 허브 /{parent}/[{child}]/) JSON-LD:
// CollectionPage + ItemList + BreadcrumbList + FAQPage (Sprint 02.5).
//
// [화면과 일치] ItemList는 실제 메인 쿼리($wp_query->posts, 현재 페이지 것만)를 그대로 반영하고,
// FAQPage는 archive-building.php/taxonomy-office_region.php가 화면에 렌더하는 것과 동일한 함수
// (ol_default_archive_faqs / region_faq_q·a ACF 필드)를 그대로 읽는다 - 화면에 없는 FAQ를 스키마에만
// 만들어 넣지 않는다. Breadcrumb도 두 템플릿의 .olx-crumb 내비게이션과 동일한 경로를 따른다.
//
// [범위] Sprint 01.5 3-5(빌딩 개별 스키마)와 분리된 "허브(권역·동·전체 아카이브)" 레벨 스키마.
// [중복 방지] Rank Math 등 SEO 플러그인 활성 시 자동 비활성(schema-home.php/schema.php와 동일 로직 재사용).
if (!defined('ABSPATH')) {
    exit;
}

function ol_should_output_hub_schema() {
    return (bool) apply_filters('ol_output_hub_schema', !ol_seo_plugin_outputs_schema());
}

add_action('wp_head', 'ol_render_hub_schema', 20);
function ol_render_hub_schema() {
    $is_archive = is_post_type_archive('building');
    $is_hub = is_tax('office_region');
    if (!$is_archive && !$is_hub) {
        return;
    }
    if (!ol_should_output_hub_schema()) {
        return;
    }

    $current_url = ol_schema_hub_current_url();
    if (!$current_url) {
        return;
    }

    $graph = [];

    $collection_node = ol_schema_hub_collection_node($current_url, $is_hub);
    if ($collection_node) {
        $graph[] = $collection_node;
    }

    $item_list_node = ol_schema_hub_item_list_node($current_url);
    if ($item_list_node) {
        $graph[] = $item_list_node;
    }

    $graph[] = ol_schema_hub_breadcrumb_node($current_url, $is_hub);

    // FAQPage는 여러 페이지에 걸쳐 반복되는 스키마 중복을 피하기 위해 1페이지에서만 출력한다.
    if (!is_paged()) {
        $faq_node = ol_schema_hub_faq_node($current_url, $is_hub);
        if ($faq_node) {
            $graph[] = $faq_node;
        }
    }

    if (empty($graph)) {
        return;
    }

    $payload = [
        '@context' => 'https://schema.org',
        '@graph' => $graph,
    ];

    echo "\n<!-- OfficeLeasing Hub Schema -->\n";
    echo '<script type="application/ld+json">'
        . wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        . "</script>\n";
}

/** 현재 아카이브/허브 페이지의 URL(페이지네이션 반영). WP 코어의 페이지네이션 링크 생성 함수를 그대로 쓴다. */
function ol_schema_hub_current_url() {
    $paged = max(1, (int) get_query_var('paged'), (int) get_query_var('page'));
    $url = get_pagenum_link($paged, false);
    return $url ?: false;
}

function ol_schema_hub_collection_node($current_url, $is_hub) {
    $home = ol_schema_home_url();

    if ($is_hub) {
        $term = get_queried_object();
        if (!$term || is_wp_error($term)) {
            return null;
        }
        $is_top_level = 0 === (int) $term->parent;
        // 화면 H1은 스타일링된 라벨("강남(GBD)")을 쓰지만, Core는 테마 표시 헬퍼를 호출하지 않는다는
        // 원칙에 따라 원본 term 이름을 쓴다 - 데이터로서는 동일하게 정확하다.
        $name = $is_top_level ? $term->name : ($term->name . ' 사무실 임대');
        $description = get_field('region_intro', $term);
        if (!$description) {
            $description = ol_default_region_intro($term->name);
        }
    } else {
        $name = '서울 사무실 임대 전체';
        $description = ol_default_archive_intro();
    }

    return [
        '@type' => 'CollectionPage',
        '@id' => $current_url . '#webpage',
        'url' => $current_url,
        'name' => $name,
        'description' => $description,
        'inLanguage' => 'ko-KR',
        'isPartOf' => ['@id' => $home . '#website'],
        'mainEntity' => ['@id' => $current_url . '#itemlist'],
    ];
}

/**
 * 현재 페이지에 실제로 보이는 빌딩만 ItemList로 - $wp_query->posts를 직접 읽는다(the_post()/have_posts()를
 * 호출하면 템플릿이 나중에 쓸 메인 루프 포인터가 틀어지므로 절대 호출하지 않는다).
 */
function ol_schema_hub_item_list_node($current_url) {
    global $wp_query;
    if (empty($wp_query->posts)) {
        return null;
    }

    $elements = [];
    foreach ($wp_query->posts as $index => $post) {
        if (!($post instanceof WP_Post) || 'building' !== $post->post_type) {
            continue;
        }
        $elements[] = [
            '@type' => 'ListItem',
            'position' => $index + 1,
            'url' => get_permalink($post),
            'name' => get_the_title($post),
        ];
    }
    if (empty($elements)) {
        return null;
    }

    return [
        '@type' => 'ItemList',
        '@id' => $current_url . '#itemlist',
        'numberOfItems' => (int) $wp_query->found_posts,
        'itemListElement' => $elements,
    ];
}

/** 화면 .olx-crumb 내비게이션과 동일한 경로: 홈 > 사무실 임대 > [상위 권역 >] 현재. */
function ol_schema_hub_breadcrumb_items($is_hub) {
    $items = [
        ['name' => '홈', 'url' => ol_schema_home_url()],
        ['name' => '사무실 임대', 'url' => get_post_type_archive_link('building')],
    ];

    if ($is_hub) {
        $term = get_queried_object();
        if ($term && !is_wp_error($term)) {
            if ($term->parent) {
                $parent = get_term($term->parent, 'office_region');
                if ($parent && !is_wp_error($parent)) {
                    $parent_link = get_term_link($parent);
                    if (!is_wp_error($parent_link)) {
                        $items[] = ['name' => $parent->name, 'url' => $parent_link];
                    }
                }
            }
            $term_link = get_term_link($term);
            if (!is_wp_error($term_link)) {
                $items[] = ['name' => $term->name, 'url' => $term_link];
            }
        }
    }

    return $items;
}

function ol_schema_hub_breadcrumb_node($current_url, $is_hub) {
    $elements = [];
    foreach (ol_schema_hub_breadcrumb_items($is_hub) as $i => $item) {
        $elements[] = [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'name' => $item['name'],
            'item' => $item['url'],
        ];
    }
    return [
        '@type' => 'BreadcrumbList',
        '@id' => $current_url . '#breadcrumb',
        'itemListElement' => $elements,
    ];
}

/** 화면(faq-section.php)이 실제로 렌더하는 것과 동일한 소스: 동/권역은 region_faq_q·a ACF, 없으면 아카이브 기본값. */
function ol_schema_hub_faq_items($is_hub) {
    if ($is_hub) {
        $term = get_queried_object();
        if (!$term || is_wp_error($term)) {
            return [];
        }
        $faqs = [];
        for ($i = 1; $i <= 5; $i++) {
            $q = get_field('region_faq_q' . $i, $term);
            $a = get_field('region_faq_a' . $i, $term);
            if ($q && $a) {
                $faqs[] = ['q' => $q, 'a' => $a];
            }
        }
        return !empty($faqs) ? $faqs : ol_default_archive_faqs();
    }
    return ol_default_archive_faqs();
}

function ol_schema_hub_faq_node($current_url, $is_hub) {
    $faqs = ol_schema_hub_faq_items($is_hub);
    if (empty($faqs)) {
        return null;
    }
    $elements = [];
    foreach ($faqs as $faq) {
        $elements[] = [
            '@type' => 'Question',
            'name' => $faq['q'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $faq['a'],
            ],
        ];
    }
    return [
        '@type' => 'FAQPage',
        '@id' => $current_url . '#faq',
        'mainEntity' => $elements,
    ];
}
