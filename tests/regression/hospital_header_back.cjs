// Tests the exact JavaScript injected by Flutter, without a browser/referrer.
const {test} = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname,
  '../../mobile/patient_app/lib/services/hospital_list_navigation.dart'), 'utf8');
const script = source.match(/headerBackScript = '''([\s\S]*?)''';/)[1];
function fixture({buttonPresent = true, bridgePresent = true} = {}) {
  const listeners = [], messages = [];
  const button = {dataset: {}, addEventListener: (name, callback, capture) => listeners.push({name, callback, capture})};
  const context = vm.createContext({document: {getElementById: id => id === 'detailBackBtn' && buttonPresent ? button : null}, window: {}});
  if (bridgePresent) context.FlutterBridge = context.window.FlutterBridge = {postMessage: message => messages.push(JSON.parse(message))};
  vm.runInContext(script, context);
  return {listeners, messages, context};
}
test('header arrow intercepts its web handler and requests native return without a referrer', () => {
  const f = fixture();
  const calls = [];
  assert.equal(f.listeners.length, 1);
  assert.equal(f.listeners[0].name, 'click');
  assert.equal(f.listeners[0].capture, true);
  f.listeners[0].callback({preventDefault: () => calls.push('prevent'), stopImmediatePropagation: () => calls.push('stop')});
  assert.deepEqual(calls, ['prevent', 'stop']);
  assert.deepEqual(f.messages, [{action: 'hospitalListBack'}]);
});
test('repeat injection does not attach multiple back handlers', () => {
  const f = fixture();
  vm.runInContext(script, f.context);
  assert.equal(f.listeners.length, 1);
});
test('missing native channel leaves ordinary web navigation available', () => {
  const f = fixture({bridgePresent: false});
  f.listeners[0].callback({preventDefault: () => assert.fail('prevented web back'), stopImmediatePropagation: () => assert.fail('stopped web back')});
  assert.equal(f.messages.length, 0);
});
test('unrelated pages without a detail header are untouched', () => {
  assert.equal(fixture({buttonPresent: false}).listeners.length, 0);
});
