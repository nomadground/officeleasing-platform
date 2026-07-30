<?php
/**
 * 필터 UI (이번 단계는 UI만, 실제 쿼리 미구현).
 * 파라미터명은 다음 검색/필터 단계에서 그대로 연결되도록 확정: region/dong/area_min/area_max/budget_min/budget_max.
 * GET method로 제출되지만, 서버 쿼리는 아직 이 값들을 처리하지 않는다(현재 값 유지만).
 */
defined( 'ABSPATH' ) || exit;

$parents = get_terms( array( 'taxonomy' => 'office_region', 'parent' => 0, 'hide_empty' => false ) );
if ( is_wp_error( $parents ) ) {
	$parents = array();
}

$get = function ( $k ) {
	return isset( $_GET[ $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) : '';
};
?>
<form class="olx-filter" method="get" action="<?php echo esc_url( get_post_type_archive_link( 'building' ) ); ?>" role="search" aria-label="매물 필터">
	<label>
		<span>권역</span>
		<select name="region">
			<option value="">전체</option>
			<?php foreach ( $parents as $p ) : ?>
				<option value="<?php echo esc_attr( $p->slug ); ?>" <?php selected( $get( 'region' ), $p->slug ); ?>><?php echo esc_html( olt_region_label( $p->name ) ); ?></option>
			<?php endforeach; ?>
		</select>
	</label>
	<label>
		<span>전용면적(평)</span>
		<span class="olx-filter-range">
			<input type="number" name="area_min" min="0" step="1" placeholder="최소" value="<?php echo esc_attr( $get( 'area_min' ) ); ?>">
			<i>~</i>
			<input type="number" name="area_max" min="0" step="1" placeholder="최대" value="<?php echo esc_attr( $get( 'area_max' ) ); ?>">
		</span>
	</label>
	<label>
		<span>월 임대료(만원)</span>
		<span class="olx-filter-range">
			<input type="number" name="budget_min" min="0" step="1" placeholder="최소" value="<?php echo esc_attr( $get( 'budget_min' ) ); ?>">
			<i>~</i>
			<input type="number" name="budget_max" min="0" step="1" placeholder="최대" value="<?php echo esc_attr( $get( 'budget_max' ) ); ?>">
		</span>
	</label>
	<button type="submit">매물 찾기</button>
</form>
