/**
 * 네이버부동산 캡처 OCR(Tesseract.js) 공용 모듈. 관리자 Flyer 편집 화면(admin-flyer-edit.js)과
 * List Up 관리 화면(admin-listup.js)의 "전체 매물" 폼이 함께 쓴다 — 파싱/정규화 로직은 여기 한 곳만
 * 두어 두 화면이 항상 동일하게 동작하게 한다(요청서: 기존 OCR 재사용).
 *
 * OCR 섹션은 폼 필드의 name(=HLF 스키마 key)만 보고 값을 채우므로, Item 폼이든 원본 매물 폼이든
 * 같은 key를 쓰는 한 그대로 동작한다. window.HLFOcr.renderSection()으로 마크업을 얻고,
 * 폼이 DOM에 붙은 뒤 window.HLFOcr.bindSection(form)으로 이벤트를 건다.
 */
( function () {
	'use strict';

	/* ---------------- 네이버부동산 캡처 OCR(선택 입력 보조) ----------------
	 * Tesseract.js(CDN, kor+eng)로 캡처 이미지에서 텍스트를 뽑아 항목 필드에 자동으로 채워 넣는다.
	 * 실제 OCR 엔진 연동이며 가짜 결과를 만들지 않는다 — 다만 추출 결과는 항상 "제안값"이고
	 * 사용자가 원문/필드를 직접 확인·수정한 뒤에만 저장 버튼으로 실제 저장된다(자동 저장 없음).
	 * 서버 계산(NOC 등)과는 무관하다 — 여기서 하는 일은 이미지 속 텍스트를 필드값 후보로
	 * 정규화하는 것뿐이고, 그 값들로 파생 지표를 계산하는 로직은 두지 않는다(그건 서버 몫).
	 */
	// 버전을 "@5"(메이저만)로 띄워두면 jsdelivr가 그때그때 최신 5.x를 내려줘 배포 시점마다 실제로
	// 뭐가 로드되는지 달라지고, 무결성도 확인할 수 없다 — 정확한 버전(현재 시점 "@5"가 가리키는
	// 5.1.1, npm 레지스트리로 확인)으로 고정하고 SRI(integrity)+crossorigin을 붙인다. 이 플러그인은
	// 관리자 전용 화면에서만 로드되므로(공개 페이지엔 없음, 이미 확인됨) 리스크는 제한적이지만,
	// CDN이 변조되거나 다른 파일을 내려줘도 해시가 다르면 브라우저가 실행을 차단하게 한다.
	// integrity 해시는 npm 레지스트리에서 내려받은 tesseract.js@5.1.1 배포본의 dist/tesseract.min.js를
	// 직접 sha384로 계산한 값이다(이 샌드박스에서 cdn.jsdelivr.net 자체는 프록시 정책상 접근이
	// 막혀 있어 jsdelivr가 서빙하는 파일과 완전히 동일한지 최종 교차 확인은 못했다 — jsdelivr의 /npm/
	// 경로는 npm 배포본을 그대로 미러링하는 것으로 알려져 있으나, 실제 설치 후 브라우저에서 OCR이
	// 정상 동작하는지 한 번은 확인해 달라). 해시가 어긋나면 브라우저가 스크립트 실행을 막고
	// script.onerror가 그대로 발생해 "OCR 라이브러리를 불러오지 못했습니다" 안내로 우아하게
	// 저하될 뿐 나머지 관리자 화면은 그대로 동작한다.
	var OCR_SCRIPT_URL = 'https://cdn.jsdelivr.net/npm/tesseract.js@5.1.1/dist/tesseract.min.js';
	var OCR_SCRIPT_INTEGRITY = 'sha384-GJqSu7vueQ9qN0E9yLPb3Wtpd7OrgK8KmYzC8T1IysG1bcvxvIO4qtYR/D3A991F';
	var OCR_MAX_MONEY = 1000000;
	var OCR_MAX_AREA_SQM = 1000000;

	function renderOcrSection() {
		return (
			'<div class="hlf-ocr-section">' +
				'<h4>네이버부동산 캡처로 자동 입력 (선택)</h4>' +
				'<p class="hlf-admin-note">필수 단계는 아닙니다 — 캡처만 붙이면 아래 입력 시간을 줄여줄 뿐, 건너뛰고 직접 입력해도 됩니다.</p>' +
				'<p class="hlf-admin-note">캡처를 파일로 저장하지 않고, 캡처 직후 클립보드에 있는 상태 그대로 이 화면 아무 곳에서나 Ctrl+V(붙여넣기)로 바로 불러올 수 있습니다.</p>' +
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
	// 것이 아예 안 채우는 것보다 더 나쁘다). 네이버부동산은 라벨 자체가 "방향(주된 출입구 기준)"
	// 처럼 항상 괄호 설명이 붙어 있어(실제 캡처로 확인: "북서향(주된 출입구 기준)") 값 앞에 그
	// 설명 잔여물이 남을 수 있다 — 그래서 문자열 맨 앞(^)에만 매치하지 않고 방향 패턴이 어디에
	// 있든 첫 번째로 나오는 것을 찾는다.
	var OCR_DIRECTION_PATTERN = /정?(?:남동|남서|북동|북서|남|북|동|서)향?/;
	function ocrExtractDirection( text ) {
		var m = String( text || '' ).match( OCR_DIRECTION_PATTERN );
		return m ? m[ 0 ] : '';
	}

	// 건축물 용도는 실무상 몇 가지 정해진 값만 쓰인다 — 닫힌 목록과 대조해 검증/정규화한다("제2증
	// 근린생활시설"처럼 숫자 뒤 "종"이 "증"으로 오인식되는 경우가 실제로 있었다). 목록에 없는
	// 값은 오인식으로 보고 버린다(방향 필드와 같은 원칙).
	var OCR_BUILDING_USE_LIST = [ '제1종 근린생활시설', '제2종 근린생활시설', '근린생활시설', '업무시설', '교육연구시설', '의료시설' ];
	function ocrExtractBuildingUse( text ) {
		var normalized = String( text || '' ).replace( /제(\d)\s*증/g, '제$1종' );
		var sorted = OCR_BUILDING_USE_LIST.slice().sort( function ( a, b ) { return b.length - a.length; } );
		var match = sorted.find( function ( candidate ) { return normalized.indexOf( candidate ) !== -1; } );
		return match || '';
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
			building_use: ocrExtractBuildingUse( ocrLabeledValue( text, [ '건축물 용도', '건축물용도' ] ) ),
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
	// 필드 라벨은 ITEM_FIELDS(호출 화면마다 다름)에 의존하지 않고, 폼 안의 실제 <label> 텍스트에서
	// 읽는다 — 그래야 이 모듈이 Item 폼/원본 매물 폼 어느 쪽에 붙어도 동일하게 동작한다.
	function fieldLabelFor( form, key ) {
		var input = form.elements[ key ];
		if ( ! input ) { return key; }
		var wrap = input.closest( '.hlf-field, .hlf-field-checkbox' );
		var label = wrap ? wrap.querySelector( 'label' ) : null;
		var text = label ? label.textContent.replace( /\s+/g, ' ' ).trim() : '';
		return text || key;
	}

	function applyOcrValuesToForm( form, values, mode ) {
		mode = mode || 'empty-only';
		var pending = [];

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
				pending.push( { key: key, label: fieldLabelFor( form, key ), oldValue: current, newValue: values[ key ] } );
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
			script.integrity = OCR_SCRIPT_INTEGRITY;
			script.crossOrigin = 'anonymous';
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

	// 화면(Item 폼/원본 매물 폼)을 다시 그릴 때마다 bindOcrSection()이 새로 호출되는데, paste는
	// 이 모듈이 아니라 document 전체에서 들어야 어느 필드에 포커스가 있어도(또는 아예 없어도) 받을 수
	// 있다 — 그래서 이전 폼의 리스너를 남겨두면 폼을 여러 번 열고 닫을 때마다 계속 쌓인다. 직전
	// 리스너를 기억해뒀다가 새로 걸기 전에 반드시 떼어낸다(한 번에 하나의 OCR 폼만 화면에 있다는
	// 전제 — SPA 특성상 이전 폼은 이미 DOM에서 사라진 상태).
	var activePasteHandler = null;

	function bindOcrSection( form ) {
		var captureInput = document.getElementById( 'hlf-ocr-capture' );
		var preview = document.getElementById( 'hlf-ocr-preview' );
		var runButton = document.getElementById( 'hlf-ocr-run' );
		var statusEl = document.getElementById( 'hlf-ocr-status' );
		var textArea = document.getElementById( 'hlf-ocr-text' );
		var applyButton = document.getElementById( 'hlf-ocr-apply' );
		var confirmListEl = document.getElementById( 'hlf-ocr-confirm-list' );
		if ( ! captureInput || ! runButton || ! textArea || ! applyButton ) { return; }

		// 클립보드 이미지를 실제 <input type=file>의 FileList에 반영해, 그 뒤의 미리보기/추출 로직
		// (change 리스너, runButton 클릭 시 captureInput.files[0] 참조)을 파일 선택과 완전히 동일하게
		// 그대로 재사용한다 — 붙여넣기 전용 별도 경로를 새로 만들지 않는다.
		function setCaptureFile( file ) {
			try {
				var dt = new DataTransfer();
				dt.items.add( file );
				captureInput.files = dt.files;
				captureInput.dispatchEvent( new Event( 'change' ) );
			} catch ( e ) {
				statusEl.textContent = '붙여넣은 이미지를 캡처 입력란에 반영하지 못했습니다 — 파일로 저장한 뒤 선택해 주세요.';
			}
		}

		if ( activePasteHandler ) { document.removeEventListener( 'paste', activePasteHandler ); }
		activePasteHandler = function ( event ) {
			if ( ! document.body.contains( captureInput ) ) { return; }
			var clipboardItems = ( event.clipboardData && event.clipboardData.items ) || [];
			var imageItem = Array.prototype.find.call( clipboardItems, function ( item ) { return item.type && 0 === item.type.indexOf( 'image/' ); } );
			if ( ! imageItem ) { return; }
			var file = imageItem.getAsFile();
			if ( ! file ) { return; }
			event.preventDefault();
			setCaptureFile( file );
			statusEl.textContent = '클립보드 이미지를 붙여넣었습니다. "텍스트 추출"을 눌러주세요.';
		};
		document.addEventListener( 'paste', activePasteHandler );

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

	window.HLFOcr = {
		renderSection: renderOcrSection,
		bindSection: bindOcrSection,
		// 아래는 자동화 테스트(스크래치패드 하네스)에서 직접 호출해 파싱을 검증하려고 함께 노출한다.
		parseOcrText: parseOcrText,
		ocrExtractDirection: ocrExtractDirection,
		ocrExtractBuildingUse: ocrExtractBuildingUse,
	};
} )();
