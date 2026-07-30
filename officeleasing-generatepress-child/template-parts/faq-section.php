<?php
/**
 * FAQ 섹션 (재사용 컴포넌트). single-building의 인라인 FAQ와 동일한 .olx-faq 마크업을 컴포넌트화.
 * 호출: get_template_part( 'template-parts/faq-section', null, array( 'faqs' => $faqs, 'title' => '...' ) );
 * $faqs: [ ['q'=>..., 'a'=>...], ... ]
 */
defined( 'ABSPATH' ) || exit;

$faqs  = isset( $args['faqs'] ) && is_array( $args['faqs'] ) ? $args['faqs'] : array();
$title = $args['title'] ?? '자주 묻는 질문';
if ( empty( $faqs ) ) {
	return;
}
?>
<section class="olx-section olx-faq-section" id="insight">
	<div class="olx-section-head">
		<div><p class="olx-eyebrow">FAQ</p><h2><?php echo esc_html( $title ); ?></h2></div>
	</div>
	<div class="olx-faq">
		<?php foreach ( $faqs as $n => $faq ) : ?>
			<details <?php echo 0 === $n ? 'open' : ''; ?>>
				<summary><span><?php echo esc_html( str_pad( (string) ( $n + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span><?php echo esc_html( $faq['q'] ); ?><i></i></summary>
				<div><?php echo esc_html( $faq['a'] ); ?></div>
			</details>
		<?php endforeach; ?>
	</div>
</section>
