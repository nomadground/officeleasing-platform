<?php
/**
 * 권역 필터 바 (재사용 컴포넌트). 헤더 하단에 노출.
 * office_region 부모 term(GBD/CBD/YBD/ETC)을 탭으로, 활성 권역의 자식 term(동)을 링크로 출력.
 * 활성 권역 판단: 현재 빌딩/매물/taxonomy 컨텍스트의 권역 > 없으면 첫 번째 부모.
 *
 * 사용처: single-building, archive-listing, taxonomy-office_region (Home은 미노출 권장 - 필요 시 조건부).
 */
defined( 'ABSPATH' ) || exit;

// 자식 테마가 header.php를 제공하면 이 바가 사이트 전역에 뜨므로, 매물 관련 컨텍스트에서만 노출한다.
// (노출 여부는 olt_show_region_bar 필터로 조정 가능)
//
// Home은 의도적으로 제외한다: front-page.php가 권역 4개 섹션을 세부지역 링크까지 전부 펼쳐 보여주므로
// 상단 권역 바가 같은 내비게이션을 중복하면서 Hero를 아래로 밀어낸다. 되살리려면
// add_filter( 'olt_show_region_bar', fn( $s ) => $s || is_front_page() ) 하면 된다.
$show = is_singular( array( 'building', 'listing' ) )
	|| is_tax( 'office_region' )
	|| is_post_type_archive( 'building' );
if ( ! apply_filters( 'olt_show_region_bar', $show ) ) {
	return;
}

$parents = get_terms( array(
	'taxonomy'   => 'office_region',
	'parent'     => 0,
	'hide_empty' => false,
	'orderby'    => 'term_order',
) );
if ( is_wp_error( $parents ) || empty( $parents ) ) {
	return;
}

// 활성 권역 결정
$active_parent_id = 0;
$active_child_id  = 0;
if ( is_singular( array( 'building', 'listing' ) ) ) {
	$regions = olt_get_region_terms( get_the_ID() );
	$active_parent_id = $regions['parent'] ? (int) $regions['parent']->term_id : 0;
	$active_child_id  = $regions['child'] ? (int) $regions['child']->term_id : 0;
} elseif ( is_tax( 'office_region' ) ) {
	$current = get_queried_object();
	if ( $current && ! is_wp_error( $current ) ) {
		if ( $current->parent ) {
			$active_parent_id = (int) $current->parent;
			$active_child_id  = (int) $current->term_id;
		} else {
			$active_parent_id = (int) $current->term_id;
		}
	}
}
if ( ! $active_parent_id ) {
	$active_parent_id = (int) $parents[0]->term_id;
}

$active_parent = get_term( $active_parent_id, 'office_region' );
$children = get_terms( array(
	'taxonomy'   => 'office_region',
	'parent'     => $active_parent_id,
	'hide_empty' => false,
) );
?>
<div class="olx-region-filter olx-wrap">
	<div class="olx-region-intro">
		<span>FOR LEASE</span>
		<strong>권역별 오피스</strong>
	</div>
	<div class="olx-region-tabs" role="group" aria-label="권역 선택">
		<?php foreach ( $parents as $parent ) :
			$is_active = ( (int) $parent->term_id === $active_parent_id );
			$label = olt_region_label( $parent->name );
			// "강남(GBD)" -> 앞부분 + <small>(GBD)</small> 형태로 분해
			if ( preg_match( '/^(.*?)\((.+)\)$/', $label, $mm ) ) {
				$main  = $mm[1];
				$small = $mm[2];
			} else {
				$main  = $label;
				$small = '';
			}
			$term_url = get_term_link( $parent );
			$term_url = is_wp_error( $term_url ) ? '' : $term_url;
			// 확정 디자인이 탭을 <button>으로 씀(JS 필터). 디자인 유지 위해 button 유지하고,
			// data-region-url을 두어 추후 JS에서 세부지역 전환/이동에 사용. 무JS 시 실제 이동은 세부지역 링크가 담당.
			?>
			<button type="button"
			   class="<?php echo $is_active ? 'is-active' : ''; ?>"
			   aria-pressed="<?php echo $is_active ? 'true' : 'false'; ?>"
			   data-region-url="<?php echo esc_url( $term_url ); ?>">
				<?php echo esc_html( $main ); ?><?php if ( $small ) : ?><small>(<?php echo esc_html( $small ); ?>)</small><?php endif; ?>
			</button>
		<?php endforeach; ?>
	</div>
	<div class="olx-region-detail" aria-live="polite">
		<span><?php echo esc_html( $active_parent ? trim( preg_replace( '/\(.+\)/', '', olt_region_label( $active_parent->name ) ) ) : '' ); ?> 세부 지역</span>
		<div>
			<?php
			if ( ! is_wp_error( $children ) ) {
				foreach ( $children as $child ) {
					printf(
						'<a href="%s"%s>%s</a>',
						esc_url( get_term_link( $child ) ),
						( (int) $child->term_id === $active_child_id ) ? ' class="is-active"' : '',
						esc_html( $child->name )
					);
				}
			}
			?>
		</div>
	</div>
</div>
