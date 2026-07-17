/**
 * Flyer 목록 화면. GET /flyers 로 렌더링, "새 Flyer 만들기"는 POST /flyers 후 편집화면으로 이동,
 * 행의 삭제는 DELETE /flyers/{id}. 전부 hlf/v1 REST(HLF_REST_Controller)만 호출하고
 * 별도 저장 로직을 만들지 않는다.
 */
( function () {
	'use strict';

	var root = document.getElementById( 'hlf-flyer-list-root' );
	var newButton = document.querySelector( '[data-hlf-new-flyer]' );

	function render( flyers ) {
		if ( ! flyers.length ) {
			root.innerHTML = '<p class="hlf-admin-empty">등록된 Flyer가 없습니다.</p>';
			return;
		}

		var rows = flyers.map( function ( flyer ) {
			var editUrl = HLF_ADMIN.editUrlBase + flyer.id;
			var contact = [ flyer.contact_name, flyer.contact_phone ].filter( Boolean ).join( ' · ' ) || '-';
			return (
				'<tr data-flyer-row="' + flyer.id + '">' +
					'<td><a href="' + editUrl + '">' + HLFAdmin.escapeHtml( flyer.flyer_number ) + '</a></td>' +
					'<td>' + HLFAdmin.escapeHtml( flyer.title ) + '</td>' +
					'<td><span class="' + HLFAdmin.statusBadgeClass( flyer.status ) + '">' + HLFAdmin.statusLabel( flyer.status ) + '</span></td>' +
					'<td>' + flyer.item_count + '개</td>' +
					'<td>' + HLFAdmin.escapeHtml( contact ) + '</td>' +
					'<td class="hlf-admin-actions">' +
						'<a class="button button-small" href="' + editUrl + '">수정</a> ' +
						'<button type="button" class="button button-small hlf-danger" data-hlf-delete-flyer="' + flyer.id + '">삭제</button>' +
					'</td>' +
				'</tr>'
			);
		} ).join( '' );

		root.innerHTML =
			'<table class="widefat striped hlf-admin-table">' +
				'<thead><tr><th>번호</th><th>제목</th><th>상태</th><th>매물</th><th>담당자</th><th>작업</th></tr></thead>' +
				'<tbody>' + rows + '</tbody>' +
			'</table>';
	}

	function load() {
		root.innerHTML = '<p class="hlf-admin-loading">불러오는 중…</p>';
		HLFAdmin.apiFetch( 'flyers' )
			.then( render )
			.catch( function ( err ) {
				root.innerHTML = '<p class="hlf-admin-error">목록을 불러오지 못했습니다: ' + HLFAdmin.escapeHtml( err.message ) + '</p>';
			} );
	}

	if ( newButton ) {
		newButton.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			if ( newButton.disabled ) { return; }
			var title = window.prompt( '새 Flyer의 제목(내부 관리용)을 입력하세요.', '' );
			if ( title === null ) { return; } // 취소.

			newButton.disabled = true;
			newButton.classList.add( 'disabled' );
			HLFAdmin.apiFetch( 'flyers', { method: 'POST', body: JSON.stringify( { title: title } ) } )
				.then( function ( flyer ) {
					window.location.href = HLF_ADMIN.editUrlBase + flyer.id;
				} )
				.catch( function ( err ) {
					window.alert( 'Flyer 생성에 실패했습니다: ' + err.message );
					newButton.disabled = false;
					newButton.classList.remove( 'disabled' );
				} );
		} );
	}

	root.addEventListener( 'click', function ( event ) {
		var deleteButton = event.target.closest( '[data-hlf-delete-flyer]' );
		if ( ! deleteButton ) { return; }
		var flyerId = deleteButton.getAttribute( 'data-hlf-delete-flyer' );
		if ( ! window.confirm( '이 Flyer와 소속된 모든 매물을 삭제합니다. 계속할까요?' ) ) { return; }

		deleteButton.disabled = true;
		HLFAdmin.apiFetch( 'flyers/' + flyerId, { method: 'DELETE' } )
			.then( function () {
				var row = root.querySelector( '[data-flyer-row="' + flyerId + '"]' );
				if ( row ) { row.remove(); }
			} )
			.catch( function ( err ) {
				window.alert( '삭제에 실패했습니다: ' + err.message );
				deleteButton.disabled = false;
			} );
	} );

	load();
} )();
