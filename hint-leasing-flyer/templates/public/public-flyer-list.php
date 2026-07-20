<?php
/**
 * 공개 Flyer 목록 — 매물 비교 리스트(표/장부형).
 * $hlf_context: ['flyer'=>[], 'status'=>string, 'items'=>[[...]]] — HLF_Routes::render()가 주입.
 *
 * 구조 원칙(불변): 최대 10개 매물의 보증금/임대료/관리비/NOC를 세로로 정렬해 한눈에 비교하는
 * 장부형 리스트. 카드형 그리드로 바꾸지 않는다(Flyer는 탐색 사이트가 아니라 비교·제안 문서).
 * 이번 라운드는 officeleasing v2 톤만 입히는 Visual Migration이라 데이터/계산은 그대로 사용한다.
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
		<p class="hlf-result-count"><?php echo esc_html( count( $items ) ); ?>개 매물 · 보증금/임대료/관리비/환산임대료(NOC) 비교</p>

		<?php if ( empty( $items ) ) : ?>
			<p class="hlf-empty">등록된 매물이 없습니다.</p>
		<?php else : ?>
			<ul class="hlf-listing-grid">
				<?php foreach ( $items as $i => $item ) :
					$metrics    = $item['metrics'];
					$detail_url = HLF_Routes::item_url( $flyer['id'], $item['item_number'] );
					$road       = $item['road_address'] ?: $item['lot_address'];
					$lot        = ( $item['lot_address'] && $item['lot_address'] !== $road ) ? $item['lot_address'] : '';
					?>
					<li class="hlf-listing-card">
						<a class="hlf-listing-link" href="<?php echo esc_url( $detail_url ); ?>">
							<span class="hlf-listing-index"><?php echo esc_html( sprintf( '%02d', $i + 1 ) ); ?></span>
							<span class="hlf-listing-address">
								<span class="hlf-listing-thumb"><?php
									if ( ! empty( $item['exterior_image_id'] ) ) {
										echo wp_get_attachment_image( (int) $item['exterior_image_id'], 'hlf-item-thumb', false, array( 'alt' => esc_attr( $road ), 'loading' => 'lazy' ) );
									}
								?></span>
								<span class="hlf-listing-address-copy">
									<span class="hlf-listing-road"><?php echo esc_html( $road ); ?></span>
									<?php if ( $lot ) : ?>
										<span class="hlf-listing-lot"><?php echo esc_html( $lot ); ?></span>
									<?php endif; ?>
								</span>
							</span>
							<span class="hlf-listing-area">
								<span class="hlf-listing-area-line"><b>임대</b><strong><?php echo esc_html( number_format( $metrics['lease_pyeong'], 1 ) ); ?>평</strong></span>
								<span class="hlf-listing-area-line"><b>전용</b><strong><?php echo esc_html( number_format( $metrics['exclusive_pyeong'], 1 ) ); ?>평</strong></span>
							</span>
							<span class="hlf-listing-metrics">
								<span class="hlf-metric">
									<span class="hlf-metric-label">보증금</span>
									<span class="hlf-metric-value"><?php echo esc_html( number_format( (float) $item['deposit_manwon'] ) ); ?><small>만원</small></span>
								</span>
								<span class="hlf-metric">
									<span class="hlf-metric-label">임대료</span>
									<span class="hlf-metric-value"><?php echo esc_html( number_format( (float) $item['monthly_rent_manwon'] ) ); ?><small>만원</small></span>
								</span>
								<span class="hlf-metric">
									<span class="hlf-metric-label">관리비</span>
									<span class="hlf-metric-value"><?php echo esc_html( number_format( (float) $item['maintenance_fee_manwon'] ) ); ?><small>만원</small></span>
								</span>
								<span class="hlf-metric hlf-metric--noc">
									<span class="hlf-metric-label">NOC</span>
									<span class="hlf-metric-value"><?php echo esc_html( number_format( $metrics['noc'], 1 ) ); ?><small>만원/전용평</small></span>
								</span>
							</span>
							<span class="hlf-listing-arrow" aria-hidden="true">›</span>
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
