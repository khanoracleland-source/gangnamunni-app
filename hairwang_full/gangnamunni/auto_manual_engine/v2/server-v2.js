/**
 * 자동 테스트 v2 - Express API 서버
 * Port: 3101 (v1=3100과 독립)
 * 플러그인 기반 테스트 실행 + SSE 진행상황 + 이력 관리
 */

const express = require('express');
const cors = require('cors');
const fs = require('fs');
const path = require('path');
const { spawn, execFile } = require('child_process');
const os = require('os');
const { createAutoTestSecurity, safeSegment, safePath } = require('../auto-test-security');

const app = express();
const PORT = 3101;
const BIND_HOST = process.env.AUTO_TEST_BIND_HOST || '127.0.0.1';
const autoTestSecurity = createAutoTestSecurity({ scope: 'v2', cookieName: 'hms_auto_test_v2' });

app.use('/api/v2', cors(autoTestSecurity.corsOptions), autoTestSecurity.middleware, express.json({ limit: '512kb' }));

// 경로 상수
const BASE_DIR = path.resolve(__dirname);
const CONFIG_PATH = path.join(BASE_DIR, 'test-config-v2.json');
const PRESETS_PATH = path.join(BASE_DIR, 'presets.json');
const UPLOADS_DIR = path.join(BASE_DIR, '..', 'uploads', 'auto_test_v2');
const RUNNER_PATH = path.join(BASE_DIR, 'test-runner-v2.js');
const MAX_TEST_RUNTIME_MS = Math.min(
    Math.max(parseInt(process.env.AUTO_TEST_MAX_RUNTIME_MS || '900000', 10) || 900000, 60000),
    3600000
);

function loadConfig() {
    return JSON.parse(fs.readFileSync(CONFIG_PATH, 'utf-8'));
}

function publicConfig() {
    const config = loadConfig();
    return {
        ...config,
        groups: (config.groups || []).map(group => ({
            ...group,
            auth: group.auth ? {
                type: group.auth.type,
                loginUrl: group.auth.loginUrl,
                successUrl: group.auth.successUrl,
            } : undefined,
        })),
    };
}

function validRunId(value) {
    return safeSegment(value, /^\d{8}_\d{6}$/);
}

function validatedRunRequest(body) {
    const config = loadConfig();
    const tests = body?.tests;
    if (!tests || typeof tests !== 'object' || Array.isArray(tests)) throw new Error('No tests selected');
    const groupsById = new Map((config.groups || []).map(group => [group.id, group]));
    const cleanTests = {};
    let total = 0;
    for (const [groupId, items] of Object.entries(tests)) {
        const group = groupsById.get(groupId);
        if (!group || !Array.isArray(items) || items.length === 0) throw new Error('Invalid test group');
        const allowed = new Set((group.tests || []).flatMap(category =>
            (category.items || []).map(item => `${category.category}\0${item.id}`)
        ));
        const seen = new Set();
        cleanTests[groupId] = items.map(item => {
            if (!item || typeof item.id !== 'string' || typeof item.category !== 'string'
                || !allowed.has(`${item.category}\0${item.id}`)) throw new Error('Unknown test item');
            const key = `${item.category}\0${item.id}`;
            if (seen.has(key)) throw new Error('Duplicate test item');
            seen.add(key);
            return { id: item.id, category: item.category };
        });
        total += cleanTests[groupId].length;
    }
    if (total < 1 || total > 100) throw new Error('Test count must be between 1 and 100');

    const rawOptions = body?.options || {};
    const delay = Number(rawOptions.delay ?? 3);
    if (!Number.isFinite(delay) || delay < 0 || delay > 15) throw new Error('Invalid delay');
    const width = Number(rawOptions.viewport?.width ?? 1920);
    const height = Number(rawOptions.viewport?.height ?? 1080);
    if (!Number.isInteger(width) || !Number.isInteger(height) || width < 320 || width > 3840 || height < 480 || height > 2160) {
        throw new Error('Invalid viewport');
    }
    const options = {
        delay,
        viewport: { width, height },
        fullPage: rawOptions.fullPage !== false,
        compare: rawOptions.compare === true,
        generateReport: rawOptions.generateReport === true,
    };
    return { tests: cleanTests, options, total };
}

// 디렉토리 초기화
if (!fs.existsSync(UPLOADS_DIR)) fs.mkdirSync(UPLOADS_DIR, { recursive: true });
if (!fs.existsSync(PRESETS_PATH)) fs.writeFileSync(PRESETS_PATH, '[]');

// 실행 중인 프로세스 관리
const activeRuns = new Map(); // runId -> { process, clients: Set<res> }

// ══════════════════════════════════════════════════════════════
// API: 설정 관리
// ══════════════════════════════════════════════════════════════

// GET /api/v2/config - 테스트 설정 반환
app.get('/api/v2/config', (req, res) => {
    try {
        res.json(publicConfig());
    } catch (e) {
        res.status(500).json({ error: 'Config load failed: ' + e.message });
    }
});

// POST /api/v2/config/group - 그룹 추가/수정
app.post('/api/v2/config/group', (req, res) => {
    res.status(403).json({ error: 'Runtime config editing is disabled' });
});

// ══════════════════════════════════════════════════════════════
// API: 프리셋 관리
// ══════════════════════════════════════════════════════════════

app.get('/api/v2/presets', (req, res) => {
    try {
        const presets = JSON.parse(fs.readFileSync(PRESETS_PATH, 'utf-8'));
        res.json(presets);
    } catch (e) {
        res.json([]);
    }
});

app.post('/api/v2/presets', (req, res) => {
    try {
        const presets = JSON.parse(fs.readFileSync(PRESETS_PATH, 'utf-8'));
        if (!req.body || typeof req.body.name !== 'string' || req.body.name.trim().length < 1 || req.body.name.length > 80) {
            return res.status(400).json({ error: 'Invalid preset name' });
        }
        const presetName = req.body.name.trim();
        if (presets.length >= 50 && !presets.some(item => item.name === presetName)) {
            return res.status(400).json({ error: 'Preset limit reached' });
        }
        let validated;
        try { validated = validatedRunRequest({ tests: req.body.tests, options: req.body.options }); }
        catch (error) { return res.status(400).json({ error: error.message }); }
        const preset = { name: presetName, tests: validated.tests, options: validated.options };
        preset.createdAt = new Date().toISOString();
        const idx = presets.findIndex(p => p.name === preset.name);
        if (idx >= 0) presets[idx] = preset;
        else presets.push(preset);
        fs.writeFileSync(PRESETS_PATH, JSON.stringify(presets, null, 2));
        res.json({ success: true });
    } catch (e) {
        res.status(500).json({ error: e.message });
    }
});

app.delete('/api/v2/presets/:name', (req, res) => {
    try {
        if (typeof req.params.name !== 'string' || req.params.name.length > 80) {
            return res.status(400).json({ error: 'Invalid preset name' });
        }
        let presets = JSON.parse(fs.readFileSync(PRESETS_PATH, 'utf-8'));
        presets = presets.filter(p => p.name !== req.params.name);
        fs.writeFileSync(PRESETS_PATH, JSON.stringify(presets, null, 2));
        res.json({ success: true });
    } catch (e) {
        res.status(500).json({ error: e.message });
    }
});

// ══════════════════════════════════════════════════════════════
// API: 테스트 실행
// ══════════════════════════════════════════════════════════════

// POST /api/v2/run - 테스트 실행
app.post('/api/v2/run', (req, res) => {
    try {
        if ([...activeRuns.values()].some(run => !run.completed)) {
            return res.status(409).json({ error: '테스트가 이미 실행 중입니다' });
        }
        let validated;
        try { validated = validatedRunRequest(req.body); }
        catch (error) { return res.status(400).json({ error: error.message }); }
        const { tests, options, total: totalTests } = validated;

        // 실행 ID 생성 (YYYYMMDD_HHMMSS)
        const now = new Date();
        const runId = now.getFullYear().toString() +
            String(now.getMonth() + 1).padStart(2, '0') +
            String(now.getDate()).padStart(2, '0') + '_' +
            String(now.getHours()).padStart(2, '0') +
            String(now.getMinutes()).padStart(2, '0') +
            String(now.getSeconds()).padStart(2, '0');

        const outputDir = path.join(UPLOADS_DIR, runId);
        if (fs.existsSync(outputDir)) {
            return res.status(409).json({ error: 'Run ID collision; retry in one second' });
        }
        fs.mkdirSync(outputDir, { recursive: true });
        fs.mkdirSync(path.join(outputDir, 'screenshots'), { recursive: true });

        // Runner 프로세스 시작
        const args = [
            RUNNER_PATH,
            '--config', CONFIG_PATH,
            '--output', outputDir,
            '--tests', JSON.stringify(tests),
            '--options', JSON.stringify(options || {})
        ];

        const proc = spawn('node', args, {
            cwd: BASE_DIR,
            env: { ...process.env, NODE_ENV: 'production' }
        });

        const run = { process: proc, clients: new Set(), totalTests, outputDir, runId, completed: false, finalized: false };
        activeRuns.set(runId, run);
        const runtimeTimer = setTimeout(() => {
            if (!run.completed) {
                broadcastSSE(runId, 'progress', { message: '[ERROR] Maximum test runtime exceeded', status: 'FAIL' });
                proc.kill('SIGTERM');
                setTimeout(() => {
                    if (!run.completed) proc.kill('SIGKILL');
                }, 5000).unref();
            }
        }, MAX_TEST_RUNTIME_MS);

        // stdout -> SSE로 브로드캐스트
        proc.stdout.on('data', (data) => {
            const lines = data.toString().split('\n').filter(l => l.trim());
            lines.forEach(line => {
                try {
                    const msg = JSON.parse(line);
                    broadcastSSE(runId, 'progress', msg);
                } catch (e) {
                    // 일반 텍스트 로그
                    broadcastSSE(runId, 'progress', { message: line.trim() });
                }
            });
        });

        proc.stderr.on('data', (data) => {
            broadcastSSE(runId, 'progress', { message: `[ERROR] ${data.toString().trim().slice(0, 10000)}`, status: 'FAIL' });
        });

        proc.on('close', (code) => {
            if (run.finalized) return;
            run.finalized = true;
            clearTimeout(runtimeTimer);
            run.completed = true;
            // 결과 파일 읽기
            const resultsPath = path.join(outputDir, 'results.json');
            let resultData = {};
            if (fs.existsSync(resultsPath)) {
                try { resultData = JSON.parse(fs.readFileSync(resultsPath, 'utf-8')); } catch (e) {}
            }

            broadcastSSE(runId, 'complete', { dir: runId, code, ...resultData });

            // 클라이언트 연결 정리
            setTimeout(() => {
                const r = activeRuns.get(runId);
                if (r) {
                    r.clients.forEach(c => c.end());
                    activeRuns.delete(runId);
                }
            }, 5000);
        });

        proc.on('error', (error) => {
            if (run.finalized) return;
            run.finalized = true;
            clearTimeout(runtimeTimer);
            run.completed = true;
            broadcastSSE(runId, 'complete', { dir: runId, code: -1, error: error.message });
            activeRuns.delete(runId);
        });

        res.json({ runId, totalTests, outputDir: runId });

    } catch (e) {
        res.status(500).json({ error: e.message });
    }
});

// GET /api/v2/progress/:runId - SSE 스트림
app.get('/api/v2/progress/:runId', (req, res) => {
    const runId = req.params.runId;
    if (!validRunId(runId)) return res.status(400).json({ error: 'Invalid run ID' });
    const run = activeRuns.get(runId);

    if (run && run.clients.size >= 5) return res.status(429).json({ error: 'Too many progress streams' });

    res.writeHead(200, {
        'Content-Type': 'text/event-stream',
        'Cache-Control': 'no-cache',
        'Connection': 'keep-alive',
        'X-Accel-Buffering': 'no'
    });

    if (!run) {
        res.write(`event: complete\ndata: ${JSON.stringify({ dir: runId, code: -1, message: 'Run not found' })}\n\n`);
        return res.end();
    }

    run.clients.add(res);
    req.on('close', () => run.clients.delete(res));

    // 연결 유지 핑
    const ping = setInterval(() => res.write(':ping\n\n'), 15000);
    req.on('close', () => clearInterval(ping));
});

// POST /api/v2/stop/:runId - 실행 중단
app.post('/api/v2/stop/:runId', (req, res) => {
    if (!validRunId(req.params.runId)) return res.status(400).json({ error: 'Invalid run ID' });
    const run = activeRuns.get(req.params.runId);
    if (run && run.process && !run.completed) {
        run.process.kill('SIGTERM');
        res.json({ success: true });
    } else {
        res.status(404).json({ error: 'Run not found' });
    }
});

function broadcastSSE(runId, event, data) {
    const run = activeRuns.get(runId);
    if (!run) return;
    const msg = `event: ${event}\ndata: ${JSON.stringify(data)}\n\n`;
    run.clients.forEach(client => {
        try { client.write(msg); } catch (e) {}
    });
}

// ══════════════════════════════════════════════════════════════
// API: 결과 관리
// ══════════════════════════════════════════════════════════════

// GET /api/v2/history - 실행 이력
app.get('/api/v2/history', (req, res) => {
    try {
        if (!fs.existsSync(UPLOADS_DIR)) return res.json({ runs: [] });
        const dirs = fs.readdirSync(UPLOADS_DIR)
            .filter(d => validRunId(d) && fs.statSync(safePath(UPLOADS_DIR, d)).isDirectory())
            .sort()
            .reverse()
            .slice(0, 50);

        const runs = dirs.map(dir => {
            const resultsPath = path.join(UPLOADS_DIR, dir, 'results.json');
            let summary = {};
            if (fs.existsSync(resultsPath)) {
                try {
                    const data = JSON.parse(fs.readFileSync(resultsPath, 'utf-8'));
                    summary = data.summary || {};
                } catch (e) {}
            }
            return { dir, summary };
        });

        res.json({ runs });
    } catch (e) {
        res.status(500).json({ error: e.message });
    }
});

// GET /api/v2/results/:dir - 상세 결과
app.get('/api/v2/results/:dir', (req, res) => {
    try {
        if (!validRunId(req.params.dir)) return res.status(400).json({ error: 'Invalid path' });
        const resultsPath = safePath(UPLOADS_DIR, req.params.dir, 'results.json');
        if (!fs.existsSync(resultsPath)) return res.status(404).json({ error: 'Results not found' });
        const data = JSON.parse(fs.readFileSync(resultsPath, 'utf-8'));
        res.json(data);
    } catch (e) {
        res.status(500).json({ error: e.message });
    }
});

// GET /api/v2/screenshot/:dir/:file - 스크린샷 이미지
app.get('/api/v2/screenshot/:dir/*', (req, res) => {
    if (!validRunId(req.params.dir) || !safeSegment(req.params[0], /^[a-zA-Z0-9_.-]+$/)) {
        return res.status(400).json({ error: 'Invalid path' });
    }
    const filePath = safePath(UPLOADS_DIR, req.params.dir, 'screenshots', req.params[0]);
    if (fs.existsSync(filePath)) {
        res.sendFile(filePath);
    } else {
        res.status(404).send('Not found');
    }
});

// GET /api/v2/download/:dir - 리포트/JSON/ZIP 다운로드
app.get('/api/v2/download/:dir', (req, res) => {
    if (!validRunId(req.params.dir)) return res.status(400).json({ error: 'Invalid path' });
    const dir = safePath(UPLOADS_DIR, req.params.dir);
    const type = req.query.type || 'json';
    if (!['json', 'docx', 'zip'].includes(type)) return res.status(400).json({ error: 'Invalid download type' });

    if (type === 'json') {
        const fp = path.join(dir, 'results.json');
        if (fs.existsSync(fp)) return res.download(fp);
    } else if (type === 'docx') {
        const fp = path.join(dir, 'report.docx');
        if (fs.existsSync(fp)) return res.download(fp);
    } else if (type === 'zip') {
        // [Track A4 - 2026-06-12] 결과 폴더 전체 ZIP 다운로드 (시스템 zip 사용)
        if (!fs.existsSync(dir)) return res.status(404).json({ error: 'Run not found' });
        const safeName = String(req.params.dir).replace(/[^a-zA-Z0-9_\-]/g, '_');
        const zipPath = path.join(os.tmpdir(), `autotest_${safeName}_${Date.now()}.zip`);
        return execFile('zip', ['-rq', zipPath, '.'], { cwd: dir }, (err) => {
            if (err) return res.status(500).json({ error: 'ZIP 생성 실패: ' + err.message });
            res.download(zipPath, `${safeName}.zip`, () => {
                fs.unlink(zipPath, () => {});
            });
        });
    }

    res.status(404).json({ error: 'File not found' });
});

// DELETE /api/v2/delete/:dir - 결과 삭제
app.delete('/api/v2/delete/:dir', (req, res) => {
    try {
        if (!validRunId(req.params.dir)) return res.status(400).json({ error: 'Invalid path' });
        const active = activeRuns.get(req.params.dir);
        if (active && !active.completed) return res.status(409).json({ error: 'Cannot delete an active run' });
        const dir = safePath(UPLOADS_DIR, req.params.dir);
        if (fs.existsSync(dir)) {
            fs.rmSync(dir, { recursive: true, force: true });
        }
        res.json({ success: true });
    } catch (e) {
        res.status(500).json({ error: e.message });
    }
});

// GET /api/v2/compare/:dir1/:dir2 - 결과 비교
app.get('/api/v2/compare/:dir1/:dir2', (req, res) => {
    try {
        if (!validRunId(req.params.dir1) || !validRunId(req.params.dir2)) {
            return res.status(400).json({ error: 'Invalid path' });
        }
        const load = (dir) => {
            const fp = safePath(UPLOADS_DIR, dir, 'results.json');
            return fs.existsSync(fp) ? JSON.parse(fs.readFileSync(fp, 'utf-8')) : null;
        };
        const r1 = load(req.params.dir1);
        const r2 = load(req.params.dir2);
        if (!r1 || !r2) return res.status(404).json({ error: 'Results not found' });

        // 비교 로직
        const status1 = {}, status2 = {};
        (r1.groups || []).forEach(g => g.results.forEach(r => status1[r.id] = r.status));
        (r2.groups || []).forEach(g => g.results.forEach(r => status2[r.id] = r.status));

        let newErrors = 0, resolved = 0, unchanged = 0;
        const allIds = new Set([...Object.keys(status1), ...Object.keys(status2)]);
        allIds.forEach(id => {
            const s1 = status1[id] || 'N/A';
            const s2 = status2[id] || 'N/A';
            if (s1 === 'PASS' && s2 !== 'PASS') newErrors++;
            else if (s1 !== 'PASS' && s2 === 'PASS') resolved++;
            else unchanged++;
        });

        res.json({ newErrors, resolved, unchanged });
    } catch (e) {
        res.status(500).json({ error: e.message });
    }
});

// GET /api/v2/plugins - 사용 가능 플러그인 목록
app.get('/api/v2/plugins', (req, res) => {
    try {
        const pluginDir = path.join(BASE_DIR, 'plugins');
        const plugins = fs.readdirSync(pluginDir)
            .filter(f => f.endsWith('.js'))
            .map(f => f.replace('.js', ''));
        res.json(plugins);
    } catch (e) {
        res.json([]);
    }
});

// GET /api/v2/status - 서버 상태
app.get('/api/v2/status', (req, res) => {
    res.json({
        status: 'running',
        version: '2.0',
        port: PORT,
        activeRuns: activeRuns.size,
        uptime: process.uptime()
    });
});

// ══════════════════════════════════════════════════════════════
// 서버 시작
// ══════════════════════════════════════════════════════════════
app.listen(PORT, BIND_HOST, () => {
    console.log(`[Auto Test v2] Server running on ${BIND_HOST}:${PORT}`);
    console.log(`[Auto Test v2] Config: ${CONFIG_PATH}`);
    console.log(`[Auto Test v2] Uploads: ${UPLOADS_DIR}`);
});
