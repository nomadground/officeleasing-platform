<?php
/**
 * HINT List Up 통합 관리 화면 껍데기. 4탭(Dashboard/전체 매물/임대안내문/설정) 렌더링과 모든
 * 데이터 입출력은 assets/js/admin-listup.js가 hlf/v1 REST를 fetch로 호출해 처리한다.
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap hlf-admin">
	<h1 class="wp-heading-inline">HINT List Up</h1>
	<hr class="wp-header-end">
	<div id="hlf-listup-root" class="hlf-admin-root">
		<p class="hlf-admin-loading">불러오는 중…</p>
	</div>
</div>
