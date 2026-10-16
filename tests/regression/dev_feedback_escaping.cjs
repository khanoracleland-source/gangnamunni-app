/**
 * The dev feedback panel renders attacker-supplied feedback into innerHTML.
 * api_dev_feedback.php used to accept POSTs with no authentication at all
 * (and 'Access-Control-Allow-Origin: *'), so anyone could store a payload
 * that then ran in an admin's browser the next time dev mode was on.
 *
 * This pins the two escaping helpers in includes/dev_feedback_panel.php:
 * if someone drops them, or goes back to concatenating raw values, this fails.
 *
 *   node tests/regression/dev_feedback_escaping.cjs
 */
const fs = require('fs');
const path = require('path');

const panelPath = path.join(__dirname, '../../hairwang_full/gangnamunni/includes/dev_feedback_panel.php');
const panel = fs.readFileSync(panelPath, 'utf8');

let pass = 0;
const fail = [];
const t = (label, got, want) => {
    if (got === want) { console.log('PASS: ' + label); pass++; }
    else { fail.push(label + '\n  got:  ' + String(got) + '\n  want: ' + String(want)); }
};
const check = (condition, label) => t(label, condition === true, true);

// --- The raw values must never reach innerHTML unescaped ----------------
check(!/\+ fb\.content/.test(panel), 'fb.content is never concatenated raw');
check(!/\+ fb\.screenshot_url/.test(panel), 'fb.screenshot_url is never concatenated raw');
check(/esc\(body\.substring\(0,80\)\)/.test(panel), 'the feedback body is escaped before display');
check(/esc\(fb\.status\)/.test(panel), 'the status badge is escaped');
check(/safeShot\(fb\.screenshot_url\)/.test(panel), 'the screenshot path goes through safeShot()');

// --- Run the helpers exactly as the panel defines them ------------------
const start = panel.indexOf('const esc = v =>');
const end = panel.indexOf("? u : '';", start);
if (start < 0 || end < 0) {
    console.error('FAIL: could not find the esc()/safeShot() helpers in dev_feedback_panel.php');
    process.exit(1);
}
const helpers = panel.slice(start, end + "? u : '';".length);
const { esc, safeShot } = new Function(helpers + '; return { esc, safeShot };')();

t('script tag is neutralised', esc('<script>alert(1)</script>'), '&lt;script&gt;alert(1)&lt;/script&gt;');
t('img onerror payload is neutralised', esc('<img src=x onerror=alert(document.cookie)>'),
    '&lt;img src=x onerror=alert(document.cookie)&gt;');
t('double-quote attribute breakout is neutralised', esc('" onmouseover="alert(1)'), '&quot; onmouseover=&quot;alert(1)');
t('single-quote attribute breakout is neutralised', esc("' onfocus='alert(1)"), '&#39; onfocus=&#39;alert(1)');
t('ampersands are escaped first', esc('&lt;'), '&amp;lt;');
t('null renders as empty', esc(null), '');
t('undefined renders as empty', esc(undefined), '');
t('ordinary text is left alone', esc('버튼이 안 눌려요'), '버튼이 안 눌려요');

t('a server-generated screenshot path is accepted',
    safeShot('uploads/feedback/fb_20261016_091800_a1b2c3.png'), 'uploads/feedback/fb_20261016_091800_a1b2c3.png');
t('jpg screenshots are accepted', safeShot('uploads/feedback/fb_x.jpg'), 'uploads/feedback/fb_x.jpg');
t('src attribute breakout is rejected', safeShot('x.png" onerror="alert(1)'), '');
t('javascript: scheme is rejected', safeShot('javascript:alert(1)'), '');
t('data: uri is rejected', safeShot('data:text/html,<script>alert(1)</script>'), '');
t('remote url is rejected', safeShot('https://evil.example/x.png'), '');
t('path traversal is rejected', safeShot('uploads/feedback/../../../dbconfig.php'), '');
t('a different upload directory is rejected', safeShot('uploads/profile/a.png'), '');
t('a non-string is rejected', safeShot({ toString: () => 'uploads/feedback/a.png' }), '');
t('null is rejected', safeShot(null), '');

// --- The write side must be behind the admin session -------------------
const apiPath = path.join(__dirname, '../../hairwang_full/gangnamunni/api_dev_feedback.php');
const api = fs.readFileSync(apiPath, 'utf8');
// Match the header() call, not the comment that explains why it was removed.
check(!/header\(\s*['"]Access-Control-Allow-Origin/.test(api), 'the API no longer sends any CORS origin header');
check(/check_admin_login\(\)/.test(api), 'the API requires an admin session');
check(api.indexOf('mysqli_connect') < api.indexOf('check_admin_login()'),
    'the DB connection is opened before the admin check (cred_epoch lookup needs it)');

if (fail.length) {
    console.error('\n' + fail.length + ' FAILED:\n' + fail.join('\n'));
    process.exit(1);
}
console.log('\n' + pass + ' checks passed');
