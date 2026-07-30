<?php
/**
 * Flyer 생성/수정 화면 껍데기. flyer_id 쿼리파라미터가 없으면 "새 Flyer" 모드(제목만 입력해
 * 만든 뒤 flyer_id가 붙은 이 화면으로 리다이렉트), 있으면 상세 편집(담당자/상태/Item 관리) 모드로
 * assets/js/admin-flyer-edit.js가 렌더링한다.
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap hlf-admin">
	<h1 class="wp-heading-inline">Flyer 편집</h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . HLF_Admin_UI::LIST_SLUG ) ); ?>" class="page-title-action">← 목록으로</a>
	<hr class="wp-header-end">
	<div id="hlf-flyer-edit-root" class="hlf-admin-root">
		<p class="hlf-admin-loading">불러오는 중…</p>
	</div>
</div>
