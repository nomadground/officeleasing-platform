<?php
/**
 * 공통 사이트 푸터 + 모바일 sticky CTA. 모든 템플릿이 get_footer()로 재사용.
 * </main>은 여기서 닫는다(header.php에서 열림).
 */
defined( 'ABSPATH' ) || exit;

// 회사 정보는 여기서 하드코딩하지 않고 단일 소스(olt_company -> 플러그인 ol_company_info)를 참조한다.
$phone = olt_company( 'phone' );
$tel   = olt_tel_href( $phone );
?>
</main>

<footer class="olx-footer">
	<div class="olx-wrap">
		<div class="olx-footer-brand">
			<b><?php echo esc_html( olt_company( 'brand' ) ); ?></b>
		</div>
		<div>
			<p><?php echo esc_html( olt_company( 'legal_name' ) ); ?></p>
			<p><?php echo esc_html( olt_company( 'address_full' ) ); ?></p>
			<p>대표전화 <?php echo esc_html( $phone ); ?></p>
			<p>중개업 등록번호 <?php echo esc_html( olt_company( 'license_number' ) ); ?></p>
		</div>
		<div class="olx-footer-contact">
			<a href="tel:<?php echo esc_attr( $tel ); ?>"><?php echo esc_html( $phone ); ?></a>
			<small>© <?php echo esc_html( date( 'Y' ) ); ?> HINT REAL ESTATE CO., LTD.</small>
		</div>
	</div>
</footer>

<div class="olx-sticky">
	<a href="tel:<?php echo esc_attr( $tel ); ?>">유선문의</a>
	<a href="#contact">온라인 문의</a>
</div>

<?php wp_footer(); ?>
</body>
</html>
