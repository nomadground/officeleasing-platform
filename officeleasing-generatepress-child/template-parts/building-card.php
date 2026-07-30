<?php
/**
 * 빌딩 카드 (재사용 컴포넌트) — 목록/허브에서 사용.
 * listing-card.php(매물 1건)와 달리, 이 카드는 빌딩 1개 + 소속 매물 "집계값"을 보여준다.
 * 집계값은 building의 캐시 필드(building-cache.php가 채움)만 읽는다 — 카드 렌더 시 매물 재쿼리 금지.
 *
 * 호출: get_template_part( 'template-parts/building-card', null, array( 'building_id' => $id ) );
 *
 * $args (모두 선택):
 *   'building_id' int   대상 빌딩 (없으면 루프의 현재 글)
 *   'context'     string 렌더 위치. 'home'이면 modifier 클래스만 추가된다(데이터 구조는 동일).
 *   'eager'       bool  true면 loading="eager" (첫 화면에 즉시 보이는 카드에만). 기본은 lazy.
 */
defined( 'ABSPATH' ) || exit;

$building_id = isset( $args['building_id'] ) ? (int) $args['building_id'] : get_the_ID();
if ( ! $building_id ) {
	return;
}

$context     = isset( $args['context'] ) ? sanitize_html_class( (string) $args['context'] ) : '';
$is_eager    = ! empty( $args['eager'] );
$card_class  = 'olx-card olx-bcard' . ( $context ? ' olx-bcard--' . $context : '' );

$building_name = get_the_title( $building_id );
$building_link = get_permalink( $building_id );
$address       = get_field( 'building_address_road', $building_id );

$regions     = olt_get_region_terms( $building_id );
$region_code = $regions['parent'] ? $regions['parent']->name : '';
$district    = $regions['child'] ? $regions['child']->name : '';

$line    = get_field( 'building_subway1_line', $building_id );
$station = get_field( 'building_subway1_station', $building_id );

$images = olt_collect_images( $building_id, 'building_image_', 8 );
$img    = $images[0] ?? null;

$active_count = (int) get_field( 'building_active_listing_count', $building_id );
$area_range   = olt_building_area_range( $building_id );
$min_rent     = (float) get_field( 'building_min_rent', $building_id );
?>
<a class="<?php echo esc_attr( $card_class ); ?>" href="<?php echo esc_url( $building_link ); ?>">
	<div class="olx-card-img">
		<?php
		if ( $img ) {
			$alt = $img['alt'] ? $img['alt'] : $building_name . ' 외관';
			$attr = array(
				'alt'      => $alt,
				'decoding' => 'async',
				'loading'  => $is_eager ? 'eager' : 'lazy',
				// 카드 실폭: 데스크탑 4열 ≈ 272px, 모바일 2열 ≈ 화면의 45%.
				// 모바일에 데스크탑용 대형 이미지가 내려가지 않도록 명시한다.
				'sizes'    => '(max-width: 700px) 45vw, 280px',
			);
			if ( ! empty( $img['id'] ) ) {
				// 첨부 ID가 있으면 wp_get_attachment_image()로 출력한다 -
				// srcset/width/height가 자동으로 붙어 CLS와 모바일 과다전송을 함께 막는다(원본 직접 출력 금지 원칙).
				echo wp_get_attachment_image( (int) $img['id'], 'medium_large', false, $attr );
			} elseif ( ! empty( $img['url'] ) ) {
				printf(
					'<img src="%s" alt="%s" loading="%s" decoding="async">',
					esc_url( $img['url'] ),
					esc_attr( $alt ),
					esc_attr( $attr['loading'] )
				);
			}
		}
		?>
		<?php if ( $active_count > 0 ) : ?>
			<span class="olx-card-status"><i></i>매물 <?php echo esc_html( (string) $active_count ); ?>건</span>
		<?php endif; ?>
	</div>
	<div class="olx-card-body">
		<small class="olx-card-meta">
			<?php if ( $region_code ) : ?>
				<span class="olx-card-region <?php echo esc_attr( olt_region_class( $region_code ) ); ?>"><?php echo esc_html( olt_region_short_code( $region_code ) ); ?></span>
			<?php endif; ?>
			<?php if ( $district ) : ?>
				<span class="olx-card-district"><?php echo esc_html( $district ); ?></span>
			<?php endif; ?>
			<?php if ( $station ) : ?>
				<span class="olx-card-transit"><i style="background:<?php echo esc_attr( olt_line_color( $line ) ); ?>"><?php echo esc_html( olt_line_badge( $line ) ); ?></i><?php echo esc_html( $station ); ?></span>
			<?php endif; ?>
		</small>
		<h3><?php echo esc_html( $building_name ); ?></h3>
		<?php if ( $address ) : ?>
			<p><?php echo esc_html( $address ); ?></p>
		<?php endif; ?>
		<div class="olx-bcard-stats">
			<?php if ( $area_range ) : ?>
				<span class="olx-bcard-area"><?php echo esc_html( $area_range ); ?></span>
			<?php endif; ?>
			<?php if ( $min_rent > 0 ) : ?>
				<span class="olx-bcard-rent">최저 임대료 <b><?php echo esc_html( olt_won( $min_rent ) ); ?></b></span>
			<?php endif; ?>
		</div>
	</div>
</a>
