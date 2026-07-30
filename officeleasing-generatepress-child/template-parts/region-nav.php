<?php
/**
 * 권역/하위지역 버튼 네비게이션 (재사용 컴포넌트).
 * 아카이브: 권역 4개(부모 term). 권역 허브: 하위 동(자식 term).
 * 호출: get_template_part( 'template-parts/region-nav', null, array( 'terms' => $terms, 'title' => '권역별로 둘러보기' ) );
 */
defined( 'ABSPATH' ) || exit;

$terms = isset( $args['terms'] ) && is_array( $args['terms'] ) ? $args['terms'] : array();
$title = $args['title'] ?? '';
$active_id = isset( $args['active_id'] ) ? (int) $args['active_id'] : 0;
if ( empty( $terms ) ) {
	return;
}
?>
<section class="olx-region-nav" aria-label="<?php echo esc_attr( $title ?: '지역 네비게이션' ); ?>">
	<?php if ( $title ) : ?>
		<p class="olx-eyebrow"><?php echo esc_html( $title ); ?></p>
	<?php endif; ?>
	<div class="olx-region-nav-grid">
		<?php foreach ( $terms as $term ) :
			$url = get_term_link( $term );
			if ( is_wp_error( $url ) ) {
				continue;
			}
			// 부모 term(GBD 등)은 프리미엄 라벨로, 자식 동은 term name 그대로
			$label = ( (int) $term->parent === 0 ) ? olt_region_label( $term->name ) : $term->name;
			$is_active = ( (int) $term->term_id === $active_id );
			?>
			<a href="<?php echo esc_url( $url ); ?>" class="olx-region-nav-btn<?php echo $is_active ? ' is-active' : ''; ?>">
				<?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>
	</div>
</section>
