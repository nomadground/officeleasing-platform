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
	// 서버(HLF_Item_Repository::MAX_IMAGES)와 같은 상한 — 대표 1장 + 슬라이드 3장(대표 포함 4장).
	var HLF_MAX_IMAGES = 4;

	// key: 스키마 필드명(HLF_Meta_Schema::writable_fields()와 동일해야 함) / type: 입력 위젯.
	var ITEM_FIELDS = [
		{ key: 'road_address', label: '도로명주소', type: 'text' },
		{ key: 'lot_address', label: '지번주소', type: 'text' },
		{ key: 'building_name', label: '건물명(선택 — 입력하지 않으면 표시되지 않음)', type: 'text' },
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
		{ key: 'elevator_available', label: '엘리베이터 있음', type: 'checkbox' },
		{ key: 'total_parking', label: '총주차대수', type: 'text', placeholder: '예: 자주식 10대' },
		{ key: 'direction', label: '방향', type: 'text' },
		{ key: 'available_date_text', label: '입주가능일', type: 'text', placeholder: '예: 즉시입주 협의가능' },
		{ key: 'approval_date', label: '사용승인일', type: 'text', placeholder: '예: 2018.06.21' },
		{ key: 'building_use', label: '건축물용도', type: 'text' },
		{ key: 'illegal_building', label: '위반건축물 여부', type: 'checkbox' },
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
		var archived = flyer.status === 'archived';
		return (
			'<section class="hlf-card">' +
				'<h2>' + HLFAdmin.escapeHtml( flyer.flyer_number ) + ( archived ? ' <span class="' + HLFAdmin.statusBadgeClass( 'archived' ) + '">' + HLFAdmin.statusLabel( 'archived' ) + '</span>' : '' ) + '</h2>' +
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
					'<label>상태</label> ' +
					// 새 안내문은 생성 즉시 발행 상태로 저장되므로 draft/published 이분법은 없앴다 — 이
					// 화면에서는 "보관 처리" 토글 하나만 남긴다(완료된 안내문을 실수로 수정 못 하게 막는
					// 별도 워크플로우이고, 발행 여부와는 무관하게 그대로 유지한다).
					( archived
						? '<span class="' + HLFAdmin.statusBadgeClass( 'archived' ) + '">보관됨</span> <button type="button" class="button" id="hlf-status-apply" data-hlf-next-status="published">보관 해제</button>'
						: '<span class="' + HLFAdmin.statusBadgeClass( 'published' ) + '">발행됨</span> <button type="button" class="button" id="hlf-status-apply" data-hlf-next-status="archived">보관하기</button>' ) +
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

		var statusButton = document.getElementById( 'hlf-status-apply' );
		var statusError = form.parentNode.querySelector( '[data-hlf-status-error]' );
		statusButton.addEventListener( 'click', function () {
			var next = statusButton.getAttribute( 'data-hlf-next-status' );
			var confirmMsg = 'archived' === next
				? '이 안내문을 보관 처리할까요? 보관하면 읽기 전용으로 바뀌어 더 이상 수정할 수 없습니다.'
				: '보관을 해제하고 다시 발행 상태로 되돌릴까요?';
			if ( ! window.confirm( confirmMsg ) ) { return; }
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

	// 주소(도로명/지번/위도/경도)는 실제 업무 흐름상 "OCR로 채운 뒤 가장 먼저 확인·확정하는 값"이라
	// renderItemForm()의 일반 필드 그리드에서 빼내 OCR 다음 순서(step 2)로 별도 배치한다
	// (renderAddressBlock 참고). 나머지 조건 필드는 여기서 제외해 중복 렌더링을 막는다.
	// 순서는 요청서 5번 관리자 UX 배치를 그대로 따른다: 지번주소(+주소 검색) → 도로명주소 → 위도/경도.
	// ITEM_FIELDS 배열 자체는 road_address가 lot_address보다 앞이라(다른 화면 순서용) 여기서는 그
	// 순서를 그대로 쓰지 않고 이 배열 순서대로 명시적으로 재배치한다.
	var ADDRESS_FIELD_KEYS = [ 'lot_address', 'road_address', 'building_name', 'latitude', 'longitude' ];

	// 체크박스 2개(주차 가능/엘리베이터 있음)는 한 줄에 나란히 둔다 — 2열 grid의 홀/짝 순서에 맞춰
	// 배열 순서만 조정하는 방식은 앞쪽 필드 개수가 바뀌면 다시 어긋난다(실제로 확인됨: 그렇게
	// 해봤지만 앞의 관리비 필드가 홀수 칸을 차지해 결국 한 줄에 못 붙었다). 그래서 이 두 필드는
	// 아예 하나의 넓은(grid-column: 1/-1) 행 안에 함께 렌더링해 앞뒤 필드 개수와 무관하게 항상
	// 같은 줄에 있게 한다.
	var CHECKBOX_ROW_KEYS = [ 'parking_available', 'elevator_available' ];

	function renderAddressBlock( item ) {
		var fieldsByKey = {};
		ITEM_FIELDS.forEach( function ( def ) { fieldsByKey[ def.key ] = def; } );
		var fields = ADDRESS_FIELD_KEYS.map( function ( key ) { return fieldsByKey[ key ]; } ).filter( Boolean );
		// 지번주소/도로명주소 박스는 라벨+입력칸만 담아 서로 높이가 맞도록 한다 — 주소 검색
		// 버튼/상태문구/후보목록을 지번주소 칸 안에 같이 쌓으면 그 칸만 세로로 길어져 옆 도로명주소
		// 칸과 높이가 어긋난다(실제로 확인됨). 그래서 이 부가 요소들은 grid 밖, 별도 줄로 뺀다.
		var fieldsHtml = fields.map( function ( def ) {
			var value = item ? item[ def.key ] : '';
			var fieldId = 'hlf-item-field-' + def.key;
			var stepAttr = def.step ? ' step="' + def.step + '"' : '';
			// 도로명주소는 지번주소 검색으로 확정된 값만 채운다 — 손으로 직접 입력하면 지번주소와
			// 어긋난 값이 저장될 수 있으므로 읽기 전용(회색)으로 두고, "주소 검색" 후보 클릭으로만
			// 채워지게 한다. readonly라 name은 그대로 유지되어 폼 제출/기존 값 표시는 문제 없다.
			var readonlyAttr = ( def.key === 'road_address' ) ? ' readonly class="hlf-field-readonly"' : '';
			// 위도/경도는 화면에 보일 필요가 없다 — 지도/NOC 등 내부 계산에만 쓰이는 값이라 입력칸
			// 자체는 그대로 두되(주소 검색 확정 시 채워지고 폼 제출에도 그대로 포함) hidden으로 화면에서만
			// 숨긴다. hidden은 렌더링만 감추고 form.elements/제출 값에는 영향을 주지 않는다.
			var hiddenAttr = ( def.key === 'latitude' || def.key === 'longitude' ) ? ' hidden' : '';
			return (
				'<div class="hlf-field"' + hiddenAttr + '><label for="' + fieldId + '">' + HLFAdmin.escapeHtml( def.label ) + '</label>' +
				'<input id="' + fieldId + '" type="' + def.type + '" name="' + def.key + '" value="' + HLFAdmin.escapeAttr( value === null || value === undefined ? '' : value ) + '"' + stepAttr + readonlyAttr + '>' +
				'</div>'
			);
		} ).join( '' );
		return (
			'<div class="hlf-address-block">' +
				'<h4>주소 확인</h4>' +
				'<div class="hlf-field-grid">' + fieldsHtml + '</div>' +
				// 카카오 Local API(서버 프록시, REST 키는 클라이언트에 노출하지 않음)로 도로명주소/좌표
				// 후보를 조회한다. 결과는 1건이든 여러 건이든 바로 채우지 않고 목록으로 보여준 뒤
				// 사용자가 클릭한 것만 폼에 반영한다("다음" 우편번호 검색과 같은 방식 — 자동확정 시
				// 오탐으로 엉뚱한 좌표가 저장되는 걸 막는다). 키가 설정 안 됐으면 버튼 클릭 시 서버가
				// 501을 돌려주고 아래 상태 문구로만 안내한다(폼 자체는 정상 동작).
				'<div class="hlf-address-search-row">' +
					'<button type="button" class="button button-small" id="hlf-address-search">주소 검색</button>' +
					'<p class="hlf-admin-note" id="hlf-address-search-status"></p>' +
				'</div>' +
				'<ul class="hlf-address-results" id="hlf-address-results" hidden></ul>' +
				// 좌표가 확정된 뒤에만 채워지는 작은 지도 미리보기 — 키 미설정/SDK 로드 실패 시에도
				// 이 영역만 숨겨질 뿐 나머지 입력은 그대로 동작한다(요청서 3-7).
				'<div class="hlf-address-map" id="hlf-address-map" hidden></div>' +
			'</div>'
		);
	}

	function renderItemForm() {
		var editing = state.editingItemId !== null;
		var item = editing ? state.items.find( function ( i ) { return i.id === state.editingItemId; } ) : null;

		var fieldsByKeyForRender = {};
		ITEM_FIELDS.forEach( function ( def ) { fieldsByKeyForRender[ def.key ] = def; } );

		var fieldsHtml = ITEM_FIELDS.map( function ( def ) {
			if ( ADDRESS_FIELD_KEYS.indexOf( def.key ) !== -1 ) { return ''; } // renderAddressBlock()가 별도 렌더링.
			if ( CHECKBOX_ROW_KEYS.indexOf( def.key ) !== -1 ) {
				// 그룹의 첫 키를 만났을 때만 그룹 전체를 한 번에 렌더링하고, 나머지 키는 건너뛴다
				// (중복 렌더링 방지 — ADDRESS_FIELD_KEYS와 같은 패턴).
				if ( def.key !== CHECKBOX_ROW_KEYS[ 0 ] ) { return ''; }
				var checkboxesHtml = CHECKBOX_ROW_KEYS.map( function ( key ) {
					var cbDef = fieldsByKeyForRender[ key ];
					var cbValue = item ? item[ key ] : '';
					return (
						'<label class="hlf-field-checkbox">' +
							'<input type="checkbox" name="' + key + '"' + ( cbValue ? ' checked' : '' ) + '> ' + HLFAdmin.escapeHtml( cbDef.label ) +
						'</label>'
					);
				} ).join( '' );
				return '<div class="hlf-field hlf-field-wide hlf-field-checkbox-row">' + checkboxesHtml + '</div>';
			}
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
			return (
				'<div class="hlf-field' + wideClass + '"><label for="' + fieldId + '">' + HLFAdmin.escapeHtml( def.label ) + '</label>' +
				'<input id="' + fieldId + '" type="' + def.type + '" name="' + def.key + '" value="' + HLFAdmin.escapeAttr( value === null || value === undefined ? '' : value ) + '"' + stepAttr + placeholderAttr + '>' +
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
				HLFOcr.renderSection() +
				renderAddressBlock( item ) +
				'<div class="hlf-field-grid">' + fieldsHtml + '</div>' +
				metricsHtml +
				'<button type="submit" class="button button-primary">저장</button> ' +
				'<button type="button" class="button" id="hlf-item-cancel">취소</button>' +
				'<p class="hlf-admin-error" data-hlf-item-error hidden></p>' +
			'</form>' +
			// 이미지 섹션은 별도 <form> submit(엔터키 등)에 휘말리지 않도록 hlf-item-form 밖의
			// 형제 요소로 둔다. 이미지는 attachment의 post_parent가 item_id라 Item이 실제로 저장돼
			// 있어야만(=수정 모드) 다룰 수 있다 — 추가(생성) 모드에서는 안내문만 보여준다(이전에는
			// 여기서 아무것도 렌더링하지 않아 "사진 섹션 자체가 없다"고 오해하기 쉬웠다).
			( editing ? renderImageSection( item ) : '<p class="hlf-admin-note hlf-image-section-pending">매물 사진은 저장한 뒤 추가할 수 있습니다 — 먼저 위 내용을 저장해 주세요.</p>' )
		);
	}

	/* ---------------- 카카오 주소 검색(후보 목록 클릭 확정) + 지도 미리보기 ----------------
	 * "다음" 우편번호 검색과 같은 흐름: 검색 → 후보 목록(지번+도로명 함께 표시) → 클릭으로 확정.
	 * 결과가 1건이어도 자동으로 채우지 않는다(요청서 3-2/3-5) — 오탐으로 엉뚱한 좌표가 저장되는
	 * 사고를 목록 확인 한 단계로 줄인다. 정렬 우선순위 자체는 서버(HLF_REST_Controller::
	 * search_kakao_address)가 이미 계산해 순서대로 내려주므로, 여기서는 순서를 그대로 렌더링만 한다.
	 */
	var ADDRESS_SEARCH_DEBOUNCE_MS = 700;
	var addressSearchDebounceTimer = null;
	var addressSearchRequestSeq = 0; // 오래된 응답이 최신 응답을 덮어쓰지 않도록.

	function bindAddressSearch( form ) {
		var button = document.getElementById( 'hlf-address-search' );
		var lotInput = form.elements.lot_address;
		if ( ! button || ! lotInput ) { return; }

		function runSearch() {
			var statusEl = document.getElementById( 'hlf-address-search-status' );
			var resultsEl = document.getElementById( 'hlf-address-results' );
			var query = ( lotInput.value || '' ).trim();
			if ( ! query ) {
				if ( statusEl ) { statusEl.textContent = '지번주소를 입력해 주세요.'; }
				if ( resultsEl ) { resultsEl.hidden = true; resultsEl.innerHTML = ''; }
				return;
			}
			var requestId = ++addressSearchRequestSeq;
			button.disabled = true;
			if ( statusEl ) { statusEl.textContent = '주소를 조회하고 있습니다…'; }

			HLFAdmin.apiFetch( 'kakao/address-search?q=' + encodeURIComponent( query ) )
				.then( function ( body ) {
					if ( requestId !== addressSearchRequestSeq ) { return; } // 더 최신 요청이 있음 — 이 응답은 버림.
					var candidates = body.results || [];
					if ( statusEl ) {
						statusEl.textContent = candidates.length > 1
							? '검색 결과 ' + candidates.length + '건 — 목록에서 정확한 주소를 선택해 주세요.'
							: '검색 결과를 확인하고 선택해 주세요.';
					}
					renderAddressResults( resultsEl, candidates );
				} )
				.catch( function ( err ) {
					if ( requestId !== addressSearchRequestSeq ) { return; }
					if ( statusEl ) { statusEl.textContent = err.message; }
					if ( resultsEl ) { resultsEl.hidden = true; resultsEl.innerHTML = ''; }
				} )
				.then( function () {
					if ( requestId === addressSearchRequestSeq ) { button.disabled = false; }
				} );
		}

		function renderAddressResults( resultsEl, candidates ) {
			if ( ! resultsEl ) { return; }
			if ( ! candidates.length ) { resultsEl.hidden = true; resultsEl.innerHTML = ''; return; }
			resultsEl.innerHTML = candidates.map( function ( c, index ) {
				return (
					'<li><button type="button" class="hlf-address-candidate" data-hlf-address-index="' + index + '">' +
						'<strong>' + HLFAdmin.escapeHtml( c.lot_address || '(지번주소 없음)' ) + '</strong>' +
						( c.road_address ? '<span>' + HLFAdmin.escapeHtml( c.road_address ) + '</span>' : '' ) +
					'</button></li>'
				);
			} ).join( '' );
			resultsEl.hidden = false;
			resultsEl.dataset.candidates = JSON.stringify( candidates );
		}

		button.addEventListener( 'click', function () {
			if ( addressSearchDebounceTimer ) { clearTimeout( addressSearchDebounceTimer ); }
			runSearch();
		} );

		// 지번주소 입력 후 잠시 멈추면 자동으로도 조회한다(요청서 3-1) — 매 키입력마다 호출하지 않도록
		// debounce. 자동조회 역시 후보를 목록으로만 보여줄 뿐 자동으로 채우지 않는다(3-2와 동일 원칙).
		lotInput.addEventListener( 'input', function () {
			if ( addressSearchDebounceTimer ) { clearTimeout( addressSearchDebounceTimer ); }
			addressSearchDebounceTimer = setTimeout( runSearch, ADDRESS_SEARCH_DEBOUNCE_MS );
		} );

		var resultsEl = document.getElementById( 'hlf-address-results' );
		if ( resultsEl ) {
			resultsEl.addEventListener( 'click', function ( event ) {
				var candidateButton = event.target.closest( '[data-hlf-address-index]' );
				if ( ! candidateButton ) { return; }
				var candidates = JSON.parse( resultsEl.dataset.candidates || '[]' );
				var chosen = candidates[ Number( candidateButton.getAttribute( 'data-hlf-address-index' ) ) ];
				if ( ! chosen ) { return; }

				if ( chosen.lot_address ) { lotInput.value = chosen.lot_address; }
				if ( chosen.road_address ) { form.elements.road_address.value = chosen.road_address; }
				if ( chosen.latitude ) { form.elements.latitude.value = chosen.latitude; }
				if ( chosen.longitude ) { form.elements.longitude.value = chosen.longitude; }

				resultsEl.hidden = true;
				resultsEl.innerHTML = '';
				var statusEl = document.getElementById( 'hlf-address-search-status' );
				if ( statusEl ) { statusEl.textContent = '선택한 주소로 도로명주소·좌표를 입력했습니다.'; }

				showAddressPreview( chosen.latitude, chosen.longitude );
			} );
		}

		// 수정 화면에 이미 좌표가 있으면(기존 매물) 진입 시점에도 미리보기를 바로 보여준다.
		if ( form.elements.latitude.value && form.elements.longitude.value ) {
			showAddressPreview( form.elements.latitude.value, form.elements.longitude.value );
		}
	}

	/* ---------------- 카카오 지도 미리보기(관리자 전용, 좌표 확정 매물 1개만) ---------------- */

	var addressMapLoader = null;
	function loadAddressMapSdk() {
		if ( window.kakao && window.kakao.maps ) { return Promise.resolve( window.kakao.maps ); }
		if ( addressMapLoader ) { return addressMapLoader; }
		var key = HLF_ADMIN.kakaoJsKey;
		if ( ! key ) { return Promise.reject( new Error( '지도 SDK를 불러오지 못했습니다.' ) ); }
		addressMapLoader = new Promise( function ( resolve, reject ) {
			var script = document.createElement( 'script' );
			script.src = 'https://dapi.kakao.com/v2/maps/sdk.js?appkey=' + encodeURIComponent( key ) + '&autoload=false';
			script.onload = function () {
				if ( window.kakao && window.kakao.maps && window.kakao.maps.load ) {
					window.kakao.maps.load( function () { resolve( window.kakao.maps ); } );
				} else {
					reject( new Error( '지도 SDK를 불러오지 못했습니다.' ) );
				}
			};
			script.onerror = function () { reject( new Error( '지도 SDK를 불러오지 못했습니다.' ) ); };
			document.head.appendChild( script );
		} );
		return addressMapLoader;
	}

	function showAddressPreview( lat, lng ) {
		var container = document.getElementById( 'hlf-address-map' );
		if ( ! container || ! lat || ! lng ) { return; }
		var latNum = Number( lat );
		var lngNum = Number( lng );
		if ( ! isFinite( latNum ) || ! isFinite( lngNum ) ) { return; }

		loadAddressMapSdk()
			.then( function ( maps ) {
				container.hidden = false;
				container.innerHTML = '';
				var map = new maps.Map( container, { center: new maps.LatLng( latNum, lngNum ), level: 4 } );
				new maps.Marker( { map: map, position: new maps.LatLng( latNum, lngNum ) } );
			} )
			.catch( function () {
				// 키 미설정/SDK 로드 실패 시에도 나머지 입력은 그대로 동작해야 한다(요청서 3-7) — 지도
				// 영역만 숨긴다. 콘솔에는 개발 확인용으로만 남긴다.
				container.hidden = true;
			} );
	}

	/* ---------------- 매물 사진(WordPress Media Library) ---------------- */

	function renderImageSection( item ) {
		return (
			'<section class="hlf-image-section">' +
				'<h3>매물 사진</h3>' +
				// 주소/금액 등 텍스트 값은 저장 시점의 완전한 스냅샷이지만, 사진은 미디어 라이브러리의
				// 원본 첨부파일을 그대로 참조한다(용량 절약, 파일 복제 없음) — 그래서 이 문구가 필요.
				'<p class="hlf-admin-note hlf-image-snapshot-warning">주의: 사진은 미디어 라이브러리 원본을 그대로 참조합니다. 다른 곳에서 이 사진을 삭제하거나 교체하면 이미 발행된 안내문의 사진도 함께 바뀌거나 사라질 수 있습니다.</p>' +
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
			// HLF_Image_Pipeline이 이 값으로 자기 업로드만 골라 최적화한다(GPT 코드 감사 P0#2).
			uploader: { params: { hlf_upload: '1' } },
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
			// 요청서: 대표 1장 + 슬라이드 3장(대표 포함 4장)까지만 — 서버(HLF_Item_Repository::MAX_IMAGES)와
			// 같은 상한을 여기서도 미리 걸어 불필요한 실패 요청을 막는다.
			if ( interior.length > HLF_MAX_IMAGES - 1 ) {
				interior = interior.slice( 0, HLF_MAX_IMAGES - 1 );
				window.alert( '사진은 대표 이미지를 포함해 최대 ' + HLF_MAX_IMAGES + '장까지 등록할 수 있습니다. 앞에서부터 ' + HLF_MAX_IMAGES + '장만 반영합니다.' );
			}
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

		HLFOcr.bindSection( itemForm );

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
			// 요청서: 주소검색으로 후보를 확정해야만(latitude/longitude가 채워짐 — bindAddressSearch의
			// 후보 클릭에서만 값이 들어간다) 저장할 수 있다.
			if ( ! itemForm.elements.latitude.value || ! itemForm.elements.longitude.value ) {
				itemError.textContent = '지번주소로 주소 검색을 실행해 후보를 선택해 주세요(좌표 확정 필요).';
				itemError.hidden = false;
				return;
			}
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

		bindAddressSearch( itemForm );

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
