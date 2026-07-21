<?php
/**
 * 공개 Flyer 목록 (Phase 1 최소 골격). 실제 디자인/차트/지도/인쇄는 Phase 4+에서.
 * $hlf_context: ['flyer'=>[], 'status'=>string, 'items'=>[[...]]] — HLF_Routes::render()가 주입.
 *
 * 서버 렌더링 이유(요청서 1-E): SEO/공유 안정성, 공개 데이터 노출 최소화.
 */
defined( 'ABSPATH' ) || exit;

$flyer  = $hlf_context['flyer'];
$status = $hlf_context['status'];
$items  = $hlf_context['items'];

// draft 미리보기 및 archived는 검색엔진 색인 대상에서 제외.
$noindex = ( 'published' !== $status );
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( $flyer['title'] . ' · ' . $flyer['flyer_number'] ); ?></title>
	<?php if ( $noindex ) : ?>
		<meta name="robots" content="noindex,nofollow">
	<?php endif; ?>
	<link rel="canonical" href="<?php echo esc_url( $flyer['url'] ); ?>">
	<link rel="stylesheet" href="<?php echo esc_url( HLF_URL . 'assets/css/public.css?v=' . HLF_VERSION ); ?>">
	<link rel="stylesheet" href="<?php echo esc_url( HLF_URL . 'assets/css/print.css?v=' . HLF_VERSION ); ?>" media="print">
</head>
<body class="hlf-public hlf-list">
	<div class="hlf-shell">
		<header class="hlf-header">
			<span class="hlf-brand">HINT Leasing Flyer</span>
			<span class="hlf-flyer-number"><?php echo esc_html( $flyer['flyer_number'] ); ?></span>
			<?php if ( 'archived' === $status ) : ?>
				<span class="hlf-badge hlf-badge--archived">보관된 목록</span>
			<?php elseif ( 'draft' === $status ) : ?>
				<span class="hlf-badge hlf-badge--draft">미발행 미리보기</span>
			<?php endif; ?>
		</header>

		<h1 class="hlf-title"><?php echo esc_html( $flyer['title'] ); ?></h1>
		<p class="hlf-result-count"><?php echo esc_html( count( $items ) ); ?>개 매물</p>

		<?php if ( empty( $items ) ) : ?>
			<p class="hlf-empty">등록된 매물이 없습니다.</p>
		<?php else : ?>
			<ul class="hlf-listing-grid">
				<?php foreach ( $items as $i => $item ) :
					$metrics = $item['metrics'];
					$detail_url = HLF_Routes::item_url( $flyer['id'], $item['item_number'] );
					$address = $item['road_address'] ?: $item['lot_address'];
					?>
					<li class="hlf-listing-card">
						<a class="hlf-listing-link" href="<?php echo esc_url( $detail_url ); ?>">
							<span class="hlf-listing-index"><?php echo esc_html( sprintf( '%02d', $i + 1 ) ); ?></span>
							<span class="hlf-listing-thumb"><?php
								if ( ! empty( $item['exterior_image_id'] ) ) {
									echo wp_get_attachment_image( (int) $item['exterior_image_id'], 'hlf-item-thumb', false, array( 'alt' => esc_attr( $address ), 'loading' => 'lazy' ) );
								}
							?></span>
							<span class="hlf-listing-address"><?php echo esc_html( $address ); ?></span>
							<span class="hlf-lease-metrics">
								<span class="hlf-lease-metric">
									<span class="hlf-lease-metric-label">보증금</span>
									<span class="hlf-lease-metric-value"><?php echo esc_html( number_format( (float) $item['deposit_manwon'] ) ); ?>만원</span>
								</span>
								<span class="hlf-lease-metric">
									<span class="hlf-lease-metric-label">임대료</span>
									<span class="hlf-lease-metric-value"><?php echo esc_html( number_format( (float) $item['monthly_rent_manwon'] ) ); ?>만원</span>
								</span>
								<span class="hlf-lease-metric">
									<span class="hlf-lease-metric-label">관리비</span>
									<span class="hlf-lease-metric-value"><?php echo esc_html( number_format( (float) $item['maintenance_fee_manwon'] ) ); ?>만원</span>
								</span>
								<span class="hlf-lease-metric">
									<span class="hlf-lease-metric-label">환산임대료(NOC)</span>
									<span class="hlf-lease-metric-value"><?php echo esc_html( number_format( $metrics['noc'], 1 ) ); ?>만원/전용평</span>
								</span>
							</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php $contact = HLF_Flyer_Repository::public_contact( $flyer ); ?>
		<footer class="hlf-footer">
			<span>© HINT <?php echo esc_html( gmdate( 'Y' ) ); ?> · <?php echo esc_html( $flyer['flyer_number'] ); ?></span>
			<span class="hlf-footer-contact">
				<?php if ( $contact['name'] ) : ?>
					<?php echo esc_html( $contact['name'] ); ?> ·
				<?php endif; ?>
				<a href="<?php echo esc_attr( 'tel:' . preg_replace( '/[^0-9+]/', '', $contact['phone'] ) ); ?>"><?php echo esc_html( $contact['phone'] ); ?></a>
			</span>
		</footer>
	</div>
</body>
</html>
<?php
