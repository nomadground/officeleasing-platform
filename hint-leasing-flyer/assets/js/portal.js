/**
 * 직원 포털(/listup/) 화면. HINT List Up 관리자 화면(admin-listup.js)과 거의 동일한 4탭
 * 구조·REST 호출을 그대로 재사용한다(Repository/REST Controller/Snapshot 로직은 전혀 새로
 * 만들지 않는다) — 차이는 두 가지뿐이다:
 *   1) 이 화면은 wp-admin이 아니라 프론트엔드 템플릿(templates/portal/portal.php)에 얹히므로
 *      좌측 nav는 이 파일이 매번 다시 그리지 않고 portal.php가 이미 그려 둔 정적 버튼을 그대로 쓴다.
 *   2) "담당자"는 워드프레스 계정이 아니라 이 기기(브라우저)에 저장한 선택값이다 — 최초 Dashboard
 *      진입 시 담당자를 고르게 하고, 신규 매물/안내문 생성 폼에만 그 담당자의 이름·연락처를
 *      자동으로 채운다(이미 저장된 값은 절대 덮어쓰지 않는다 — admin-listup.js의 defaultContact()와
 *      같은 자리에 currentStaffContact()를 대신 꽂아 넣었을 뿐, 그 외 자동채움 규칙은 동일하다).
 *
 * window.HLF_PORTAL(restUrl/nonce/portalUrl/sourcePreviewUrlBase/maxItems/defaultPhone/user/isAdmin)은
 * HLF_Portal::maybe_enqueue_assets()가 로컬라이즈한다. OCR은 admin-ocr.js(window.HLFOcr)를,
 * 이스케이프/포맷 헬퍼는 admin-common.js(window.HLFAdmin)를 그대로 재사용한다 — REST 호출만 이 파일
 * 안에 독립된 api()로 새로 둔다(admin-common.js의 apiFetch는 window.HLF_ADMIN을 참조하므로 이 화면과
 * 맞지 않는다, admin-common.js 자체는 수정하지 않는다).
 */
( function () {
	'use strict';

	var root = document.getElementById( 'hlf-portal-root' );
	if ( ! root ) { return; }
	var A = window.HLFAdmin;
	var CONF = window.HLF_PORTAL;
	var STAFF_KEY = 'hlf_portal_staff_selection';

	var SOURCE_FIELDS = [
		{ key: 'floor_current', label: '해당층', type: 'text', placeholder: '예: 3 또는 B1' },
		{ key: 'floor_total', label: '총층', type: 'text', placeholder: '예: 6' },
		{ key: 'lease_area_sqm', label: '공급면적 (㎡)', type: 'number', step: '0.01' },
		{ key: 'exclusive_area_sqm', label: '전용면적 (㎡)', type: 'number', step: '0.01' },
		{ key: 'deposit_manwon', label: '보증금 (만원)', type: 'number', step: 'any' },
		{ key: 'monthly_rent_manwon', label: '임대료 (만원)', type: 'number', step: 'any' },
		{ key: 'maintenance_fee_manwon', label: '관리비 (만원)', type: 'number', step: 'any' },
		{ key: 'parking_available', label: '주차 가능', type: 'checkbox' },
		{ key: 'elevator_available', label: '엘리베이터 있음', type: 'checkbox' },
		// 요청서: 주차 가능일 때 지금 쓸 수 있는 대수를 따로 받아 Property Details 카드에
		// "가능주차대수/총주차대수"로 보여준다.
		{ key: 'available_parking', label: '가능주차대수', type: 'number', step: '1', placeholder: '예: 3' },
		{ key: 'total_parking', label: '총주차대수', type: 'text', placeholder: '예: 자주식 10대' },
		{ key: 'direction', label: '방향', type: 'text' },
		{ key: 'approval_date', label: '사용승인일', type: 'text', placeholder: '예: 2018.06.21' },
		{ key: 'building_use', label: '건축물용도', type: 'text' },
		{ key: 'illegal_building', label: '위반건축물 여부', type: 'checkbox' },
		{ key: 'available_date_text', label: '입주가능일', type: 'text', placeholder: '예: 즉시입주 협의가능' },
		{ key: 'features', label: '매물특징', type: 'textarea', wide: true },
		{ key: 'contact_name', label: '담당자명', type: 'text' },
		{ key: 'contact_phone', label: '담당자 연락처', type: 'text' }
	];
	var CHECKBOX_ROW_KEYS = [ 'parking_available', 'elevator_available' ];
	var ADDRESS_SEARCH_DEBOUNCE_MS = 700;
	// 서버(HLF_Item_Repository::MAX_IMAGES)와 같은 상한 — 대표 1장 + 슬라이드 3장(대표 포함 4장).
	var HLF_MAX_IMAGES = 4;

	var OFFICELEASING_STATUS_CHOICES = [
		{ value: '', label: '전체 상태' },
		{ value: 'available', label: '임대가능' },
		{ value: 'reserved', label: '협의중' },
		{ value: 'contract_pending', label: '계약진행중' },
		{ value: 'leased', label: '거래완료' },
		{ value: 'temporarily_hidden', label: '노출중지' },
		{ value: 'expired', label: '만료' }
	];
	var importState = { open: false, results: null, importingId: null, lastQuery: { search: '', status: '' } };

	var state = {
		tab: 'dashboard',
		contacts: null,
		flyers: null,
		sourceFilter: ''
	};

	/* ==================== 공통 ==================== */

	function api( path, options ) {
		options = options || {};
		var headers = Object.assign(
			{ 'X-WP-Nonce': CONF.nonce },
			options.body ? { 'Content-Type': 'application/json' } : {},
			options.headers || {}
		);
		return fetch( CONF.restUrl + path, Object.assign( { credentials: 'same-origin' }, options, { headers: headers } ) )
			.then( function ( response ) {
				return response.json().catch( function () { return {}; } ).then( function ( body ) {
					if ( ! response.ok ) {
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
	function esc( v ) { return A.escapeHtml( v ); }
	function escAttr( v ) { return A.escapeAttr( v ); }
	function won( v ) { return A.formatManwon( v ); }
	function num( v ) { return ( v === null || v === undefined || v === '' ) ? '-' : v; }
	function toast( msg ) {
		var el = document.createElement( 'div' );
		el.className = 'hlf-toast';
		el.textContent = msg;
		document.body.appendChild( el );
		setTimeout( function () { el.remove(); }, 1800 );
	}
	function errorText( container, message ) {
		container.innerHTML = '<p class="hlf-admin-error">' + esc( message ) + '</p>';
	}

	function loadContacts( cb ) {
		if ( state.contacts ) { cb( state.contacts ); return; }
		api( 'contacts' ).then( function ( d ) { state.contacts = d; cb( d ); } ).catch( function () { cb( { contacts: [], default_index: null } ); } );
	}
	function loadFlyers( cb ) {
		api( 'flyers?per_page=100' ).then( function ( list ) { state.flyers = list; cb( list ); } ).catch( function () { cb( [] ); } );
	}

	/* ==================== 담당자(이 기기의 선택값 — 워드프레스 계정과 무관) ==================== */

	function loadStaffSelection() {
		try {
			var raw = window.localStorage.getItem( STAFF_KEY );
			return raw ? JSON.parse( raw ) : null;
		} catch ( e ) { return null; }
	}
	function saveStaffSelection( sel ) {
		try { window.localStorage.setItem( STAFF_KEY, JSON.stringify( sel ) ); } catch ( e ) { /* localStorage 불가(프라이빗 모드 등) — 이번 세션만 미지정으로 동작 */ }
	}
	/** 신규 매물/안내문 생성 폼에 채울 담당자 이름·연락처. 미선택/"미지정(HINT)"이면 빈 값(서버의
	 *  대표번호 fallback이 자연스럽게 적용된다 — HLF_Flyer_Repository::DEFAULT_PHONE). */
	function currentStaffContact( dir ) {
		var sel = loadStaffSelection();
		if ( ! sel || null === sel.index || undefined === sel.index ) { return { name: '', phone: '' }; }
		var c = dir.contacts[ sel.index ];
		return c ? { name: c.name, phone: c.phone } : { name: '', phone: '' };
	}
	// 요청서: "전체 매물"/"포함 매물 관리" 목록이 커지면 로딩이 느려지므로, 기본값으로 이 기기에
	// 선택된 담당자("나") 매물만 먼저 보여준다 — 담당자를 아직 선택하지 않았으면(미지정) 필터링할
	// "나"가 없으므로 처음부터 전체를 보여준다.
	function preferredContactName( dir ) {
		var c = currentStaffContact( dir );
		return ( c && c.name ) ? c.name : '';
	}

	// 담당자 드롭다운 + "전체 보기" 토글 마크업(전체 매물/포함 매물 관리 공용).
	function contactFilterHtml( dir, selected, showAll, idPrefix ) {
		var contacts = ( dir && dir.contacts ) || [];
		if ( ! contacts.length ) { return ''; }
		var options = contacts.map( function ( c ) {
			return '<option value="' + escAttr( c.name ) + '"' + ( c.name === selected ? ' selected' : '' ) + '>' + esc( c.name ) + '</option>';
		} ).join( '' );
		return '<div class="hlf-contact-filter">' +
			'<select id="' + idPrefix + '-contact"' + ( showAll ? ' disabled' : '' ) + '>' + options + '</select> ' +
			'<button type="button" class="button' + ( showAll ? ' is-active' : '' ) + '" id="' + idPrefix + '-showall">' + ( showAll ? '내 매물만 보기' : '전체 보기' ) + '</button>' +
		'</div>';
	}

	function staffLabel( dir ) {
		var sel = loadStaffSelection();
		if ( ! sel || null === sel.index || undefined === sel.index ) { return '미지정 (HINT)'; }
		var c = dir.contacts[ sel.index ];
		return c ? c.name : '미지정 (HINT)';
	}
	function updateStaffLabel( dir ) {
		var label = document.getElementById( 'hlf-portal-staff-label' );
		if ( label ) { label.textContent = '담당자: ' + staffLabel( dir ); }
	}
	function openStaffPicker( onDone ) {
		loadContacts( function ( dir ) {
			var overlay = document.createElement( 'div' );
			overlay.className = 'hlf-portal-modal-overlay';
			overlay.innerHTML =
				'<div class="hlf-portal-modal">' +
					'<h3>담당자를 선택하세요</h3>' +
					'<p class="hlf-admin-note">이 기기에서 새 매물·안내문을 등록할 때 담당자 이름과 연락처가 자동으로 채워집니다. 언제든 우측 상단 "담당자 변경"으로 바꿀 수 있습니다.</p>' +
					'<select id="hlf-portal-staff-select">' +
						'<option value="">미지정 (HINT)</option>' +
						dir.contacts.map( function ( c ) {
							return '<option value="' + c.index + '">' + escAttr( c.name || '(이름 없음)' ) + '</option>';
						} ).join( '' ) +
					'</select>' +
					'<div class="hlf-form-actions"><button type="button" class="button button-primary" id="hlf-portal-staff-confirm">확인</button></div>' +
				'</div>';
			document.body.appendChild( overlay );

			var current = loadStaffSelection();
			var select = overlay.querySelector( '#hlf-portal-staff-select' );
			if ( current && null !== current.index && undefined !== current.index ) { select.value = String( current.index ); }

			overlay.querySelector( '#hlf-portal-staff-confirm' ).addEventListener( 'click', function () {
				var v = select.value;
				saveStaffSelection( { index: '' === v ? null : Number( v ) } );
				overlay.remove();
				updateStaffLabel( dir );
				if ( onDone ) { onDone(); }
			} );
		} );
	}

	/* ==================== 셸(정적 nav는 portal.php가 이미 그려 둠) ==================== */

	function setTab( tab ) {
		state.tab = tab;
		document.querySelectorAll( '.hlf-portal-navbtn' ).forEach( function ( btn ) {
			btn.classList.toggle( 'is-active', btn.getAttribute( 'data-hlf-tab' ) === tab );
		} );
		renderTab();
	}

	function main() { return root; }

	function renderTab() {
		if ( 'dashboard' === state.tab ) { return renderDashboard(); }
		if ( 'sources' === state.tab ) {
			var pendingFilter = state.sourceFilter;
			state.sourceFilter = '';
			return renderSourceList( '', pendingFilter );
		}
		if ( 'flyers' === state.tab ) { return renderFlyerList(); }
		if ( 'settings' === state.tab ) { return renderSettings(); }
	}

	/* ==================== Dashboard ==================== */

	// 성능 리뷰(2026-07-22): 통계(dashboard)와 임대안내문 표(flyers)는 서로 다른, 값을 주고받을
	// 필요 없는 REST 호출이다 — 예전에는 dashboard 응답을 기다린 "다음에" flyers를 불렀는데(순차
	// waterfall), 그럴 이유가 없어 shell을 먼저 그리고 두 요청을 동시에 시작한다.
	function renderDashboard() {
		var el = main();
		el.innerHTML =
			'<h2 class="hlf-listup-title">Dashboard</h2>' +
			'<div class="hlf-stat-grid" id="hlf-dash-stats"><p class="hlf-admin-loading">불러오는 중…</p></div>' +
			'<p class="hlf-admin-note">“연결된 매물”은 하나 이상의 임대안내문에 포함된 매물, “미연결”은 아직 어떤 안내문에도 들어가지 않은 매물입니다. 숫자를 클릭하면 해당 목록으로 이동합니다.</p>' +
			'<section class="hlf-card"><h3>임대안내문</h3><div id="hlf-dash-flyers"><p class="hlf-admin-loading">불러오는 중…</p></div></section>';

		var statsBox = document.getElementById( 'hlf-dash-stats' );
		api( 'dashboard' ).then( function ( d ) {
			statsBox.innerHTML =
				statCard( d.source_total, '전체 매물', 'sources', '' ) +
				statCard( d.flyer_total, '임대안내문', 'flyers', '' ) +
				statCard( d.source_linked, '연결된 매물', 'sources', 'linked' ) +
				statCard( d.source_unlinked, '미연결 매물', 'sources', 'unlinked' );
			statsBox.querySelectorAll( '[data-hlf-stat-tab]' ).forEach( function ( card ) {
				card.addEventListener( 'click', function () {
					state.sourceFilter = card.getAttribute( 'data-hlf-stat-filter' ) || '';
					setTab( card.getAttribute( 'data-hlf-stat-tab' ) );
				} );
			} );
		} ).catch( function ( err ) { errorText( statsBox, '통계를 불러오지 못했습니다: ' + err.message ); } );

		renderDashboardFlyers();
	}
	function statCard( value, label, tab, filter ) {
		return '<button type="button" class="hlf-stat-card" data-hlf-stat-tab="' + tab + '" data-hlf-stat-filter="' + filter + '">' +
			'<strong>' + esc( value ) + '</strong><span>' + esc( label ) + '</span></button>';
	}

	function renderDashboardFlyers() {
		var box = document.getElementById( 'hlf-dash-flyers' );
		if ( ! box ) { return; }
		api( 'flyers?per_page=100' ).then( function ( flyers ) {
			state.flyers = flyers;
			if ( ! flyers.length ) { box.innerHTML = '<p class="hlf-empty">등록된 임대안내문이 없습니다.</p>'; return; }
			box.innerHTML =
				'<div class="hlf-table-wrap"><table class="hlf-table">' +
					'<thead><tr><th>날짜</th><th>번호</th><th>제목</th><th>상태</th><th>매물수</th><th>담당자</th></tr></thead><tbody>' +
					flyers.map( function ( f ) {
						var dateStr = f.created ? f.created.substring( 0, 10 ) : '-';
						return '<tr class="hlf-dash-flyer-row" data-hlf-dash-flyer="' + f.id + '">' +
							'<td>' + esc( dateStr ) + '</td>' +
							'<td>' + esc( f.flyer_number ) + '</td>' +
							'<td>' + esc( f.title || '(제목 없음)' ) + '</td>' +
							'<td><span class="' + A.statusBadgeClass( f.status ) + '">' + esc( A.statusLabel( f.status ) ) + '</span></td>' +
							'<td class="hlf-td-center">' + esc( f.item_count ) + '</td>' +
							'<td class="hlf-td-contact">' + esc( f.contact_name || '-' ) + '</td>' +
						'</tr>';
					} ).join( '' ) +
					'</tbody></table></div>';
			box.querySelectorAll( '[data-hlf-dash-flyer]' ).forEach( function ( row ) {
				row.addEventListener( 'click', function () { goToFlyerManage( Number( row.getAttribute( 'data-hlf-dash-flyer' ) ) ); } );
			} );
		} ).catch( function ( err ) { errorText( box, '임대안내문을 불러오지 못했습니다: ' + err.message ); } );
	}

	function goToFlyerManage( flyerId ) {
		state.tab = 'flyers';
		document.querySelectorAll( '.hlf-portal-navbtn' ).forEach( function ( btn ) {
			btn.classList.toggle( 'is-active', btn.getAttribute( 'data-hlf-tab' ) === 'flyers' );
		} );
		renderFlyerManage( flyerId );
	}

	/* ==================== 전체 매물(원본) 목록 ==================== */

	var SOURCE_FILTERS = [
		{ key: '', label: '전체' },
		{ key: 'linked', label: '연결됨' },
		{ key: 'unlinked', label: '미연결' }
	];

	// contact가 undefined면(탭을 처음 열 때) 담당자 디렉터리를 먼저 읽어 이 기기에 선택된 담당자로
	// 한 번 재호출한다(요청서 — 목록이 커지면 기본값은 "내 매물"만 먼저, "전체 보기"로 전체 전환).
	function renderSourceList( searchTerm, filter, contact, showAll ) {
		filter = filter || '';
		if ( undefined === contact ) {
			loadContacts( function ( dir ) { renderSourceList( searchTerm, filter, preferredContactName( dir ), false ); } );
			return;
		}
		showAll = !! showAll;
		var el = main();
		el.innerHTML =
			'<div class="hlf-listup-head">' +
				'<h2 class="hlf-listup-title">전체 매물</h2>' +
				'<button type="button" class="button button-primary" id="hlf-src-new">+ 새 매물 등록</button>' +
			'</div>' +
			'<div class="hlf-toolbar">' +
				'<input type="search" id="hlf-src-search" placeholder="주소·키워드 검색" value="' + escAttr( searchTerm || '' ) + '">' +
				'<button type="button" class="button" id="hlf-src-search-btn">검색</button>' +
				'<div class="hlf-filter-chips">' +
					SOURCE_FILTERS.map( function ( f ) {
						return '<button type="button" class="hlf-filter-chip' + ( filter === f.key ? ' is-active' : '' ) + '" data-hlf-src-filter="' + f.key + '">' + esc( f.label ) + '</button>';
					} ).join( '' ) +
				'</div>' +
				contactFilterHtml( state.contacts, contact, showAll, 'hlf-src' ) +
			'</div>' +
			'<div class="hlf-bulk-bar" id="hlf-src-bulk" hidden>' +
				'<span id="hlf-src-bulk-count">0개 선택됨</span> → ' +
				'<select id="hlf-src-bulk-flyer"><option value="">임대안내문 선택</option></select> ' +
				'<button type="button" class="button" id="hlf-src-bulk-add">선택 매물 추가</button>' +
			'</div>' +
			'<div id="hlf-src-results"><p class="hlf-admin-loading">불러오는 중…</p></div>';

		document.getElementById( 'hlf-src-new' ).addEventListener( 'click', function () { renderSourceForm( null ); } );
		var searchInput = document.getElementById( 'hlf-src-search' );
		document.getElementById( 'hlf-src-search-btn' ).addEventListener( 'click', function () { renderSourceList( searchInput.value, filter, contact, showAll ); } );
		searchInput.addEventListener( 'keydown', function ( e ) { if ( 'Enter' === e.key ) { renderSourceList( searchInput.value, filter, contact, showAll ); } } );
		el.querySelectorAll( '[data-hlf-src-filter]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () { renderSourceList( searchInput.value, btn.getAttribute( 'data-hlf-src-filter' ), contact, showAll ); } );
		} );
		var contactSelect = document.getElementById( 'hlf-src-contact' );
		if ( contactSelect ) {
			contactSelect.addEventListener( 'change', function () { renderSourceList( searchInput.value, filter, contactSelect.value, false ); } );
		}
		var showAllBtn = document.getElementById( 'hlf-src-showall' );
		if ( showAllBtn ) {
			showAllBtn.addEventListener( 'click', function () {
				renderSourceList( searchInput.value, filter, contactSelect ? contactSelect.value : contact, ! showAll );
			} );
		}

		loadFlyers( function ( flyers ) {
			var sel = document.getElementById( 'hlf-src-bulk-flyer' );
			if ( sel ) {
				sel.innerHTML = '<option value="">임대안내문 선택</option>' + flyers.map( function ( f ) {
					return '<option value="' + f.id + '">' + escAttr( f.title || '(제목 없음)' ) + ' / ' + escAttr( f.flyer_number ) + '</option>';
				} ).join( '' );
			}
		} );

		var q = '' !== ( searchTerm || '' ) ? ( '&search=' + encodeURIComponent( searchTerm ) ) : '';
		q += '' !== filter ? ( '&linked=' + encodeURIComponent( filter ) ) : '';
		q += ( ! showAll && contact ) ? ( '&contact=' + encodeURIComponent( contact ) ) : '';
		api( 'source-listings?per_page=100' + q ).then( function ( data ) {
			renderSourceTable( data.items || [] );
		} ).catch( function ( err ) { errorText( document.getElementById( 'hlf-src-results' ), '목록을 불러오지 못했습니다: ' + err.message ); } );
	}

	function renderSourceTable( items ) {
		var box = document.getElementById( 'hlf-src-results' );
		if ( ! items.length ) { box.innerHTML = '<p class="hlf-empty">등록된 매물이 없습니다.</p>'; return; }
		box.innerHTML =
			'<div class="hlf-table-wrap"><table class="hlf-table">' +
				'<thead><tr>' +
					'<th><input type="checkbox" id="hlf-src-all"></th>' +
					'<th>지번주소</th><th>층</th><th>임대면적</th><th>전용면적</th>' +
					'<th>보증금</th><th>임대료</th><th>관리비</th><th>포함 안내문</th><th>담당자</th><th>작업</th>' +
				'</tr></thead><tbody>' +
				items.map( function ( it ) {
					return '<tr>' +
						'<td><input type="checkbox" class="hlf-src-pick" value="' + it.id + '"></td>' +
						'<td>' + esc( it.lot_address || '-' ) + '<small>' + esc( it.road_address || '' ) + '</small></td>' +
						'<td>' + esc( num( it.floor_current ) ) + '/' + esc( num( it.floor_total ) ) + '</td>' +
						'<td>' + esc( num( it.lease_area_sqm ) ) + '㎡</td>' +
						'<td>' + esc( num( it.exclusive_area_sqm ) ) + '㎡</td>' +
						'<td>' + esc( won( it.deposit_manwon ) ) + '</td>' +
						'<td>' + esc( won( it.monthly_rent_manwon ) ) + '</td>' +
						'<td>' + esc( won( it.maintenance_fee_manwon ) ) + '</td>' +
						'<td class="hlf-td-center">' + esc( it.included_flyer_count ) + '</td>' +
						'<td class="hlf-td-contact">' + esc( it.contact_name || '-' ) + '</td>' +
						'<td class="hlf-row-actions">' +
							'<button type="button" class="button button-small" data-hlf-src-edit="' + it.id + '">수정</button>' +
							'<button type="button" class="button button-small" data-hlf-src-preview="' + it.id + '">미리보기</button>' +
							'<button type="button" class="button button-small" data-hlf-src-copy="' + it.id + '">링크 복사</button>' +
							'<button type="button" class="button button-small hlf-danger" data-hlf-src-delete="' + it.id + '">삭제</button>' +
						'</td>' +
					'</tr>';
				} ).join( '' ) +
				'</tbody></table></div>';

		bindSourceTable( items );
	}

	function bindSourceTable( items ) {
		var box = document.getElementById( 'hlf-src-results' );
		var bulk = document.getElementById( 'hlf-src-bulk' );
		var bulkCount = document.getElementById( 'hlf-src-bulk-count' );
		var all = document.getElementById( 'hlf-src-all' );

		function picked() { return Array.prototype.map.call( box.querySelectorAll( '.hlf-src-pick:checked' ), function ( c ) { return Number( c.value ); } ); }
		function refreshBulk() {
			var n = picked().length;
			bulk.hidden = 0 === n;
			bulkCount.textContent = n + '개 선택됨';
		}
		box.querySelectorAll( '.hlf-src-pick' ).forEach( function ( c ) { c.addEventListener( 'change', refreshBulk ); } );
		if ( all ) { all.addEventListener( 'change', function () { box.querySelectorAll( '.hlf-src-pick' ).forEach( function ( c ) { c.checked = all.checked; } ); refreshBulk(); } ); }

		document.getElementById( 'hlf-src-bulk-add' ).addEventListener( 'click', function () {
			var flyerId = document.getElementById( 'hlf-src-bulk-flyer' ).value;
			var ids = picked();
			if ( ! flyerId ) { toast( '임대안내문을 먼저 선택하세요.' ); return; }
			if ( ! ids.length ) { toast( '매물을 선택하세요.' ); return; }
			bulkAddToFlyer( Number( flyerId ), ids );
		} );

		box.querySelectorAll( '[data-hlf-src-edit]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { renderSourceForm( Number( b.getAttribute( 'data-hlf-src-edit' ) ) ); } ); } );
		box.querySelectorAll( '[data-hlf-src-preview]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { window.open( CONF.sourcePreviewUrlBase + b.getAttribute( 'data-hlf-src-preview' ), '_blank', 'noopener' ); } ); } );
		box.querySelectorAll( '[data-hlf-src-copy]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var id = Number( b.getAttribute( 'data-hlf-src-copy' ) );
				var item = items.filter( function ( x ) { return x.id === id; } )[ 0 ];
				var links = ( item && item.linked_items ) || [];
				// 원본 매물 하나가 여러 안내문에 동시에 포함될 수 있다(class-hlf-source-listing-repository.php
				// 참고) — 어느 안내문의 상세페이지인지 구분할 정보가 목록에는 없으므로, 가장 최근에
				// 포함된(배열 끝) 링크를 복사하고 여러 개일 때는 그 사실을 알린다.
				if ( ! links.length ) { toast( '아직 어느 안내문에도 포함되지 않아 복사할 링크가 없습니다.' ); return; }
				var url = links[ links.length - 1 ].url;
				var message = links.length > 1 ? ( links.length + '개 안내문에 포함되어 최근 안내문의 링크를 복사했습니다.' ) : '링크를 복사했습니다.';
				if ( navigator.clipboard ) { navigator.clipboard.writeText( url ).then( function () { toast( message ); }, function () { window.prompt( '링크', url ); } ); }
				else { window.prompt( '링크', url ); }
			} );
		} );
		box.querySelectorAll( '[data-hlf-src-delete]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var id = Number( b.getAttribute( 'data-hlf-src-delete' ) );
				var item = items.filter( function ( x ) { return x.id === id; } )[ 0 ];
				var warn = item && item.included_flyer_count > 0
					? '이 매물은 ' + item.included_flyer_count + '개 안내문에 포함돼 있습니다. 원본을 삭제해도 각 안내문에 이미 담긴 매물(스냅샷)은 그대로 유지됩니다. 원본을 삭제할까요?'
					: '이 매물을 삭제할까요?';
				if ( ! window.confirm( warn ) ) { return; }
				api( 'source-listings/' + id, { method: 'DELETE' } ).then( function () { toast( '삭제했습니다.' ); renderSourceList(); } )
					.catch( function ( err ) { window.alert( '삭제하지 못했습니다: ' + err.message ); } );
			} );
		} );
	}

	function bulkAddToFlyer( flyerId, sourceIds ) {
		var i = 0, added = 0;
		function next() {
			if ( i >= sourceIds.length ) { toast( added + '개 매물을 안내문에 추가했습니다.' ); renderSourceList(); return; }
			var sid = sourceIds[ i++ ];
			api( 'flyers/' + flyerId + '/source-listings/' + sid, { method: 'PUT' } )
				.then( function () { added++; next(); } )
				.catch( function ( err ) { window.alert( '추가 중 중단되었습니다(' + added + '개 완료): ' + err.message ); renderSourceList(); } );
		}
		next();
	}

	/* ==================== 전체 매물 폼(등록/수정) ==================== */

	// returnFlyerId: "임대안내문 > 포함 매물 관리"에서 "+ 신규 매물 등록"으로 들어온 경우에만 넘어온다
	// — 새 매물을 저장하자마자 이 Flyer에 바로 포함시키고 그 관리 화면으로 되돌아가기 위한 값이다.
	function renderSourceForm( sourceId, returnFlyerId ) {
		var el = main();
		el.innerHTML = '<p class="hlf-admin-loading">불러오는 중…</p>';
		if ( sourceId ) {
			api( 'source-listings/' + sourceId ).then( function ( src ) { drawSourceForm( src, returnFlyerId ); } )
				.catch( function ( err ) { errorText( el, '매물을 불러오지 못했습니다: ' + err.message ); } );
		} else {
			importState = { open: false, results: null, importingId: null, lastQuery: { search: '', status: '' } };
			loadContacts( function ( dir ) {
				var def = currentStaffContact( dir );
				drawSourceForm( { id: null, contact_name: def.name, contact_phone: def.phone }, returnFlyerId );
			} );
		}
	}

	function fieldInput( def, value ) {
		var id = 'hlf-src-f-' + def.key;
		var v = ( value === null || value === undefined ) ? '' : value;
		if ( def.type === 'checkbox' ) {
			return '<label class="hlf-field-checkbox"><input type="checkbox" name="' + def.key + '"' + ( v ? ' checked' : '' ) + '> ' + esc( def.label ) + '</label>';
		}
		if ( def.type === 'textarea' ) {
			return '<div class="hlf-field hlf-field-wide"><label for="' + id + '">' + esc( def.label ) + '</label>' +
				'<textarea id="' + id + '" name="' + def.key + '">' + esc( v ) + '</textarea></div>';
		}
		var step = def.step ? ' step="' + def.step + '"' : '';
		var ph = def.placeholder ? ' placeholder="' + escAttr( def.placeholder ) + '"' : '';
		return '<div class="hlf-field"><label for="' + id + '">' + esc( def.label ) + '</label>' +
			'<input id="' + id + '" type="' + def.type + '" name="' + def.key + '" value="' + escAttr( v ) + '"' + step + ph + '></div>';
	}

	function drawSourceForm( src, returnFlyerId ) {
		var editing = !! src.id;
		var el = main();

		var fieldsHtml = SOURCE_FIELDS.map( function ( def ) {
			if ( CHECKBOX_ROW_KEYS.indexOf( def.key ) !== -1 ) {
				if ( def.key !== CHECKBOX_ROW_KEYS[ 0 ] ) { return ''; }
				return '<div class="hlf-field hlf-field-wide hlf-field-checkbox-row">' +
					CHECKBOX_ROW_KEYS.map( function ( k ) {
						var d = SOURCE_FIELDS.filter( function ( x ) { return x.key === k; } )[ 0 ];
						return fieldInput( d, src[ k ] );
					} ).join( '' ) + '</div>';
			}
			return fieldInput( def, src[ def.key ] );
		} ).join( '' );

		el.innerHTML =
			'<div class="hlf-listup-head">' +
				'<h2 class="hlf-listup-title">' + ( editing ? '매물 수정' : '새 매물 등록' ) + '</h2>' +
				'<button type="button" class="button" id="hlf-src-back">← 목록</button>' +
			'</div>' +
			'<div class="hlf-src-form-grid">' +
				'<section class="hlf-card">' + window.HLFOcr.renderSection() + '</section>' +
				'<form id="hlf-src-form" class="hlf-card">' +
					addressBlock( src ) +
					'<div class="hlf-field-grid">' + fieldsHtml + '</div>' +
					'<div class="hlf-form-actions">' +
						'<button type="submit" class="button button-primary">저장</button>' +
						'<p class="hlf-admin-error" data-hlf-src-error hidden></p>' +
					'</div>' +
				'</form>' +
			'</div>' +
			( editing ?
				'<section class="hlf-card" id="hlf-src-images"></section>' +
				'<section class="hlf-card" id="hlf-src-flyer-include">' +
					'<h4>임대안내문에 포함</h4>' +
					'<p class="hlf-admin-note">이 매물을 바로 특정 임대안내문에 포함시킬 수 있습니다(나중에 "임대안내문" 탭에서도 언제든 추가·제외할 수 있습니다).</p>' +
					'<div class="hlf-src-flyer-include-row">' +
						'<select id="hlf-src-flyer-select"><option value="">임대안내문 선택</option></select> ' +
						'<button type="button" class="button" id="hlf-src-flyer-add">추가</button>' +
					'</div>' +
					'<p class="hlf-admin-note" id="hlf-src-flyer-include-status"></p>' +
				'</section>'
				: '<p class="hlf-admin-note hlf-image-pending">매물 사진은 저장한 뒤 추가할 수 있습니다 — 먼저 위 내용을 저장해 주세요.</p>' ) +
			( editing ? '' : '<div id="hlf-src-import-wrap">' + renderImportToggle() + renderImportPanel() + '</div>' );

		document.getElementById( 'hlf-src-back' ).addEventListener( 'click', function () {
			if ( returnFlyerId ) { renderFlyerManage( returnFlyerId ); } else { renderSourceList(); }
		} );
		var form = document.getElementById( 'hlf-src-form' );
		window.HLFOcr.bindSection( form );
		bindAddressSearch( form );
		bindContactPicker( form );
		bindSourceFormSubmit( form, src, returnFlyerId );
		if ( editing ) { renderSourceImages( src ); bindSourceFlyerInclude( src ); }
		else { bindImportToggle(); if ( importState.open ) { bindImportResults(); } }
	}

	// 요청서: 매물 저장 직후 화면에서 바로 임대안내문에 포함시킬 수 있는 선택창.
	function bindSourceFlyerInclude( src ) {
		var select = document.getElementById( 'hlf-src-flyer-select' );
		var addBtn = document.getElementById( 'hlf-src-flyer-add' );
		var statusEl = document.getElementById( 'hlf-src-flyer-include-status' );
		if ( ! select || ! addBtn ) { return; }
		loadFlyers( function ( flyers ) {
			select.innerHTML = '<option value="">임대안내문 선택</option>' + flyers.map( function ( f ) {
				return '<option value="' + f.id + '">' + escAttr( f.title || '(제목 없음)' ) + ' / ' + escAttr( f.flyer_number ) + '</option>';
			} ).join( '' );
		} );
		addBtn.addEventListener( 'click', function () {
			var flyerId = select.value;
			if ( ! flyerId ) { statusEl.textContent = '임대안내문을 먼저 선택하세요.'; return; }
			addBtn.disabled = true;
			api( 'flyers/' + flyerId + '/source-listings/' + src.id, { method: 'PUT' } )
				.then( function () { statusEl.textContent = '포함했습니다.'; addBtn.disabled = false; } )
				.catch( function ( err ) { statusEl.textContent = '추가하지 못했습니다: ' + err.message; addBtn.disabled = false; } );
		} );
	}

	/* ---------- 원본 매물(officeleasing) 가져오기 — "새 매물 등록"에서만 ---------- */

	function renderImportToggle() {
		return '<button type="button" class="button" id="hlf-src-import-toggle">원본 매물에서 가져오기</button>';
	}

	function renderImportPanel() {
		if ( ! importState.open ) { return ''; }
		var statusOptions = OFFICELEASING_STATUS_CHOICES.map( function ( c ) {
			return '<option value="' + escAttr( c.value ) + '">' + esc( c.label ) + '</option>';
		} ).join( '' );
		return (
			'<div class="hlf-import-panel">' +
				'<h3>원본 매물에서 가져오기</h3>' +
				'<form id="hlf-src-import-search-form">' +
					'<div class="hlf-field"><label for="hlf-src-import-term">검색어(매물 제목/건물명/주소)</label><input id="hlf-src-import-term" type="text" name="search" placeholder="예: 파르나스타워, 테헤란로"></div>' +
					'<div class="hlf-field"><label for="hlf-src-import-status">상태</label><select id="hlf-src-import-status" name="status">' + statusOptions + '</select></div>' +
					'<button type="submit" class="button button-primary">검색</button> ' +
					'<button type="button" class="button" id="hlf-src-import-close">닫기</button>' +
				'</form>' +
				'<p class="hlf-admin-error" data-hlf-src-import-error hidden></p>' +
				'<div id="hlf-src-import-results">' + renderImportResults() + '</div>' +
			'</div>'
		);
	}

	function renderImportResults() {
		var results = importState.results;
		if ( null === results ) {
			return '<p class="hlf-admin-note">검색어를 입력하고 검색을 눌러 주세요(비워두면 전체 목록).</p>';
		}
		if ( ! results.items.length ) {
			return '<p class="hlf-empty">일치하는 매물이 없습니다.</p>';
		}
		var statusLabelMap = {};
		OFFICELEASING_STATUS_CHOICES.forEach( function ( c ) { statusLabelMap[ c.value ] = c.label; } );

		var rows = results.items.map( function ( listing ) {
			var address = listing.road_address || listing.lot_address || '-';
			var statusLabel = statusLabelMap[ listing.listing_status ] || listing.listing_status || '-';
			var importing = importState.importingId === listing.listing_id;
			return (
				'<tr>' +
					'<td>' + esc( listing.listing_title ) + '</td>' +
					'<td>' + esc( listing.building_title ) + '<br><small>' + esc( address ) + '</small></td>' +
					'<td>' + esc( statusLabel ) + '</td>' +
					'<td>' + won( listing.deposit_manwon ) + ' / ' + won( listing.monthly_rent_manwon ) + '</td>' +
					'<td><button type="button" class="button button-small" data-hlf-src-import-listing="' + escAttr( listing.listing_id ) + '"' + ( importing ? ' disabled' : '' ) + '>' + ( importing ? '가져오는 중…' : '가져오기' ) + '</button></td>' +
				'</tr>'
			);
		} ).join( '' );

		var pager =
			'<div class="hlf-import-pager">' +
				'<button type="button" class="button button-small" id="hlf-src-import-prev">← 이전</button> ' +
				'<span>' + results.page + ' 페이지 (총 ' + results.total + '건)</span> ' +
				'<button type="button" class="button button-small" id="hlf-src-import-next"' + ( results.page * results.per_page >= results.total ? ' disabled' : '' ) + '>다음 →</button>' +
			'</div>';

		return (
			'<div class="hlf-table-wrap"><table class="hlf-table">' +
				'<thead><tr><th>매물</th><th>빌딩/주소</th><th>상태</th><th>보증금/임대료</th><th>작업</th></tr></thead>' +
				'<tbody>' + rows + '</tbody>' +
			'</table></div>' + pager
		);
	}

	var importSearchSeq = 0;

	function runImportSearch( searchTerm, status, page ) {
		importState.lastQuery = { search: searchTerm, status: status };
		var errorEl = document.querySelector( '[data-hlf-src-import-error]' );
		var query = 'officeleasing/listings?page=' + encodeURIComponent( page ) +
			( searchTerm ? '&search=' + encodeURIComponent( searchTerm ) : '' ) +
			( status ? '&status=' + encodeURIComponent( status ) : '' );
		var requestId = ++importSearchSeq;

		api( query ).then( function ( result ) {
			if ( requestId !== importSearchSeq ) { return; }
			importState.results = result;
			document.getElementById( 'hlf-src-import-results' ).innerHTML = renderImportResults();
		} ).catch( function ( err ) {
			if ( requestId !== importSearchSeq ) { return; }
			if ( errorEl ) { errorEl.textContent = err.message; errorEl.hidden = false; }
		} );
	}

	function bindImportResults() {
		var wrap = document.getElementById( 'hlf-src-import-results' );
		if ( ! wrap ) { return; }
		wrap.addEventListener( 'click', function ( event ) {
			var prevBtn = event.target.closest( '#hlf-src-import-prev' );
			var nextBtn = event.target.closest( '#hlf-src-import-next' );
			if ( prevBtn || nextBtn ) {
				var page = ( importState.results ? importState.results.page : 1 ) + ( nextBtn ? 1 : -1 );
				if ( page < 1 ) { return; }
				runImportSearch( importState.lastQuery.search, importState.lastQuery.status, page );
				return;
			}
			var importBtn = event.target.closest( '[data-hlf-src-import-listing]' );
			if ( ! importBtn || importBtn.disabled ) { return; }
			var listingId = Number( importBtn.getAttribute( 'data-hlf-src-import-listing' ) );
			importState.importingId = listingId;
			document.getElementById( 'hlf-src-import-results' ).innerHTML = renderImportResults();

			api( 'source-listings/import-officeleasing', {
				method: 'POST',
				body: JSON.stringify( { listing_id: listingId } )
			} ).then( function ( saved ) {
				toast( '가져왔습니다.' );
				renderSourceForm( saved.id );
			} ).catch( function ( err ) {
				importState.importingId = null;
				window.alert( '가져오기에 실패했습니다: ' + err.message );
				document.getElementById( 'hlf-src-import-results' ).innerHTML = renderImportResults();
			} );
		} );
	}

	function bindImportToggle() {
		var toggle = document.getElementById( 'hlf-src-import-toggle' );
		if ( toggle ) {
			toggle.addEventListener( 'click', function () {
				importState.open = true;
				importState.results = null;
				document.getElementById( 'hlf-src-import-wrap' ).innerHTML = renderImportToggle() + renderImportPanel();
				bindImportToggle();
				bindImportPanelForm();
				bindImportResults();
			} );
		}
		bindImportPanelForm();
	}

	function bindImportPanelForm() {
		var closeBtn = document.getElementById( 'hlf-src-import-close' );
		if ( closeBtn ) {
			closeBtn.addEventListener( 'click', function () {
				importState.open = false;
				document.getElementById( 'hlf-src-import-wrap' ).innerHTML = renderImportToggle() + renderImportPanel();
				bindImportToggle();
			} );
		}
		var form = document.getElementById( 'hlf-src-import-search-form' );
		if ( form ) {
			form.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				runImportSearch( form.search.value, form.status.value, 1 );
			} );
		}
	}

	function bindSourceFormSubmit( form, src, returnFlyerId ) {
		var errorEl = form.querySelector( '[data-hlf-src-error]' );
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			// 요청서: 주소검색으로 후보를 확정해야만(latitude/longitude가 채워짐, addressBlock의
			// hidden input — bindAddressSearch의 후보 클릭에서만 값이 들어간다) 저장할 수 있다 —
			// 지번주소를 직접 타이핑만 하고 검색 버튼/후보 선택을 건너뛰면 좌표 없는 매물이 되어
			// 공개 상세페이지 지도에 표시되지 않는 문제를 막는다.
			if ( ! form.elements.latitude.value || ! form.elements.longitude.value ) {
				errorEl.textContent = '지번주소로 주소 검색을 실행해 후보를 선택해 주세요(좌표 확정 필요).';
				errorEl.hidden = false;
				return;
			}
			var btn = form.querySelector( 'button[type="submit"]' );
			btn.disabled = true; errorEl.hidden = true;
			var payload = readSourceForm( form );
			var isNew = ! src.id;
			var path = src.id ? ( 'source-listings/' + src.id ) : 'source-listings';
			api( path, { method: src.id ? 'PUT' : 'POST', body: JSON.stringify( payload ) } )
				.then( function ( saved ) {
					// "포함 매물 관리" 화면의 "+ 신규 매물 등록"으로 들어온 새 매물이면, 별도로 다시
					// 포함 선택창을 거치지 않고 바로 이 안내문에 포함시킨 뒤 그 관리 화면으로 돌아간다.
					if ( isNew && returnFlyerId ) {
						return api( 'flyers/' + returnFlyerId + '/source-listings/' + saved.id, { method: 'PUT' } )
							.then( function () {
								toast( '등록하고 이 안내문에 포함했습니다.' );
								renderFlyerManage( returnFlyerId );
							} );
					}
					toast( '저장했습니다.' );
					renderSourceForm( saved.id );
				} )
				.catch( function ( err ) { errorEl.textContent = err.message; errorEl.hidden = false; btn.disabled = false; } );
		} );
	}

	function readSourceForm( form ) {
		var out = {};
		var defs = SOURCE_FIELDS.concat( [
			{ key: 'lot_address', type: 'text' }, { key: 'road_address', type: 'text' },
			{ key: 'building_name', type: 'text' },
			{ key: 'latitude', type: 'number' }, { key: 'longitude', type: 'number' }
		] );
		defs.forEach( function ( def ) {
			var input = form.elements[ def.key ];
			if ( ! input ) { return; }
			if ( def.type === 'checkbox' ) { out[ def.key ] = input.checked; }
			else if ( def.type === 'number' ) { out[ def.key ] = input.value === '' ? '' : Number( input.value ); }
			else { out[ def.key ] = input.value; }
		} );
		return out;
	}

	/* ---------- 주소 검색 블록 ---------- */

	function addressBlock( src ) {
		return '<div class="hlf-address-block"><h4>주소 확인</h4>' +
			'<div class="hlf-field-grid">' +
				'<div class="hlf-field"><label for="hlf-src-f-lot_address">지번주소</label>' +
					'<input id="hlf-src-f-lot_address" type="text" name="lot_address" value="' + escAttr( src.lot_address || '' ) + '"></div>' +
				'<div class="hlf-field"><label for="hlf-src-f-road_address">도로명주소</label>' +
					'<input id="hlf-src-f-road_address" type="text" name="road_address" value="' + escAttr( src.road_address || '' ) + '" readonly class="hlf-field-readonly"></div>' +
				'<div class="hlf-field"><label for="hlf-src-f-building_name">건물명(선택)</label>' +
					'<input id="hlf-src-f-building_name" type="text" name="building_name" placeholder="입력하지 않으면 표시되지 않습니다" value="' + escAttr( src.building_name || '' ) + '"></div>' +
			'</div>' +
			'<input type="hidden" name="latitude" value="' + escAttr( src.latitude || '' ) + '">' +
			'<input type="hidden" name="longitude" value="' + escAttr( src.longitude || '' ) + '">' +
			'<div class="hlf-address-search-row">' +
				'<button type="button" class="button button-small" id="hlf-src-addr-search">주소 검색</button>' +
				'<p class="hlf-admin-note" id="hlf-src-addr-status"></p>' +
			'</div>' +
			'<ul class="hlf-address-results" id="hlf-src-addr-results" hidden></ul>' +
			'</div>';
	}

	function bindAddressSearch( form ) {
		var button = document.getElementById( 'hlf-src-addr-search' );
		var statusEl = document.getElementById( 'hlf-src-addr-status' );
		var resultsEl = document.getElementById( 'hlf-src-addr-results' );
		var lotInput = form.elements.lot_address;
		if ( ! button || ! lotInput ) { return; }
		var seq = 0;
		var timer = null;

		function runSearch() {
			var q = lotInput.value.trim();
			if ( q.length < 2 ) { statusEl.textContent = '두 글자 이상 입력해 주세요.'; resultsEl.hidden = true; return; }
			var mySeq = ++seq;
			statusEl.textContent = '검색 중…';
			api( 'kakao/address-search?q=' + encodeURIComponent( q ) )
				.then( function ( data ) {
					if ( mySeq !== seq ) { return; }
					var results = ( data && data.results ) || [];
					if ( ! results.length ) { statusEl.textContent = '결과가 없습니다.'; resultsEl.hidden = true; return; }
					statusEl.textContent = results.length + '건 — 클릭해 확정하세요.';
					renderAddressResults( results );
				} )
				.catch( function ( err ) {
					if ( mySeq !== seq ) { return; }
					statusEl.textContent = err.status === 501 ? '카카오 주소 검색 키가 설정되지 않았습니다(좌표 수동 입력 가능).' : ( '검색 실패: ' + err.message );
					resultsEl.hidden = true;
				} );
		}

		function renderAddressResults( candidates ) {
			resultsEl.innerHTML = candidates.map( function ( c, i ) {
				return '<li><button type="button" class="hlf-address-candidate" data-i="' + i + '">' +
					'<strong>' + esc( c.lot_address || c.road_address || '(주소)' ) + '</strong>' +
					'<span>' + esc( c.road_address || '' ) + '</span></button></li>';
			} ).join( '' );
			resultsEl.hidden = false;
			resultsEl.querySelectorAll( '.hlf-address-candidate' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					var c = candidates[ Number( btn.getAttribute( 'data-i' ) ) ];
					form.elements.lot_address.value = c.lot_address || '';
					form.elements.road_address.value = c.road_address || '';
					form.elements.latitude.value = c.latitude || '';
					form.elements.longitude.value = c.longitude || '';
					resultsEl.hidden = true;
					statusEl.textContent = '주소를 확정했습니다.';
				} );
			} );
		}

		button.addEventListener( 'click', runSearch );
		lotInput.addEventListener( 'input', function () {
			if ( timer ) { clearTimeout( timer ); }
			timer = setTimeout( runSearch, ADDRESS_SEARCH_DEBOUNCE_MS );
		} );
	}

	/* ---------- 담당자 디렉터리 선택(폼 안의 보조 드롭다운 — 담당자 "전환"과는 별개) ---------- */

	function bindContactPicker( form ) {
		var nameInput = form.elements.contact_name;
		if ( ! nameInput ) { return; }
		var wrap = nameInput.closest( '.hlf-field' );
		if ( ! wrap ) { return; }
		loadContacts( function ( dir ) {
			if ( ! dir.contacts || ! dir.contacts.length ) { return; }
			var picker = document.createElement( 'select' );
			picker.className = 'hlf-contact-picker';
			picker.innerHTML = '<option value="">담당자 선택…</option>' + dir.contacts.map( function ( c ) {
				return '<option value="' + c.index + '">' + escAttr( ( c.name || '(이름 없음)' ) + ' / ' + ( c.phone || '' ) ) + '</option>';
			} ).join( '' );
			picker.addEventListener( 'change', function () {
				var c = dir.contacts[ Number( picker.value ) ];
				if ( ! c ) { return; }
				form.elements.contact_name.value = c.name;
				if ( form.elements.contact_phone ) { form.elements.contact_phone.value = c.phone; }
			} );
			wrap.appendChild( picker );
		} );
	}

	/* ---------- 원본 매물 이미지(wp.media) ---------- */

	function renderSourceImages( src ) {
		var box = document.getElementById( 'hlf-src-images' );
		if ( ! box ) { return; }
		var previews = src.image_previews || {};
		var blurMap = src.image_blur || {};
		var ext = Number( src.exterior_image_id || 0 );
		var interior = ( src.interior_image_ids || [] ).map( Number );
		var ordered = ( ext ? [ ext ] : [] ).concat( interior );

		box.innerHTML =
			'<h4>매물 사진</h4>' +
			'<p class="hlf-admin-note">첫 번째 사진이 대표 이미지입니다. 미디어 라이브러리에서 선택하거나 새로 업로드할 수 있습니다.</p>' +
			'<p class="hlf-admin-note hlf-image-snapshot-warning">주의: 사진은 미디어 라이브러리 원본을 그대로 참조합니다. 이 원본을 다른 곳에서 삭제·교체하면, 이미 임대안내문에 포함된 매물의 사진도 함께 바뀌거나 사라질 수 있습니다.</p>' +
			'<button type="button" class="button" id="hlf-src-img-pick">사진 선택/추가</button>' +
			'<div class="hlf-img-strip">' +
				( ordered.length ? ordered.map( function ( id, i ) {
					var url = previews[ id ];
					var isBlurred = !! ( blurMap[ id ] || blurMap[ String( id ) ] );
					return '<div class="hlf-img-thumb' + ( 0 === i ? ' is-primary' : '' ) + '">' +
						( url ? '<img src="' + escAttr( url ) + '" alt="">' : '<span class="hlf-img-missing">이미지</span>' ) +
						( 0 === i ? '<span class="hlf-img-badge">대표</span>' : '<button type="button" class="hlf-img-promote" data-id="' + id + '">대표로</button>' ) +
						'<label class="hlf-img-blur"><input type="checkbox" data-hlf-img-blur="' + id + '"' + ( isBlurred ? ' checked' : '' ) + '> 블러</label>' +
						'<button type="button" class="hlf-img-remove" data-id="' + id + '">×</button>' +
					'</div>';
				} ).join( '' ) : '<p class="hlf-empty">등록된 사진이 없습니다.</p>' ) +
			'</div>';

		document.getElementById( 'hlf-src-img-pick' ).addEventListener( 'click', function () { openImagePicker( src ); } );
		box.querySelectorAll( '.hlf-img-promote' ).forEach( function ( b ) { b.addEventListener( 'click', function () { promoteImage( src, Number( b.getAttribute( 'data-id' ) ) ); } ); } );
		box.querySelectorAll( '[data-hlf-img-blur]' ).forEach( function ( cb ) {
			cb.addEventListener( 'change', function () {
				var id = Number( cb.getAttribute( 'data-hlf-img-blur' ) );
				A.saveAttachmentMeta( id, { _hlf_photo_blur: cb.checked } )
					.catch( function ( err ) {
						window.alert( '블러 설정을 저장하지 못했습니다: ' + err.message );
						cb.checked = ! cb.checked;
					} );
			} );
		} );
		box.querySelectorAll( '.hlf-img-remove' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				if ( ! window.confirm( '이 사진을 매물에서 뗄까요? (파일 자체는 삭제되지 않습니다)' ) ) { return; }
				api( 'source-listings/' + src.id + '/images/' + b.getAttribute( 'data-id' ), { method: 'DELETE' } )
					.then( function ( updated ) { renderSourceImages( updated ); } )
					.catch( function ( err ) { window.alert( '삭제하지 못했습니다: ' + err.message ); } );
			} );
		} );
	}

	function openImagePicker( src ) {
		if ( ! window.wp || ! window.wp.media ) { window.alert( '미디어 라이브러리를 불러오지 못했습니다. 새로고침 후 다시 시도해 주세요.' ); return; }
		// uploader.params.hlf_upload: HLF_Image_Pipeline이 이 값으로 자기 업로드만 골라 최적화한다
		// (GPT 코드 감사 P0#2 — 이 표시가 없으면 다른 화면의 업로드까지 전역으로 영향을 준다).
		var frame = window.wp.media( { title: '매물 사진 선택', multiple: 'add', library: { type: 'image' }, button: { text: '선택' }, uploader: { params: { hlf_upload: '1' } } } );
		frame.on( 'select', function () {
			var chosen = frame.state().get( 'selection' ).map( function ( a ) { return a.id; } );
			var current = ( src.exterior_image_id ? [ Number( src.exterior_image_id ) ] : [] ).concat( ( src.interior_image_ids || [] ).map( Number ) );
			var merged = current.concat( chosen.filter( function ( id ) { return current.indexOf( id ) === -1; } ) );
			// 요청서: 대표 1장 + 슬라이드 3장(대표 포함 4장)까지만 — 서버(HLF_Source_Listing_Repository::
			// set_images, HLF_Item_Repository::MAX_IMAGES 재사용)와 같은 상한을 여기서도 미리 건다.
			if ( merged.length > HLF_MAX_IMAGES ) {
				merged = merged.slice( 0, HLF_MAX_IMAGES );
				window.alert( '사진은 대표 이미지를 포함해 최대 ' + HLF_MAX_IMAGES + '장까지 등록할 수 있습니다. 앞에서부터 ' + HLF_MAX_IMAGES + '장만 반영합니다.' );
			}
			saveSourceImages( src, merged[ 0 ] || 0, merged.slice( 1 ) );
		} );
		frame.open();
	}

	function promoteImage( src, id ) {
		var current = ( src.exterior_image_id ? [ Number( src.exterior_image_id ) ] : [] ).concat( ( src.interior_image_ids || [] ).map( Number ) );
		var rest = current.filter( function ( x ) { return x !== id; } );
		saveSourceImages( src, id, rest );
	}

	function saveSourceImages( src, exteriorId, interiorIds ) {
		api( 'source-listings/' + src.id + '/images', {
			method: 'PUT',
			body: JSON.stringify( { exterior_image_id: exteriorId, interior_image_ids: interiorIds } )
		} ).then( function ( updated ) { renderSourceImages( updated ); toast( '사진을 저장했습니다.' ); } )
			.catch( function ( err ) { window.alert( '사진을 저장하지 못했습니다: ' + err.message ); } );
	}

	/* ==================== 임대안내문(Flyer) ==================== */

	function renderFlyerList() {
		var el = main();
		el.innerHTML =
			'<div class="hlf-listup-head">' +
				'<h2 class="hlf-listup-title">임대안내문</h2>' +
				'<button type="button" class="button button-primary" id="hlf-fl-new">+ 새 임대안내문</button>' +
			'</div>' +
			'<div id="hlf-fl-results"><p class="hlf-admin-loading">불러오는 중…</p></div>';

		document.getElementById( 'hlf-fl-new' ).addEventListener( 'click', renderFlyerCreate );

		api( 'flyers?per_page=100' ).then( function ( flyers ) {
			state.flyers = flyers;
			var box = document.getElementById( 'hlf-fl-results' );
			if ( ! flyers.length ) { box.innerHTML = '<p class="hlf-empty">등록된 임대안내문이 없습니다.</p>'; return; }
			box.innerHTML =
				'<div class="hlf-table-wrap"><table class="hlf-table">' +
					'<thead><tr><th>번호</th><th>제목</th><th>매물 수</th><th>담당자</th><th>작업</th></tr></thead><tbody>' +
					flyers.map( function ( f ) {
						return '<tr>' +
							'<td>' + esc( f.flyer_number ) + '</td>' +
							'<td>' + esc( f.title || '(제목 없음)' ) +
								( f.status === 'archived' ? ' <span class="' + A.statusBadgeClass( 'archived' ) + '">보관</span>' : '' ) + '</td>' +
							'<td class="hlf-td-center">' + esc( f.item_count ) + '</td>' +
							'<td class="hlf-td-contact">' + esc( f.contact_name || '-' ) + '</td>' +
							'<td class="hlf-row-actions">' +
								'<button type="button" class="button button-small button-primary" data-hlf-fl-manage="' + f.id + '">포함 매물 관리</button>' +
								'<button type="button" class="button button-small" data-hlf-fl-rename="' + f.id + '">이름 수정</button>' +
								'<button type="button" class="button button-small" data-hlf-fl-preview="' + f.id + '">미리보기</button>' +
								'<button type="button" class="button button-small" data-hlf-fl-copy="' + f.id + '">링크 복사</button>' +
								'<button type="button" class="button button-small hlf-danger" data-hlf-fl-delete="' + f.id + '">삭제</button>' +
							'</td>' +
						'</tr>';
					} ).join( '' ) +
					'</tbody></table></div>';
			bindFlyerTable( flyers );
		} ).catch( function ( err ) { errorText( document.getElementById( 'hlf-fl-results' ), '목록을 불러오지 못했습니다: ' + err.message ); } );
	}

	function bindFlyerTable( flyers ) {
		function find( id ) { return flyers.filter( function ( f ) { return f.id === id; } )[ 0 ]; }
		var box = document.getElementById( 'hlf-fl-results' );
		box.querySelectorAll( '[data-hlf-fl-manage]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { renderFlyerManage( Number( b.getAttribute( 'data-hlf-fl-manage' ) ) ); } ); } );
		box.querySelectorAll( '[data-hlf-fl-preview]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { var f = find( Number( b.getAttribute( 'data-hlf-fl-preview' ) ) ); if ( f ) { window.open( f.url, '_blank', 'noopener' ); } } ); } );
		box.querySelectorAll( '[data-hlf-fl-copy]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var f = find( Number( b.getAttribute( 'data-hlf-fl-copy' ) ) );
				if ( ! f ) { return; }
				if ( navigator.clipboard ) { navigator.clipboard.writeText( f.url ).then( function () { toast( '링크를 복사했습니다.' ); }, function () { window.prompt( '링크', f.url ); } ); }
				else { window.prompt( '링크', f.url ); }
			} );
		} );
		box.querySelectorAll( '[data-hlf-fl-rename]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var f = find( Number( b.getAttribute( 'data-hlf-fl-rename' ) ) );
				if ( ! f ) { return; }
				var name = window.prompt( '임대안내문 제목', f.title || '' );
				if ( null === name ) { return; }
				api( 'flyers/' + f.id, { method: 'PUT', body: JSON.stringify( { title: name } ) } ).then( function () { toast( '수정했습니다.' ); renderFlyerList(); } )
					.catch( function ( err ) { window.alert( '수정하지 못했습니다: ' + err.message ); } );
			} );
		} );
		box.querySelectorAll( '[data-hlf-fl-delete]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var f = find( Number( b.getAttribute( 'data-hlf-fl-delete' ) ) );
				if ( ! f ) { return; }
				if ( ! window.confirm( '“' + ( f.title || f.flyer_number ) + '” 안내문을 삭제할까요? 포함된 매물(스냅샷)도 함께 삭제됩니다(원본 매물은 유지).' ) ) { return; }
				api( 'flyers/' + f.id, { method: 'DELETE' } ).then( function () { toast( '삭제했습니다.' ); renderFlyerList(); } )
					.catch( function ( err ) { window.alert( '삭제하지 못했습니다: ' + err.message ); } );
			} );
		} );
	}

	function renderFlyerCreate() {
		var el = main();
		loadContacts( function ( dir ) {
			var def = currentStaffContact( dir );
			el.innerHTML =
				'<div class="hlf-listup-head"><h2 class="hlf-listup-title">새 임대안내문</h2>' +
					'<button type="button" class="button" id="hlf-fl-back">← 목록</button></div>' +
				'<form id="hlf-fl-create" class="hlf-card">' +
					'<div class="hlf-field"><label for="hlf-fl-title">제목(내부 관리용)</label><input id="hlf-fl-title" type="text" name="title" required></div>' +
					'<div class="hlf-field"><label for="hlf-fl-cname">담당자명</label><input id="hlf-fl-cname" type="text" name="contact_name" value="' + escAttr( def.name ) + '"></div>' +
					'<div class="hlf-field"><label for="hlf-fl-cphone">담당자 연락처</label><input id="hlf-fl-cphone" type="text" name="contact_phone" value="' + escAttr( def.phone ) + '"></div>' +
					'<p class="hlf-admin-note">담당자는 현재 선택된 담당자로 미리 채워집니다. 필요하면 수정하세요.</p>' +
					'<div class="hlf-form-actions"><button type="submit" class="button button-primary">만들기</button>' +
						'<p class="hlf-admin-error" data-hlf-fl-error hidden></p></div>' +
				'</form>';
			document.getElementById( 'hlf-fl-back' ).addEventListener( 'click', renderFlyerList );
			var form = document.getElementById( 'hlf-fl-create' );
			bindContactPicker( form );
			var errorEl = form.querySelector( '[data-hlf-fl-error]' );
			form.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				var btn = form.querySelector( 'button[type="submit"]' );
				btn.disabled = true; errorEl.hidden = true;
				api( 'flyers', { method: 'POST', body: JSON.stringify( {
					title: form.title.value, contact_name: form.contact_name.value, contact_phone: form.contact_phone.value
				} ) } ).then( function ( f ) { toast( '만들었습니다.' ); renderFlyerManage( f.id ); } )
					.catch( function ( err ) { errorEl.textContent = err.message; errorEl.hidden = false; btn.disabled = false; } );
			} );
		} );
	}

	// contact/showAll: renderSourceList와 같은 이유(요청서) — 기본값은 이 기기에 선택된 담당자("나")
	// 매물만 후보로 먼저 보여주고, "전체 보기"를 눌러야 전체 후보가 나온다.
	function renderFlyerManage( flyerId, searchTerm, contact, showAll ) {
		if ( undefined === contact ) {
			loadContacts( function ( dir ) { renderFlyerManage( flyerId, searchTerm, preferredContactName( dir ), false ); } );
			return;
		}
		showAll = !! showAll;
		var el = main();
		el.innerHTML = '<p class="hlf-admin-loading">불러오는 중…</p>';
		// 성능(GPT/Codex 코드 감사): source-listings 목록 쿼리는 flyer 상세 응답 값을 전혀 쓰지 않는데도
		// 예전에는 flyers/{id}가 끝난 "다음에" 불렀다(순차 waterfall) — 두 요청을 동시에 시작하고,
		// flyer 상세가 다 그려진 뒤 이미 진행 중인 source-listings 응답을 이어 붙인다.
		var q = '' !== ( searchTerm || '' ) ? ( '&search=' + encodeURIComponent( searchTerm ) ) : '';
		q += ( ! showAll && contact ) ? ( '&contact=' + encodeURIComponent( contact ) ) : '';
		var sourceListingsPromise = api( 'source-listings?per_page=100' + q );
		api( 'flyers/' + flyerId ).then( function ( flyer ) {
			el.innerHTML =
				'<div class="hlf-listup-head">' +
					'<h2 class="hlf-listup-title">포함 매물 관리 — ' + esc( flyer.title || flyer.flyer_number ) + '</h2>' +
					'<div class="hlf-listup-head-actions">' +
						'<button type="button" class="button button-primary" id="hlf-fm-new-source">+ 신규 매물 등록</button>' +
						'<button type="button" class="button" id="hlf-fm-back">← 목록</button>' +
					'</div>' +
				'</div>' +
				'<p class="hlf-admin-note hlf-safe-note">체크 해제 시 이 안내문에서만 제거되며, 원본 매물과 다른 안내문에 포함된 동일 매물은 그대로 유지됩니다. (최대 ' + esc( CONF.maxItems ) + '개)</p>' +
				'<div class="hlf-included-summary" id="hlf-fm-included"></div>' +
				'<div class="hlf-toolbar">' +
					'<input type="search" id="hlf-fm-search" placeholder="원본 매물 주소·키워드 검색" value="' + escAttr( searchTerm || '' ) + '">' +
					'<button type="button" class="button" id="hlf-fm-search-btn">검색</button>' +
					contactFilterHtml( state.contacts, contact, showAll, 'hlf-fm' ) +
				'</div>' +
				'<div id="hlf-fm-results"><p class="hlf-admin-loading">불러오는 중…</p></div>';

			document.getElementById( 'hlf-fm-back' ).addEventListener( 'click', renderFlyerList );
			document.getElementById( 'hlf-fm-new-source' ).addEventListener( 'click', function () { renderSourceForm( null, flyerId ); } );
			var si = document.getElementById( 'hlf-fm-search' );
			document.getElementById( 'hlf-fm-search-btn' ).addEventListener( 'click', function () { renderFlyerManage( flyerId, si.value, contact, showAll ); } );
			si.addEventListener( 'keydown', function ( e ) { if ( 'Enter' === e.key ) { renderFlyerManage( flyerId, si.value, contact, showAll ); } } );
			var fmContactSelect = document.getElementById( 'hlf-fm-contact' );
			if ( fmContactSelect ) {
				fmContactSelect.addEventListener( 'change', function () { renderFlyerManage( flyerId, si.value, fmContactSelect.value, false ); } );
			}
			var fmShowAllBtn = document.getElementById( 'hlf-fm-showall' );
			if ( fmShowAllBtn ) {
				fmShowAllBtn.addEventListener( 'click', function () {
					renderFlyerManage( flyerId, si.value, fmContactSelect ? fmContactSelect.value : contact, ! showAll );
				} );
			}

			renderIncludedSummary( flyer );

			sourceListingsPromise.then( function ( data ) {
				renderManageResults( flyer, data.items || [] );
			} ).catch( function ( err ) { errorText( document.getElementById( 'hlf-fm-results' ), '원본 매물을 불러오지 못했습니다: ' + err.message ); } );
		} ).catch( function ( err ) { errorText( el, '안내문을 불러오지 못했습니다: ' + err.message ); } );
	}

	function renderIncludedSummary( flyer ) {
		var box = document.getElementById( 'hlf-fm-included' );
		var items = flyer.items || [];
		box.innerHTML = '<h4>현재 포함된 매물 (' + items.length + '건)</h4>' +
			( items.length
				? '<ul class="hlf-included-list">' + items.map( function ( it ) {
					return '<li>' + esc( it.lot_address || it.road_address || '(주소 없음)' ) + ' — ' + esc( won( it.deposit_manwon ) ) + ' / ' + esc( won( it.monthly_rent_manwon ) ) + '</li>';
				} ).join( '' ) + '</ul>'
				: '<p class="hlf-empty">아직 포함된 매물이 없습니다. 아래 목록에서 체크해 추가하세요.</p>' );
	}

	function renderManageResults( flyer, sources ) {
		var box = document.getElementById( 'hlf-fm-results' );
		var includedSet = {};
		( flyer.items || [] ).forEach( function ( it ) { if ( it.source_listing_id ) { includedSet[ Number( it.source_listing_id ) ] = true; } } );

		if ( ! sources.length ) { box.innerHTML = '<p class="hlf-empty">등록된 원본 매물이 없습니다. “전체 매물” 탭에서 먼저 등록하세요.</p>'; return; }
		box.innerHTML =
			'<div class="hlf-table-wrap"><table class="hlf-table">' +
				'<thead><tr><th>포함</th><th>지번주소</th><th>층</th><th>임대면적</th><th>전용면적</th>' +
					'<th>보증금</th><th>임대료</th><th>관리비</th><th>작업</th></tr></thead><tbody>' +
				sources.map( function ( s ) {
					var on = !! includedSet[ s.id ];
					return '<tr><td><input type="checkbox" class="hlf-fm-pick" data-id="' + s.id + '"' + ( on ? ' checked' : '' ) + '></td>' +
						'<td>' + esc( s.lot_address || '-' ) + '<small>' + esc( s.road_address || '' ) + '</small></td>' +
						'<td>' + esc( num( s.floor_current ) ) + '/' + esc( num( s.floor_total ) ) + '</td>' +
						'<td>' + esc( num( s.lease_area_sqm ) ) + '㎡</td>' +
						'<td>' + esc( num( s.exclusive_area_sqm ) ) + '㎡</td>' +
						'<td>' + esc( won( s.deposit_manwon ) ) + '</td>' +
						'<td>' + esc( won( s.monthly_rent_manwon ) ) + '</td>' +
						'<td>' + esc( won( s.maintenance_fee_manwon ) ) + '</td>' +
						'<td class="hlf-row-actions">' +
							'<button type="button" class="button button-small" data-hlf-fm-edit="' + s.id + '">수정</button>' +
						'</td></tr>';
				} ).join( '' ) +
				'</tbody></table></div>';

		box.querySelectorAll( '.hlf-fm-pick' ).forEach( function ( c ) {
			c.addEventListener( 'change', function () {
				var sourceId = Number( c.getAttribute( 'data-id' ) );
				if ( ! c.checked && 'published' === flyer.status &&
					! window.confirm( '이 안내문은 이미 발행되었습니다. 이 매물을 제거하면 공유된 링크에서 즉시 사라집니다. 계속할까요?' ) ) {
					c.checked = true;
					return;
				}
				c.disabled = true;
				var method = c.checked ? 'PUT' : 'DELETE';
				api( 'flyers/' + flyer.id + '/source-listings/' + sourceId, { method: method } )
					.then( function () {
						c.disabled = false;
						toast( c.checked ? '안내문에 추가했습니다.' : '안내문에서 제거했습니다.' );
						api( 'flyers/' + flyer.id ).then( function ( fresh ) { flyer.items = fresh.items; renderIncludedSummary( fresh ); } );
					} )
					.catch( function ( err ) {
						c.disabled = false; c.checked = ! c.checked;
						window.alert( '변경하지 못했습니다: ' + err.message );
					} );
			} );
		} );

		// 체크박스(포함/제외)가 이미 "리스트에서 빼기"이므로 별도 삭제 버튼은 두지 않는다 — 원본 매물
		// 자체 삭제는 "전체 매물" 탭에서만 한다. "수정"은 원본 매물 편집 폼으로 이동할 뿐이다.
		box.querySelectorAll( '[data-hlf-fm-edit]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () { renderSourceForm( Number( b.getAttribute( 'data-hlf-fm-edit' ) ) ); } );
		} );
	}

	/* ==================== 설정(담당자 디렉터리) ==================== */

	function renderSettings() {
		var el = main();
		state.contacts = null;
		el.innerHTML = '<h2 class="hlf-listup-title">설정 — 담당자 디렉터리</h2><div id="hlf-set-body"><p class="hlf-admin-loading">불러오는 중…</p></div>';
		api( 'contacts' ).then( function ( dir ) { state.contacts = dir; drawSettings( dir ); } )
			.catch( function ( err ) { errorText( document.getElementById( 'hlf-set-body' ), err.message ); } );
	}

	function drawSettings( dir ) {
		var body = document.getElementById( 'hlf-set-body' );
		body.innerHTML =
			'<section class="hlf-card"><p class="hlf-admin-note">여기 저장한 담당자는 새 매물/새 안내문 폼에서 빠르게 선택할 수 있습니다. 이 기기에서 "현재 담당자"로 쓸 사람은 상단 "담당자 변경" 버튼으로 고릅니다(여기 "기본" 지정과는 별개입니다).</p>' +
				'<div class="hlf-table-wrap"><table class="hlf-table"><thead><tr><th>이름</th><th>연락처</th><th>기본</th><th>작업</th></tr></thead><tbody>' +
				( dir.contacts.length ? dir.contacts.map( function ( c ) {
					return '<tr>' +
						'<td>' + esc( c.name || '-' ) + '</td><td>' + esc( c.phone || '-' ) + '</td>' +
						'<td class="hlf-td-center">' + ( c.is_default ? '★' : '<button type="button" class="button button-small" data-hlf-c-default="' + c.index + '">기본 지정</button>' ) + '</td>' +
						'<td class="hlf-row-actions">' +
							'<button type="button" class="button button-small" data-hlf-c-edit="' + c.index + '">수정</button>' +
							'<button type="button" class="button button-small hlf-danger" data-hlf-c-del="' + c.index + '">삭제</button>' +
						'</td></tr>';
				} ).join( '' ) : '<tr><td colspan="4" class="hlf-empty">등록된 담당자가 없습니다.</td></tr>' ) +
				'</tbody></table></div>' +
			'</section>' +
			'<section class="hlf-card"><h4>담당자 추가</h4>' +
				'<form id="hlf-c-add" class="hlf-inline-form">' +
					'<input type="text" name="name" placeholder="이름" required>' +
					'<input type="text" name="phone" placeholder="연락처">' +
					'<button type="submit" class="button button-primary">추가</button>' +
				'</form>' +
				'<p class="hlf-admin-error" data-hlf-c-error hidden></p>' +
			'</section>';

		var errorEl = body.querySelector( '[data-hlf-c-error]' );
		function refresh( dirNew ) { state.contacts = dirNew; drawSettings( dirNew ); }
		function act( path, method, payload ) {
			return api( path, payload ? { method: method, body: JSON.stringify( payload ) } : { method: method } )
				.then( refresh ).catch( function ( err ) { window.alert( err.message ); } );
		}

		body.querySelector( '#hlf-c-add' ).addEventListener( 'submit', function ( e ) {
			e.preventDefault(); errorEl.hidden = true;
			var f = e.target;
			api( 'contacts', { method: 'POST', body: JSON.stringify( { name: f.name.value, phone: f.phone.value } ) } )
				.then( refresh ).catch( function ( err ) { errorEl.textContent = err.message; errorEl.hidden = false; } );
		} );
		body.querySelectorAll( '[data-hlf-c-default]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { act( 'contacts/' + b.getAttribute( 'data-hlf-c-default' ) + '/default', 'POST' ); } ); } );
		body.querySelectorAll( '[data-hlf-c-del]' ).forEach( function ( b ) { b.addEventListener( 'click', function () { if ( window.confirm( '이 담당자를 삭제할까요?' ) ) { act( 'contacts/' + b.getAttribute( 'data-hlf-c-del' ), 'DELETE' ); } } ); } );
		body.querySelectorAll( '[data-hlf-c-edit]' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var idx = Number( b.getAttribute( 'data-hlf-c-edit' ) );
				var c = dir.contacts[ idx ];
				var name = window.prompt( '이름', c.name ); if ( null === name ) { return; }
				var phone = window.prompt( '연락처', c.phone ); if ( null === phone ) { return; }
				act( 'contacts/' + idx, 'PUT', { name: name, phone: phone } );
			} );
		} );
	}

	/* ==================== 시작 ==================== */

	// tab=dashboard|listings|flyers|settings|flyer-edit(&flyer_id=123) 쿼리스트링으로 진입 탭을
	// 정할 수 있다(요청서 1항) — 없으면 항상 Dashboard. listings/flyer-edit은 이 앱의 실제 탭 키
	// (sources/flyers)로 매핑한다. flyer-edit&flyer_id는 "그 Flyer의 포함 매물 관리 화면 열기"로
	// 해석한다(이 앱에는 admin-flyer-edit.php 같은 별도 상세편집 화면이 없고, 포함 매물 관리 화면이
	// 실질적으로 같은 역할을 한다).
	function initialViewFromUrl() {
		var params = new URLSearchParams( window.location.search );
		var tab = params.get( 'tab' );
		var flyerId = params.get( 'flyer_id' );
		var map = { dashboard: 'dashboard', listings: 'sources', sources: 'sources', flyers: 'flyers', 'flyer-edit': 'flyers', settings: 'settings' };
		return {
			tab: map[ tab ] || 'dashboard',
			flyerId: flyerId ? Number( flyerId ) : null,
			wantManage: 'flyer-edit' === tab
		};
	}

	document.querySelectorAll( '.hlf-portal-navbtn' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () { setTab( btn.getAttribute( 'data-hlf-tab' ) ); } );
	} );

	var staffChangeBtn = document.getElementById( 'hlf-portal-staff-change' );
	if ( staffChangeBtn ) { staffChangeBtn.addEventListener( 'click', function () { openStaffPicker(); } ); }

	loadContacts( function ( dir ) {
		updateStaffLabel( dir );
		ensureStaffSelected( dir );
	} );

	function ensureStaffSelected( dir ) {
		var view = initialViewFromUrl();
		function start() {
			setTab( view.tab );
			if ( view.wantManage && view.flyerId ) { renderFlyerManage( view.flyerId ); }
		}
		if ( loadStaffSelection() ) { start(); return; }
		openStaffPicker( start );
	}
} )();
