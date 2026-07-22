<?php
/**
 * 직원 포털(/listad/) 화면 껍데기. 로그인 + capability 검사는 HLF_Portal::dispatch()가 이 파일을
 * include하기 전에 이미 끝낸 상태다. 4탭(Dashboard/전체 매물/임대안내문/설정) 렌더링과 REST 호출은
 * assets/js/portal.js가 전담한다(admin-listup.js와 같은 구조, 다만 wp-admin이 아니라 이 프론트엔드
 * 템플릿에서 wp.media/OCR을 쓸 수 있어야 하므로 wp_head()/wp_footer()를 직접 호출한다 — 이 플러그인의
 * 다른 공개 템플릿(templates/public/*)은 wp.media가 필요 없어 이 두 훅을 쓰지 않는다는 점과 다르다).
 */
defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>HINT List Up — 직원 포털</title>
	<meta name="robots" content="noindex, nofollow, noarchive">
	<?php wp_head(); ?>
</head>
<body class="hlf-portal hlf-admin">
	<header class="hlf-portal-top">
		<div class="hlf-portal-brand">HINT <small>List Up</small></div>
		<div class="hlf-portal-topright">
			<span class="hlf-portal-staff-label" id="hlf-portal-staff-label"></span>
			<button type="button" class="hlf-portal-staff-change" id="hlf-portal-staff-change">담당자 변경</button>
			<a class="hlf-portal-logout" href="<?php echo esc_url( wp_logout_url( HLF_Portal::portal_url() ) ); ?>">로그아웃</a>
		</div>
	</header>
	<div class="hlf-portal-layout">
		<nav class="hlf-portal-nav">
			<button type="button" class="hlf-portal-navbtn" data-hlf-tab="dashboard">Dashboard</button>
			<button type="button" class="hlf-portal-navbtn" data-hlf-tab="sources">전체 매물</button>
			<button type="button" class="hlf-portal-navbtn" data-hlf-tab="flyers">임대안내문</button>
			<button type="button" class="hlf-portal-navbtn" data-hlf-tab="settings">설정</button>
		</nav>
		<main class="hlf-portal-main" id="hlf-portal-root">
			<p class="hlf-admin-loading">불러오는 중…</p>
		</main>
	</div>
	<?php wp_footer(); ?>
</body>
</html>
