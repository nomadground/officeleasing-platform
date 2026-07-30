<?php
/**
 * 공통 사이트 헤더. 모든 템플릿이 get_header()로 재사용한다.
 * 로고 + GNB(ABOUT / FOR LEASE / CONTACT) + 권역 필터 바.
 * GNB는 Appearance→Menus의 primary 메뉴를 쓰고, 메뉴 미설정 시에만 아래 fallback을 렌더한다
 * (front-page.php 등 개별 템플릿에 메뉴를 하드코딩하지 않는다).
 */
defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#355c73">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'olx' ); ?>>
<?php wp_body_open(); ?>

<header class="olx-site-header">
	<div class="olx-sitebar olx-wrap">
		<a class="olx-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Office Leasing 홈">
			<span><b>OFFICE LEASING</b></span>
		</a>
		<nav class="olx-gnb" aria-label="주요 메뉴">
			<?php
			if ( has_nav_menu( 'primary' ) ) {
				wp_nav_menu( array(
					'theme_location' => 'primary',
					'container'      => false,
					'items_wrap'     => '%3$s',
					'walker'         => new Olt_Gnb_Walker(),
					'fallback_cb'    => false,
					'depth'          => 1,
				) );
			} else {
				// 기본 GNB (Appearance→Menus 미설정 시의 fallback). 확정 메뉴는 ABOUT / FOR LEASE / CONTACT
				// (CHECKLIST는 기능 추가 시점에 넣는다).
				//
				// 존재하지 않는 페이지로 링크하거나 임시 # 링크를 만들지 않는다:
				// - FOR LEASE는 URL 문자열을 조립하지 않고 get_post_type_archive_link()로 실제 URL을 얻는다.
				// - ABOUT/CONTACT는 해당 슬러그의 페이지가 실제로 있을 때만 렌더한다.
				//   (CONTACT 페이지가 없으면 메인의 상담 섹션 앵커로 대체 — 실제로 존재하는 경로)
				$gnb = array();

				$about_page = get_page_by_path( 'about' );
				if ( $about_page ) {
					$gnb['ABOUT'] = get_permalink( $about_page );
				}

				$archive_url = get_post_type_archive_link( 'building' );
				if ( $archive_url ) {
					$gnb['FOR LEASE'] = $archive_url;
				}

				$contact_page = get_page_by_path( 'contact' );
				if ( $contact_page ) {
					$gnb['CONTACT'] = get_permalink( $contact_page );
				} elseif ( is_front_page() ) {
					$gnb['CONTACT'] = '#contact';
				}

				foreach ( $gnb as $label => $url ) {
					printf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( $label ) );
				}
			}
			?>
		</nav>
	</div>

	<?php get_template_part( 'template-parts/region-bar' ); ?>
</header>

<main class="olx-wrap">
