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
		statusLabel: statusLabel,
		statusBadgeClass: statusBadgeClass,
		formatManwon: formatManwon,
		formatNumber1: formatNumber1,
	};
} )();
