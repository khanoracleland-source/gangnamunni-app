// NODE_PATH=/path/to/jsdom/node_modules node --test tests/regression/hospital_sections_dom.cjs
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { JSDOM, VirtualConsole } = require('jsdom');
const html = require('./hospital_sections_browser.cjs');

test('AJAX section saves preserve unrelated drafts, handle failures and recover without duplicate POSTs', async () => {
    const errors = [];
    const virtualConsole = new VirtualConsole();
    virtualConsole.on('jsdomError', error => errors.push(error.message));
    const dom = new JSDOM(html, {
        url: 'https://fixture.test/admin_hospitals.php?view=1&edit=1',
        runScripts: 'dangerously', virtualConsole,
        // All requests are mocked by the fixture. No real uploads or navigation occur.
        beforeParse(window) {
            window.Headers = Headers;
            // jsdom omits browser form named-property shadowing (input[name=action]).
            const action = Object.getOwnPropertyDescriptor(window.HTMLFormElement.prototype, 'action');
            Object.defineProperty(window.HTMLFormElement.prototype, 'action', {
                ...action,
                get() { return this.elements.namedItem('action') || action.get.call(this); }
            });
            window.URL.createObjectURL = () => 'blob:fixture';
            window.URL.revokeObjectURL = () => {};
            // jsdom lacks inert and DataTransfer. Keep their DOM-facing state for these tests.
            Object.defineProperty(window.HTMLElement.prototype, 'inert', {
                get() { return this.hasAttribute('inert'); },
                set(value) { this.toggleAttribute('inert', value); }
            });
            const files = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'files');
            const selections = new WeakMap();
            Object.defineProperty(window.HTMLInputElement.prototype, 'files', {
                get() { return selections.get(this) || files.get.call(this); },
                set(value) { selections.set(this, value); }
            });
            window.DataTransfer = class {
                constructor() { this.files = []; this.items = { add: file => this.files.push(file) }; }
            };
        }
    });
    try {
        await new Promise((resolve, reject) => {
            const timeout = setTimeout(() => reject(Error('DOM regression timed out')), 5000);
            const poll = setInterval(() => {
                if (/^(PASS|FAIL):/.test(dom.window.document.title)) {
                    clearInterval(poll); clearTimeout(timeout); resolve();
                }
            }, 20);
        });
        const results = dom.window.document.getElementById('results').textContent;
        assert.equal(dom.window.document.title, 'PASS: hospital section regressions', results);
        assert.deepEqual(errors, []);
        assert.equal(dom.window.document.querySelectorAll('#results li').length, 8);
        console.log(results);
    } finally { dom.window.close(); }
});
