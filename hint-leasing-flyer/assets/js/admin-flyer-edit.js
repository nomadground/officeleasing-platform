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
		{ key: 'source_listing_id', label: '원본 매물 ID (선택)', type: 'number', step: '1' },
		{ key: 'source_building_id', label: '원본 빌딩 ID (선택)', type: 'number', step: '1' },
	];

	var state = { flyer: null, items: [], editingItemId: null };

	/* ---------------- 신규 Flyer 생성 모드 ---------------- */

	function renderCreateForm() {
		root.innerHTML =
			'<section class="hlf-card">' +
				'<form id="hlf-new-flyer-form">' +
					'<div class="hlf-field"><label>제목(내부 관리용)</label><input type="text" name="title" required></div>' +
					'<div class="hlf-field"><label>담당자명</label><input type="text" name="contact_name"></div>' +
					'<div class="hlf-field"><label>담당자 연락처</label><input type="text" name="contact_phone"></div>' +
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
	}

	function renderFlyerCard() {
		var flyer = state.flyer;
		return (
			'<section class="hlf-card">' +
				'<h2>' + HLFAdmin.escapeHtml( flyer.flyer_number ) + ' <span class="' + HLFAdmin.statusBadgeClass( flyer.status ) + '">' + HLFAdmin.statusLabel( flyer.status ) + '</span></h2>' +
				'<form id="hlf-flyer-form">' +
					'<div class="hlf-field"><label>제목(내부 관리용)</label><input type="text" name="title" value="' + HLFAdmin.escapeHtml( flyer.title ) + '" required></div>' +
					'<div class="hlf-field"><label>담당자명</label><input type="text" name="contact_name" value="' + HLFAdmin.escapeHtml( flyer.contact_name ) + '"></div>' +
					'<div class="hlf-field"><label>담당자 연락처</label><input type="text" name="contact_phone" value="' + HLFAdmin.escapeHtml( flyer.contact_phone ) + '"></div>' +
					'<p class="hlf-admin-note">공개 화면 하단 문의처로 쓰입니다. 비워두면 대표번호(' + HLFAdmin.escapeHtml( HLF_ADMIN.defaultPhone ) + ')로 표시됩니다.</p>' +
					'<button type="submit" class="button button-primary">저장</button>' +
					'<p class="hlf-admin-error" data-hlf-flyer-error hidden></p>' +
				'</form>' +
				'<hr>' +
				'<div class="hlf-field">' +
					'<label>상태</label>' +
					'<select id="hlf-status-select">' +
						'<option value="draft"' + ( flyer.status === 'draft' ? ' selected' : '' ) + '>미발행(draft)</option>' +
						'<option value="published"' + ( flyer.status === 'published' ? ' selected' : '' ) + '>발행됨(published)</option>' +
						'<option value="archived"' + ( flyer.status === 'archived' ? ' selected' : '' ) + '>보관(archived)</option>' +
					'</select> ' +
					'<button type="button" class="button" id="hlf-status-apply">상태 변경</button>' +
					'<p class="hlf-admin-error" data-hlf-status-error hidden></p>' +
				'</div>' +
				'<p class="hlf-admin-note"><a href="' + HLFAdmin.escapeHtml( flyer.url ) + '" target="_blank" rel="noopener">공개 링크 열기 ↗</a></p>' +
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
		return (
			'<section class="hlf-card">' +
				'<h2>매물(Item) — ' + state.items.length + ' / ' + HLF_ADMIN.maxItems + '</h2>' +
				'<div id="hlf-items-table-wrap">' + renderItemsTable() + '</div>' +
				'<button type="button" class="button button-primary" id="hlf-add-item"' + ( atLimit ? ' disabled title="최대 ' + HLF_ADMIN.maxItems + '개까지만 담을 수 있습니다."' : '' ) + '>매물 추가</button>' +
				renderItemForm() +
			'</section>'
		);
	}

	function renderItemsTable() {
		if ( ! state.items.length ) {
			return '<p class="hlf-admin-empty">아직 등록된 매물이 없습니다.</p>';
		}
		var rows = state.items.map( function ( item ) {
			var address = item.road_address || item.lot_address || '-';
			var m = item.metrics || {};
			return (
				'<tr data-item-row="' + item.id + '">' +
					'<td>' + HLFAdmin.escapeHtml( item.item_number ) + '</td>' +
					'<td>' + HLFAdmin.escapeHtml( address ) + '</td>' +
					'<td>' + HLFAdmin.formatManwon( item.deposit_manwon ) + ' / ' + HLFAdmin.formatManwon( item.monthly_rent_manwon ) + ' / ' + HLFAdmin.formatManwon( item.maintenance_fee_manwon ) + '</td>' +
					'<td>' + HLFAdmin.formatNumber1( m.noc ) + '만원/평</td>' +
					'<td class="hlf-admin-actions">' +
						'<button type="button" class="button button-small" data-hlf-edit-item="' + item.id + '">수정</button> ' +
						'<button type="button" class="button button-small hlf-danger" data-hlf-delete-item="' + item.id + '">삭제</button>' +
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
			if ( def.type === 'textarea' ) {
				return (
					'<div class="hlf-field' + wideClass + '"><label>' + HLFAdmin.escapeHtml( def.label ) + '</label>' +
					'<textarea name="' + def.key + '">' + HLFAdmin.escapeHtml( value || '' ) + '</textarea></div>'
				);
			}
			var stepAttr = def.step ? ' step="' + def.step + '"' : '';
			var placeholderAttr = def.placeholder ? ' placeholder="' + HLFAdmin.escapeHtml( def.placeholder ) + '"' : '';
			return (
				'<div class="hlf-field' + wideClass + '"><label>' + HLFAdmin.escapeHtml( def.label ) + '</label>' +
				'<input type="' + def.type + '" name="' + def.key + '" value="' + HLFAdmin.escapeHtml( value === null || value === undefined ? '' : value ) + '"' + stepAttr + placeholderAttr + '></div>'
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
			'</form>'
		);
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
	}

	if ( ! flyerId ) {
		renderCreateForm();
	} else {
		loadFlyer();
	}
} )();
