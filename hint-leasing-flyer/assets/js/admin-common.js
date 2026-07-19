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
						// body.message는 서버(WP_Error)가 이미 직원이 이해할 수 있는 문구로 내려준다.
						// 그게 없는 경우(네트워크 중간 장비 차단, PHP 치명적 오류 등 JSON 바디 자체가
						// 없는 응답)에만 "HTTP 500" 같은 개발자용 문자열 대신 이 기본 문구를 쓴다.
						var message = ( body && body.message )
							? body.message
							: ( response.status >= 500
								? '서버에 문제가 발생했습니다(오류 코드 ' + response.status + '). 잠시 후 다시 시도해 주세요.'
								: '요청을 처리하지 못했습니다(오류 코드 ' + response.status + ').' );
						var err = new Error( message );
						err.status = response.status;
						err.body = body;
						throw err;
					}
					return body;
				} );
			} );
	}

	// DOM 엘리먼트를 만들어 textContent→innerHTML 왕복으로 이스케이프하던 이전 방식은 렌더링마다
	// (필드 수 × 항목 수만큼) 불필요한 <div>를 생성했다 — 순수 문자열 치환으로도 결과가 동일하므로
	// 이렇게 바꾼다. 순서가 중요하다: 치환으로 새로 생긴 "&"를 다시 이스케이프하지 않도록 반드시 "&"를
	// 가장 먼저 치환해야 한다.
	function escapeHtml( value ) {
		if ( value === null || value === undefined ) { return ''; }
		return String( value )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' );
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
