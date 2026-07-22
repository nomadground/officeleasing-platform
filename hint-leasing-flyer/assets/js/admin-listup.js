/**
 * HINT List Up 통합 관리 화면(요청서 4). 좌측 4탭: Dashboard / 전체 매물 / 임대안내문 / 설정.
 *
 * 데이터는 전부 hlf/v1 REST(HLF_REST_Controller)를 fetch로 호출한다 — 이 파일은 순수 화면/상호작용만
 * 담당한다(비즈니스 로직은 서버). OCR은 공용 모듈(window.HLFOcr)을, 공통 포맷/이스케이프는
 * window.HLFAdmin을 그대로 쓴다. Flyer/Item CRUD·계산(NOC)·스냅샷·공개 URL은 재설계하지 않는다.
 */
( function () {
	'use strict';

	var root = document.getElementById( 'hlf-listup-root' );
	if ( ! root ) { return; }
	var A = window.HLFAdmin;
	var CONF = window.HLF_ADMIN;

	// 원본 매물 폼의 조건 필드(주소/좌표는 별도 주소 블록에서 처리, article_no는 폼에 노출하지 않음).
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
		{ key: 'total_parking', label: '총주차대수', type: 'text', placeholder: '예: 자주식 10대' },
		{ key: 'direction', label: '방향', type: 'text' },
		{ key: 'approval_date', label: '사용승인일', type: 'text', placeholder: '예: 2018.06.21' },
		{ key: 'building_use', label: '건축물용도', type: 'text' },
		{ key: 'available_date_text', label: '입주가능일', type: 'text', placeholder: '예: 즉시입주 협의가능' },
		{ key: 'features', label: '매물특징', type: 'textarea', wide: true },
		{ key: 'contact_name', label: '담당자명', type: 'text' },
		{ key: 'contact_phone', label: '담당자 연락처', type: 'text' }
	];
	var CHECKBOX_ROW_KEYS = [ 'parking_available', 'elevator_available' ];
	var ADDRESS_SEARCH_DEBOUNCE_MS = 700;

	var state = {
		tab: 'dashboard',
		contacts: null,      // { contacts:[{index,name,phone,is_default}], default_index }
		flyers: null         // 최근 조회한 Flyer 목록(전체 매물 일괄 추가 드롭다운/임대안내문 탭 공용)
	};

	/* ==================== 공통 ==================== */

	function api( path, options ) { return A.apiFetch( path, options ); }
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

	/* ==================== 셸(탭 내비) ==================== */

	var TABS = [
		{ key: 'dashboard', label: 'Dashboard' },
		{ key: 'sources', label: '전체 매물' },
		{ key: 'flyers', label: '임대안내문' },
		{ key: 'settings', label: '설정' }
	];

	function setTab( tab ) { state.tab = tab; render(); }

	function render() {
		root.innerHTML =
			'<div class="hlf-listup">' +
				'<nav class="hlf-listup-nav">' +
					TABS.map( function ( t ) {
						return '<button type="button" class="hlf-listup-tab' + ( state.tab === t.key ? ' is-active' : '' ) + '" data-hlf-tab="' + t.key + '">' + esc( t.label ) + '</button>';
					} ).join( '' ) +
				'</nav>' +
				'<main class="hlf-listup-main" id="hlf-listup-main"><p class="hlf-admin-loading">불러오는 중…</p></main>' +
			'</div>';
		root.querySelectorAll( '[data-hlf-tab]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () { setTab( btn.getAttribute( 'data-hlf-tab' ) ); } );
		} );
		renderTab();
	}

	function main() { return document.getElementById( 'hlf-listup-main' ); }

	function renderTab() {
		if ( 'dashboard' === state.tab ) { return renderDashboard(); }
		if ( 'sources' === state.tab ) { return renderSourceList(); }
		if ( 'flyers' === state.tab ) { return renderFlyerList(); }
		if ( 'settings' === state.tab ) { return renderSettings(); }
	}

	/* ==================== Dashboard ==================== */

	function renderDashboard() {
		var el = main();
		api( 'dashboard' ).then( function ( d ) {
			el.innerHTML =
				'<h2 class="hlf-listup-title">Dashboard</h2>' +
				'<div class="hlf-stat-grid">' +
					statCard( d.source_total, '전체 매물' ) +
					statCard( d.flyer_total, '임대안내문' ) +
					statCard( d.source_linked, '연결된 매물' ) +
					statCard( d.source_unlinked, '미연결 매물' ) +
				'</div>' +
				'<p class="hlf-admin-note">“연결된 매물”은 하나 이상의 임대안내문에 포함된 매물, “미연결”은 아직 어떤 안내문에도 들어가지 않은 매물입니다.</p>';
		} ).catch( function ( err ) { errorText( el, '대시보드를 불러오지 못했습니다: ' + err.message ); } );
	}
	function statCard( value, label ) {
		return '<div class="hlf-stat-card"><strong>' + esc( value ) + '</strong><span>' + esc( label ) + '</span></div>';
	}

	/* ==================== 전체 매물(원본) 목록 ==================== */

	function renderSourceList( searchTerm ) {
		var el = main();
		el.innerHTML =
			'<div class="hlf-listup-head">' +
				'<h2 class="hlf-listup-title">전체 매물</h2>' +
				'<button type="button" class="button button-primary" id="hlf-src-new">+ 새 매물 등록</button>' +
			'</div>' +
			'<div class="hlf-toolbar">' +
				'<input type="search" id="hlf-src-search" placeholder="주소·키워드 검색" value="' + escAttr( searchTerm || '' ) + '">' +
				'<button type="button" class="button" id="hlf-src-search-btn">검색</button>' +
			'</div>' +
			'<div class="hlf-bulk-bar" id="hlf-src-bulk" hidden>' +
				'<span id="hlf-src-bulk-count">0개 선택됨</span> → ' +
				'<select id="hlf-src-bulk-flyer"><option value="">임대안내문 선택</option></select> ' +
				'<button type="button" class="button" id="hlf-src-bulk-add">선택 매물 추가</button>' +
			'</div>' +
			'<div id="hlf-src-results"><p class="hlf-admin-loading">불러오는 중…</p></div>';

		document.getElementById( 'hlf-src-new' ).addEventListener( 'click', function () { renderSourceForm( null ); } );
		var searchInput = document.getElementById( 'hlf-src-search' );
		document.getElementById( 'hlf-src-search-btn' ).addEventListener( 'click', function () { renderSourceList( searchInput.value ); } );
		searchInput.addEventListener( 'keydown', function ( e ) { if ( 'Enter' === e.key ) { renderSourceList( searchInput.value ); } } );

		// 일괄 추가용 Flyer 드롭다운 채우기.
		loadFlyers( function ( flyers ) {
			var sel = document.getElementById( 'hlf-src-bulk-flyer' );
			if ( sel ) {
				sel.innerHTML = '<option value="">임대안내문 선택</option>' + flyers.map( function ( f ) {
					return '<option value="' + f.id + '">' + escAttr( f.title || '(제목 없음)' ) + ' / ' + escAttr( f.flyer_number ) + '</option>';
				} ).join( '' );
			}
		} );

		var q = '' !== ( searchTerm || '' ) ? ( '&search=' + encodeURIComponent( searchTerm ) ) : '';
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
					'<th>보증금</th><th>임대료</th><th>관리비</th><th>포함 안내문</th><th>작업</th>' +
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
						'<td class="hlf-row-actions">' +
							'<button type="button" class="button button-small" data-hlf-src-edit="' + it.id + '">수정</button>' +
							'<button type="button" class="button button-small" data-hlf-src-preview="' + it.id + '">미리보기</button>' +
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

	// 여러 원본 매물을 한 Flyer에 순차 포함(각각 PUT). 하나라도 실패하면 멈추고 사유를 알린다.
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

	function renderSourceForm( sourceId ) {
		var el = main();
		el.innerHTML = '<p class="hlf-admin-loading">불러오는 중…</p>';
		if ( sourceId ) {
			api( 'source-listings/' + sourceId ).then( function ( src ) { drawSourceForm( src ); } )
				.catch( function ( err ) { errorText( el, '매물을 불러오지 못했습니다: ' + err.message ); } );
		} else {
			loadContacts( function ( dir ) {
				var def = defaultContact( dir );
				drawSourceForm( { id: null, contact_name: def.name, contact_phone: def.phone } );
			} );
		}
	}

	function defaultContact( dir ) {
		if ( dir && dir.contacts && dir.contacts.length ) {
			var idx = ( dir.default_index !== null && dir.default_index !== undefined ) ? dir.default_index : 0;
			var c = dir.contacts[ idx ] || dir.contacts[ 0 ];
			return { name: c.name, phone: c.phone };
		}
		return { name: '', phone: '' };
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

	function drawSourceForm( src ) {
		var editing = !! src.id;
		var el = main();

		// 조건 필드(체크박스 2개는 한 줄로 묶는다 — 관리자 편집 화면과 동일 규칙).
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
			( editing ? '<section class="hlf-card" id="hlf-src-images"></section>'
				: '<p class="hlf-admin-note hlf-image-pending">매물 사진은 저장한 뒤 추가할 수 있습니다 — 먼저 위 내용을 저장해 주세요.</p>' );

		document.getElementById( 'hlf-src-back' ).addEventListener( 'click', function () { renderSourceList(); } );
		var form = document.getElementById( 'hlf-src-form' );
		window.HLFOcr.bindSection( form );
		bindAddressSearch( form );
		bindContactPicker( form );
		bindSourceFormSubmit( form, src );
		if ( editing ) { renderSourceImages( src ); }
	}

	function bindSourceFormSubmit( form, src ) {
		var errorEl = form.querySelector( '[data-hlf-src-error]' );
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var btn = form.querySelector( 'button[type="submit"]' );
			btn.disabled = true; errorEl.hidden = true;
			var payload = readSourceForm( form );
			var path = src.id ? ( 'source-listings/' + src.id ) : 'source-listings';
			api( path, { method: src.id ? 'PUT' : 'POST', body: JSON.stringify( payload ) } )
				.then( function ( saved ) {
					toast( '저장했습니다.' );
					renderSourceForm( saved.id ); // 저장 후 편집 모드(이미지 섹션 노출)로.
				} )
				.catch( function ( err ) { errorEl.textContent = err.message; errorEl.hidden = false; btn.disabled = false; } );
		} );
	}

	function readSourceForm( form ) {
		var out = {};
		var defs = SOURCE_FIELDS.concat( [
			{ key: 'lot_address', type: 'text' }, { key: 'road_address', type: 'text' },
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

	/* ---------- 주소 검색 블록(지번→도로명/좌표 후보) ---------- */

	function addressBlock( src ) {
		return '<div class="hlf-address-block"><h4>주소 확인</h4>' +
			'<div class="hlf-field-grid">' +
				'<div class="hlf-field"><label for="hlf-src-f-lot_address">지번주소</label>' +
					'<input id="hlf-src-f-lot_address" type="text" name="lot_address" value="' + escAttr( src.lot_address || '' ) + '"></div>' +
				'<div class="hlf-field"><label for="hlf-src-f-road_address">도로명주소</label>' +
					'<input id="hlf-src-f-road_address" type="text" name="road_address" value="' + escAttr( src.road_address || '' ) + '" readonly class="hlf-field-readonly"></div>' +
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

	/* ---------- 담당자 선택(디렉터리 → 폼 채우기) ---------- */

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
		var ext = Number( src.exterior_image_id || 0 );
		var interior = ( src.interior_image_ids || [] ).map( Number );
		var ordered = ( ext ? [ ext ] : [] ).concat( interior );

		box.innerHTML =
			'<h4>매물 사진</h4>' +
			'<p class="hlf-admin-note">첫 번째 사진이 대표 이미지입니다. 미디어 라이브러리에서 선택하거나 새로 업로드할 수 있습니다.</p>' +
			'<button type="button" class="button" id="hlf-src-img-pick">사진 선택/추가</button>' +
			'<div class="hlf-img-strip">' +
				( ordered.length ? ordered.map( function ( id, i ) {
					var url = previews[ id ];
					return '<div class="hlf-img-thumb' + ( 0 === i ? ' is-primary' : '' ) + '">' +
						( url ? '<img src="' + escAttr( url ) + '" alt="">' : '<span class="hlf-img-missing">이미지</span>' ) +
						( 0 === i ? '<span class="hlf-img-badge">대표</span>' : '<button type="button" class="hlf-img-promote" data-id="' + id + '">대표로</button>' ) +
						'<button type="button" class="hlf-img-remove" data-id="' + id + '">×</button>' +
					'</div>';
				} ).join( '' ) : '<p class="hlf-empty">등록된 사진이 없습니다.</p>' ) +
			'</div>';

		document.getElementById( 'hlf-src-img-pick' ).addEventListener( 'click', function () { openImagePicker( src ); } );
		box.querySelectorAll( '.hlf-img-promote' ).forEach( function ( b ) { b.addEventListener( 'click', function () { promoteImage( src, Number( b.getAttribute( 'data-id' ) ) ); } ); } );
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
		var frame = window.wp.media( { title: '매물 사진 선택', multiple: 'add', library: { type: 'image' }, button: { text: '선택' } } );
		frame.on( 'select', function () {
			var chosen = frame.state().get( 'selection' ).map( function ( a ) { return a.id; } );
			var current = ( src.exterior_image_id ? [ Number( src.exterior_image_id ) ] : [] ).concat( ( src.interior_image_ids || [] ).map( Number ) );
			var merged = current.concat( chosen.filter( function ( id ) { return current.indexOf( id ) === -1; } ) );
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
					'<thead><tr><th>번호</th><th>제목</th><th>상태</th><th>매물 수</th><th>작업</th></tr></thead><tbody>' +
					flyers.map( function ( f ) {
						return '<tr>' +
							'<td>' + esc( f.flyer_number ) + '</td>' +
							'<td>' + esc( f.title || '(제목 없음)' ) + '</td>' +
							'<td><span class="' + A.statusBadgeClass( f.status ) + '">' + esc( A.statusLabel( f.status ) ) + '</span></td>' +
							'<td class="hlf-td-center">' + esc( f.item_count ) + '</td>' +
							'<td class="hlf-row-actions">' +
								'<button type="button" class="button button-small button-primary" data-hlf-fl-manage="' + f.id + '">포함 매물 관리</button>' +
								'<button type="button" class="button button-small" data-hlf-fl-rename="' + f.id + '">이름 수정</button>' +
								'<button type="button" class="button button-small" data-hlf-fl-preview="' + f.id + '">미리보기</button>' +
								'<button type="button" class="button button-small" data-hlf-fl-copy="' + f.id + '">링크 복사</button>' +
								'<a class="button button-small" href="' + escAttr( CONF.editUrlBase + f.id ) + '">상세 편집</a>' +
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
			var def = defaultContact( dir );
			el.innerHTML =
				'<div class="hlf-listup-head"><h2 class="hlf-listup-title">새 임대안내문</h2>' +
					'<button type="button" class="button" id="hlf-fl-back">← 목록</button></div>' +
				'<form id="hlf-fl-create" class="hlf-card">' +
					'<div class="hlf-field"><label for="hlf-fl-title">제목(내부 관리용)</label><input id="hlf-fl-title" type="text" name="title" required></div>' +
					'<div class="hlf-field"><label for="hlf-fl-cname">담당자명</label><input id="hlf-fl-cname" type="text" name="contact_name" value="' + escAttr( def.name ) + '"></div>' +
					'<div class="hlf-field"><label for="hlf-fl-cphone">담당자 연락처</label><input id="hlf-fl-cphone" type="text" name="contact_phone" value="' + escAttr( def.phone ) + '"></div>' +
					'<p class="hlf-admin-note">담당자는 기본 담당자로 미리 채워집니다. 필요하면 수정하세요.</p>' +
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

	function renderFlyerManage( flyerId, searchTerm ) {
		var el = main();
		el.innerHTML = '<p class="hlf-admin-loading">불러오는 중…</p>';
		api( 'flyers/' + flyerId ).then( function ( flyer ) {
			el.innerHTML =
				'<div class="hlf-listup-head">' +
					'<h2 class="hlf-listup-title">포함 매물 관리 — ' + esc( flyer.title || flyer.flyer_number ) + '</h2>' +
					'<button type="button" class="button" id="hlf-fm-back">← 목록</button>' +
				'</div>' +
				'<p class="hlf-admin-note hlf-safe-note">체크 해제 시 이 안내문에서만 제거되며, 원본 매물과 다른 안내문에 포함된 동일 매물은 그대로 유지됩니다. (최대 ' + esc( CONF.maxItems ) + '개)</p>' +
				'<div class="hlf-included-summary" id="hlf-fm-included"></div>' +
				'<div class="hlf-toolbar">' +
					'<input type="search" id="hlf-fm-search" placeholder="원본 매물 주소·키워드 검색" value="' + escAttr( searchTerm || '' ) + '">' +
					'<button type="button" class="button" id="hlf-fm-search-btn">검색</button>' +
				'</div>' +
				'<div id="hlf-fm-results"><p class="hlf-admin-loading">불러오는 중…</p></div>';

			document.getElementById( 'hlf-fm-back' ).addEventListener( 'click', renderFlyerList );
			var si = document.getElementById( 'hlf-fm-search' );
			document.getElementById( 'hlf-fm-search-btn' ).addEventListener( 'click', function () { renderFlyerManage( flyerId, si.value ); } );
			si.addEventListener( 'keydown', function ( e ) { if ( 'Enter' === e.key ) { renderFlyerManage( flyerId, si.value ); } } );

			renderIncludedSummary( flyer );

			var q = '' !== ( searchTerm || '' ) ? ( '&search=' + encodeURIComponent( searchTerm ) ) : '';
			api( 'source-listings?per_page=100' + q ).then( function ( data ) {
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
		// 이 Flyer에 이미 포함된 원본 id 집합(item.source_listing_id 기준).
		var includedSet = {};
		( flyer.items || [] ).forEach( function ( it ) { if ( it.source_listing_id ) { includedSet[ Number( it.source_listing_id ) ] = true; } } );

		if ( ! sources.length ) { box.innerHTML = '<p class="hlf-empty">등록된 원본 매물이 없습니다. “전체 매물” 탭에서 먼저 등록하세요.</p>'; return; }
		box.innerHTML =
			'<div class="hlf-table-wrap"><table class="hlf-table">' +
				'<thead><tr><th>포함</th><th>지번주소</th><th>층</th><th>보증금</th><th>임대료</th><th>관리비</th></tr></thead><tbody>' +
				sources.map( function ( s ) {
					var on = !! includedSet[ s.id ];
					return '<tr><td><input type="checkbox" class="hlf-fm-pick" data-id="' + s.id + '"' + ( on ? ' checked' : '' ) + '></td>' +
						'<td>' + esc( s.lot_address || '-' ) + '<small>' + esc( s.road_address || '' ) + '</small></td>' +
						'<td>' + esc( num( s.floor_current ) ) + '/' + esc( num( s.floor_total ) ) + '</td>' +
						'<td>' + esc( won( s.deposit_manwon ) ) + '</td>' +
						'<td>' + esc( won( s.monthly_rent_manwon ) ) + '</td>' +
						'<td>' + esc( won( s.maintenance_fee_manwon ) ) + '</td></tr>';
				} ).join( '' ) +
				'</tbody></table></div>';

		box.querySelectorAll( '.hlf-fm-pick' ).forEach( function ( c ) {
			c.addEventListener( 'change', function () {
				var sourceId = Number( c.getAttribute( 'data-id' ) );
				// 2-3: 이미 발행된(published) 안내문에서 매물을 빼는 것은 고객에게 공유된 링크에 즉시
				// 반영되므로 확인을 한 번 받는다(실제 제거는 막지 않음 — 완전 차단은 보관 상태만).
				if ( ! c.checked && 'published' === flyer.status &&
					! window.confirm( '이 안내문은 이미 발행되었습니다. 이 매물을 제거하면 공유된 링크에서 즉시 사라집니다. 계속할까요?' ) ) {
					c.checked = true; // 롤백.
					return;
				}
				c.disabled = true;
				var method = c.checked ? 'PUT' : 'DELETE';
				api( 'flyers/' + flyer.id + '/source-listings/' + sourceId, { method: method } )
					.then( function () {
						c.disabled = false;
						toast( c.checked ? '안내문에 추가했습니다.' : '안내문에서 제거했습니다.' );
						// 포함 요약을 갱신하려면 flyer를 다시 읽어 요약만 새로 그린다.
						api( 'flyers/' + flyer.id ).then( function ( fresh ) { flyer.items = fresh.items; renderIncludedSummary( fresh ); } );
					} )
					.catch( function ( err ) {
						c.disabled = false; c.checked = ! c.checked; // 롤백.
						window.alert( '변경하지 못했습니다: ' + err.message );
					} );
			} );
		} );
	}

	/* ==================== 설정(담당자 디렉터리) ==================== */

	function renderSettings() {
		var el = main();
		state.contacts = null; // 설정 화면 진입 시 최신값으로.
		el.innerHTML = '<h2 class="hlf-listup-title">설정 — 담당자 디렉터리</h2><div id="hlf-set-body"><p class="hlf-admin-loading">불러오는 중…</p></div>';
		api( 'contacts' ).then( function ( dir ) { state.contacts = dir; drawSettings( dir ); } )
			.catch( function ( err ) { errorText( document.getElementById( 'hlf-set-body' ), err.message ); } );
	}

	function drawSettings( dir ) {
		var body = document.getElementById( 'hlf-set-body' );
		body.innerHTML =
			'<section class="hlf-card"><p class="hlf-admin-note">여기 저장한 담당자는 새 매물/새 안내문 폼에서 빠르게 선택할 수 있고, “기본”으로 지정한 담당자는 새 안내문에 자동으로 채워집니다.</p>' +
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

	render();
} )();
