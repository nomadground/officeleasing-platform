<?php
/**
 * archive-building.php — 전체 빌딩 목록 (/사무실임대/).
 * 메인 쿼리(building post type archive)를 그대로 사용. per_page/정렬은 플러그인 archive-query.php가 보정.
 * 모든 값은 get_field()/WP 함수 출력. 컴포넌트는 template-parts/* 재사용.
 */
defined( 'ABSPATH' ) || exit;

get_header();

if ( ! olt_core_active() ) {
	olt_render_core_inactive_notice();
	get_footer();
	return;
}

$parents = get_terms( array( 'taxonomy' => 'office_region', 'parent' => 0, 'hide_empty' => false ) );
if ( is_wp_error( $parents ) ) {
	$parents = array();
}
?>

<nav class="olx-crumb" aria-label="현재 위치">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>">홈</a><span>›</span>
	<b>사무실 임대</b>
</nav>

<header class="olx-head">
	<h1>서울 <mark>사무실 임대</mark></h1>
	<p><?php echo esc_html( olt_archive_seo_intro() ); ?></p>
</header>

<?php get_template_part( 'template-parts/region-nav', null, array( 'terms' => $parents, 'title' => '권역별로 둘러보기' ) ); ?>

<?php get_template_part( 'template-parts/filter-bar' ); ?>

<section class="olx-section" aria-label="빌딩 목록">
	<?php if ( have_posts() ) : ?>
		<div class="olx-grid">
			<?php while ( have_posts() ) : the_post();
				get_template_part( 'template-parts/building-card', null, array( 'building_id' => get_the_ID() ) );
			endwhile; ?>
		</div>
		<?php get_template_part( 'template-parts/pagination' ); ?>
	<?php else : ?>
		<p class="olx-search-message">현재 등록된 빌딩이 없습니다.</p>
	<?php endif; ?>
</section>

<section class="olx-section olx-seo-content">
	<p><?php echo esc_html( olt_archive_seo_content() ); ?></p>
</section>

<?php get_template_part( 'template-parts/faq-section', null, array( 'faqs' => olt_archive_faqs(), 'title' => '자주 묻는 질문' ) ); ?>

<?php get_template_part( 'template-parts/contact-cta', null, array( 'title' => '원하는 오피스를 찾고 계신가요?' ) ); ?>

<?php
get_footer();
