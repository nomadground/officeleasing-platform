/**
 * Flyer 생성/수정 화면.
 *
 * - HLF_ADMIN.flyerId 가 없으면(신규): 제목만 입력받는 최소 폼 → POST /flyers → 성공 시
 *   flyer_id가 붙은 같은 화면으로 리다이렉트(전체 새로고침, WP 관리자 화면의 표준 "추가 후 편집
 *   화면으로 이동" 패턴을 따른다 — Item은 Flyer가 실제로 존재해야 붙일 수 있으므로 이 편이
 *   생성 직후 상태를 복잡하게 다루는 것보다 단순하고 안전하다).
 * - flyerId 가 있으면: GET /flyers/{id} 로 flyer + items(계산 지표 포함)를 받아 전체 렌더링.
 *   제목/담당자 저장은 PUT, 상태변경은 POST .../status, Item CRUD는 POST/PUT/DELETE
 *   .../items[/:item_id] — 전부 hlf/v1 REST(HLF_REST_Controller) 그대로 호출한다.
 *
 * 계산값(임대평/전용평/평당 지표/NOC)은 입력 필드가 없다 — 서버가 저장된 값으로
 * hlf_calculate_item_metrics()를 호출해 응답에 실어 보내는 값을 표시만 한다(DB 재저장 없음).
 */
( function () {
	'use strict';

	var root = document.getElementById( 'hlf-flyer-edit-root' );
	var flyerId = HLF_ADMIN.flyerId;

	// key: 스키마 필드명(HLF_Meta_Schema::writable_fields()와 동일해야 함) / type: 입력 위젯.
	var ITEM_FIELDS = [
		{ key: 'road_address', label: '도로명주소', type: 'text' },
		{ key: 'lot_address', label: '지번주소', type: 'text' },
		{ key: 'latitude', label: '위도', type: 'number', step: 'any' },
		{ key: 'longitude', label: '경도', type: 'number', step: 'any' },
		{ key: 'floor_current', label: '해당층', type: 'text', placeholder: '예: 3 또는 B1' },
		{ key: 'floor_total', label: '총층', type: 'text', placeholder: '예: 6' },
		{ key: 'lease_area_sqm', label: '공급면적 (㎡)', type: 'number', step: '0.01' },
		{ key: 'exclusive_area_sqm', label: '전용면적 (㎡)', type: 'number', step: '0.01' },
		{ key: 'deposit_manwon', label: '보증금 (만원)', type: 'number', step: 'any' },
		{ key: 'monthly_rent_manwon', label: '임대료 (만원)', type: 'number', step: 'any' },
		{ key: 'maintenance_fee_manwon', label: '관리비 (만원)', type: 'number', step: 'any' },
		{ key: 'parking_available', label: '주차 가능', type: 'checkbox' },
		{ key: 'total_parking', label: '총주차대수', type: 'text', placeholder: '예: 자주식 10대' },
		{ key: 'elevator_available', label: '엘리베이터 있음', type: 'checkbox' },
		{ key: 'direction', label: '방향', type: 'text' },
		{ key: 'available_date_text', label: '입주가능일', type: 'text', placeholder: '예: 즉시입주 협의가능' },
		{ key: 'approval_date', label: '사용승인일', type: 'text', placeholder: '예: 2018.06.21' },
		{ key: 'building_use', label: '건축물용도', type: 'text' },
		{ key: 'features', label: '매물특징', type: 'textarea', wide: true },
		{ key: 'contact_name', label: '담당자명 (선택 — 이 매물만 다르면 입력)', type: 'text' },
		{ key: 'contact_phone', label: '담당자 연락처 (선택 — 이 매물만 다르면 입력)', type: 'text' },
		{ key: 'article_no', label: '매물번호', type: 'text' },
	];

	// officeleasing-core acf-json(group_ol_listing.json)의 listing_status 실제 choices 그대로.
	var OFFICELEASING_STATUS_CHOICES = [
		{ value: '', label: '전체 상태' },
		{ value: 'available', label: '임대가능' },
		{ value: 'reserved', label: '협의중' },
		{ value: 'contract_pending', label: '계약진행중' },
		{ value: 'leased', label: '거래완료' },
		{ value: 'temporarily_hidden', label: '노출중지' },
		{ value: 'expired', label: '만료' },
	];

	var state = {
		flyer: null, items: [], editingItemId: null,
		search: { open: false, page: 1, results: null, importingId: null },
	};

	/* ---------------- 신규 Flyer 생성 모드 ---------------- */

	function renderCreateForm() {
		root.innerHTML =
			'<section class="hlf-card">' +
				'<form id="hlf-new-flyer-form">' +
					'<div class="hlf-field"><label for="hlf-new-title">제목(내부 관리용)</label><input id="hlf-new-title" type="text" name="title" required></div>' +
					'<div class="hlf-field"><label for="hlf-new-contact-name">담당자명</label><input id="hlf-new-contact-name" type="text" name="contact_name"></div>' +
					'<div class="hlf-field"><label for="hlf-new-contact-phone">담당자 연락처</label><input id="hlf-new-contact-phone" type="text" name="contact_phone"></div>' +
					'<p class="hlf-admin-note">매물(Item)은 Flyer를 먼저 만든 뒤 이어서 추가합니다.</p>' +
					'<button type="submit" class="button button-primary">만들기</button>' +
					'<p class="hlf-admin-error" data-hlf-create-error hidden></p>' +
				'</form>' +
			'</section>';

		var form = document.getElementById( 'hlf-new-flyer-form' );
		var errorEl = form.querySelector( '[data-hlf-create-error]' );
		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			var submitButton = form.querySelector( 'button[type="submit"]' );
			submitButton.disabled = true;
			errorEl.hidden = true;

			HLFAdmin.apiFetch( 'flyers', {
				method: 'POST',
				body: JSON.stringify( {
					title: form.title.value,
					contact_name: form.contact_name.value,
					contact_phone: form.contact_phone.value,
				} ),
			} )
				.then( function ( flyer ) {
					window.location.href = HLF_ADMIN.editUrlBase + flyer.id;
				} )
				.catch( function ( err ) {
					errorEl.textContent = err.message;
					errorEl.hidden = false;
					submitButton.disabled = false;
				} );
		} );
	}

	/* ---------------- 기존 Flyer 편집 모드 ---------------- */

	function loadFlyer() {
		root.innerHTML = '<p class="hlf-admin-loading">불러오는 중…</p>';
		HLFAdmin.apiFetch( 'flyers/' + flyerId )
			.then( function ( flyer ) {
				state.flyer = flyer;
				state.items = flyer.items || [];
				renderEdit();
			} )
			.catch( function ( err ) {
				root.innerHTML = '<p class="hlf-admin-error">Flyer를 불러오지 못했습니다: ' + HLFAdmin.escapeHtml( err.message ) + '</p>';
			} );
	}

	function renderEdit() {
		root.innerHTML =
			'<div class="hlf-edit-grid">' +
				renderFlyerCard() +
				renderItemsCard() +
			'</div>';
		bindFlyerCard();
		bindItemsCard();
		bindImportPanel();
	}

	function renderFlyerCard() {
		var flyer = state.flyer;
		return (
			'<section class="hlf-card">' +
				'<h2>' + HLFAdmin.escapeHtml( flyer.flyer_number ) + ' <span class="' + HLFAdmin.statusBadgeClass( flyer.status ) + '">' + HLFAdmin.statusLabel( flyer.status ) + '</span></h2>' +
				'<form id="hlf-flyer-form">' +
					'<div class="hlf-field"><label for="hlf-flyer-title">제목(내부 관리용)</label><input id="hlf-flyer-title" type="text" name="title" value="' + HLFAdmin.escapeAttr( flyer.title ) + '" required></div>' +
					'<div class="hlf-field"><label for="hlf-flyer-contact-name">담당자명</label><input id="hlf-flyer-contact-name" type="text" name="contact_name" value="' + HLFAdmin.escapeAttr( flyer.contact_name ) + '"></div>' +
					'<div class="hlf-field"><label for="hlf-flyer-contact-phone">담당자 연락처</label><input id="hlf-flyer-contact-phone" type="text" name="contact_phone" value="' + HLFAdmin.escapeAttr( flyer.contact_phone ) + '"></div>' +
					'<p class="hlf-admin-note">공개 화면 하단 문의처로 쓰입니다. 비워두면 대표번호(' + HLFAdmin.escapeHtml( HLF_ADMIN.defaultPhone ) + ')로 표시됩니다.</p>' +
					'<button type="submit" class="button button-primary">저장</button>' +
					'<p class="hlf-admin-error" data-hlf-flyer-error hidden></p>' +
				'</form>' +
				'<hr>' +
				'<div class="hlf-field">' +
					'<label for="hlf-status-select">상태</label>' +
					'<select id="hlf-status-select">' +
						'<option value="draft"' + ( flyer.status === 'draft' ? ' selected' : '' ) + '>미발행(draft)</option>' +
						'<option value="published"' + ( flyer.status === 'published' ? ' selected' : '' ) + '>발행됨(published)</option>' +
						'<option value="archived"' + ( flyer.status === 'archived' ? ' selected' : '' ) + '>보관(archived)</option>' +
					'</select> ' +
					'<button type="button" class="button" id="hlf-status-apply">상태 변경</button>' +
					'<p class="hlf-admin-error" data-hlf-status-error hidden></p>' +
				'</div>' +
				'<p class="hlf-admin-note"><a href="' + HLFAdmin.escapeAttr( flyer.url ) + '" target="_blank" rel="noopener">공개 링크 열기 ↗</a></p>' +
			'</section>'
		);
	}

	function bindFlyerCard() {
		var form = document.getElementById( 'hlf-flyer-form' );
		var flyerError = form.querySelector( '[data-hlf-flyer-error]' );
		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			var submitButton = form.querySelector( 'button[type="submit"]' );
			submitButton.disabled = true;
			flyerError.hidden = true;

			HLFAdmin.apiFetch( 'flyers/' + flyerId, {
				method: 'PUT',
				body: JSON.stringify( {
					title: form.title.value,
					contact_name: form.contact_name.value,
					contact_phone: form.contact_phone.value,
				} ),
			} )
				.then( function ( flyer ) {
					state.flyer = Object.assign( state.flyer, flyer );
					submitButton.disabled = false;
				} )
				.catch( function ( err ) {
					flyerError.textContent = err.message;
					flyerError.hidden = false;
					submitButton.disabled = false;
				} );
		} );

		var statusSelect = document.getElementById( 'hlf-status-select' );
		var statusButton = document.getElementById( 'hlf-status-apply' );
		var statusError = form.parentNode.querySelector( '[data-hlf-status-error]' );
		statusButton.addEventListener( 'click', function () {
			var next = statusSelect.value;
			if ( next === state.flyer.status ) { return; }
			if ( ( next === 'published' || next === 'archived' ) &&
				! window.confirm( '상태를 "' + HLFAdmin.statusLabel( next ) + '"(으)로 바꿉니다. 공개 링크의 열람 가능 범위가 즉시 바뀝니다. 계속할까요?' ) ) {
				statusSelect.value = state.flyer.status;
				return;
			}
			statusButton.disabled = true;
			statusError.hidden = true;
			HLFAdmin.apiFetch( 'flyers/' + flyerId + '/status', {
				method: 'POST',
				body: JSON.stringify( { status: next } ),
			} )
				.then( function ( flyer ) {
					state.flyer = flyer;
					renderEdit();
				} )
				.catch( function ( err ) {
					statusError.textContent = err.message;
					statusError.hidden = false;
					statusButton.disabled = false;
					statusSelect.value = state.flyer.status;
				} );
		} );
	}

	/* ---------------- Item 목록 + 폼 ---------------- */

	function renderItemsCard() {
		var atLimit = state.items.length >= HLF_ADMIN.maxItems;
		var limitAttr = atLimit ? ' disabled title="최대 ' + HLF_ADMIN.maxItems + '개까지만 담을 수 있습니다."' : '';
		return (
			'<section class="hlf-card">' +
				'<h2>매물(Item) — ' + state.items.length + ' / ' + HLF_ADMIN.maxItems + '</h2>' +
				'<div id="hlf-items-table-wrap">' + renderItemsTable() + '</div>' +
				'<button type="button" class="button button-primary" id="hlf-add-item"' + limitAttr + '>매물 추가</button> ' +
				'<button type="button" class="button" id="hlf-import-toggle"' + limitAttr + '>원본 매물에서 가져오기</button>' +
				renderItemForm() +
				renderImportPanel() +
			'</section>'
		);
	}

	/* ---------------- officeleasing 검색/가져오기 패널 ---------------- */

	function renderImportPanel() {
		if ( ! state.search.open ) {
			return '';
		}
		var statusOptions = OFFICELEASING_STATUS_CHOICES.map( function ( c ) {
			return '<option value="' + HLFAdmin.escapeAttr( c.value ) + '">' + HLFAdmin.escapeHtml( c.label ) + '</option>';
		} ).join( '' );

		return (
			'<div class="hlf-import-panel">' +
				'<h3>원본 매물에서 가져오기</h3>' +
				'<form id="hlf-officeleasing-search-form">' +
					'<div class="hlf-field"><label for="hlf-search-term">검색어(매물 제목/건물명/주소)</label><input id="hlf-search-term" type="text" name="search" placeholder="예: 파르나스타워, 테헤란로"></div>' +
					'<div class="hlf-field"><label for="hlf-search-status">상태</label><select id="hlf-search-status" name="status">' + statusOptions + '</select></div>' +
					'<button type="submit" class="button button-primary">검색</button> ' +
					'<button type="button" class="button" id="hlf-import-panel-close">닫기</button>' +
				'</form>' +
				'<p class="hlf-admin-error" data-hlf-search-error hidden></p>' +
				'<div id="hlf-officeleasing-results">' + renderImportResults() + '</div>' +
			'</div>'
		);
	}

	function renderImportResults() {
		var results = state.search.results;
		if ( null === results ) {
			return '<p class="hlf-admin-note">검색어를 입력하고 검색을 눌러 주세요(비워두면 전체 목록).</p>';
		}
		if ( ! results.items.length ) {
			return '<p class="hlf-admin-empty">일치하는 매물이 없습니다.</p>';
		}
		var atLimit = state.items.length >= HLF_ADMIN.maxItems;
		var statusLabelMap = {};
		OFFICELEASING_STATUS_CHOICES.forEach( function ( c ) { statusLabelMap[ c.value ] = c.label; } );

		var rows = results.items.map( function ( listing ) {
			var address = listing.road_address || listing.lot_address || '-';
			var statusLabel = statusLabelMap[ listing.listing_status ] || listing.listing_status || '-';
			var importing = state.search.importingId === listing.listing_id;
			return (
				'<tr>' +
					'<td>' + HLFAdmin.escapeHtml( listing.listing_title ) + '</td>' +
					'<td>' + HLFAdmin.escapeHtml( listing.building_title ) + '<br><small>' + HLFAdmin.escapeHtml( address ) + '</small></td>' +
					'<td>' + HLFAdmin.escapeHtml( statusLabel ) + '</td>' +
					'<td>' + HLFAdmin.formatManwon( listing.deposit_manwon ) + ' / ' + HLFAdmin.formatManwon( listing.monthly_rent_manwon ) + '</td>' +
					'<td><button type="button" class="button button-small" data-hlf-import-listing="' + HLFAdmin.escapeAttr( listing.listing_id ) + '"' + ( atLimit || importing ? ' disabled' : '' ) + '>' + ( importing ? '가져오는 중…' : '가져오기' ) + '</button></td>' +
				'</tr>'
			);
		} ).join( '' );

		var pager =
			'<div class="hlf-import-pager">' +
				'<button type="button" class="button button-small" id="hlf-search-prev-page"' + ( results.page <= 1 ? ' disabled' : '' ) + '>← 이전</button> ' +
				'<span>' + results.page + ' 페이지 (총 ' + results.total + '건)</span> ' +
				'<button type="button" class="button button-small" id="hlf-search-next-page"' + ( results.page * results.per_page >= results.total ? ' disabled' : '' ) + '>다음 →</button>' +
			'</div>';

		return (
			'<table class="widefat striped hlf-admin-table">' +
				'<thead><tr><th>매물</th><th>빌딩/주소</th><th>상태</th><th>보증금/임대료</th><th>작업</th></tr></thead>' +
				'<tbody>' + rows + '</tbody>' +
			'</table>' + pager
		);
	}

	// 요청이 늦게 끝난 이전 검색 응답이 이후 검색 결과를 덮어쓰지 않도록 매 호출마다 증가시키는
	// 시퀀스 번호. 응답이 도착했을 때 자신이 "가장 최근에 보낸 요청"이 아니면 렌더링하지 않는다.
	var searchRequestSeq = 0;

	function runOfficeleasingSearch( searchTerm, status, page, onSettled ) {
		var errorEl = document.querySelector( '[data-hlf-search-error]' );
		var query = 'officeleasing/listings?page=' + encodeURIComponent( page ) +
			( searchTerm ? '&search=' + encodeURIComponent( searchTerm ) : '' ) +
			( status ? '&status=' + encodeURIComponent( status ) : '' );

		var requestId = ++searchRequestSeq;

		HLFAdmin.apiFetch( query )
			.then( function ( result ) {
				if ( requestId !== searchRequestSeq ) { return; } // 더 최신 요청이 이미 나가 있음 — 이 응답은 버린다.
				state.search.page = result.page;
				state.search.results = result;
				// wrap 엘리먼트 자체는 그대로 두고 내용만 갱신한다 — 클릭 리스너는 bindImportResults()가
				// 패널이 열릴 때 wrap에 딱 한 번 위임 방식으로 붙여두므로(아래), 여기서 다시 부르면
				// 같은 엘리먼트에 리스너가 중복으로 쌓인다(클릭 한 번에 핸들러가 N번 실행되는 버그).
				document.getElementById( 'hlf-officeleasing-results' ).innerHTML = renderImportResults();
			} )
			.catch( function ( err ) {
				if ( requestId !== searchRequestSeq ) { return; }
				if ( errorEl ) {
					errorEl.textContent = err.message;
					errorEl.hidden = false;
				}
			} )
			.then( function () {
				if ( requestId === searchRequestSeq && onSettled ) { onSettled(); }
			} );
	}

	// wrap에 클릭 리스너를 정확히 한 번만 붙인다(이벤트 위임) — bindImportPanel()에서만 호출한다.
	// 이후 검색 결과 갱신/가져오기 진행 중 표시는 wrap.innerHTML만 바꾸고 리스너는 다시 붙이지 않는다.
	function bindImportResults() {
		var wrap = document.getElementById( 'hlf-officeleasing-results' );
		if ( ! wrap ) { return; }

		wrap.addEventListener( 'click', function ( event ) {
			var prevBtn = event.target.closest( '#hlf-search-prev-page' );
			var nextBtn = event.target.closest( '#hlf-search-next-page' );
			if ( prevBtn || nextBtn ) {
				var lastSearch = state.search.lastQuery || { search: '', status: '' };
				var nextPage = state.search.page + ( nextBtn ? 1 : -1 );
				runOfficeleasingSearch( lastSearch.search, lastSearch.status, nextPage );
				return;
			}
			var importBtn = event.target.closest( '[data-hlf-import-listing]' );
			if ( ! importBtn || importBtn.disabled ) { return; }

			var listingId = importBtn.getAttribute( 'data-hlf-import-listing' );
			state.search.importingId = Number( listingId ); // 중복 클릭 방지: 진행 중인 listing_id를 기록.
			document.getElementById( 'hlf-officeleasing-results' ).innerHTML = renderImportResults();

			HLFAdmin.apiFetch( 'flyers/' + flyerId + '/items/import', {
				method: 'POST',
				body: JSON.stringify( { listing_id: Number( listingId ) } ),
			} )
				.then( function () {
					return HLFAdmin.apiFetch( 'flyers/' + flyerId );
				} )
				.then( function ( flyer ) {
					state.flyer = flyer;
					state.items = flyer.items || [];
					state.search.open = false;
					state.search.importingId = null;
					renderEdit();
				} )
				.catch( function ( err ) {
					state.search.importingId = null;
					window.alert( '가져오기에 실패했습니다: ' + err.message );
					document.getElementById( 'hlf-officeleasing-results' ).innerHTML = renderImportResults();
				} );
		} );
	}

	function bindImportPanel() {
		var toggleButton = document.getElementById( 'hlf-import-toggle' );
		if ( toggleButton && ! toggleButton.disabled ) {
			toggleButton.addEventListener( 'click', function () {
				state.search.open = true;
				state.search.results = null;
				renderEdit();
			} );
		}

		var closeButton = document.getElementById( 'hlf-import-panel-close' );
		if ( closeButton ) {
			closeButton.addEventListener( 'click', function () {
				state.search.open = false;
				renderEdit();
			} );
		}

		var searchForm = document.getElementById( 'hlf-officeleasing-search-form' );
		if ( searchForm ) {
			searchForm.addEventListener( 'submit', function ( event ) {
				event.preventDefault();
				var searchTerm = searchForm.search.value.trim();
				var status = searchForm.status.value;
				state.search.lastQuery = { search: searchTerm, status: status };
				var submitButton = searchForm.querySelector( 'button[type="submit"]' );
				submitButton.disabled = true;
				// 요청이 실제로 끝난 뒤에만 버튼을 다시 활성화한다(고정 setTimeout이 아님) — 느린 응답이
				// 늦게 도착해 최신 검색 결과를 덮어쓰는 경쟁 상태는 runOfficeleasingSearch()의 시퀀스
				// 번호 검사로 별도 차단된다.
				runOfficeleasingSearch( searchTerm, status, 1, function () { submitButton.disabled = false; } );
			} );
			bindImportResults();
		}
	}

	function renderItemsTable() {
		if ( ! state.items.length ) {
			return '<p class="hlf-admin-empty">아직 등록된 매물이 없습니다.</p>';
		}
		var rows = state.items.map( function ( item ) {
			var address = item.road_address || item.lot_address || '-';
			var m = item.metrics || {};
			return (
				'<tr data-item-row="' + HLFAdmin.escapeAttr( item.id ) + '">' +
					'<td>' + HLFAdmin.escapeHtml( item.item_number ) + '</td>' +
					'<td>' + HLFAdmin.escapeHtml( address ) + '</td>' +
					'<td>' + HLFAdmin.formatManwon( item.deposit_manwon ) + ' / ' + HLFAdmin.formatManwon( item.monthly_rent_manwon ) + ' / ' + HLFAdmin.formatManwon( item.maintenance_fee_manwon ) + '</td>' +
					'<td>' + HLFAdmin.formatNumber1( m.noc ) + '만원/평</td>' +
					'<td class="hlf-admin-actions">' +
						'<button type="button" class="button button-small" data-hlf-edit-item="' + HLFAdmin.escapeAttr( item.id ) + '">수정</button> ' +
						'<button type="button" class="button button-small hlf-danger" data-hlf-delete-item="' + HLFAdmin.escapeAttr( item.id ) + '">삭제</button>' +
					'</td>' +
				'</tr>'
			);
		} ).join( '' );

		return (
			'<table class="widefat striped hlf-admin-table">' +
				'<thead><tr><th>번호</th><th>주소</th><th>보증금/임대료/관리비</th><th>NOC</th><th>작업</th></tr></thead>' +
				'<tbody>' + rows + '</tbody>' +
			'</table>'
		);
	}

	function renderItemForm() {
		var editing = state.editingItemId !== null;
		var item = editing ? state.items.find( function ( i ) { return i.id === state.editingItemId; } ) : null;

		var fieldsHtml = ITEM_FIELDS.map( function ( def ) {
			var value = item ? item[ def.key ] : '';
			var wideClass = def.wide ? ' hlf-field-wide' : '';
			if ( def.type === 'checkbox' ) {
				return (
					'<label class="hlf-field hlf-field-checkbox' + wideClass + '">' +
						'<input type="checkbox" name="' + def.key + '"' + ( value ? ' checked' : '' ) + '> ' + HLFAdmin.escapeHtml( def.label ) +
					'</label>'
				);
			}
			// name(=def.key, HLF_Meta_Schema 필드명)은 폼 하나 안에서 항상 유일하므로 그대로 id로
			// 재사용해도 충돌하지 않는다(같은 폼 인스턴스는 항상 하나만 렌더링됨).
			var fieldId = 'hlf-item-field-' + def.key;
			if ( def.type === 'textarea' ) {
				return (
					'<div class="hlf-field' + wideClass + '"><label for="' + fieldId + '">' + HLFAdmin.escapeHtml( def.label ) + '</label>' +
					'<textarea id="' + fieldId + '" name="' + def.key + '">' + HLFAdmin.escapeHtml( value || '' ) + '</textarea></div>'
				);
			}
			var stepAttr = def.step ? ' step="' + def.step + '"' : '';
			var placeholderAttr = def.placeholder ? ' placeholder="' + HLFAdmin.escapeAttr( def.placeholder ) + '"' : '';
			// 지번주소 필드에만 "주소 검색" 버튼을 붙인다 — 카카오 Local API(서버 프록시, REST 키는
			// 클라이언트에 노출하지 않음)로 도로명주소/좌표를 자동 채운다. 설정 안 됐으면 버튼 클릭
			// 시 서버가 501을 돌려주고 아래 상태 문구로만 안내한다(폼 자체는 그대로 동작).
			var addressSearchHtml = ( def.key === 'lot_address' ) ?
				' <button type="button" class="button button-small" id="hlf-address-search">주소 검색</button>' +
				'<p class="hlf-admin-note" id="hlf-address-search-status"></p>' : '';
			return (
				'<div class="hlf-field' + wideClass + '"><label for="' + fieldId + '">' + HLFAdmin.escapeHtml( def.label ) + '</label>' +
				'<input id="' + fieldId + '" type="' + def.type + '" name="' + def.key + '" value="' + HLFAdmin.escapeAttr( value === null || value === undefined ? '' : value ) + '"' + stepAttr + placeholderAttr + '>' +
				addressSearchHtml +
				'</div>'
			);
		} ).join( '' );

		var metricsHtml = '';
		if ( item && item.metrics ) {
			var m = item.metrics;
			metricsHtml =
				'<div class="hlf-metrics-readonly">' +
					'<strong>계산값(마지막 저장 기준, 읽기 전용)</strong>' +
					'<ul>' +
						'<li>공급평: ' + HLFAdmin.formatNumber1( m.lease_pyeong ) + '평</li>' +
						'<li>전용평: ' + HLFAdmin.formatNumber1( m.exclusive_pyeong ) + '평</li>' +
						'<li>공급평당 보증금/임대료/관리비: ' + HLFAdmin.formatNumber1( m.deposit_per_lease_pyeong ) + ' / ' + HLFAdmin.formatNumber1( m.rent_per_lease_pyeong ) + ' / ' + HLFAdmin.formatNumber1( m.maintenance_per_lease_pyeong ) + ' 만원</li>' +
						'<li>NOC(전용평당 환산임대료): ' + HLFAdmin.formatNumber1( m.noc ) + '만원</li>' +
					'</ul>' +
				'</div>';
		}

		return (
			'<form id="hlf-item-form" class="hlf-item-form"' + ( editing ? '' : ' hidden' ) + '>' +
				'<h3>' + ( editing ? '매물 수정 (' + HLFAdmin.escapeHtml( item.item_number ) + ')' : '매물 추가' ) + '</h3>' +
				'<div class="hlf-field-grid">' + fieldsHtml + '</div>' +
				metricsHtml +
				'<button type="submit" class="button button-primary">저장</button> ' +
				'<button type="button" class="button" id="hlf-item-cancel">취소</button>' +
				'<p class="hlf-admin-error" data-hlf-item-error hidden></p>' +
			'</form>' +
			// 이미지 섹션은 별도 <form> submit(엔터키 등)에 휘말리지 않도록 hlf-item-form 밖의
			// 형제 요소로 둔다. 이미지는 attachment의 post_parent가 item_id라 Item이 실제로 저장돼
			// 있어야만(=수정 모드) 다룰 수 있다 — 추가(생성) 모드에서는 안내문만 보여준다.
			( editing ? renderImageSection( item ) : '' )
		);
	}

	/* ---------------- 매물 사진(WordPress Media Library) ---------------- */

	function renderImageSection( item ) {
		return (
			'<section class="hlf-image-section">' +
				'<h3>매물 사진</h3>' +
				'<div id="hlf-saved-images">' + renderSavedImages( item ) + '</div>' +
				'<button type="button" class="button button-primary" id="hlf-image-picker">사진 선택</button>' +
			'</section>'
		);
	}

	function renderSavedImages( item ) {
		var previews = item.image_previews || {};
		var exteriorId = item.exterior_image_id;
		var interiorIds = item.interior_image_ids || [];

		if ( ! exteriorId && ! interiorIds.length ) {
			return '<p class="hlf-admin-note">아직 선택된 사진이 없습니다.</p>';
		}

		function renderThumb( id, isExterior ) {
			var url = previews[ id ] || previews[ String( id ) ] || '';
			var radioId = 'hlf-image-primary-' + id;
			return (
				'<div class="hlf-saved-image' + ( isExterior ? ' is-primary' : '' ) + '">' +
					( url ? '<img src="' + HLFAdmin.escapeAttr( url ) + '" alt="">' : '<div class="hlf-saved-image-missing">미리보기 없음</div>' ) +
					( isExterior ? '<span class="hlf-saved-image-badge">대표</span>' : '' ) +
					'<label class="hlf-saved-image-radio" for="' + radioId + '">' +
						'<input type="radio" id="' + radioId + '" name="hlf-primary-image" data-hlf-image-primary="' + id + '"' + ( isExterior ? ' checked' : '' ) + '> 대표사진' +
					'</label>' +
					'<div class="hlf-saved-image-actions">' +
						'<button type="button" class="button button-small hlf-danger" data-hlf-image-delete="' + id + '">삭제</button>' +
					'</div>' +
				'</div>'
			);
		}

		var html = '<div class="hlf-saved-image-grid">';
		if ( exteriorId ) {
			html += renderThumb( exteriorId, true );
		}
		interiorIds.forEach( function ( id ) {
			html += renderThumb( id, false );
		} );
		html += '</div>';
		return html;
	}

	/** "사진 선택" 버튼(wp.media 모달 오픈), 대표사진 라디오, 삭제 버튼 — 전부 한 번만 바인딩. */
	function bindImageSection( item ) {
		var pickerButton = document.getElementById( 'hlf-image-picker' );
		if ( pickerButton ) {
			pickerButton.addEventListener( 'click', function () {
				openImagePicker( item );
			} );
		}

		var savedWrap = document.getElementById( 'hlf-saved-images' );
		if ( savedWrap ) {
			savedWrap.addEventListener( 'change', function ( event ) {
				var radio = event.target.closest( '[data-hlf-image-primary]' );
				if ( radio && radio.checked ) {
					promoteImage( item, Number( radio.getAttribute( 'data-hlf-image-primary' ) ) );
				}
			} );
			savedWrap.addEventListener( 'click', function ( event ) {
				var deleteBtn = event.target.closest( '[data-hlf-image-delete]' );
				if ( deleteBtn ) {
					var id = Number( deleteBtn.getAttribute( 'data-hlf-image-delete' ) );
					if ( ! window.confirm( '이 사진을 매물에서 뗄까요? (파일 자체는 삭제되지 않습니다)' ) ) { return; }
					deleteImage( item, id );
				}
			} );
		}
	}

	// wp.media는 워드프레스 코어 스크립트라 플러그인이 직접 구현하지 않는다(HLF_Admin_UI::enqueue_assets()의
	// wp_enqueue_media() 호출로 로드됨). multiple:true + library.type:'image'로 신규 업로드/기존 미디어
	// 선택을 모두 허용한다. 선택 완료 시 기존 대표사진이 없으면 첫 번째를 대표로, 있으면 전부 나머지에 추가.
	function openImagePicker( item ) {
		if ( ! window.wp || ! wp.media ) {
			window.alert( '미디어 라이브러리를 불러오지 못했습니다. 페이지를 새로고침한 뒤 다시 시도해 주세요.' );
			return;
		}
		var frame = wp.media( {
			title: '매물 사진 선택',
			button: { text: '선택 완료' },
			multiple: true,
			library: { type: 'image' },
		} );
		frame.on( 'select', function () {
			var selectedIds = frame.state().get( 'selection' ).toJSON().map( function ( attachment ) { return attachment.id; } );
			if ( ! selectedIds.length ) { return; }

			var exterior = item.exterior_image_id;
			var interior = ( item.interior_image_ids || [] ).slice();
			if ( ! exterior ) {
				exterior = selectedIds.shift();
			}
			selectedIds.forEach( function ( id ) {
				if ( id !== exterior && interior.indexOf( id ) === -1 ) {
					interior.push( id );
				}
			} );
			saveImageState( item, exterior, interior );
		} );
		frame.open();
	}

	function saveImageState( item, exteriorId, interiorIds ) {
		HLFAdmin.apiFetch( 'flyers/' + flyerId + '/items/' + item.id + '/images', {
			method: 'PUT',
			body: JSON.stringify( { exterior_image_id: exteriorId, interior_image_ids: interiorIds } ),
		} )
			.then( function () { return HLFAdmin.apiFetch( 'flyers/' + flyerId ); } )
			.then( function ( flyer ) {
				state.flyer = flyer;
				state.items = flyer.items || [];
				renderEdit();
			} )
			.catch( function ( err ) {
				window.alert( '이미지 정보를 저장하지 못했습니다: ' + err.message );
			} );
	}

	function promoteImage( item, id ) {
		var interior = ( item.interior_image_ids || [] ).filter( function ( i ) { return i !== id; } );
		if ( item.exterior_image_id ) {
			interior.unshift( item.exterior_image_id );
		}
		saveImageState( item, id, interior );
	}

	function deleteImage( item, id ) {
		HLFAdmin.apiFetch( 'flyers/' + flyerId + '/items/' + item.id + '/images/' + id, { method: 'DELETE' } )
			.then( function () { return HLFAdmin.apiFetch( 'flyers/' + flyerId ); } )
			.then( function ( flyer ) {
				state.flyer = flyer;
				state.items = flyer.items || [];
				renderEdit();
			} )
			.catch( function ( err ) {
				window.alert( '이미지를 삭제하지 못했습니다: ' + err.message );
			} );
	}

	function readItemForm( form ) {
		var out = {};
		ITEM_FIELDS.forEach( function ( def ) {
			var input = form.elements[ def.key ];
			if ( ! input ) { return; }
			if ( def.type === 'checkbox' ) {
				out[ def.key ] = input.checked;
			} else if ( def.type === 'number' ) {
				out[ def.key ] = input.value === '' ? '' : Number( input.value );
			} else {
				out[ def.key ] = input.value;
			}
		} );
		return out;
	}

	function bindItemsCard() {
		var addButton = document.getElementById( 'hlf-add-item' );
		var itemForm = document.getElementById( 'hlf-item-form' );
		var cancelButton = document.getElementById( 'hlf-item-cancel' );
		var itemError = itemForm.querySelector( '[data-hlf-item-error]' );

		if ( addButton && ! addButton.disabled ) {
			addButton.addEventListener( 'click', function () {
				// 직전에 다른 항목을 수정 중이었을 수 있으므로(헤더 문구·계산값 표시가 그 항목 것으로
				// 남아있음) 폼을 손으로 리셋하지 않고 항상 다시 그린다 — "추가" 상태의 깨끗한 폼 보장.
				state.editingItemId = null;
				renderEdit();
				var freshForm = document.getElementById( 'hlf-item-form' );
				if ( freshForm ) {
					freshForm.hidden = false;
					freshForm.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
				}
			} );
		}

		cancelButton.addEventListener( 'click', function () {
			itemForm.hidden = true;
			state.editingItemId = null;
		} );

		itemForm.addEventListener( 'submit', function ( event ) {
			event.preventDefault();
			var submitButton = itemForm.querySelector( 'button[type="submit"]' );
			submitButton.disabled = true;
			itemError.hidden = true;

			var payload = readItemForm( itemForm );
			var isEdit = state.editingItemId !== null;
			var path = 'flyers/' + flyerId + '/items' + ( isEdit ? '/' + state.editingItemId : '' );

			HLFAdmin.apiFetch( path, { method: isEdit ? 'PUT' : 'POST', body: JSON.stringify( payload ) } )
				.then( function () {
					return HLFAdmin.apiFetch( 'flyers/' + flyerId );
				} )
				.then( function ( flyer ) {
					state.flyer = flyer;
					state.items = flyer.items || [];
					state.editingItemId = null;
					renderEdit();
				} )
				.catch( function ( err ) {
					itemError.textContent = err.message;
					itemError.hidden = false;
					submitButton.disabled = false;
				} );
		} );

		var addressSearchButton = document.getElementById( 'hlf-address-search' );
		if ( addressSearchButton ) {
			addressSearchButton.addEventListener( 'click', function () {
				var statusEl = document.getElementById( 'hlf-address-search-status' );
				var query = ( itemForm.elements.lot_address.value || '' ).trim();
				if ( ! query ) {
					if ( statusEl ) { statusEl.textContent = '지번주소를 입력해 주세요.'; }
					return;
				}
				addressSearchButton.disabled = true;
				if ( statusEl ) { statusEl.textContent = '주소를 조회하고 있습니다…'; }

				HLFAdmin.apiFetch( 'kakao/address-search?q=' + encodeURIComponent( query ) )
					.then( function ( result ) {
						if ( result.road_address ) { itemForm.elements.road_address.value = result.road_address; }
						if ( result.latitude ) { itemForm.elements.latitude.value = result.latitude; }
						if ( result.longitude ) { itemForm.elements.longitude.value = result.longitude; }
						if ( statusEl ) { statusEl.textContent = '도로명주소·좌표를 자동 입력했습니다.'; }
					} )
					.catch( function ( err ) {
						if ( statusEl ) { statusEl.textContent = err.message; }
					} )
					.then( function () {
						addressSearchButton.disabled = false;
					} );
			} );
		}

		document.getElementById( 'hlf-items-table-wrap' ).addEventListener( 'click', function ( event ) {
			var editBtn = event.target.closest( '[data-hlf-edit-item]' );
			if ( editBtn ) {
				state.editingItemId = Number( editBtn.getAttribute( 'data-hlf-edit-item' ) );
				renderEdit(); // 폼을 편집 대상 값으로 다시 그리고(및 재바인딩) 스크롤은 아래서 처리.
				var reRenderedForm = document.getElementById( 'hlf-item-form' );
				if ( reRenderedForm ) { reRenderedForm.scrollIntoView( { behavior: 'smooth', block: 'nearest' } ); }
				return;
			}
			var deleteBtn = event.target.closest( '[data-hlf-delete-item]' );
			if ( deleteBtn ) {
				var itemId = deleteBtn.getAttribute( 'data-hlf-delete-item' );
				if ( ! window.confirm( '이 매물을 삭제할까요?' ) ) { return; }
				deleteBtn.disabled = true;
				HLFAdmin.apiFetch( 'flyers/' + flyerId + '/items/' + itemId, { method: 'DELETE' } )
					.then( function () {
						state.items = state.items.filter( function ( i ) { return String( i.id ) !== String( itemId ); } );
						renderEdit();
					} )
					.catch( function ( err ) {
						window.alert( '삭제에 실패했습니다: ' + err.message );
						deleteBtn.disabled = false;
					} );
			}
		} );

		if ( state.editingItemId !== null ) {
			var editingItem = state.items.find( function ( i ) { return i.id === state.editingItemId; } );
			if ( editingItem ) {
				bindImageSection( editingItem );
			}
		}
	}

	if ( ! flyerId ) {
		renderCreateForm();
	} else {
		loadFlyer();
	}
} )();
