// Each test file gets its own temp folder, resolved to its real path (macOS keeps /var -> /private/var).
// Upload handlers (multer `dest: tmpdir()`) and the specs that assert "no temp file left behind" both call
// os.tmpdir(), so suites running in parallel workers no longer see each other's in-flight upload files.
// A spec that sets its own process.env.TMPDIR before importing the upload module keeps that folder: the
// sandboxed process.env does not reach the real os module, so it is read here instead.
// Assigned directly (not jest.spyOn) because the specs call jest.restoreAllMocks() after every test.
const fs = require('fs');
const os = require('os');
const path = require('path');

const original = os.tmpdir;
const dir = fs.realpathSync(fs.mkdtempSync(path.join(original.call(os), 'sb-admin-jest-')));
delete process.env.TMPDIR;
os.tmpdir = () => process.env.TMPDIR || dir;

afterAll(() => {
  os.tmpdir = original;
  fs.rmSync(dir, { recursive: true, force: true });
});
