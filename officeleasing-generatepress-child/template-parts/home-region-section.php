<?php
/**
 * Home 권역 섹션 (재사용 컴포넌트).
 * GBD/CBD/YBD/ETC 네 권역이 이 파일 하나를 반복 호출한다 - 동일 HTML을 네 번 복사하지 않는다.
 *
 * $args:
 *   'term'         WP_Term  권역 부모 term (필수)
 *   'building_ids' int[]    노출할 빌딩 ID 배열 (Core의 ol_get_home_region_buildings 결과)
 *   'variant'      string   배경 교차용 클래스 ('' | 'is-tint' | 'is-warm')
 *   'eager_count'  int      상단 즉시 노출분으로 lazy를 끄는 카드 수 (첫 권역만 4, 나머지 0)
 *
 * 구조: eyebrow -> H2 -> 설명 -> 세부지역 텍스트 링크 -> 전체보기 -> 빌딩 슬라이드 -> Empty State
 * 모든 링크는 get_term_link()/get_permalink() 기반 (문자열 URL 조립 금지).
 */
defined( 'ABSPATH' ) || exit;

$term = isset( $args['term'] ) ? $args['term'] : null;
if ( ! $term || is_wp_error( $term ) ) {
	return;
}

$building_ids = isset( $args['building_ids'] ) && is_array( $args['building_ids'] ) ? $args['building_ids'] : array();
$variant      = isset( $args['variant'] ) ? (string) $args['variant'] : '';
$eager_count  = isset( $args['eager_count'] ) ? (int) $args['eager_count'] : 0;

$copy      = olt_home_region_copy( $term->name );
$term_link = get_term_link( $term );
$term_link = is_wp_error( $term_link ) ? '' : $term_link;

// 세부 지역: 실제로 존재하는 자식 term만. 없으면 링크 줄 자체를 렌더하지 않는다(가짜 링크 금지).
$children = get_terms( array(
	'taxonomy'   => 'office_region',
	'parent'     => (int) $term->term_id,
	'hide_empty' => false,
) );
$children = is_wp_error( $children ) ? array() : $children;

$count     = count( $building_ids );
$has_arrow = $count > 4; // 4개 이하는 화살표를 숨기고 정적으로 표시
$slug      = sanitize_html_class( olt_region_short_code( $term->name ) );
$title_id  = 'region-' . $slug . '-title';
?>
<section class="olx-home-region <?php echo esc_attr( $variant ); ?>" id="region-<?php echo esc_attr( $slug ); ?>" aria-labelledby="<?php echo esc_attr( $title_id ); ?>">
	<div class="olx-wrap">
		<div class="olx-section-head">
			<div>
				<p class="olx-eyebrow"><?php echo esc_html( $copy['eyebrow'] ); ?></p>
				<h2 id="<?php echo esc_attr( $title_id ); ?>"><?php echo esc_html( $copy['title'] ); ?></h2>
			</div>
			<?php if ( $term_link ) : ?>
				<a class="olx-home-region-all" href="<?php echo esc_url( $term_link ); ?>">
					<?php echo esc_html( $copy['all'] ); ?> <span aria-hidden="true">→</span>
				</a>
			<?php endif; ?>
		</div>

		<?php if ( $copy['desc'] ) : ?>
			<p class="olx-home-region-desc"><?php echo esc_html( $copy['desc'] ); ?></p>
		<?php endif; ?>

		<?php if ( ! empty( $children ) ) : ?>
			<nav class="olx-home-districts" aria-label="<?php echo esc_attr( $copy['title'] ); ?> 세부 지역">
				<?php
				foreach ( $children as $child ) {
					$child_link = get_term_link( $child );
					if ( is_wp_error( $child_link ) ) {
						continue;
					}
					printf(
						'<a href="%s">%s 사무실 임대</a>',
						esc_url( $child_link ),
						esc_html( $child->name )
					);
				}
				?>
			</nav>
		<?php endif; ?>

		<?php if ( $count > 0 ) : ?>
			<div class="olx-home-slider<?php echo $has_arrow ? ' has-nav' : ''; ?>" data-olx-slider>
				<?php if ( $has_arrow ) : ?>
					<button type="button" class="olx-home-arrow is-prev" data-olx-slide="prev"
						aria-label="<?php echo esc_attr( $copy['title'] ); ?> 이전 빌딩 보기" disabled>
						<span aria-hidden="true">‹</span>
					</button>
				<?php endif; ?>

				<?php
				// tabindex="0"으로 키보드만으로도 트랙을 좌우 스크롤할 수 있게 한다(JS 없이도 동작).
				?>
				<ul class="olx-home-track" data-olx-track tabindex="0"
					aria-label="<?php echo esc_attr( $copy['title'] ); ?> 빌딩 <?php echo esc_attr( (string) $count ); ?>건">
					<?php foreach ( $building_ids as $i => $building_id ) : ?>
						<li class="olx-home-slide">
							<?php
							get_template_part(
								'template-parts/building-card',
								null,
								array(
									'building_id' => $building_id,
									'context'     => 'home',
									// 첫 권역의 첫 화면(4장)만 즉시 로드, 나머지는 lazy
									'eager'       => ( $i < $eager_count ),
								)
							);
							?>
						</li>
					<?php endforeach; ?>
				</ul>

				<?php if ( $has_arrow ) : ?>
					<button type="button" class="olx-home-arrow is-next" data-olx-slide="next"
						aria-label="<?php echo esc_attr( $copy['title'] ); ?> 다음 빌딩 보기">
						<span aria-hidden="true">›</span>
					</button>
				<?php endif; ?>
			</div>

			<?php if ( $term_link ) : ?>
				<a class="olx-home-region-all-mobile" href="<?php echo esc_url( $term_link ); ?>">
					<?php echo esc_html( $copy['all'] ); ?> <span aria-hidden="true">→</span>
				</a>
			<?php endif; ?>

		<?php else : ?>
			<div class="olx-home-empty">
				<p>현재 등록된 임대 매물을 확인 중입니다.<br>희망 지역과 면적을 알려주시면 담당 중개사가 확인해 드립니다.</p>
				<div class="olx-home-empty-cta">
					<a class="olx-btn olx-btn-call" href="#contact">임대 조건 문의</a>
					<?php if ( $term_link ) : ?>
						<a class="olx-btn olx-btn-online" href="<?php echo esc_url( $term_link ); ?>"><?php echo esc_html( $copy['all'] ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
</section>
