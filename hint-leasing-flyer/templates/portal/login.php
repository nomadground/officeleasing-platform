<?php
/**
 * 직원 포털(/listad/) 로그인 화면. 미로그인 사용자에게만 노출된다(HLF_Portal::dispatch()).
 * 인증은 워드프레스 네이티브(wp_signon)만 쓴다 — 이 파일은 폼(마크업)만 담당하고, 실제 인증
 * 처리는 HLF_Portal::handle_login_request()가 이 파일이 include되기 전에 이미 끝낸 상태다
 * (성공하면 그 안에서 바로 redirect+exit하므로 이 파일까지 오지 않는다).
 */
defined( 'ABSPATH' ) || exit;

$login_error   = HLF_Portal::login_error();
$portal_url    = HLF_Portal::portal_url();
$prefill_login = isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- 로그인 실패 후 아이디만 재표시(비밀번호는 절대 재표시하지 않음), 값 자체는 아래에서 esc_attr로 출력.
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>HINT List Up — 로그인</title>
	<meta name="robots" content="noindex, nofollow, noarchive">
	<link rel="stylesheet" href="<?php echo esc_url( HLF_URL . 'assets/css/portal.css?v=' . HLF_VERSION ); ?>">
</head>
<body class="hlf-portal hlf-portal-login">
	<div class="hlf-login-shell">
		<form class="hlf-login-card" method="post" action="<?php echo esc_url( $portal_url ); ?>" autocomplete="off">
			<div class="hlf-login-brand">HINT <small>List Up</small></div>
			<p class="hlf-login-sub">직원 포털 로그인</p>
			<?php if ( '' !== $login_error ) : ?>
				<p class="hlf-login-error"><?php echo esc_html( $login_error ); ?></p>
			<?php endif; ?>
			<div class="hlf-field">
				<label for="hlf-login-log">아이디 또는 이메일</label>
				<input id="hlf-login-log" type="text" name="log" value="<?php echo esc_attr( $prefill_login ); ?>" autocomplete="username" required autofocus>
			</div>
			<div class="hlf-field">
				<label for="hlf-login-pwd">비밀번호</label>
				<input id="hlf-login-pwd" type="password" name="pwd" autocomplete="current-password" required>
			</div>
			<input type="hidden" name="hlf_portal_login_submit" value="1">
			<?php wp_nonce_field( HLF_Portal::LOGIN_NONCE_ACTION ); ?>
			<button type="submit" class="hlf-login-submit">로그인</button>
		</form>
	</div>
</body>
</html>
