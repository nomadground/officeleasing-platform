/**
 * single.js의 initListingToggle()(매물 2~3건 빌딩 전용 "면적 슬라이더")을 최소 DOM fixture로 검증한다.
 * test-single-gallery.js와 동일한 방식 - Node vm 모듈에서 실제 single.js 파일을 그대로 실행시킨다.
 *
 * 검증 대상: 점(dot) 클릭(또는 hover) 시 (1) Hero의 data-toggle-field 요소들이 선택한 매물의
 * 값으로 바뀌고, (2) 그 점만 is-active가 되고, (3) 하단 "임대 정보" 섹션의 매물 카드도 같은
 * 인덱스만 is-active가 되는지 - 이 세 가지가 서버 재쿼리 없이 미리 임베드된 JSON 데이터만으로
 * 동기화되는지.
 *
 * [listing-detail-ux-pass4] "면적 슬라이더"가 이제 페이지에 두 벌(Hero + 임대정보 섹션) 있다 -
 * 아래 fixture는 각 매물마다 점을 2개씩(heroDots/leaseDots) 만들어, 한쪽 인스턴스의 점을 눌러도
 * 반대쪽 인스턴스의 is-active까지 함께 갱신되는지("어느 쪽을 조작해도 전부 동기화") 확인한다.
 *
 * [listing-detail-ux-pass3] "floor" 값은 PHP(olt_floor_tier())가 정확한 층수 대신 고층/중층/저층으로
 * 미리 단순화해서 JSON에 담아준다(single-building.php).
 *
 * [listing-detail-ux-pass3] "마우스오버나 선택 시" 요청으로 click에 더해 mouseenter도 같은 select()를
 * 트리거한다 - 아래 hover() 테스트가 그 동작을 검증한다.
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
        hover() {
            (listeners.mouseenter || []).forEach((fn) => fn());
        },
        textContent: initialText || '',
    };
}

const listingsData = [
    {
        id: 101, floor: '3층', lease_pyeong: '363평', lease_sqm: '1,200.0㎡',
        exclusive_pyeong: '227평', exclusive_sqm: '750.4㎡',
        deposit: '150,000만원', deposit_per_lease_pyeong: '413.2만원',
        rent: '15,000만원', rent_per_lease_pyeong: '41.3만원',
        maintenance: '3,500만원', maintenance_per_lease_pyeong: '9.6만원',
    },
    {
        id: 102, floor: '5층', lease_pyeong: '280평', lease_sqm: '925.6㎡',
        exclusive_pyeong: '170평', exclusive_sqm: '562.0㎡',
        deposit: '110,000만원', deposit_per_lease_pyeong: '392.9만원',
        rent: '11,000만원', rent_per_lease_pyeong: '39.3만원',
        maintenance: '2,600만원', maintenance_per_lease_pyeong: '9.3만원',
    },
    {
        id: 103, floor: '7층', lease_pyeong: '200평', lease_sqm: '661.2㎡',
        exclusive_pyeong: '121평', exclusive_sqm: '400.0㎡',
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

    // [listing-detail-ux-pass4] Hero 슬라이더 점(heroDots)과 임대정보 슬라이더 점(leaseDots)을
    // 매물마다 하나씩, 총 두 벌 만든다 - 실제 페이지에서 olt_area_slider()가 두 군데 렌더되는 것과 동일.
    const heroDots = listingsData.map((l, i) =>
        makeElement({ 'data-listing-index': String(i) }, i === 0 ? ['is-active'] : [])
    );
    const leaseDots = listingsData.map((l, i) =>
        makeElement({ 'data-listing-index': String(i) }, i === 0 ? ['is-active'] : [])
    );
    const buttons = heroDots.concat(leaseDots);
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
            if (sel === '.olx-area-slider-dot') {
                return buttons;
            }
            if (sel === '.olx-floor-fact [data-toggle-field], .olx-price [data-toggle-field]') {
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
    // JS는 점을 누르기 전까지 아무것도 안 바꾼다 - 이 테스트는 "클릭 후" 동작만 검증한다.

    // Hero 슬라이더의 2번째 점(인덱스 1) 클릭
    heroDots[1].click();
    Object.keys(fields).forEach((f) => {
        check(`Hero 점2 클릭 - ${f} 필드가 매물2 값으로 갱신`, fields[f].textContent, listingsData[1][f]);
    });
    check('Hero 점2 클릭 - Hero 점2만 active', heroDots[1].classList.contains('is-active'), true);
    check('Hero 점2 클릭 - Hero 점1 active 해제', heroDots[0].classList.contains('is-active'), false);
    check('Hero 점2 클릭 - Hero 점3 active 해제', heroDots[2].classList.contains('is-active'), false);
    // [listing-detail-ux-pass4] Hero 쪽 점을 눌렀는데 임대정보 쪽 점도 같은 인덱스로 동기화되는지 -
    // 두 슬라이더 인스턴스가 어느 쪽을 조작해도 함께 갱신된다는 요청의 핵심 검증.
    check('Hero 점2 클릭 - 임대정보 점2도 active(양쪽 슬라이더 동기화)', leaseDots[1].classList.contains('is-active'), true);
    check('Hero 점2 클릭 - 임대정보 점1 active 해제', leaseDots[0].classList.contains('is-active'), false);
    check('Hero 점2 클릭 - 카드2만 active(하단 섹션 동기화)', cards[1].classList.contains('is-active'), true);
    check('Hero 점2 클릭 - 카드1 active 해제(하단 섹션 동기화)', cards[0].classList.contains('is-active'), false);
    check('Hero 점2 클릭 - 카드3 active 해제(하단 섹션 동기화)', cards[2].classList.contains('is-active'), false);

    // 이번엔 반대로 임대정보 슬라이더의 3번째 점(인덱스 2)을 클릭 - 값이 계속 정확히 전환되는지,
    // 이전 선택이 깔끔히 풀리는지, 그리고 Hero 쪽도 함께 따라오는지.
    leaseDots[2].click();
    check('임대정보 점3 클릭 - floor 필드가 매물3 값으로 갱신', fields.floor.textContent, listingsData[2].floor);
    check('임대정보 점3 클릭 - deposit_per_lease_pyeong 갱신', fields.deposit_per_lease_pyeong.textContent, listingsData[2].deposit_per_lease_pyeong);
    check('임대정보 점3 클릭 - 임대정보 점3만 active', leaseDots[2].classList.contains('is-active'), true);
    check('임대정보 점3 클릭 - 임대정보 점2 active 해제', leaseDots[1].classList.contains('is-active'), false);
    check('임대정보 점3 클릭 - Hero 점3도 active(반대 방향 동기화)', heroDots[2].classList.contains('is-active'), true);
    check('임대정보 점3 클릭 - Hero 점2 active 해제', heroDots[1].classList.contains('is-active'), false);
    check('임대정보 점3 클릭 - 카드3만 active', cards[2].classList.contains('is-active'), true);
    check('임대정보 점3 클릭 - 카드2 active 해제', cards[1].classList.contains('is-active'), false);

    // 1번째 점(인덱스 0)으로 되돌아가기
    heroDots[0].click();
    check('점1로 복귀 - floor 필드가 매물1 값으로 갱신', fields.floor.textContent, listingsData[0].floor);
    check('점1로 복귀 - Hero 점1만 active', heroDots[0].classList.contains('is-active'), true);
    check('점1로 복귀 - 카드1만 active', cards[0].classList.contains('is-active'), true);

    // [listing-detail-ux-pass3] hover(mouseenter)만으로도 클릭과 동일하게 전환되는지 - "마우스오버나
    // 선택 시" 요청 검증. 지금 활성 상태는 점1(위에서 복귀) - 점3에 마우스를 올린다.
    heroDots[2].hover();
    check('점3 hover - floor 필드가 매물3 값으로 갱신', fields.floor.textContent, listingsData[2].floor);
    check('점3 hover - Hero 점3만 active', heroDots[2].classList.contains('is-active'), true);
    check('점3 hover - Hero 점1 active 해제', heroDots[0].classList.contains('is-active'), false);
    check('점3 hover - 카드3만 active(하단 섹션도 hover로 동기화)', cards[2].classList.contains('is-active'), true);
}

run();

console.log(`\n${pass} passed, ${fail} failed`);
if (fail > 0) {
    process.exit(1);
}
