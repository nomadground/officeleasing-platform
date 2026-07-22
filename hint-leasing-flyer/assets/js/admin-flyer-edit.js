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

	// 주소(도로명/지번/위도/경도)는 실제 업무 흐름상 "OCR로 채운 뒤 가장 먼저 확인·확정하는 값"이라
	// renderItemForm()의 일반 필드 그리드에서 빼내 OCR 다음 순서(step 2)로 별도 배치한다
	// (renderAddressBlock 참고). 나머지 조건 필드는 여기서 제외해 중복 렌더링을 막는다.
	// 순서는 요청서 5번 관리자 UX 배치를 그대로 따른다: 지번주소(+주소 검색) → 도로명주소 → 위도/경도.
	// ITEM_FIELDS 배열 자체는 road_address가 lot_address보다 앞이라(다른 화면 순서용) 여기서는 그
	// 순서를 그대로 쓰지 않고 이 배열 순서대로 명시적으로 재배치한다.
	var ADDRESS_FIELD_KEYS = [ 'lot_address', 'road_address', 'latitude', 'longitude' ];

	function renderAddressBlock( item ) {
		var fieldsByKey = {};
		ITEM_FIELDS.forEach( function ( def ) { fieldsByKey[ def.key ] = def; } );
		var fields = ADDRESS_FIELD_KEYS.map( function ( key ) { return fieldsByKey[ key ]; } ).filter( Boolean );
		var fieldsHtml = fields.map( function ( def ) {
			var value = item ? item[ def.key ] : '';
			var fieldId = 'hlf-item-field-' + def.key;
			var stepAttr = def.step ? ' step="' + def.step + '"' : '';
			// 지번주소 필드에만 "주소 검색" 버튼을 붙인다 — 카카오 Local API(서버 프록시, REST 키는
			// 클라이언트에 노출하지 않음)로 도로명주소/좌표 후보를 조회한다. 결과는 1건이든 여러 건이든
			// 바로 채우지 않고 목록으로 보여준 뒤 사용자가 클릭한 것만 폼에 반영한다("다음" 우편번호
			// 검색과 같은 방식 — 자동확정 시 오탐으로 엉뚱한 좌표가 저장되는 걸 막는다). 키가 설정 안
			// 됐으면 버튼 클릭 시 서버가 501을 돌려주고 아래 상태 문구로만 안내한다(폼 자체는 정상 동작).
			var addressSearchHtml = ( def.key === 'lot_address' ) ?
				' <button type="button" class="button button-small" id="hlf-address-search">주소 검색</button>' +
				'<p class="hlf-admin-note" id="hlf-address-search-status"></p>' +
				'<ul class="hlf-address-results" id="hlf-address-results" hidden></ul>' : '';
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
				addressSearchHtml +
				'</div>'
			);
		} ).join( '' );
		return (
			'<div class="hlf-address-block">' +
				'<h4>주소 확인</h4>' +
				'<div class="hlf-field-grid">' + fieldsHtml + '</div>' +
				// 좌표가 확정된 뒤에만 채워지는 작은 지도 미리보기 — 키 미설정/SDK 로드 실패 시에도
				// 이 영역만 숨겨질 뿐 나머지 입력은 그대로 동작한다(요청서 3-7).
				'<div class="hlf-address-map" id="hlf-address-map" hidden></div>' +
			'</div>'
		);
	}

	function renderItemForm() {
		var editing = state.editingItemId !== null;
		var item = editing ? state.items.find( function ( i ) { return i.id === state.editingItemId; } ) : null;

		var fieldsHtml = ITEM_FIELDS.map( function ( def ) {
			if ( ADDRESS_FIELD_KEYS.indexOf( def.key ) !== -1 ) { return ''; } // renderAddressBlock()가 별도 렌더링.
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
				renderOcrSection() +
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

	/* ---------------- 네이버부동산 캡처 OCR(선택 입력 보조) ----------------
	 * Tesseract.js(CDN, kor+eng)로 캡처 이미지에서 텍스트를 뽑아 항목 필드에 자동으로 채워 넣는다.
	 * 실제 OCR 엔진 연동이며 가짜 결과를 만들지 않는다 — 다만 추출 결과는 항상 "제안값"이고
	 * 사용자가 원문/필드를 직접 확인·수정한 뒤에만 저장 버튼으로 실제 저장된다(자동 저장 없음).
	 * 서버 계산(NOC 등)과는 무관하다 — 여기서 하는 일은 이미지 속 텍스트를 필드값 후보로
	 * 정규화하는 것뿐이고, 그 값들로 파생 지표를 계산하는 로직은 두지 않는다(그건 서버 몫).
	 */
	var OCR_SCRIPT_URL = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';
	var OCR_MAX_MONEY = 1000000;
	var OCR_MAX_AREA_SQM = 1000000;

	function renderOcrSection() {
		return (
			'<div class="hlf-ocr-section">' +
				'<h4>네이버부동산 캡처로 자동 입력 (선택)</h4>' +
				'<p class="hlf-admin-note">필수 단계는 아닙니다 — 캡처만 붙이면 아래 입력 시간을 줄여줄 뿐, 건너뛰고 직접 입력해도 됩니다.</p>' +
				'<div class="hlf-field"><label for="hlf-ocr-capture">캡처 이미지</label>' +
					'<input type="file" id="hlf-ocr-capture" accept="image/*"></div>' +
				'<img id="hlf-ocr-preview" class="hlf-ocr-preview" alt="캡처 미리보기" hidden>' +
				'<button type="button" class="button" id="hlf-ocr-run" disabled>텍스트 추출</button>' +
				'<p class="hlf-admin-note" id="hlf-ocr-status"></p>' +
				'<div class="hlf-field hlf-field-wide"><label for="hlf-ocr-text">추출된 원문(직접 수정 가능)</label>' +
					'<textarea id="hlf-ocr-text" class="hlf-ocr-textarea" rows="6"></textarea></div>' +
				// 기존 값 보호(요청서 2-7) — 기본값은 항상 "빈 항목만 자동입력". 이미 값이 있는 필드까지
				// 덮어쓰거나(전체 덮어쓰기) 항목마다 확인하고 싶을 때만 사용자가 직접 바꾼다.
				'<fieldset class="hlf-ocr-mode"><legend>자동입력 방식</legend>' +
					'<label><input type="radio" name="hlf-ocr-mode" value="empty-only" checked> 빈 항목만 자동입력(기본)</label>' +
					'<label><input type="radio" name="hlf-ocr-mode" value="overwrite-all"> 전체 덮어쓰기</label>' +
					'<label><input type="radio" name="hlf-ocr-mode" value="confirm-each"> 항목별 확인</label>' +
				'</fieldset>' +
				'<button type="button" class="button button-primary" id="hlf-ocr-apply">원문에서 항목 채우기</button>' +
				'<div id="hlf-ocr-confirm-list" class="hlf-ocr-confirm-list" hidden></div>' +
			'</div>'
		);
	}

	// 2-8 개선: 보증금/임대료/관리비/면적처럼 "숫자만 나와야 하는" 좁은 범위의 값에서 Tesseract가
	// 흔히 혼동하는 문자(O/o↔0, l/I↔1, S↔5, B↔8, Z↔2, G↔6)를 숫자로 교정한다. 이 값들은 이미
	// 라벨로 좁혀진 짧은 조각이라(자유 문장이 아님) 전역 치환해도 실제 단어를 깨뜨릴 위험이 낮다 —
	// 자유 텍스트 필드(매물특징 등)에는 이 함수를 쓰지 않는다.
	function ocrFixDigitConfusion( text ) {
		return String( text || '' ).replace( /[OolISBZG]/g, function ( ch ) {
			switch ( ch ) {
				case 'O': case 'o': return '0';
				case 'l': case 'I': return '1';
				case 'S': return '5';
				case 'B': return '8';
				case 'Z': return '2';
				case 'G': return '6';
				default: return ch;
			}
		} );
	}

	function ocrNormalizeMoney( value ) {
		if ( value === null || value === undefined ) { return ''; }
		var raw = ocrFixDigitConfusion( String( value ).replace( /,/g, '' ).replace( /\s+/g, '' ).trim() );
		if ( ! raw || raw === '-' ) { return ''; }
		var eokMatch = raw.match( /(\d+(?:\.\d+)?)억/ );
		var remainder = raw.replace( /\d+(?:\.\d+)?억/, '' );
		var remainderMatch = remainder.match( /\d+(?:\.\d+)?/ );
		if ( eokMatch || remainderMatch ) {
			var total = ( eokMatch ? Number( eokMatch[ 1 ] ) * 10000 : 0 ) + ( remainderMatch ? Number( remainderMatch[ 0 ] ) : 0 );
			return isFinite( total ) ? Math.min( OCR_MAX_MONEY, Math.max( 0, total ) ) : '';
		}
		var numberMatch = raw.match( /\d+(?:\.\d+)?/ );
		var number = numberMatch ? Number( numberMatch[ 0 ] ) : NaN;
		return isFinite( number ) ? Math.min( OCR_MAX_MONEY, Math.max( 0, number ) ) : '';
	}

	function ocrNormalizeAreaSqm( value ) {
		if ( value === null || value === undefined ) { return ''; }
		var raw = ocrFixDigitConfusion( String( value ).replace( /,/g, '' ).trim() );
		if ( ! raw || raw === '-' ) { return ''; }
		var numberMatch = raw.match( /\d+(?:\.\d+)?/ );
		if ( ! numberMatch ) { return ''; }
		var number = Number( numberMatch[ 0 ] );
		if ( ! isFinite( number ) ) { return ''; }
		return Math.min( OCR_MAX_AREA_SQM, raw.indexOf( '평' ) !== -1 ? number / 0.3025 : number );
	}

	function ocrNormalizeText( text ) {
		return String( text || '' )
			.replace( /\r/g, '' )
			.replace( /m(?:²|2|\^2)/gi, '㎡' )
			// Tesseract가 ㎡를 자주 "ㅠ"로 오인식한다(실제 캡처로 확인) — 숫자 바로 뒤에 오는 "ㅠ"만
			// 좁혀서 교정한다(자유 텍스트의 "ㅠㅠ" 같은 표현을 건드리지 않기 위해 숫자+ㅠ 패턴에만 적용).
			.replace( /(\d)ㅠ/g, '$1㎡' )
			.replace( /월\s*세/g, '월세' )
			.replace( /관\s*리\s*비/g, '관리비' )
			.replace( /(\d)\s+(?=\d)/g, '$1' )
			.replace( /\s*,\s*/g, ',' );
	}

	// 라벨(예: "전용면적") 뒤에 오는 값을 줄 안 또는 다음 줄에서 찾는다. 네이버부동산 캡처는
	// "라벨 값"이 같은 줄이거나(표 형태) 라벨 다음 줄에 값만 있는 경우(카드 형태) 둘 다 흔하다.
	function ocrLabeledValue( text, labels ) {
		var lines = String( text || '' ).split( /\n/ ).map( function ( l ) { return l.trim(); } ).filter( Boolean );
		var sortedLabels = labels.slice().sort( function ( a, b ) { return b.length - a.length; } );
		for ( var i = 0; i < lines.length; i++ ) {
			var line = lines[ i ];
			var label = sortedLabels.find( function ( candidate ) { return line.toLowerCase().indexOf( candidate.toLowerCase() ) !== -1; } );
			if ( ! label ) { continue; }
			var labelIndex = line.toLowerCase().indexOf( label.toLowerCase() );
			var sameLine = line.slice( labelIndex + label.length ).replace( /^[\s:：\-|]+/, '' ).trim();
			if ( sameLine ) { return sameLine; }
			if ( lines[ i + 1 ] ) { return lines[ i + 1 ]; }
		}
		return '';
	}

	// 보증금/월세를 "보증금 3억 / 월세 350" 또는 "3억/350" 형태에서 뽑는다(라벨 없는 슬래시 표기 fallback 포함).
	//
	// 네이버부동산 캡처는 두 가지 형태가 섞여 나온다:
	//  (a) "월세 8,000/710"처럼 거래유형 라벨 하나가 슬래시쌍 전체의 헤더 역할(보증금/월세 각각
	//      앞/뒤) — 최상단 요약줄에 흔하다.
	//  (b) "보증금 3,000만원 / 월세 350만원"처럼 각 값에 자기 라벨이 따로 붙는 형태 — 상세 표에 흔하다.
	// "값 뒤에 슬래시가 오는지"로 (a)/(b)를 구분하려 했으나(음의 전방탐색), 정규식 역추적이 탐색
	// 조건을 만족할 때까지 캡처 길이를 줄여버려 오히려 값이 잘리는 문제가 있었다(실제로 확인됨:
	// "8,000/710"에서 "800"만 캡처). 대신 "보증금" 라벨의 유무로 두 형태를 구분한다 — 보증금 라벨이
	// 있으면 (b)로 보고 각자 라벨링된 값을 그대로 쓰고, 없으면 (a)로 보고 헤더+슬래시쌍을 쓴다.
	function ocrParseLeaseAmounts( text ) {
		var depositLabel = text.match( /보증금\s*([\d억,.\s]+(?:만원)?)/ );
		var rentLabelExplicit = text.match( /(?:월세|임대료)\s*([\d억,.\s]+(?:만원)?)/ );
		var feeLabel = text.match( /관리비\s*([\d억,.\s]+(?:만원)?)/ );

		var deposit = '';
		var rent = '';
		if ( depositLabel ) {
			// (b) 각자 라벨링된 형태 — "보증금"이 있으니 뒤의 "월세/임대료" 라벨도 곧이곧대로 믿는다.
			deposit = depositLabel[ 1 ];
			rent = rentLabelExplicit ? rentLabelExplicit[ 1 ] : '';
		} else {
			// (a) 거래유형 헤더 + 슬래시쌍, 또는 라벨이 아예 없는 순수 슬래시 표기.
			var dealTypePair = text.match( /(?:월세|전세)\s*([\d억,.\s]+)\s*\/\s*([\d억,.\s]+)/ );
			if ( dealTypePair ) {
				deposit = dealTypePair[ 1 ];
				rent = dealTypePair[ 2 ];
			} else {
				var slash = text.match( /([\d억,.\s]+(?:만원)?)\s*\/\s*([\d억,.\s]+(?:만원)?)/ );
				deposit = slash ? slash[ 1 ] : '';
				rent = slash ? slash[ 2 ] : ( rentLabelExplicit ? rentLabelExplicit[ 1 ] : '' );
			}
		}

		return {
			deposit_manwon: ocrNormalizeMoney( deposit ),
			monthly_rent_manwon: ocrNormalizeMoney( rent ),
			maintenance_fee_manwon: ocrNormalizeMoney( feeLabel && feeLabel[ 1 ] ),
		};
	}

	function ocrParseFloor( text ) {
		// 끝의 "층"을 필수로 요구해야 한다(이전에는 선택이라 "8,000/710" 같은 보증금/월세 숫자쌍이
		// 먼저 매치되어 층수 대신 그 값을 잘못 채우는 버그가 있었다 — 실제 캡처로 확인됨). 층수
		// 표기는 항상 "4/6층"처럼 마지막 숫자 뒤에만 "층"이 붙으므로 이걸로 금액 쌍과 구분한다.
		var pair = text.match( /(?:해당층\s*\/\s*총층\s*[:：]?\s*)?(B?\d+(?:~\d+)?)\s*층?\s*\/\s*(\d+)\s*층/i );
		return { floor_current: ( pair && pair[ 1 ] ) || '', floor_total: ( pair && pair[ 2 ] ) || '' };
	}

	function ocrParseAreas( text ) {
		var pair = text.match( /(\d+(?:\.\d+)?)\s*㎡\s*\/\s*(\d+(?:\.\d+)?)\s*㎡/ );
		var contract = ocrLabeledValue( text, [ '계약면적', '임대면적' ] );
		var exclusive = ocrLabeledValue( text, [ '전용면적' ] );
		return {
			lease_area_sqm: ocrNormalizeAreaSqm( ( pair && pair[ 1 ] ) || contract ),
			exclusive_area_sqm: ocrNormalizeAreaSqm( ( pair && pair[ 2 ] ) || exclusive ),
		};
	}

	// 한글 위주 값(주소/특징/용도)은 라벨과 값 사이에 낀 OCR 잡음(실제 캡처로 확인: "소재^ HEA
	// 강남구 역삼동"의 "HEA")이 그대로 값 앞에 붙어 나온다 — 값의 첫 한글 글자 앞에 온 것은 전부
	// 잡음으로 보고 잘라낸다(실제 한글 주소/특징 표기가 영문자로 시작하는 경우는 없다).
	function ocrStripLeadingNoise( text ) {
		var m = String( text || '' ).match( /[가-힣]/ );
		return m ? text.slice( m.index ) : text;
	}

	// "방향"은 정해진 8방위 표기만 유효하다 — 라벨 바로 뒤 텍스트가 오인식된 다른 내용(실제 캡처로
	// 확인: "방향 Jes 출입구 기")이면 그대로 채우지 않고 버린다(방향이 아닌 값을 방향 필드에 넣는
	// 것이 아예 안 채우는 것보다 더 나쁘다).
	var OCR_DIRECTION_PATTERN = /^정?(?:남동|남서|북동|북서|남|북|동|서)향?/;
	function ocrExtractDirection( text ) {
		var m = String( text || '' ).trim().match( OCR_DIRECTION_PATTERN );
		return m ? m[ 0 ] : '';
	}

	// 난방/사무실 수/화장실 수/위반건축물 여부는 HLF Item 스키마에 없는 필드라 의도적으로 추출하지
	// 않는다(요청서 확인 결과 불필요 — 실제로 표시할 곳이 없는 값을 폼에 채우면 혼란만 준다).
	function ocrParsePropertyTable( text ) {
		// 라벨 자체가 오인식되는 경우(실제 캡처로 확인: "소재지"→"소재^", "매물특징"→"매쿨특징",
		// "입주가능일"→"임주가능일", "총주차대수"→"층주차대수")를 대비해, 원래 라벨이 안 잡히면
		// 오인식 가능성이 낮은 더 짧은/뒷부분 문자열로도 찾아본다(ocrLabeledValue는 길이가 긴
		// 라벨을 먼저 시도하므로 정확한 라벨이 있으면 그게 우선이고, 이 fallback은 원래 라벨이
		// 통째로 안 잡힐 때만 쓰인다).
		return {
			lot_address: ocrStripLeadingNoise( ocrLabeledValue( text, [ '소재지', '소재' ] ) ),
			features: ocrStripLeadingNoise( ocrLabeledValue( text, [ '매물특징', '물특징', '특징' ] ) ),
			maintenance_fee_manwon: ocrNormalizeMoney( ocrLabeledValue( text, [ '월관리비', '관리비' ] ) ),
			direction: ocrExtractDirection( ocrLabeledValue( text, [ '방향' ] ) ),
			available_date_text: ocrLabeledValue( text, [ '입주가능일', '주가능일' ] ),
			total_parking: ocrLabeledValue( text, [ '총주차대수', '주차대수' ] ),
			approval_date: ocrLabeledValue( text, [ '사용승인일' ] ),
			building_use: ocrStripLeadingNoise( ocrLabeledValue( text, [ '건축물 용도', '건축물용도' ] ) ),
		};
	}

	function ocrParseArticleNo( text ) {
		var labeled = ocrLabeledValue( text, [ '매물번호', '확인매물번호' ] );
		var digits = labeled.match( /\d{8,12}/ );
		return digits ? digits[ 0 ] : '';
	}

	function parseOcrText( rawText ) {
		var text = ocrNormalizeText( rawText );
		var values = Object.assign(
			{ article_no: ocrParseArticleNo( text ) },
			ocrParseLeaseAmounts( text ),
			ocrParseAreas( text ),
			ocrParseFloor( text ),
			ocrParsePropertyTable( text )
		);
		// 라벨 기반 관리비(ocrParsePropertyTable)가 비어 있으면 슬래시/라벨 조합(ocrParseLeaseAmounts)
		// 결과를 덮어쓰지 않도록 빈 값은 제거한다 — Object.assign 순서상 뒤 항목이 이기므로.
		Object.keys( values ).forEach( function ( key ) {
			if ( values[ key ] === '' ) { delete values[ key ]; }
		} );
		return values;
	}

	// 자동입력된 필드는 잠시 강조 표시했다가(요청서 2-5) 사용자가 직접 고치거나 일정 시간이 지나면
	// 강조를 지운다 — 어떤 값이 방금 자동으로 채워졌는지 한눈에 보이게 하되 영구 표시로 남기지 않는다.
	var AUTOFILL_HIGHLIGHT_MS = 6000;
	function markAutofilled( input ) {
		input.classList.add( 'hlf-field--autofilled' );
		if ( input._hlfAutofillTimer ) { clearTimeout( input._hlfAutofillTimer ); }
		input._hlfAutofillTimer = setTimeout( function () {
			input.classList.remove( 'hlf-field--autofilled' );
		}, AUTOFILL_HIGHLIGHT_MS );
		if ( ! input._hlfAutofillClearBound ) {
			input._hlfAutofillClearBound = true;
			input.addEventListener( 'input', function () {
				input.classList.remove( 'hlf-field--autofilled' );
				if ( input._hlfAutofillTimer ) { clearTimeout( input._hlfAutofillTimer ); }
			} );
		}
	}

	/**
	 * 파싱 결과를 폼에 채운다. mode(요청서 2-7, 기본값은 항상 'empty-only'):
	 *  - 'empty-only'    : 현재 값이 비어 있는 필드만 채운다(기존 값이 있는 필드는 절대 건드리지 않음).
	 *  - 'overwrite-all' : 추출된 값이 있는 필드는 기존 값과 무관하게 전부 덮어쓴다.
	 *  - 'confirm-each'  : 값이 비어 있는 필드는 바로 채우고, 기존 값과 충돌하는 필드만 목록으로 반환해
	 *                      호출자가 사용자 확인 UI를 그린 뒤 개별 승인된 것만 applyConfirmedOcrValues로 채운다.
	 * 반환값: confirm-each에서 사용자 확인이 필요한 [{key,label,oldValue,newValue}] 목록(그 외 모드는 항상 빈 배열).
	 */
	function applyOcrValuesToForm( form, values, mode ) {
		mode = mode || 'empty-only';
		var pending = [];
		var fieldLabels = {};
		ITEM_FIELDS.concat( [
			{ key: 'road_address', label: '도로명주소' }, { key: 'lot_address', label: '지번주소' },
			{ key: 'latitude', label: '위도' }, { key: 'longitude', label: '경도' },
		] ).forEach( function ( def ) { fieldLabels[ def.key ] = def.label; } );

		Object.keys( values ).forEach( function ( key ) {
			var input = form.elements[ key ];
			if ( ! input ) { return; }
			var current = input.type === 'checkbox' ? input.checked : input.value;
			var isEmpty = current === '' || current === null || current === undefined || current === false;

			if ( isEmpty || 'overwrite-all' === mode ) {
				input.value = values[ key ];
				markAutofilled( input );
				return;
			}
			if ( 'confirm-each' === mode ) {
				pending.push( { key: key, label: fieldLabels[ key ] || key, oldValue: current, newValue: values[ key ] } );
			}
			// 'empty-only'이고 이미 값이 있으면 아무것도 하지 않는다(기존 값 보호가 기본 동작).
		} );
		return pending;
	}

	/** confirm-each 모드에서 사용자가 체크한 항목만 실제로 폼에 반영한다. */
	function applyConfirmedOcrValues( form, confirmed ) {
		confirmed.forEach( function ( entry ) {
			var input = form.elements[ entry.key ];
			if ( ! input ) { return; }
			input.value = entry.newValue;
			markAutofilled( input );
		} );
	}

	function ocrPreprocessImage( file ) {
		return new Promise( function ( resolve, reject ) {
			var reader = new FileReader();
			reader.onerror = reject;
			reader.onload = function () {
				var image = new Image();
				image.onerror = reject;
				image.onload = function () {
					var scale = Math.min( 1.8, Math.max( 1, 1600 / Math.max( image.width, image.height ) ) );
					var canvas = document.createElement( 'canvas' );
					canvas.width = Math.round( image.width * scale );
					canvas.height = Math.round( image.height * scale );
					var context = canvas.getContext( '2d', { willReadFrequently: true } );
					context.drawImage( image, 0, 0, canvas.width, canvas.height );
					var pixels = context.getImageData( 0, 0, canvas.width, canvas.height );
					for ( var i = 0; i < pixels.data.length; i += 4 ) {
						var gray = pixels.data[ i ] * .299 + pixels.data[ i + 1 ] * .587 + pixels.data[ i + 2 ] * .114;
						var contrast = Math.max( 0, Math.min( 255, ( gray - 128 ) * 1.35 + 128 ) );
						pixels.data[ i ] = contrast;
						pixels.data[ i + 1 ] = contrast;
						pixels.data[ i + 2 ] = contrast;
					}
					context.putImageData( pixels, 0, 0 );
					resolve( canvas.toDataURL( 'image/png' ) );
				};
				image.src = reader.result;
			};
			reader.readAsDataURL( file );
		} );
	}

	var ocrEngineLoader = null;
	function loadOcrEngine() {
		if ( window.Tesseract ) { return Promise.resolve( window.Tesseract ); }
		if ( ocrEngineLoader ) { return ocrEngineLoader; }
		ocrEngineLoader = new Promise( function ( resolve, reject ) {
			var script = document.createElement( 'script' );
			script.src = OCR_SCRIPT_URL;
			script.onload = function () { window.Tesseract ? resolve( window.Tesseract ) : reject( new Error( 'OCR 엔진을 찾을 수 없습니다.' ) ); };
			script.onerror = function () { reject( new Error( 'OCR 엔진을 불러오지 못했습니다.' ) ); };
			document.head.appendChild( script );
		} );
		return ocrEngineLoader;
	}

	function currentOcrMode( form ) {
		var checked = form.querySelector( 'input[name="hlf-ocr-mode"]:checked' );
		return checked ? checked.value : 'empty-only';
	}

	// confirm-each 모드에서 기존 값과 충돌하는 필드만 "기존값 → 제안값 [적용]" 목록으로 보여주고,
	// 사용자가 체크한 것만 실제로 반영한다(2-7 "항목별 확인").
	function renderOcrConfirmList( form, listEl, pending ) {
		if ( ! pending.length ) { listEl.hidden = true; listEl.innerHTML = ''; return; }
		listEl.hidden = false;
		listEl.innerHTML =
			'<p class="hlf-admin-note">이미 값이 있는 항목입니다 — 적용할 항목만 체크한 뒤 반영해 주세요.</p>' +
			'<ul>' + pending.map( function ( entry, index ) {
				return (
					'<li><label>' +
						'<input type="checkbox" data-hlf-ocr-confirm-index="' + index + '" checked> ' +
						'<strong>' + HLFAdmin.escapeHtml( entry.label ) + '</strong>: ' +
						'<span class="hlf-ocr-confirm-old">' + HLFAdmin.escapeHtml( String( entry.oldValue ) ) + '</span>' +
						' → <span class="hlf-ocr-confirm-new">' + HLFAdmin.escapeHtml( String( entry.newValue ) ) + '</span>' +
					'</label></li>'
				);
			} ).join( '' ) + '</ul>' +
			'<button type="button" class="button button-small" id="hlf-ocr-confirm-apply">체크한 항목 반영</button>';

		document.getElementById( 'hlf-ocr-confirm-apply' ).addEventListener( 'click', function () {
			var confirmed = pending.filter( function ( entry, index ) {
				var box = listEl.querySelector( '[data-hlf-ocr-confirm-index="' + index + '"]' );
				return box && box.checked;
			} );
			applyConfirmedOcrValues( form, confirmed );
			listEl.hidden = true;
			listEl.innerHTML = '';
		} );
	}

	function bindOcrSection( form ) {
		var captureInput = document.getElementById( 'hlf-ocr-capture' );
		var preview = document.getElementById( 'hlf-ocr-preview' );
		var runButton = document.getElementById( 'hlf-ocr-run' );
		var statusEl = document.getElementById( 'hlf-ocr-status' );
		var textArea = document.getElementById( 'hlf-ocr-text' );
		var applyButton = document.getElementById( 'hlf-ocr-apply' );
		var confirmListEl = document.getElementById( 'hlf-ocr-confirm-list' );
		if ( ! captureInput || ! runButton || ! textArea || ! applyButton ) { return; }

		function applyAndReport( rawText ) {
			var pending = applyOcrValuesToForm( form, parseOcrText( rawText ), currentOcrMode( form ) );
			if ( pending.length ) {
				renderOcrConfirmList( form, confirmListEl, pending );
				statusEl.textContent = '일부 항목만 자동입력되었습니다. 내용을 확인해 주세요.';
			} else {
				statusEl.textContent = 'OCR 원문에서 입력 필드를 채웠습니다. 내용을 확인해 주세요.';
			}
		}

		captureInput.addEventListener( 'change', function () {
			var file = captureInput.files && captureInput.files[ 0 ];
			runButton.disabled = ! file;
			if ( ! file ) { preview.hidden = true; return; }
			var reader = new FileReader();
			reader.onload = function () {
				preview.src = reader.result;
				preview.hidden = false;
			};
			reader.readAsDataURL( file );
		} );

		runButton.addEventListener( 'click', function () {
			var file = captureInput.files && captureInput.files[ 0 ];
			if ( ! file ) {
				statusEl.textContent = '이미지를 선택해 주세요.';
				return;
			}
			runButton.disabled = true;
			statusEl.textContent = 'OCR 엔진을 준비하고 있습니다. 첫 실행은 조금 걸릴 수 있습니다.';

			loadOcrEngine()
				.catch( function () {
					// 라이브러리 자체를 못 불러온 경우(CDN 차단/네트워크 오류)와 인식 실패를 구분해서
					// 안내한다 — 사용자가 재시도할지 수동 입력으로 넘어갈지 판단할 수 있도록.
					var err = new Error( 'OCR 라이브러리를 불러오지 못했습니다.' );
					err.hlfStage = 'engine-load';
					throw err;
				} )
				.then( function ( tesseract ) {
					return tesseract.createWorker( 'kor+eng' ).then( function ( worker ) {
						// 2-8 개선: 네이버부동산 캡처는 표 형태 구조가 많아 기본 자동모드(PSM 3)보다
						// PSM 6(균일한 텍스트 블록)이 대체로 더 정확하다 — 실제 캡처 샘플로 재현/A-B
						// 비교는 이 환경(네트워크로 Tesseract CDN에 접근 불가)에서 직접 실행할 수
						// 없었으므로, 설치 후 실제 캡처로 확인해 볼 것(완료 보고의 "알려진 한계" 참고).
						var setPsm = worker.setParameters ? worker.setParameters( { tessedit_pageseg_mode: '6' } ) : Promise.resolve();
						return setPsm.then( function () {
							return ocrPreprocessImage( file ).then( function ( processedImage ) {
								return worker.recognize( processedImage ).then( function ( result ) {
									return worker.terminate().then( function () { return result; } );
								} );
							} );
						} );
					} ).catch( function ( err ) {
						err.hlfStage = err.hlfStage || 'recognize';
						throw err;
					} );
				} )
				.then( function ( result ) {
					var text = ( result && result.data && result.data.text ) || '';
					textArea.value = text;
					if ( ! text.trim() ) {
						statusEl.textContent = '이미지에서 텍스트를 인식하지 못했습니다.';
						runButton.disabled = false;
						return;
					}
					applyAndReport( text );
					runButton.disabled = false;
				} )
				.catch( function ( err ) {
					statusEl.textContent = 'engine-load' === err.hlfStage
						? 'OCR 라이브러리를 불러오지 못했습니다.'
						: '이미지에서 텍스트를 인식하지 못했습니다.';
					runButton.disabled = false;
				} );
		} );

		applyButton.addEventListener( 'click', function () {
			applyAndReport( textArea.value );
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

		bindOcrSection( itemForm );

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
