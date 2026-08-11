/**
 * single.js의 initListingToggle()(매물 2~3건 빌딩 전용 "면적 버튼 토글")을 최소 DOM fixture로 검증한다.
 * test-single-gallery.js와 동일한 방식 - Node vm 모듈에서 실제 single.js 파일을 그대로 실행시킨다.
 *
 * 검증 대상: 버튼 클릭 시 (1) 상단 Hero의 data-toggle-field 요소들이 클릭한 매물의 값으로 바뀌고,
 * (2) 클릭한 버튼만 is-active가 되고, (3) 하단 "임대 정보" 섹션의 매물 카드도 같은 인덱스만
 * is-active가 되는지 - 이 세 가지가 서버 재쿼리 없이 미리 임베드된 JSON 데이터만으로 동기화되는지.
 *
 * [리뷰 지적, 실제 버그였음] "해당층 / 총층" 칸은 실제 마크업에서
 * <strong><span data-toggle-field="floor">3층</span> / 40F</strong> 구조다(single-building.php) -
 * "/ 40F"는 빌딩 고정값이라 span 밖 정적 텍스트로 둬야 한다. 예전엔 data-toggle-field가 <strong>
 * 자체에 붙어 있어서, 클릭 시 textContent를 통째로 갈아치우면 "/ 40F"가 함께 사라졌다.
 * 이 fixture는 각 필드를 독립된 fake 엘리먼트로 다루기 때문에(진짜 부모/자식 DOM 트리를 흉내내지
 * 않음) "부모의 다른 텍스트가 안 지워진다"는 것 자체를 여기서 직접 재현하지는 못한다 - 그 보장은
 * span으로 감싸는 것 자체가 DOM 구조상 자동으로 성립한다(형제 텍스트 노드는 별도 노드라 JS가
 * el.textContent를 span에만 할당하면 절대 못 건드림). 이 테스트가 실질적으로 확인하는 건 JS가
 * "floor" 필드에 넣는 값 자체가 매물별로 정확한지(아래) - 마크업이 실제로 값만 span으로 감쌌는지는
 * single-building.php 코드 리뷰로 별도 확인했다(grep 'data-toggle-field="floor"').
 *
 * 실행: node tests/test-single-listing-toggle.js
 */

const fs = require('fs');
const path = require('path');
const vm = require('vm');

let pass = 0;
let fail = 0;
function check(label, got, expected) {
    const ok = JSON.stringify(got) === JSON.stringify(expected);
    if (ok) {
        pass++;
        console.log(`[PASS] ${label}`);
    } else {
        fail++;
        console.log(`[FAIL] ${label}\n  got:      ${JSON.stringify(got)}\n  expected: ${JSON.stringify(expected)}`);
    }
}

/** single.js가 실제로 쓰는 최소 API만 지원하는 가짜 DOM 엘리먼트. */
function makeElement(initialAttrs, initialClasses, initialText) {
    const attrs = Object.assign({}, initialAttrs);
    const classes = new Set(initialClasses || []);
    const listeners = {};
    return {
        getAttribute(name) {
            return Object.prototype.hasOwnProperty.call(attrs, name) ? attrs[name] : null;
        },
        setAttribute(name, value) {
            attrs[name] = value;
        },
        classList: {
            add: (c) => classes.add(c),
            remove: (c) => classes.delete(c),
            contains: (c) => classes.has(c),
            toggle: (c, force) => {
                const on = force === undefined ? !classes.has(c) : force;
                if (on) {
                    classes.add(c);
                } else {
                    classes.delete(c);
                }
            },
        },
        addEventListener(type, fn) {
            (listeners[type] = listeners[type] || []).push(fn);
        },
        click() {
            (listeners.click || []).forEach((fn) => fn());
        },
        textContent: initialText || '',
    };
}

const listingsData = [
    {
        id: 101, floor: '3층', lease_pyeong: '(363평)', lease_sqm: '1,200.0㎡',
        exclusive_pyeong: '(227평)', exclusive_sqm: '750.4㎡',
        deposit: '150,000만원', deposit_per_lease_pyeong: '413.2만원',
        rent: '15,000만원', rent_per_lease_pyeong: '41.3만원',
        maintenance: '3,500만원', maintenance_per_lease_pyeong: '9.6만원',
    },
    {
        id: 102, floor: '5층', lease_pyeong: '(280평)', lease_sqm: '925.6㎡',
        exclusive_pyeong: '(170평)', exclusive_sqm: '562.0㎡',
        deposit: '110,000만원', deposit_per_lease_pyeong: '392.9만원',
        rent: '11,000만원', rent_per_lease_pyeong: '39.3만원',
        maintenance: '2,600만원', maintenance_per_lease_pyeong: '9.3만원',
    },
    {
        id: 103, floor: '7층', lease_pyeong: '(200평)', lease_sqm: '661.2㎡',
        exclusive_pyeong: '(121평)', exclusive_sqm: '400.0㎡',
        deposit: '80,000만원', deposit_per_lease_pyeong: '400.0만원',
        rent: '8,000만원', rent_per_lease_pyeong: '40.0만원',
        maintenance: '2,000만원', maintenance_per_lease_pyeong: '10.0만원',
    },
];

function run() {
    const dataEl = makeElement({}, [], JSON.stringify(listingsData));

    const fields = {};
    ['floor', 'lease_pyeong', 'lease_sqm', 'exclusive_pyeong', 'exclusive_sqm',
        'deposit', 'deposit_per_lease_pyeong', 'rent', 'rent_per_lease_pyeong',
        'maintenance', 'maintenance_per_lease_pyeong'].forEach((f) => {
        fields[f] = makeElement({ 'data-toggle-field': f });
    });
    const fieldEls = Object.values(fields);

    const buttons = listingsData.map((l, i) =>
        makeElement({ 'data-listing-index': String(i) }, i === 0 ? ['is-active'] : [])
    );
    const cards = listingsData.map((l, i) =>
        makeElement({ 'data-listing-index': String(i) }, i === 0 ? ['is-active'] : [])
    );

    const fakeDocument = {
        readyState: 'complete',
        getElementById(id) {
            return id === 'olx-toggle-data' ? dataEl : null;
        },
        querySelector() {
            return null; // 갤러리 쪽 셀렉터는 이 테스트에선 없음 - init()이 조용히 early-return해야 한다.
        },
        querySelectorAll(sel) {
            if (sel === '.olx-listing-toggle button') {
                return buttons;
            }
            if (sel === '#olx-toggle-specs [data-toggle-field], .olx-price [data-toggle-field]') {
                return fieldEls;
            }
            if (sel === '#olx-toggle-cards .olx-toggle-card') {
                return cards;
            }
            if (sel === '.olx-gallery-thumbs button') {
                return []; // 갤러리 썸네일 없음(init()이 조용히 return하는지도 같이 확인)
            }
            return [];
        },
        addEventListener() {
            /* readyState=complete라 안 씀 */
        },
    };

    const src = fs.readFileSync(path.join(__dirname, '..', 'assets', 'js', 'single.js'), 'utf8');
    const context = { document: fakeDocument, console };
    vm.createContext(context);
    vm.runInContext(src, context);

    // 초기 상태: 0번(첫 매물)이 이미 PHP 렌더 시점에 active로 표시돼 있어야 하고(HTML 자체에 심어둠),
    // JS는 버튼을 누르기 전까지 아무것도 안 바꾼다 - 이 테스트는 "클릭 후" 동작만 검증한다.

    // 2번째 버튼(인덱스 1) 클릭
    buttons[1].click();
    Object.keys(fields).forEach((f) => {
        check(`버튼2 클릭 - ${f} 필드가 매물2 값으로 갱신`, fields[f].textContent, listingsData[1][f]);
    });
    check('버튼2 클릭 - 버튼2만 active', buttons[1].classList.contains('is-active'), true);
    check('버튼2 클릭 - 버튼1 active 해제', buttons[0].classList.contains('is-active'), false);
    check('버튼2 클릭 - 버튼3 active 해제', buttons[2].classList.contains('is-active'), false);
    check('버튼2 클릭 - 카드2만 active(하단 섹션 동기화)', cards[1].classList.contains('is-active'), true);
    check('버튼2 클릭 - 카드1 active 해제(하단 섹션 동기화)', cards[0].classList.contains('is-active'), false);
    check('버튼2 클릭 - 카드3 active 해제(하단 섹션 동기화)', cards[2].classList.contains('is-active'), false);

    // 3번째 버튼(인덱스 2) 클릭 - 값이 계속 정확히 전환되는지, 이전 선택이 깔끔히 풀리는지
    buttons[2].click();
    check('버튼3 클릭 - floor 필드가 매물3 값으로 갱신', fields.floor.textContent, listingsData[2].floor);
    check('버튼3 클릭 - deposit_per_lease_pyeong 갱신', fields.deposit_per_lease_pyeong.textContent, listingsData[2].deposit_per_lease_pyeong);
    check('버튼3 클릭 - 버튼3만 active', buttons[2].classList.contains('is-active'), true);
    check('버튼3 클릭 - 버튼2 active 해제', buttons[1].classList.contains('is-active'), false);
    check('버튼3 클릭 - 카드3만 active', cards[2].classList.contains('is-active'), true);
    check('버튼3 클릭 - 카드2 active 해제', cards[1].classList.contains('is-active'), false);

    // 1번째 버튼(인덱스 0)으로 되돌아가기
    buttons[0].click();
    check('버튼1로 복귀 - floor 필드가 매물1 값으로 갱신', fields.floor.textContent, listingsData[0].floor);
    check('버튼1로 복귀 - 버튼1만 active', buttons[0].classList.contains('is-active'), true);
    check('버튼1로 복귀 - 카드1만 active', cards[0].classList.contains('is-active'), true);
}

run();

console.log(`\n${pass} passed, ${fail} failed`);
if (fail > 0) {
    process.exit(1);
}
