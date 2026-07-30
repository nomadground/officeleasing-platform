<?php
/**
 * taxonomy-office_region.php — 권역/동 허브.
 * 권역(부모 term)과 동(자식 term)을 한 템플릿에서 레벨 조건분기로 처리한다.
 * 메인 쿼리는 플러그인 archive-query.php가 building으로 제한 + 권역 레벨은 하위 동 포함(include_children).
 */
defined( 'ABSPATH' ) || exit;

get_header();

if ( ! olt_core_active() ) {
	olt_render_core_inactive_notice();
	get_footer();
	return;
}

$term          = get_queried_object();
$is_top_level  = ( $term && (int) $term->parent === 0 );
$term_name     = $term ? $term->name : '';
$display_label = $is_top_level ? olt_region_label( $term_name ) : $term_name;

// 브레드크럼용 부모
$parent_term = ( ! $is_top_level && $term && $term->parent ) ? get_term( $term->parent, 'office_region' ) : null;

// 지역 소개 (없으면 기본 문구). 폴백 문구는 플러그인 ol_default_region_intro()가 정본 -
// schema-hub.php(CollectionPage.description)도 같은 함수를 불러 화면과 스키마가 갈라지지 않게 한다.
$region_intro = $term ? get_field( 'region_intro', $term ) : '';
if ( ! $region_intro ) {
	$region_intro = function_exists( 'ol_default_region_intro' )
		? ol_default_region_intro( $term_name )
		: sprintf( '%s 오피스 임대 매물을 확인하세요. 검증된 빌딩 정보와 실시간 공실 현황을 제공합니다.', $display_label );
}

// 하위 동 버튼 (권역 레벨만)
$children = array();
if ( $is_top_level && $term ) {
	$children = get_terms( array( 'taxonomy' => 'office_region', 'parent' => $term->term_id, 'hide_empty' => false ) );
	if ( is_wp_error( $children ) ) {
		$children = array();
	}
}
?>

<nav class="olx-crumb" aria-label="현재 위치">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>">홈</a><span>›</span>
	<a href="<?php echo esc_url( get_post_type_archive_link( 'building' ) ); ?>">사무실 임대</a><span>›</span>
	<?php if ( $parent_term && ! is_wp_error( $parent_term ) ) : ?>
		<a href="<?php echo esc_url( get_term_link( $parent_term ) ); ?>"><?php echo esc_html( olt_region_label( $parent_term->name ) ); ?></a><span>›</span>
	<?php endif; ?>
	<b><?php echo esc_html( $display_label ); ?></b>
</nav>

<header class="olx-head">
	<h1><mark><?php echo esc_html( $display_label ); ?></mark></h1>
	<p><?php echo esc_html( $region_intro ); ?></p>
</header>

<?php if ( $is_top_level && ! empty( $children ) ) : ?>
	<?php get_template_part( 'template-parts/region-nav', null, array( 'terms' => $children, 'title' => '하위 지역' ) ); ?>
<?php endif; ?>

<section class="olx-section" aria-label="빌딩 목록">
	<?php if ( have_posts() ) : ?>
		<div class="olx-grid">
			<?php while ( have_posts() ) : the_post();
				get_template_part( 'template-parts/building-card', null, array( 'building_id' => get_the_ID() ) );
			endwhile; ?>
		</div>
		<?php get_template_part( 'template-parts/pagination' ); ?>
	<?php else :
		// Empty state: 안내문 + 인근 지역 빌딩 추천 (완전 빈 페이지는 SEO 불리)
		$nearby = olt_get_nearby_buildings( $term, 4 );
		?>
		<p class="olx-search-message">현재 <?php echo esc_html( $display_label ); ?>에 등록된 매물이 없습니다. 인근 지역 빌딩을 확인해 보세요.</p>
		<?php if ( ! empty( $nearby ) ) : ?>
			<div class="olx-grid">
				<?php foreach ( $nearby as $bid ) {
					get_template_part( 'template-parts/building-card', null, array( 'building_id' => $bid ) );
				} ?>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</section>

<?php get_template_part( 'template-parts/faq-section', null, array( 'faqs' => olt_region_faqs( $term ), 'title' => $display_label . ' 자주 묻는 질문' ) ); ?>

<?php get_template_part( 'template-parts/contact-cta', null, array( 'title' => $display_label . ' 오피스, 전문 중개사와 상담하세요' ) ); ?>

<?php
get_footer();
