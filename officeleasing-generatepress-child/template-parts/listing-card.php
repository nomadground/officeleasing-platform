<?php
/**
 * 매물 카드 (핵심 재사용 컴포넌트).
 * 추천매물(single-building), archive-listing, taxonomy-office_region, Home에서 모두 이 파일 하나를 재사용한다.
 *
 * 호출: get_template_part( 'template-parts/listing-card', null, array( 'listing_id' => $id ) );
 * 카드 링크는 URL 정책(빌딩 URL 고정, 매물 개별 URL 없음)에 따라 연결 빌딩 permalink로 간다.
 */
defined( 'ABSPATH' ) || exit;

$listing_id = isset( $args['listing_id'] ) ? (int) $args['listing_id'] : get_the_ID();
if ( ! $listing_id ) {
	return;
}

$building_id = (int) get_field( 'related_building', $listing_id );
if ( ! $building_id ) {
	return;
}

$regions      = olt_get_region_terms( $building_id );
$region_code  = $regions['parent'] ? $regions['parent']->name : '';
$district     = $regions['child'] ? $regions['child']->name : '';

$building_name  = get_the_title( $building_id );
$building_link  = get_permalink( $building_id );
$total_floors   = get_field( 'building_ground_floors', $building_id );
$floor_display  = get_field( 'floor_display', $listing_id );
$status         = get_field( 'listing_status', $listing_id );

// 대표 이미지: 매물 사진 우선, 없으면 빌딩 사진
$card_images = olt_collect_images( $listing_id, 'listing_image_', 6 );
if ( empty( $card_images ) ) {
	$card_images = olt_collect_images( $building_id, 'building_image_', 8 );
}
$img = $card_images[0] ?? null;

// 교통(첫 번째 노선)
$line    = get_field( 'building_subway1_line', $building_id );
$station = get_field( 'building_subway1_station', $building_id );
?>
<a class="olx-card" href="<?php echo esc_url( $building_link ); ?>">
	<div class="olx-card-img">
		<?php if ( $img && ! empty( $img['url'] ) ) : ?>
			<img src="<?php echo esc_url( $img['url'] ); ?>" alt="<?php echo esc_attr( $img['alt'] ?: $building_name . ' 오피스 이미지' ); ?>" loading="lazy" decoding="async">
		<?php endif; ?>
		<span class="olx-card-status"><i></i><?php echo esc_html( olt_status_label( $status ) ); ?></span>
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
		<div class="olx-card-areas">
			<span class="olx-card-floor"><?php echo esc_html( $floor_display ); ?><?php echo $total_floors ? ' / ' . esc_html( $total_floors ) . 'F' : ''; ?></span>
			<span>
				<b>임대</b>
				<strong><?php echo esc_html( olt_sqm( get_field( 'lease_area_sqm', $listing_id ) ) ); ?></strong>
				<small><?php echo esc_html( olt_pyeong( get_field( 'lease_area_pyeong', $listing_id ) ) ); ?></small>
			</span>
			<span>
				<b>전용</b>
				<strong><?php echo esc_html( olt_sqm( get_field( 'exclusive_area_sqm', $listing_id ) ) ); ?></strong>
				<small><?php echo esc_html( olt_pyeong( get_field( 'exclusive_area_pyeong', $listing_id ) ) ); ?></small>
			</span>
		</div>
		<div class="olx-card-prices">
			<span><i class="chip-deposit">보</i><?php echo esc_html( olt_won( get_field( 'deposit_amount', $listing_id ) ) ); ?></span>
			<span><i class="chip-rent">월</i><?php echo esc_html( olt_won( get_field( 'monthly_rent', $listing_id ) ) ); ?></span>
			<span><i class="chip-maintenance">관</i><?php echo esc_html( olt_won( get_field( 'maintenance_fee', $listing_id ) ) ); ?></span>
		</div>
	</div>
</a>
