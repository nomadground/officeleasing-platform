<?php
/**
 * 공개 Flyer 상세 (Phase 1 최소 골격). 갤러리/지도/인쇄/차트는 Phase 4+에서.
 * $hlf_context: ['flyer'=>[], 'status'=>string, 'item'=>[...], 'items'=>[...]]
 */
defined( 'ABSPATH' ) || exit;

$flyer   = $hlf_context['flyer'];
$status  = $hlf_context['status'];
$item    = $hlf_context['item'];
$metrics = $item['metrics'];
$noindex = ( 'published' !== $status );
$address = $item['road_address'] ?: $item['lot_address'];

$basic = array(
	'해당층'       => trim( ( $item['floor_current'] ?: '-' ) . ' / ' . ( $item['floor_total'] ?: '-' ) . '층' ),
	'공급면적'     => $item['lease_area_sqm'] ? number_format( (float) $item['lease_area_sqm'], 1 ) . '㎡ (' . number_format( $metrics['lease_pyeong'], 1 ) . '평)' : '-',
	'전용면적'     => $item['exclusive_area_sqm'] ? number_format( (float) $item['exclusive_area_sqm'], 1 ) . '㎡ (' . number_format( $metrics['exclusive_pyeong'], 1 ) . '평)' : '-',
	'방향'         => $item['direction'] ?: '-',
	'주차'         => $item['parking_available'] ? ( $item['total_parking'] ?: '가능' ) : '불가',
	'엘리베이터'   => $item['elevator_available'] ? '있음' : '없음',
	'입주가능일'   => $item['available_date_text'] ?: '-',
	'사용승인일'   => $item['approval_date'] ?: '-',
	'건축물용도'   => $item['building_use'] ?: '-',
);
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( $address . ' · ' . $flyer['flyer_number'] . ' ' . $item['item_number'] ); ?></title>
	<?php if ( $noindex ) : ?>
		<meta name="robots" content="noindex,nofollow">
	<?php endif; ?>
	<link rel="canonical" href="<?php echo esc_url( HLF_Routes::item_url( $flyer['id'], $item['item_number'] ) ); ?>">
	<link rel="stylesheet" href="<?php echo esc_url( HLF_URL . 'assets/css/public.css?v=' . HLF_VERSION ); ?>">
	<link rel="stylesheet" href="<?php echo esc_url( HLF_URL . 'assets/css/print.css?v=' . HLF_VERSION ); ?>" media="print">
</head>
<body class="hlf-public hlf-detail">
	<div class="hlf-shell">
		<header class="hlf-header">
			<a class="hlf-back" href="<?php echo esc_url( $flyer['url'] ); ?>">← 목록</a>
			<span class="hlf-flyer-number"><?php echo esc_html( $flyer['flyer_number'] . ' · ' . $item['item_number'] ); ?></span>
		</header>

		<h1 class="hlf-title"><?php echo esc_html( $address ); ?></h1>
		<?php if ( $item['lot_address'] && $item['lot_address'] !== $address ) : ?>
			<p class="hlf-subaddress"><?php echo esc_html( $item['lot_address'] ); ?></p>
		<?php endif; ?>

		<section class="hlf-lease-metrics hlf-detail-metrics">
			<div class="hlf-lease-metric"><span class="hlf-lease-metric-label">보증금</span><span class="hlf-lease-metric-value"><?php echo esc_html( number_format( (float) $item['deposit_manwon'] ) ); ?>만원</span></div>
			<div class="hlf-lease-metric"><span class="hlf-lease-metric-label">임대료</span><span class="hlf-lease-metric-value"><?php echo esc_html( number_format( (float) $item['monthly_rent_manwon'] ) ); ?>만원</span></div>
			<div class="hlf-lease-metric"><span class="hlf-lease-metric-label">관리비</span><span class="hlf-lease-metric-value"><?php echo esc_html( number_format( (float) $item['maintenance_fee_manwon'] ) ); ?>만원</span></div>
			<div class="hlf-lease-metric"><span class="hlf-lease-metric-label">환산임대료(NOC)</span><span class="hlf-lease-metric-value"><?php echo esc_html( number_format( $metrics['noc'], 1 ) ); ?>만원/전용평</span></div>
		</section>

		<?php if ( $item['features'] ) : ?>
			<p class="hlf-features"><?php echo esc_html( $item['features'] ); ?></p>
		<?php endif; ?>

		<dl class="hlf-property-details">
			<?php foreach ( $basic as $label => $value ) : ?>
				<div class="hlf-basic-item">
					<dt><?php echo esc_html( $label ); ?></dt>
					<dd><?php echo esc_html( $value ); ?></dd>
				</div>
			<?php endforeach; ?>
		</dl>

		<?php if ( $item['contact_name'] || $item['contact_phone'] ) : ?>
			<p class="hlf-contact">
				담당: <?php echo esc_html( $item['contact_name'] ); ?>
				<?php if ( $item['contact_phone'] ) : ?>
					<a href="<?php echo esc_attr( 'tel:' . preg_replace( '/[^0-9+]/', '', $item['contact_phone'] ) ); ?>"><?php echo esc_html( $item['contact_phone'] ); ?></a>
				<?php endif; ?>
			</p>
		<?php endif; ?>

		<footer class="hlf-footer">
			<span>© HINT <?php echo esc_html( gmdate( 'Y' ) ); ?> · <?php echo esc_html( $flyer['flyer_number'] ); ?></span>
		</footer>
	</div>
</body>
</html>
<?php
