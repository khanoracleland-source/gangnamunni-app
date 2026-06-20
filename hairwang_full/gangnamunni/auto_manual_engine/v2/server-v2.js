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

const app = express();
const PORT = 3101;

app.use(cors());
app.use(express.json({ limit: '10mb' }));

// 경로 상수
const BASE_DIR = path.resolve(__dirname);
const CONFIG_PATH = path.join(BASE_DIR, 'test-config-v2.json');
const PRESETS_PATH = path.join(BASE_DIR, 'presets.json');
const UPLOADS_DIR = path.join(BASE_DIR, '..', 'uploads', 'auto_test_v2');
const RUNNER_PATH = path.join(BASE_DIR, 'test-runner-v2.js');

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
        const config = JSON.parse(fs.readFileSync(CONFIG_PATH, 'utf-8'));
        res.json(config);
    } catch (e) {
        res.status(500).json({ error: 'Config load failed: ' + e.message });
    }
});

// POST /api/v2/config/group - 그룹 추가/수정
app.post('/api/v2/config/group', (req, res) => {
    try {
        const config = JSON.parse(fs.readFileSync(CONFIG_PATH, 'utf-8'));
        const group = req.body;
        const idx = config.groups.findIndex(g => g.id === group.id);
        if (idx >= 0) config.groups[idx] = group;
        else config.groups.push(group);
        fs.writeFileSync(CONFIG_PATH, JSON.stringify(config, null, 2));
        res.json({ success: true });
    } catch (e) {
        res.status(500).json({ error: e.message });
    }
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
        const preset = req.body;
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
        const { tests, options } = req.body;
        if (!tests || Object.keys(tests).length === 0) {
            return res.status(400).json({ error: 'No tests selected' });
        }

        // 총 테스트 수 계산
        let totalTests = 0;
        Object.values(tests).forEach(items => totalTests += items.length);

        // 실행 ID 생성 (YYYYMMDD_HHMMSS)
        const now = new Date();
        const runId = now.getFullYear().toString() +
            String(now.getMonth() + 1).padStart(2, '0') +
            String(now.getDate()).padStart(2, '0') + '_' +
            String(now.getHours()).padStart(2, '0') +
            String(now.getMinutes()).padStart(2, '0') +
            String(now.getSeconds()).padStart(2, '0');

        const outputDir = path.join(UPLOADS_DIR, runId);
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

        const run = { process: proc, clients: new Set(), totalTests, outputDir, runId };
        activeRuns.set(runId, run);

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
            broadcastSSE(runId, 'progress', { message: `[ERROR] ${data.toString().trim()}`, status: 'FAIL' });
        });

        proc.on('close', (code) => {
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

        res.json({ runId, totalTests, outputDir: runId });

    } catch (e) {
        res.status(500).json({ error: e.message });
    }
});

// GET /api/v2/progress/:runId - SSE 스트림
app.get('/api/v2/progress/:runId', (req, res) => {
    const runId = req.params.runId;
    const run = activeRuns.get(runId);

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
    const run = activeRuns.get(req.params.runId);
    if (run && run.process) {
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
            .filter(d => fs.statSync(path.join(UPLOADS_DIR, d)).isDirectory())
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
        const resultsPath = path.join(UPLOADS_DIR, req.params.dir, 'results.json');
        if (!fs.existsSync(resultsPath)) return res.status(404).json({ error: 'Results not found' });
        const data = JSON.parse(fs.readFileSync(resultsPath, 'utf-8'));
        res.json(data);
    } catch (e) {
        res.status(500).json({ error: e.message });
    }
});

// GET /api/v2/screenshot/:dir/:file - 스크린샷 이미지
app.get('/api/v2/screenshot/:dir/*', (req, res) => {
    const filePath = path.join(UPLOADS_DIR, req.params.dir, 'screenshots', req.params[0]);
    if (fs.existsSync(filePath)) {
        res.sendFile(filePath);
    } else {
        res.status(404).send('Not found');
    }
});

// GET /api/v2/download/:dir - 리포트/JSON/ZIP 다운로드
app.get('/api/v2/download/:dir', (req, res) => {
    const dir = path.join(UPLOADS_DIR, req.params.dir);
    const type = req.query.type || 'json';

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
        const dir = path.join(UPLOADS_DIR, req.params.dir);
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
        const load = (dir) => {
            const fp = path.join(UPLOADS_DIR, dir, 'results.json');
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
app.listen(PORT, '0.0.0.0', () => {
    console.log(`[Auto Test v2] Server running on port ${PORT}`);
    console.log(`[Auto Test v2] Config: ${CONFIG_PATH}`);
    console.log(`[Auto Test v2] Uploads: ${UPLOADS_DIR}`);
});
