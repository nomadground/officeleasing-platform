/**
 * hlf/v1 REST 호출 + 공용 포맷 헬퍼. admin-flyer-list.js / admin-flyer-edit.js가 공유한다.
 * 빌드 없이 <script> 태그로 그대로 로드되므로 전역 네임스페이스 하나(window.HLFAdmin)만 만든다.
 * 인증은 워드프레스 쿠키 + X-WP-Nonce 헤더(wp_create_nonce('wp_rest'), HLF_ADMIN.nonce로 전달).
 */
( function () {
	'use strict';

	function apiFetch( path, options ) {
		options = options || {};
		var headers = Object.assign(
			{ 'X-WP-Nonce': HLF_ADMIN.nonce },
			options.body ? { 'Content-Type': 'application/json' } : {},
			options.headers || {}
		);
		return fetch( HLF_ADMIN.restUrl + path, Object.assign( { credentials: 'same-origin' }, options, { headers: headers } ) )
			.then( function ( response ) {
				return response.json().catch( function () { return {}; } ).then( function ( body ) {
					if ( ! response.ok ) {
						var message = ( body && body.message ) ? body.message : ( 'HTTP ' + response.status );
						var err = new Error( message );
						err.status = response.status;
						err.body = body;
						throw err;
					}
					return body;
				} );
			} );
	}

	function escapeHtml( value ) {
		var div = document.createElement( 'div' );
		div.textContent = value === null || value === undefined ? '' : String( value );
		return div.innerHTML;
	}

	// escapeHtml()은 텍스트 노드 콘텐츠(&, <, >)만 안전하다 — 따옴표는 그대로 남기므로
	// value="' + escapeHtml(x) + '"' 처럼 속성값 자리에 쓰면 "나 '로 속성을 깨고 나가는
	// 스토어드 XSS가 가능하다(예: 콘텐츠에 "><img src=x onerror=alert(1)> 저장). 속성값
	// 자리에는 반드시 이 escapeAttr()을 쓴다 — &/</>는 escapeHtml과 동일하게, 그 위에
	// 남은 리터럴 따옴표를 엔티티로 추가 치환한다.
	function escapeAttr( value ) {
		return escapeHtml( value )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

	var STATUS_LABELS = { draft: '미발행', published: '발행됨', archived: '보관' };
	var STATUS_CLASSES = { draft: 'hlf-badge--draft', published: 'hlf-badge--published', archived: 'hlf-badge--archived' };

	function statusLabel( status ) { return STATUS_LABELS[ status ] || status; }
	function statusBadgeClass( status ) { return 'hlf-badge ' + ( STATUS_CLASSES[ status ] || '' ); }

	function formatManwon( value ) {
		var n = Number( value );
		if ( ! isFinite( n ) ) { return '-'; }
		return n.toLocaleString( 'ko-KR' ) + '만원';
	}

	function formatNumber1( value ) {
		var n = Number( value );
		return isFinite( n ) ? n.toFixed( 1 ) : '-';
	}

	window.HLFAdmin = {
		apiFetch: apiFetch,
		escapeHtml: escapeHtml,
		escapeAttr: escapeAttr,
		statusLabel: statusLabel,
		statusBadgeClass: statusBadgeClass,
		formatManwon: formatManwon,
		formatNumber1: formatNumber1,
	};
} )();
