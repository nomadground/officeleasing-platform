<?php
/**
 * 페이지네이션 (재사용 컴포넌트). paginate_links() 사용, 무한스크롤 없음.
 * 기본은 메인 쿼리($wp_query). 커스텀 쿼리를 쓰면 args로 max/current를 넘긴다.
 *
 * 호출: get_template_part( 'template-parts/pagination' );
 *   또는 get_template_part( 'template-parts/pagination', null, array( 'total' => $q->max_num_pages, 'current' => $paged ) );
 */
defined( 'ABSPATH' ) || exit;

global $wp_query;
$total   = isset( $args['total'] ) ? (int) $args['total'] : (int) $wp_query->max_num_pages;
$current = isset( $args['current'] ) ? (int) $args['current'] : max( 1, (int) get_query_var( 'paged' ) );

if ( $total <= 1 ) {
	return;
}

$links = paginate_links( array(
	'total'     => $total,
	'current'   => $current,
	'mid_size'  => 1,
	'prev_text' => '‹ 이전',
	'next_text' => '다음 ›',
	'type'      => 'array',
) );

if ( empty( $links ) ) {
	return;
}
?>
<nav class="olx-pagination" aria-label="페이지 이동">
	<?php foreach ( $links as $link ) {
		// paginate_links가 이미 안전한 마크업(<a>/<span>)을 반환하므로 그대로 출력
		echo $link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	} ?>
</nav>
