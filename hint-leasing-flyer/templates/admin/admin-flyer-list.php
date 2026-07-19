<?php
/**
 * Flyer 목록 화면 껍데기. 실제 목록 조회/생성/삭제는 assets/js/admin-flyer-list.js가
 * hlf/v1 REST를 fetch로 호출해 #hlf-flyer-list-root 안에 렌더링한다.
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap hlf-admin">
	<h1 class="wp-heading-inline">Leasing Flyer</h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . HLF_Admin_UI::EDIT_SLUG ) ); ?>" class="page-title-action">새 Flyer 만들기</a>
	<hr class="wp-header-end">
	<div id="hlf-flyer-list-root" class="hlf-admin-root">
		<p class="hlf-admin-loading">불러오는 중…</p>
	</div>
</div>
