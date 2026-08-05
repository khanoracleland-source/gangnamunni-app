/**
 * 자동 매뉴얼 캡처 엔진
 * Express + Playwright + SSE + docx 내보내기
 */

const express = require('express');
const cors = require('cors');
const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');
const archiver = require('archiver');
const { Document, Packer, Paragraph, TextRun, ImageRun, HeadingLevel, AlignmentType, PageBreak } = require('docx');
const { createAutoTestSecurity, safeSegment, safePath } = require('./auto-test-security');

const app = express();
const PORT = 3100;
const BIND_HOST = process.env.AUTO_TEST_BIND_HOST || '127.0.0.1';
const autoTestSecurity = createAutoTestSecurity({ scope: 'v1', cookieName: 'hms_auto_test_v1' });

// 업로드 디렉토리
const UPLOAD_DIR = path.join(__dirname, '..', 'uploads', 'auto_manual');
if (!fs.existsSync(UPLOAD_DIR)) fs.mkdirSync(UPLOAD_DIR, { recursive: true });

const legacyCors = cors();
app.use((req, res, next) => req.path.startsWith('/api/auto-test') ? next() : legacyCors(req, res, next));
app.use('/api/auto-test', cors(autoTestSecurity.corsOptions), autoTestSecurity.middleware, express.json({ limit: '512kb' }));
// 자동 매뉴얼의 base64 이미지 업로드는 기존 한도를 유지한다.
app.use(express.json({ limit: '50mb' }));
app.use('/screenshots', express.static(UPLOAD_DIR));

// === 세션 관리 ===
const sessions = new Map();
const sseClients = new Map();

// === 헬스 체크 ===
app.get('/api/health', (req, res) => {
    res.json({ status: 'ok', uptime: process.uptime() });
});

// === 캡처 시작 ===
app.post('/api/start', async (req, res) => {
    const {
        targetUrl, menuSelector, contentSelector,
        captureDelay = 2000, fullPage = false,
        viewport = { width: 1920, height: 1080 },
        loginRequired = false, loginUrl, loginCredentials
    } = req.body;

    if (!targetUrl) return res.status(400).json({ error: 'targetUrl is required' });

    const sessionId = Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
    const sessionDir = path.join(UPLOAD_DIR, sessionId);
    fs.mkdirSync(sessionDir, { recursive: true });

    const session = {
        id: sessionId,
        targetUrl, menuSelector, contentSelector,
        captureDelay, fullPage, viewport,
        loginRequired, loginUrl, loginCredentials,
        status: 'running',
        captures: [],
        total: 0,
        current: 0,
        dir: sessionDir,
        aborted: false,
        browser: null
    };
    sessions.set(sessionId, session);

    res.json({ sessionId, status: 'started' });

    // 비동기로 캡처 시작
    runCapture(session).catch(err => {
        console.error('Capture error:', err);
        sendSSE(sessionId, 'log', { level: 'error', message: err.message });
    });
});

// === SSE 진행 상황 스트림 ===
app.get('/api/progress/:sessionId', (req, res) => {
    const { sessionId } = req.params;
    const session = sessions.get(sessionId);
    if (!session) return res.status(404).json({ error: 'Session not found' });

    res.writeHead(200, {
        'Content-Type': 'text/event-stream',
        'Cache-Control': 'no-cache',
        'Connection': 'keep-alive',
        'X-Accel-Buffering': 'no'
    });

    const clientId = Date.now().toString();
    if (!sseClients.has(sessionId)) sseClients.set(sessionId, new Map());
    sseClients.get(sessionId).set(clientId, res);

    // 기존 캡처 결과 전송
    session.captures.forEach(cap => {
        res.write(`event: capture\ndata: ${JSON.stringify(cap)}\n\n`);
    });

    req.on('close', () => {
        const clients = sseClients.get(sessionId);
        if (clients) clients.delete(clientId);
    });
});

// === 캡처 중지 ===
app.post('/api/stop/:sessionId', (req, res) => {
    const session = sessions.get(req.params.sessionId);
    if (!session) return res.status(404).json({ error: 'Session not found' });
    session.aborted = true;
    session.status = 'stopped';
    res.json({ status: 'stopping' });
});

// === 어노테이션 저장 ===
app.post('/api/annotation', async (req, res) => {
    const { sessionId, captureIndex, annotatedImage, comment } = req.body;
    const session = sessions.get(sessionId);
    if (!session) return res.status(404).json({ error: 'Session not found' });

    const capture = session.captures[captureIndex];
    if (!capture) return res.status(404).json({ error: 'Capture not found' });

    capture.comment = comment || '';

    if (annotatedImage) {
        // base64 이미지 저장
        const base64Data = annotatedImage.replace(/^data:image\/\w+;base64,/, '');
        const filename = `annotated_${captureIndex}_${Date.now()}.png`;
        const filePath = path.join(session.dir, filename);
        fs.writeFileSync(filePath, Buffer.from(base64Data, 'base64'));
        capture.annotatedPath = filePath;
        capture.annotatedUrl = `/screenshots/${sessionId}/${filename}`;
    }

    res.json({ success: true, annotatedUrl: capture.annotatedUrl || null });
});

// === 재캡처 ===
app.post('/api/recapture', async (req, res) => {
    const { sessionId, captureIndex, menuUrl } = req.body;
    const session = sessions.get(sessionId);
    if (!session) return res.status(404).json({ error: 'Session not found' });

    let browser;
    try {
        browser = await chromium.launch({ headless: true });
        const context = await browser.newContext({ viewport: session.viewport });
        const page = await context.newPage();

        await page.goto(menuUrl, { waitUntil: 'networkidle', timeout: 30000 });
        await page.waitForTimeout(session.captureDelay);

        const filename = `recapture_${captureIndex}_${Date.now()}.png`;
        const filePath = path.join(session.dir, filename);

        if (session.contentSelector) {
            const el = await page.$(session.contentSelector);
            if (el) {
                await el.screenshot({ path: filePath });
            } else {
                await page.screenshot({ path: filePath, fullPage: session.fullPage });
            }
        } else {
            await page.screenshot({ path: filePath, fullPage: session.fullPage });
        }

        const screenshotUrl = `/screenshots/${sessionId}/${filename}`;
        if (session.captures[captureIndex]) {
            session.captures[captureIndex].screenshotPath = filePath;
            session.captures[captureIndex].screenshotUrl = screenshotUrl;
            session.captures[captureIndex].status = 'success';
            session.captures[captureIndex].errorMessage = null;
        }

        res.json({ success: true, screenshotUrl });
    } catch (e) {
        res.status(500).json({ error: e.message });
    } finally {
        if (browser) await browser.close();
    }
});

// === 내보내기 ===
app.post('/api/export/:sessionId', async (req, res) => {
    const { sessionId } = req.params;
    const format = req.query.format || 'docx';
    const session = sessions.get(sessionId);
    const clientCaptures = req.body.captures || [];

    // 세션 또는 클라이언트 데이터 사용
    const captureData = clientCaptures.length > 0 ? clientCaptures : (session ? session.captures : []);
    if (captureData.length === 0) return res.status(400).json({ error: 'No captures to export' });

    try {
        if (format === 'docx') {
            const buffer = await generateDocx(captureData, session);
            res.setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            res.setHeader('Content-Disposition', `attachment; filename=auto-manual-${sessionId}.docx`);
            res.send(buffer);
        } else if (format === 'pdf') {
            // PDF: Playwright로 HTML → PDF 변환
            const buffer = await generatePdf(captureData, session);
            res.setHeader('Content-Type', 'application/pdf');
            res.setHeader('Content-Disposition', `attachment; filename=auto-manual-${sessionId}.pdf`);
            res.send(buffer);
        } else {
            res.status(400).json({ error: 'Unsupported format' });
        }
    } catch (e) {
        console.error('Export error:', e);
        res.status(500).json({ error: e.message });
    }
});

// ZIP 내보내기
app.get('/api/export/:sessionId', (req, res) => {
    const { sessionId } = req.params;
    const format = req.query.format;
    const session = sessions.get(sessionId);

    if (format === 'zip' && session) {
        res.setHeader('Content-Type', 'application/zip');
        res.setHeader('Content-Disposition', `attachment; filename=auto-manual-${sessionId}.zip`);

        const archive = archiver('zip', { zlib: { level: 9 } });
        archive.pipe(res);

        session.captures.forEach((cap, idx) => {
            if (cap && cap.screenshotPath && fs.existsSync(cap.screenshotPath)) {
                archive.file(cap.screenshotPath, { name: `${String(idx + 1).padStart(2, '0')}_${sanitizeFilename(cap.menuName || 'unknown')}.png` });
            }
            if (cap && cap.annotatedPath && fs.existsSync(cap.annotatedPath)) {
                archive.file(cap.annotatedPath, { name: `${String(idx + 1).padStart(2, '0')}_${sanitizeFilename(cap.menuName || 'unknown')}_annotated.png` });
            }
        });

        archive.finalize();
    } else {
        res.status(400).json({ error: 'Invalid request' });
    }
});

// === Playwright 캡처 메인 로직 ===
async function runCapture(session) {
    let browser;
    try {
        sendSSE(session.id, 'log', { level: 'info', message: '브라우저 시작 중...' });
        browser = await chromium.launch({ headless: true });
        session.browser = browser;

        const context = await browser.newContext({
            viewport: session.viewport,
            locale: 'ko-KR'
        });
        const page = await context.newPage();

        // 로그인이 필요한 경우
        if (session.loginRequired && session.loginUrl && session.loginCredentials) {
            sendSSE(session.id, 'log', { level: 'info', message: '로그인 중...' });
            await performLogin(page, session);
        }

        // 대상 페이지 접속
        sendSSE(session.id, 'log', { level: 'info', message: '대상 페이지 접속: ' + session.targetUrl });
        await page.goto(session.targetUrl, { waitUntil: 'networkidle', timeout: 30000 });
        await page.waitForTimeout(1000);

        // 메뉴 항목 수집
        const menuItems = await collectMenuItems(page, session.menuSelector);
        session.total = menuItems.length;
        sendSSE(session.id, 'log', { level: 'info', message: `메뉴 ${menuItems.length}개 발견` });

        if (menuItems.length === 0) {
            sendSSE(session.id, 'log', { level: 'error', message: '메뉴를 찾을 수 없습니다. 셀렉터를 확인해주세요: ' + session.menuSelector });
            session.status = 'completed';
            sendSSE(session.id, 'complete', { success: 0, failed: 0, total: 0 });
            return;
        }

        let successCount = 0;
        let failCount = 0;

        // 각 메뉴 순회하며 캡처
        for (let i = 0; i < menuItems.length; i++) {
            if (session.aborted) {
                sendSSE(session.id, 'log', { level: 'info', message: '사용자 중지' });
                break;
            }

            const menu = menuItems[i];
            session.current = i + 1;
            sendSSE(session.id, 'progress', { current: i + 1, total: menuItems.length, menuName: menu.text });

            const captureResult = await captureMenuItem(page, context, menu, i, session);
            session.captures.push(captureResult);

            if (captureResult.status === 'success') {
                successCount++;
                sendSSE(session.id, 'capture', captureResult);
            } else {
                failCount++;
                sendSSE(session.id, 'error_capture', captureResult);
                // 실패해도 카드에 표시
                sendSSE(session.id, 'capture', captureResult);
            }
        }

        session.status = 'completed';
        sendSSE(session.id, 'complete', { success: successCount, failed: failCount, total: menuItems.length });

    } catch (err) {
        console.error('runCapture error:', err);
        sendSSE(session.id, 'log', { level: 'error', message: '치명적 오류: ' + err.message });
        session.status = 'error';
    } finally {
        if (browser) {
            await browser.close().catch(() => {});
            session.browser = null;
        }
    }
}

// 로그인 수행
async function performLogin(page, session) {
    try {
        await page.goto(session.loginUrl, { waitUntil: 'networkidle', timeout: 15000 });

        // 일반적인 로그인 폼 감지 및 입력
        const usernameSelectors = ['input[name="username"]', 'input[name="email"]', 'input[name="user"]', 'input[type="email"]', '#username', '#email'];
        const passwordSelectors = ['input[name="password"]', 'input[type="password"]', '#password'];

        let usernameField = null;
        for (const sel of usernameSelectors) {
            usernameField = await page.$(sel);
            if (usernameField) break;
        }

        let passwordField = null;
        for (const sel of passwordSelectors) {
            passwordField = await page.$(sel);
            if (passwordField) break;
        }

        if (usernameField && passwordField) {
            await usernameField.fill(session.loginCredentials.username);
            await passwordField.fill(session.loginCredentials.password);

            // 제출 버튼 찾기
            const submitSelectors = ['button[type="submit"]', 'input[type="submit"]', 'button:has-text("로그인")', 'button:has-text("Login")'];
            for (const sel of submitSelectors) {
                const btn = await page.$(sel);
                if (btn) {
                    await btn.click();
                    break;
                }
            }

            await page.waitForNavigation({ waitUntil: 'networkidle', timeout: 10000 }).catch(() => {});
            sendSSE(session.id, 'log', { level: 'success', message: '로그인 완료' });
        } else {
            sendSSE(session.id, 'log', { level: 'error', message: '로그인 폼을 찾을 수 없습니다' });
        }
    } catch (e) {
        sendSSE(session.id, 'log', { level: 'error', message: '로그인 실패: ' + e.message });
    }
}

// 메뉴 항목 수집
async function collectMenuItems(page, selector) {
    const items = await page.evaluate((sel) => {
        // 여러 셀렉터 시도
        const selectors = sel.split(',').map(s => s.trim());
        let elements = [];

        for (const s of selectors) {
            const found = document.querySelectorAll(s);
            if (found.length > 0) {
                elements = Array.from(found);
                break;
            }
        }

        return elements
            .filter(el => {
                const href = el.getAttribute('href');
                // 빈 링크, #으로 시작, javascript:, 로그아웃 등 제외
                if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.includes('logout')) return false;
                // 외부 링크 제외 (같은 도메인만)
                if (href.startsWith('http') && !href.includes(location.hostname)) return false;
                return true;
            })
            .map(el => ({
                text: el.textContent.trim().replace(/\s+/g, ' '),
                href: el.getAttribute('href'),
                fullUrl: el.href
            }));
    }, selector);

    return items;
}

// 단일 메뉴 캡처
async function captureMenuItem(page, context, menu, index, session) {
    const timestamp = new Date().toLocaleString('ko-KR');
    const result = {
        index: index + 1,
        total: session.total,
        menuName: menu.text,
        menuUrl: menu.fullUrl,
        capturedAt: timestamp,
        status: 'failed',
        errorMessage: null,
        screenshotPath: null,
        screenshotUrl: null,
        annotatedPath: null,
        annotatedUrl: null,
        comment: ''
    };

    // 최대 3회 재시도
    for (let attempt = 1; attempt <= 3; attempt++) {
        try {
            const currentPages = context.pages();
            const pageCountBefore = currentPages.length;

            // 메뉴 클릭 시도
            let navigated = false;
            try {
                // 직접 URL 이동 (가장 안정적)
                await page.goto(menu.fullUrl, { waitUntil: 'networkidle', timeout: 15000 });
                navigated = true;
            } catch (navErr) {
                // 타임아웃이면 그냥 진행 (일부 컨텐트는 로드되었을 수 있음)
                if (navErr.message.includes('Timeout')) {
                    sendSSE(session.id, 'log', { level: 'info', message: `${menu.text}: 로딩 타임아웃, 현재 상태로 캡처 시도` });
                    navigated = true;
                } else {
                    throw navErr;
                }
            }

            // 새 탭이 열렸는지 확인
            const pagesAfter = context.pages();
            let capturePage = page;
            if (pagesAfter.length > pageCountBefore) {
                capturePage = pagesAfter[pagesAfter.length - 1];
                await capturePage.waitForLoadState('networkidle').catch(() => {});
                sendSSE(session.id, 'log', { level: 'info', message: `${menu.text}: 새 탭 감지, 전환됨` });
            }

            // 대기
            await capturePage.waitForTimeout(session.captureDelay);

            // 스크린샷 캡처
            const filename = `${String(index + 1).padStart(3, '0')}_${sanitizeFilename(menu.text)}_${Date.now()}.png`;
            const filePath = path.join(session.dir, filename);

            if (session.contentSelector && !session.fullPage) {
                // 컨텐트 영역만 캡처 시도
                const contentEl = await capturePage.$(session.contentSelector);
                if (contentEl) {
                    await contentEl.screenshot({ path: filePath });
                } else {
                    // 폴백: 전체 페이지
                    sendSSE(session.id, 'log', { level: 'info', message: `${menu.text}: 컨텐트 셀렉터 없음, 전체 페이지 캡처` });
                    await capturePage.screenshot({ path: filePath, fullPage: true });
                }
            } else {
                await capturePage.screenshot({ path: filePath, fullPage: session.fullPage });
            }

            // 새 탭 닫기
            if (capturePage !== page && pagesAfter.length > pageCountBefore) {
                await capturePage.close().catch(() => {});
            }

            result.status = 'success';
            result.screenshotPath = filePath;
            result.screenshotUrl = `/screenshots/${session.id}/${filename}`;
            break; // 성공하면 루프 종료

        } catch (err) {
            result.errorMessage = `시도 ${attempt}/3: ${err.message}`;
            sendSSE(session.id, 'log', { level: 'error', message: `${menu.text} 시도 ${attempt}/3 실패: ${err.message}` });

            if (attempt < 3) {
                // 재시도 전 대기
                await page.waitForTimeout(1000 * attempt);
            }
        }
    }

    return result;
}

// === 워드 문서 생성 ===
async function generateDocx(captureData, session) {
    const children = [];

    // 표지
    children.push(
        new Paragraph({ text: '', spacing: { before: 4000 } }),
        new Paragraph({
            children: [new TextRun({ text: '자동 매뉴얼', bold: true, size: 52, font: 'Malgun Gothic' })],
            alignment: AlignmentType.CENTER
        }),
        new Paragraph({
            children: [new TextRun({ text: session ? session.targetUrl : '', size: 20, color: '666666', font: 'Malgun Gothic' })],
            alignment: AlignmentType.CENTER,
            spacing: { before: 400 }
        }),
        new Paragraph({
            children: [new TextRun({ text: `생성일: ${new Date().toLocaleString('ko-KR')}`, size: 20, color: '999999', font: 'Malgun Gothic' })],
            alignment: AlignmentType.CENTER,
            spacing: { before: 200 }
        }),
        new Paragraph({
            children: [new TextRun({ text: `총 ${captureData.filter(c => c).length}개 페이지`, size: 20, color: '999999', font: 'Malgun Gothic' })],
            alignment: AlignmentType.CENTER,
            spacing: { before: 100 }
        }),
        new Paragraph({ children: [new PageBreak()] })
    );

    // 목차
    children.push(
        new Paragraph({ text: '목차', heading: HeadingLevel.HEADING_1, spacing: { after: 300 } })
    );
    captureData.forEach((cap, idx) => {
        if (!cap) return;
        children.push(
            new Paragraph({
                children: [new TextRun({ text: `${idx + 1}. ${cap.menuName || 'Unknown'}`, size: 22, font: 'Malgun Gothic' })],
                spacing: { before: 100 }
            })
        );
    });
    children.push(new Paragraph({ children: [new PageBreak()] }));

    // 각 캡처 섹션
    for (let idx = 0; idx < captureData.length; idx++) {
        const cap = captureData[idx];
        if (!cap) continue;

        children.push(
            new Paragraph({
                text: `${idx + 1}. ${cap.menuName || 'Unknown'}`,
                heading: HeadingLevel.HEADING_2,
                spacing: { before: 400, after: 200 }
            })
        );

        // URL
        children.push(
            new Paragraph({
                children: [
                    new TextRun({ text: 'URL: ', bold: true, size: 18, font: 'Malgun Gothic' }),
                    new TextRun({ text: cap.menuUrl || '', size: 18, color: '0066CC', font: 'Malgun Gothic' })
                ],
                spacing: { after: 100 }
            })
        );

        // 상태
        children.push(
            new Paragraph({
                children: [
                    new TextRun({ text: '상태: ', bold: true, size: 18, font: 'Malgun Gothic' }),
                    new TextRun({ text: cap.status === 'success' ? '성공' : '실패', size: 18, color: cap.status === 'success' ? '198754' : 'DC3545', font: 'Malgun Gothic' })
                ],
                spacing: { after: 200 }
            })
        );

        // 스크린샷 이미지
        const imgPath = cap.annotatedPath || cap.screenshotPath;
        if (imgPath && fs.existsSync(imgPath)) {
            try {
                const imgBuffer = fs.readFileSync(imgPath);
                children.push(
                    new Paragraph({
                        children: [new TextRun({ text: cap.annotatedPath ? '스크린샷 (피드백 포함)' : '스크린샷', bold: true, size: 18, font: 'Malgun Gothic' })],
                        spacing: { before: 200, after: 100 }
                    }),
                    new Paragraph({
                        children: [
                            new ImageRun({
                                data: imgBuffer,
                                transformation: { width: 600, height: 338 },
                                type: 'png'
                            })
                        ],
                        alignment: AlignmentType.CENTER
                    })
                );
            } catch (e) {
                children.push(
                    new Paragraph({
                        children: [new TextRun({ text: '[이미지 로드 실패]', color: 'DC3545', size: 18, font: 'Malgun Gothic' })]
                    })
                );
            }
        }

        // 코멘트
        if (cap.comment) {
            children.push(
                new Paragraph({
                    children: [new TextRun({ text: '클라이언트 코멘트:', bold: true, size: 18, font: 'Malgun Gothic' })],
                    spacing: { before: 200 }
                }),
                new Paragraph({
                    children: [new TextRun({ text: cap.comment, size: 18, font: 'Malgun Gothic' })],
                    spacing: { after: 200 },
                    border: { left: { color: '0066CC', size: 3, space: 5, style: 'single' } }
                })
            );
        }

        // 에러 메시지
        if (cap.errorMessage) {
            children.push(
                new Paragraph({
                    children: [new TextRun({ text: '에러: ' + cap.errorMessage, size: 16, color: 'DC3545', font: 'Malgun Gothic' })],
                    spacing: { after: 100 }
                })
            );
        }

        // 페이지 구분선
        if (idx < captureData.length - 1) {
            children.push(new Paragraph({ children: [new PageBreak()] }));
        }
    }

    const doc = new Document({
        sections: [{ children }],
        creator: 'Seoul Beauty Auto Manual',
        title: '자동 매뉴얼',
        description: `자동 생성된 매뉴얼 - ${new Date().toLocaleString('ko-KR')}`
    });

    return await Packer.toBuffer(doc);
}

// === PDF 생성 (Playwright로 HTML→PDF) ===
async function generatePdf(captureData, session) {
    let browser;
    try {
        browser = await chromium.launch({ headless: true });
        const page = await browser.newPage();

        // HTML 생성
        let html = `<!DOCTYPE html><html><head><meta charset="UTF-8">
        <style>
            body { font-family: 'Malgun Gothic', sans-serif; padding: 40px; color: #333; }
            h1 { text-align: center; margin-top: 200px; font-size: 36px; }
            .subtitle { text-align: center; color: #666; margin-top: 10px; }
            .section { page-break-before: always; margin-top: 30px; }
            .section h2 { color: #333; border-bottom: 2px solid #0066cc; padding-bottom: 8px; }
            .meta { color: #666; font-size: 13px; margin: 8px 0; }
            img { max-width: 100%; border: 1px solid #ddd; border-radius: 4px; margin: 12px 0; }
            .comment { background: #f8f9fa; border-left: 4px solid #0066cc; padding: 12px; margin: 12px 0; }
            .error { color: #dc3545; font-size: 13px; }
            .status-ok { color: #198754; font-weight: bold; }
            .status-fail { color: #dc3545; font-weight: bold; }
            .toc { page-break-after: always; }
            .toc li { margin: 6px 0; }
        </style></head><body>`;

        // 표지
        html += `<h1>자동 매뉴얼</h1>`;
        html += `<p class="subtitle">${session ? session.targetUrl : ''}</p>`;
        html += `<p class="subtitle">생성일: ${new Date().toLocaleString('ko-KR')}</p>`;
        html += `<p class="subtitle">총 ${captureData.filter(c => c).length}개 페이지</p>`;

        // 목차
        html += `<div class="toc section"><h2>목차</h2><ol>`;
        captureData.forEach((cap, idx) => {
            if (!cap) return;
            html += `<li>${cap.menuName || 'Unknown'}</li>`;
        });
        html += `</ol></div>`;

        // 각 섹션
        captureData.forEach((cap, idx) => {
            if (!cap) return;
            html += `<div class="section">`;
            html += `<h2>${idx + 1}. ${escapeHtml(cap.menuName || 'Unknown')}</h2>`;
            html += `<p class="meta">URL: ${escapeHtml(cap.menuUrl || '')}</p>`;
            html += `<p class="meta">상태: <span class="${cap.status === 'success' ? 'status-ok' : 'status-fail'}">${cap.status === 'success' ? '성공' : '실패'}</span></p>`;

            const imgPath = cap.annotatedPath || cap.screenshotPath;
            if (imgPath && fs.existsSync(imgPath)) {
                const imgBase64 = fs.readFileSync(imgPath).toString('base64');
                html += `<p><strong>${cap.annotatedPath ? '스크린샷 (피드백 포함)' : '스크린샷'}</strong></p>`;
                html += `<img src="data:image/png;base64,${imgBase64}">`;
            }

            if (cap.comment) {
                html += `<p><strong>클라이언트 코멘트:</strong></p><div class="comment">${escapeHtml(cap.comment)}</div>`;
            }
            if (cap.errorMessage) {
                html += `<p class="error">에러: ${escapeHtml(cap.errorMessage)}</p>`;
            }
            html += `</div>`;
        });

        html += `</body></html>`;

        await page.setContent(html, { waitUntil: 'networkidle' });
        const pdfBuffer = await page.pdf({
            format: 'A4',
            margin: { top: '20mm', right: '15mm', bottom: '20mm', left: '15mm' },
            printBackground: true
        });

        return pdfBuffer;
    } finally {
        if (browser) await browser.close();
    }
}

// === 유틸리티 ===
function sendSSE(sessionId, event, data) {
    const clients = sseClients.get(sessionId);
    if (!clients) return;
    const msg = `event: ${event}\ndata: ${JSON.stringify(data)}\n\n`;
    clients.forEach((res) => {
        try { res.write(msg); } catch (e) {}
    });
}

function sanitizeFilename(name) {
    return (name || 'unknown').replace(/[^a-zA-Z0-9가-힣_-]/g, '_').slice(0, 50);
}

function escapeHtml(str) {
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// =========================================================
// 자동 테스트 API (auto-test)
// - 33개 페이지 자동 테스트 실행/이력/다운로드
// - test-runner.js를 child_process로 실행
// - SSE로 실시간 진행 전송
// =========================================================

const { spawn } = require('child_process');

/** 실행 중인 테스트 세션 */
const activeTestRuns = new Map();
/** 테스트 SSE 클라이언트 */
const testSseClients = new Map();
/** 테스트 결과 디렉토리 */
const AUTO_TEST_DIR = path.join(__dirname, '..', 'uploads', 'auto_test');
const AUTO_TEST_CONFIG_PATH = path.join(__dirname, 'test-config.json');
const MAX_TEST_RUNTIME_MS = Math.min(
    Math.max(parseInt(process.env.AUTO_TEST_MAX_RUNTIME_MS || '900000', 10) || 900000, 60000),
    3600000
);

function loadAutoTestConfig() {
    return JSON.parse(fs.readFileSync(AUTO_TEST_CONFIG_PATH, 'utf-8'));
}

function publicAutoTestConfig() {
    const config = loadAutoTestConfig();
    return {
        ...config,
        systems: (config.systems || []).map(system => ({
            ...system,
            credentials: system.credentials ? { username: system.credentials.username } : undefined,
        })),
    };
}

function validResultDir(dir) {
    return safeSegment(dir, /^\d{8}_\d{6}$/);
}

function validatedNotes(notes) {
    if (notes === undefined || notes === null) return {};
    if (typeof notes !== 'object' || Array.isArray(notes)) throw new Error('Invalid notes');
    const entries = Object.entries(notes);
    if (entries.length > 100) throw new Error('Too many notes');
    let totalLength = 0;
    const result = {};
    for (const [url, note] of entries) {
        if (typeof url !== 'string' || url.length > 2048 || typeof note !== 'string' || note.length > 5000) {
            throw new Error('Invalid note');
        }
        totalLength += url.length + note.length;
        if (totalLength > 200000) throw new Error('Notes are too large');
        result[url] = note;
    }
    return result;
}

function resultDirIsActive(dir) {
    const expected = safePath(AUTO_TEST_DIR, dir);
    return [...activeTestRuns.values()].some(run =>
        (run.status === 'running' || run.status === 'stopping')
        && (!run.resultDir || run.resultDir === expected)
    );
}

/**
 * 테스트 SSE 전송 헬퍼
 * @param {string} runId - 테스트 실행 ID
 * @param {string} event - SSE 이벤트명
 * @param {object} data - 전송 데이터
 */
function sendTestSSE(runId, event, data) {
    const clients = testSseClients.get(runId);
    if (!clients) return;
    const msg = `event: ${event}\ndata: ${JSON.stringify(data)}\n\n`;
    clients.forEach((res) => {
        try { res.write(msg); } catch (e) {}
    });
}

function appendRunOutput(run, line) {
    run.output.push(line);
    if (run.output.length > 5000) run.output.splice(0, run.output.length - 5000);
}

/**
 * POST /api/auto-test/run
 * 테스트 실행 시작
 * body: { system?: string, page?: string }
 * 응답: { runId, status: 'started' }
 */
app.post('/api/auto-test/run', (req, res) => {
    const { system, page: pageName, pages, delay } = req.body || {};

    let config;
    try { config = loadAutoTestConfig(); }
    catch (error) { return res.status(500).json({ error: 'Test config load failed' }); }
    const systems = config.systems || [];
    const selectedSystem = system ? systems.find(item => item.id === system) : null;
    if (system && !selectedSystem) return res.status(400).json({ error: 'Invalid system' });
    if (pageName && (typeof pageName !== 'string' || pageName.length > 100)) {
        return res.status(400).json({ error: 'Invalid page' });
    }
    if (pageName) {
        const candidateSystems = selectedSystem ? [selectedSystem] : systems;
        const pageExists = candidateSystems.some(item => (item.pages || []).some(page => page.name.includes(pageName)));
        if (!pageExists) return res.status(400).json({ error: 'Unknown page' });
    }
    if (pages !== undefined) {
        if (!Array.isArray(pages) || pages.length > 100 || pages.some(value => typeof value !== 'string')) {
            return res.status(400).json({ error: 'Invalid pages' });
        }
        const allowedPages = new Set((selectedSystem ? [selectedSystem] : systems)
            .flatMap(item => item.pages || []).map(item => item.url));
        if (pages.some(url => !allowedPages.has(url))) {
            return res.status(400).json({ error: 'Unknown page URL' });
        }
    }
    const parsedDelay = delay === undefined ? 0 : Number(delay);
    if (!Number.isFinite(parsedDelay) || parsedDelay < 0 || parsedDelay > 15000) {
        return res.status(400).json({ error: 'Delay must be between 0 and 15000 ms' });
    }

    // 중복 실행 방지
    for (const [id, run] of activeTestRuns) {
        if (run.status === 'running' || run.status === 'stopping') {
            return res.status(409).json({
                error: '테스트가 이미 실행 중입니다',
                runId: id,
                startTime: run.startTime
            });
        }
    }

    const runId = Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
    const args = [path.join(__dirname, 'test-runner.js')];
    if (system) args.push('--system', system);
    if (pageName) args.push('--page', pageName);
    if (pages && Array.isArray(pages) && pages.length > 0) args.push('--pages', JSON.stringify(pages));
    if (parsedDelay > 0) args.push('--delay', String(Math.trunc(parsedDelay)));

    // child_process로 test-runner.js 실행
    const child = spawn('node', args, {
        cwd: __dirname,
        env: { ...process.env, FORCE_COLOR: '0' }
    });

    const run = {
        id: runId,
        pid: child.pid,
        status: 'running',
        startTime: new Date().toISOString(),
        endTime: null,
        system: system || 'all',
        page: pageName || null,
        output: [],
        resultDir: null,
        exitCode: null,
        finalized: false
    };
    Object.defineProperty(run, 'process', { value: child, enumerable: false });
    activeTestRuns.set(runId, run);
    const runtimeTimer = setTimeout(() => {
        if (run.status === 'running') {
            run.status = 'stopping';
            appendRunOutput(run, '[ERROR] Maximum test runtime exceeded');
            sendTestSSE(runId, 'error', { message: 'Maximum test runtime exceeded' });
            child.kill('SIGTERM');
            setTimeout(() => {
                if (run.status === 'stopping') child.kill('SIGKILL');
            }, 5000).unref();
        }
    }, MAX_TEST_RUNTIME_MS);

    // stdout 수집 + SSE 전송
    child.stdout.on('data', (data) => {
        const text = data.toString('utf-8');
        const lines = text.split('\n').filter(l => l.trim());
        lines.forEach(line => {
            // 이모지 등 특수문자 제거 (로그용)
            const cleanLine = line.replace(/[\u{1F600}-\u{1F64F}\u{1F300}-\u{1F5FF}\u{1F680}-\u{1F6FF}\u{1F900}-\u{1F9FF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}\u{FE00}-\u{FE0F}\u{1F000}-\u{1FFFF}]/gu, '').trim();
            if (cleanLine) {
                appendRunOutput(run, cleanLine);
                sendTestSSE(runId, 'log', { message: cleanLine, raw: line });
            }
        });

        // 출력 디렉토리 감지
        const dirMatch = text.match(/출력 디렉토리: (.+)/);
        if (dirMatch) {
            const candidate = path.resolve(dirMatch[1].trim());
            const dirName = path.basename(candidate);
            if (validResultDir(dirName) && candidate === safePath(AUTO_TEST_DIR, dirName)) {
                run.resultDir = candidate;
            }
        }
    });

    child.stderr.on('data', (data) => {
        const text = data.toString('utf-8').slice(0, 10000);
        appendRunOutput(run, '[STDERR] ' + text.slice(0, 10000));
        sendTestSSE(runId, 'error', { message: text });
    });

    child.on('close', (code) => {
        if (run.finalized) return;
        run.finalized = true;
        clearTimeout(runtimeTimer);
        run.status = code === 0 ? 'completed' : 'failed';
        run.endTime = new Date().toISOString();
        run.exitCode = code;

        // 결과 디렉토리에서 summary 읽기
        let summary = null;
        if (run.resultDir) {
            const resultsPath = path.join(run.resultDir, 'results.json');
            try {
                if (fs.existsSync(resultsPath)) {
                    summary = JSON.parse(fs.readFileSync(resultsPath, 'utf-8')).summary;
                }
            } catch (e) {}
        }

        sendTestSSE(runId, 'complete', {
            status: run.status,
            exitCode: code,
            summary,
            resultDir: run.resultDir ? path.basename(run.resultDir) : null
        });

        console.log(`[Auto Test] 완료: runId=${runId}, code=${code}, dir=${run.resultDir}`);

        setTimeout(() => {
            const clients = testSseClients.get(runId);
            if (clients) clients.forEach(client => { try { client.end(); } catch (error) {} });
            testSseClients.delete(runId);
            activeTestRuns.delete(runId);
        }, 300000);
    });

    child.on('error', (err) => {
        if (run.finalized) return;
        run.finalized = true;
        clearTimeout(runtimeTimer);
        run.status = 'failed';
        run.endTime = new Date().toISOString();
        run.exitCode = -1;
        sendTestSSE(runId, 'error', { message: 'Process error: ' + err.message });
        sendTestSSE(runId, 'complete', { status: run.status, exitCode: -1, resultDir: null });
        setTimeout(() => {
            const clients = testSseClients.get(runId);
            if (clients) clients.forEach(client => { try { client.end(); } catch (error) {} });
            testSseClients.delete(runId);
            activeTestRuns.delete(runId);
        }, 5000);
    });

    console.log(`[Auto Test] 시작: runId=${runId}, system=${system || 'all'}, pid=${child.pid}`);
    res.json({ runId, status: 'started', pid: child.pid });
});

app.post('/api/auto-test/stop/:runId', (req, res) => {
    const { runId } = req.params;
    if (!safeSegment(runId)) return res.status(400).json({ error: 'Invalid run ID' });
    const run = activeTestRuns.get(runId);
    if (!run || run.status !== 'running') return res.status(404).json({ error: 'Run not found' });
    run.status = 'stopping';
    appendRunOutput(run, '[INFO] Stop requested by administrator');
    try { run.process.kill('SIGTERM'); } catch (error) {
        return res.status(500).json({ error: 'Unable to stop test' });
    }
    res.json({ success: true });
});

/**
 * GET /api/auto-test/progress/:runId
 * SSE 스트림으로 실시간 진행 전송
 */
app.get('/api/auto-test/progress/:runId', (req, res) => {
    const { runId } = req.params;
    if (!safeSegment(runId)) return res.status(400).json({ error: 'Invalid run ID' });
    const run = activeTestRuns.get(runId);
    if (!run) return res.status(404).json({ error: 'Run not found' });
    if (!testSseClients.has(runId)) testSseClients.set(runId, new Map());
    if (testSseClients.get(runId).size >= 5) return res.status(429).json({ error: 'Too many progress streams' });

    res.writeHead(200, {
        'Content-Type': 'text/event-stream',
        'Cache-Control': 'no-cache',
        'Connection': 'keep-alive',
        'X-Accel-Buffering': 'no'
    });

    // 기존 출력 전송
    run.output.forEach(line => {
        res.write(`event: log\ndata: ${JSON.stringify({ message: line })}\n\n`);
    });

    // 이미 완료된 경우
    if (run.status !== 'running' && run.status !== 'stopping') {
        res.write(`event: complete\ndata: ${JSON.stringify({ status: run.status, exitCode: run.exitCode })}\n\n`);
    }

    const clientId = `${Date.now()}_${Math.random().toString(36).slice(2, 6)}`;
    testSseClients.get(runId).set(clientId, res);

    const ping = setInterval(() => {
        try { res.write(':ping\n\n'); } catch (error) {}
    }, 15000);

    req.on('close', () => {
        clearInterval(ping);
        const clients = testSseClients.get(runId);
        if (clients) clients.delete(clientId);
    });
});

/**
 * GET /api/auto-test/status
 * 현재 실행 중인 테스트 상태
 */
app.get('/api/auto-test/status', (req, res) => {
    let running = null;
    for (const [id, run] of activeTestRuns) {
        if (run.status === 'running' || run.status === 'stopping') {
            running = { runId: id, ...run, output: run.output.slice(-20) };
            break;
        }
    }
    res.json({ running });
});

/**
 * GET /api/auto-test/config
 * 테스트 설정 파일 (시스템/페이지 목록) 반환
 */
app.get('/api/auto-test/config', (req, res) => {
    try {
        res.json(publicAutoTestConfig());
    } catch (e) {
        res.status(500).json({ error: e.message });
    }
});

/**
 * GET /api/auto-test/history
 * 이전 테스트 실행 이력 (최근 20개)
 */
app.get('/api/auto-test/history', (req, res) => {
    if (!fs.existsSync(AUTO_TEST_DIR)) return res.json([]);

    try {
        const dirs = fs.readdirSync(AUTO_TEST_DIR)
            .filter(d => {
                if (!validResultDir(d)) return false;
                try { return fs.statSync(safePath(AUTO_TEST_DIR, d)).isDirectory(); }
                catch (e) { return false; }
            })
            .sort()
            .reverse()
            .slice(0, 20); // 최근 20개만

        const history = dirs.map(d => {
            const dirPath = path.join(AUTO_TEST_DIR, d);
            const resultsPath = path.join(dirPath, 'results.json');
            let summary = null;

            if (fs.existsSync(resultsPath)) {
                try {
                    summary = JSON.parse(fs.readFileSync(resultsPath, 'utf-8')).summary;
                } catch (e) {}
            }

            // 날짜 파싱 (YYYYMMDD_HHMMSS 형식)
            let dateStr = d;
            const m = d.match(/(\d{4})(\d{2})(\d{2})_(\d{2})(\d{2})(\d{2})/);
            if (m) {
                dateStr = `${m[1]}-${m[2]}-${m[3]} ${m[4]}:${m[5]}:${m[6]}`;
            }

            return {
                dir: d,
                date: dateStr,
                summary,
                hasDocx: fs.existsSync(path.join(dirPath, 'report.docx')),
                hasPdf: fs.existsSync(path.join(dirPath, 'report.pdf')),
                hasJson: fs.existsSync(resultsPath),
                screenshotCount: (() => {
                    const ssDir = path.join(dirPath, 'screenshots');
                    try { return fs.readdirSync(ssDir).filter(f => f.endsWith('.png')).length; }
                    catch (e) { return 0; }
                })()
            };
        });

        res.json(history);
    } catch (err) {
        res.status(500).json({ error: err.message });
    }
});

/**
 * GET /api/auto-test/results/:dir
 * 특정 테스트 실행의 상세 결과 JSON
 */
app.get('/api/auto-test/results/:dir', (req, res) => {
    const { dir } = req.params;
    if (!validResultDir(dir)) return res.status(400).json({ error: 'Invalid path' });

    const resultsPath = safePath(AUTO_TEST_DIR, dir, 'results.json');
    if (!fs.existsSync(resultsPath)) return res.status(404).json({ error: 'Results not found' });

    try {
        const data = JSON.parse(fs.readFileSync(resultsPath, 'utf-8'));
        res.json(data);
    } catch (err) {
        res.status(500).json({ error: err.message });
    }
});

/**
 * GET /api/auto-test/download/:dir/:file
 * 보고서 파일 다운로드 (report.docx, report.pdf, results.json)
 * 파일명에 날짜+시스템+결과 포함
 */
app.get('/api/auto-test/download/:dir/:file', (req, res) => {
    const { dir, file } = req.params;
    if (!validResultDir(dir)) return res.status(400).json({ error: 'Invalid path' });

    // 허용 파일만 다운로드
    const allowed = ['report.docx', 'report.pdf', 'results.json'];
    if (!allowed.includes(file)) return res.status(403).json({ error: 'File not allowed' });

    const filePath = safePath(AUTO_TEST_DIR, dir, file);
    if (!fs.existsSync(filePath)) return res.status(404).json({ error: 'File not found' });

    // 파일명 생성: 서울미녀_자동테스트_20260213_190345_프론트엔드10P_10PASS0WARN0FAIL.pdf
    let downloadName = file;
    try {
        const resultsPath = path.join(AUTO_TEST_DIR, dir, 'results.json');
        if (fs.existsSync(resultsPath)) {
            const data = JSON.parse(fs.readFileSync(resultsPath, 'utf-8'));
            const s = data.summary || {};
            // 시스템 이름 축약
            const sysNames = (s.systems || []).map(n =>
                n.replace(/\s*\(.*?\)\s*/g, '').replace(/서울미녀\s*/, '서울미녀_')
            );
            const sysLabel = sysNames.length > 2 ? '전체' :
                             sysNames.length === 0 ? '' : sysNames.join('+');
            const ext = path.extname(file);
            downloadName = `서울미녀_테스트_${dir}_${sysLabel}${s.totalPages || ''}P_${s.pass || 0}PASS${s.warn || 0}WARN${s.fail || 0}FAIL${ext}`;
        }
    } catch (e) { /* 파일명 생성 실패 시 원본 파일명 사용 */ }

    res.download(filePath, downloadName);
});

/**
 * GET /api/auto-test/screenshot/:dir/:file
 * 스크린샷 이미지 서빙
 */
app.get('/api/auto-test/screenshot/:dir/:file', (req, res) => {
    const { dir, file } = req.params;
    if (!validResultDir(dir) || !safeSegment(file, /^[\p{L}\p{N}_.-]+$/u)) {
        return res.status(400).json({ error: 'Invalid path' });
    }
    if (!file.endsWith('.png')) return res.status(400).json({ error: 'PNG only' });

    const filePath = safePath(AUTO_TEST_DIR, dir, 'screenshots', file);
    if (!fs.existsSync(filePath)) return res.status(404).json({ error: 'Screenshot not found' });

    res.setHeader('Content-Type', 'image/png');
    res.sendFile(filePath);
});

/**
 * DELETE /api/auto-test/history/:dir
 * 테스트 이력 삭제
 */
app.delete('/api/auto-test/history/:dir', (req, res) => {
    const { dir } = req.params;
    if (!validResultDir(dir)) return res.status(400).json({ error: 'Invalid path' });
    if (resultDirIsActive(dir)) return res.status(409).json({ error: 'Cannot delete an active run' });

    const dirPath = safePath(AUTO_TEST_DIR, dir);
    if (!fs.existsSync(dirPath)) return res.status(404).json({ error: 'Directory not found' });

    try {
        fs.rmSync(dirPath, { recursive: true, force: true });
        res.json({ success: true, deleted: dir });
    } catch (err) {
        res.status(500).json({ error: err.message });
    }
});


/**
 * POST /api/auto-test/history/bulk-delete
 * 여러 테스트 이력 일괄 삭제
 */
app.post('/api/auto-test/history/bulk-delete', (req, res) => {
    const { dirs } = req.body || {};
    if (!Array.isArray(dirs) || dirs.length === 0 || dirs.length > 20) {
        return res.status(400).json({ error: '삭제할 디렉토리 목록이 필요합니다' });
    }

    const results = [];
    for (const dir of dirs) {
        if (!validResultDir(dir)) {
            results.push({ dir, success: false, error: 'Invalid path' });
            continue;
        }
        if (resultDirIsActive(dir)) {
            results.push({ dir, success: false, error: 'Active run' });
            continue;
        }
        const dirPath = safePath(AUTO_TEST_DIR, dir);
        if (!fs.existsSync(dirPath)) {
            results.push({ dir, success: false, error: 'Not found' });
            continue;
        }
        try {
            fs.rmSync(dirPath, { recursive: true, force: true });
            results.push({ dir, success: true });
        } catch (err) {
            results.push({ dir, success: false, error: err.message });
        }
    }

    const deleted = results.filter(r => r.success).length;
    res.json({ success: true, deleted, total: dirs.length, results });
});

/**
 * GET /api/auto-test/notes/:dir
 * 테스트 결과의 수정 프롬프트(노트) 조회
 */
app.get('/api/auto-test/notes/:dir', (req, res) => {
    const { dir } = req.params;
    if (!validResultDir(dir)) return res.status(400).json({ error: 'Invalid path' });

    const notesPath = safePath(AUTO_TEST_DIR, dir, 'notes.json');
    if (!fs.existsSync(notesPath)) return res.json({ notes: {} });

    try {
        const data = JSON.parse(fs.readFileSync(notesPath, 'utf-8'));
        res.json(data);
    } catch (err) {
        res.json({ notes: {} });
    }
});

/**
 * POST /api/auto-test/notes/:dir
 * 테스트 결과의 수정 프롬프트(노트) 저장
 * body: { notes: { "pageUrl": "프롬프트 텍스트", ... } }
 */
app.post('/api/auto-test/notes/:dir', (req, res) => {
    const { dir } = req.params;
    if (!validResultDir(dir)) return res.status(400).json({ error: 'Invalid path' });

    const dirPath = safePath(AUTO_TEST_DIR, dir);
    if (!fs.existsSync(dirPath)) return res.status(404).json({ error: 'Not found' });

    let notes;
    try { notes = validatedNotes((req.body || {}).notes); }
    catch (error) { return res.status(400).json({ error: error.message }); }
    const notesPath = path.join(dirPath, 'notes.json');

    try {
        fs.writeFileSync(notesPath, JSON.stringify({
            notes: notes || {},
            savedAt: new Date().toISOString()
        }, null, 2), 'utf-8');
        res.json({ success: true });
    } catch (err) {
        res.status(500).json({ error: err.message });
    }
});

/**
 * POST /api/auto-test/export-report/:dir
 * 수정 프롬프트 포함 DOCX 보고서 재생성 + 다운로드
 * body: { notes: { ... } } (optional, 없으면 저장된 notes.json 사용)
 */
app.post('/api/auto-test/export-report/:dir', async (req, res) => {
    const { dir } = req.params;
    if (!validResultDir(dir)) return res.status(400).json({ error: 'Invalid path' });

    const dirPath = safePath(AUTO_TEST_DIR, dir);
    const resultsPath = path.join(dirPath, 'results.json');
    if (!fs.existsSync(resultsPath)) return res.status(404).json({ error: 'Results not found' });

    try {
        const resultsJson = JSON.parse(fs.readFileSync(resultsPath, 'utf-8'));

        // 노트: 요청 body 우선, 없으면 저장된 파일
        let notes = {};
        if (req.body?.notes && Object.keys(req.body.notes).length > 0) {
            notes = validatedNotes(req.body.notes);
        } else {
            const notesPath = path.join(dirPath, 'notes.json');
            if (fs.existsSync(notesPath)) {
                try { notes = JSON.parse(fs.readFileSync(notesPath, 'utf-8')).notes || {}; }
                catch (e) {}
            }
        }

        // report-generator를 매번 새로 로드 (캐시 문제 방지)
        delete require.cache[require.resolve('./report-generator')];
        const { generateReport } = require('./report-generator');
        const { docxPath } = await generateReport(dirPath, resultsJson, notes);

        const s = resultsJson.summary || {};
        const downloadName = `서울미녀_테스트보고서_${dir}_${s.totalPages || ''}P.docx`;
        res.download(docxPath, downloadName);
    } catch (err) {
        console.error('[export-report] Error:', err);
        res.status(500).json({ error: err.message });
    }
});

// =========================================================
// 스크린샷 매뉴얼 북 API (book)
// - 35개 관리 페이지 캡처 → HTML/DOCX 매뉴얼 생성
// - book-generator.js + book-html-renderer.js 사용
// =========================================================

const { generateBook } = require('./book-generator');
const { renderBookHTML } = require('./book-html-renderer');

/** 실행 중인 북 세션 */
const activeBookRuns = new Map();
/** 북 SSE 클라이언트 */
const bookSseClients = new Map();
/** 북 결과 디렉토리 */
const BOOK_DIR = path.join(__dirname, '..', 'uploads', 'book');
/** 매뉴얼 디렉토리 (영구 URL) */
const MANUAL_DIR = path.join(__dirname, '..', 'manual');

function sendBookSSE(runId, event, data) {
    const clients = bookSseClients.get(runId);
    if (!clients) return;
    const msg = `event: ${event}\ndata: ${JSON.stringify(data)}\n\n`;
    clients.forEach((res) => {
        try { res.write(msg); } catch (e) {}
    });
}

/**
 * POST /api/book/generate
 * 매뉴얼 북 생성 시작
 */
app.post('/api/book/generate', async (req, res) => {
    // 중복 실행 방지
    for (const [id, run] of activeBookRuns) {
        if (run.status === 'running') {
            return res.status(409).json({
                error: '매뉴얼 생성이 이미 실행 중입니다',
                runId: id,
                startTime: run.startTime
            });
        }
    }

    const runId = Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
    const run = {
        id: runId,
        status: 'running',
        startTime: new Date().toISOString(),
        endTime: null,
        resultDir: null,
        aborted: false
    };
    activeBookRuns.set(runId, run);

    res.json({ runId, status: 'started' });

    // 비동기 실행
    (async () => {
        try {
            const result = await generateBook(
                { enableAI: false },
                (event, data) => {
                    sendBookSSE(runId, event, { ...data, current: data.current || 0, total: data.total || 35 });
                }
            );

            run.resultDir = result.outputDir;

            // HTML 책 생성
            const screenshotsRelPath = 'screenshots';
            const bookHTML = renderBookHTML(result.bookData, screenshotsRelPath);
            const bookHtmlPath = path.join(result.outputDir, 'book.html');
            fs.writeFileSync(bookHtmlPath, bookHTML, 'utf-8');

            // 매뉴얼 디렉토리에 복사 (영구 URL용)
            try {
                const manualBookDir = path.join(MANUAL_DIR, 'screenshot_book');
                if (!fs.existsSync(manualBookDir)) fs.mkdirSync(manualBookDir, { recursive: true });
                // screenshots 복사
                const srcScreenshots = path.join(result.outputDir, 'screenshots');
                const dstScreenshots = path.join(manualBookDir, 'screenshots');
                if (fs.existsSync(dstScreenshots)) fs.rmSync(dstScreenshots, { recursive: true });
                fs.cpSync(srcScreenshots, dstScreenshots, { recursive: true });
                // book.html 복사
                fs.copyFileSync(bookHtmlPath, path.join(manualBookDir, 'index.html'));
            } catch (copyErr) {
                console.error('[Book] Manual copy error:', copyErr.message);
            }

            // DOCX 생성 (report-generator 재사용 - 데이터 포맷 변환)
            try {
                delete require.cache[require.resolve('./report-generator')];
                const { generateReport } = require('./report-generator');
                const bookResults = JSON.parse(fs.readFileSync(path.join(result.outputDir, 'results.json'), 'utf-8'));
                // book results → report-generator 포맷 변환
                const screenshotsDir = path.join(result.outputDir, 'screenshots');
                const systems = [...new Set(bookResults.results.map(r => r.chapterTitle))];
                const convertedResults = bookResults.results.map(r => ({
                    system: r.chapterTitle,
                    page: r.label,
                    url: r.url,
                    status: (r.status || 'pass').toUpperCase(),
                    screenshotPath: r.screenshotFile ? path.join(screenshotsDir, r.screenshotFile) : null,
                    loadTime: r.loadTime || 0,
                    errors: r.errors || {},
                    analysis: r.analysis || {},
                    description: r.description || '',
                }));
                const convertedSummary = {
                    testDate: bookResults.summary.generatedAt || new Date().toISOString(),
                    total: bookResults.summary.total,
                    pass: bookResults.summary.pass,
                    warn: bookResults.summary.warn,
                    fail: bookResults.summary.fail,
                    systems: systems,
                    elapsedSeconds: bookResults.summary.elapsedSeconds,
                };
                await generateReport(result.outputDir, { summary: convertedSummary, results: convertedResults }, {});
                // report.docx → book.docx 이름 변경
                const reportDocx = path.join(result.outputDir, 'report.docx');
                const bookDocx = path.join(result.outputDir, 'book.docx');
                if (fs.existsSync(reportDocx) && !fs.existsSync(bookDocx)) {
                    fs.renameSync(reportDocx, bookDocx);
                }
                console.log('[Book] DOCX 생성 완료');
            } catch (docxErr) {
                console.error('[Book] DOCX generation error:', docxErr.message);
            }

            run.status = 'completed';
            run.endTime = new Date().toISOString();

            const dirName = path.basename(result.outputDir);
            sendBookSSE(runId, 'complete', {
                status: 'completed',
                summary: result.summary,
                resultDir: dirName,
                manualUrl: '/hairwang/gangnamunni/manual/screenshot_book/'
            });

            console.log(`[Book] 완료: runId=${runId}, dir=${dirName}, pages=${result.summary.total}`);
        } catch (err) {
            run.status = 'failed';
            run.endTime = new Date().toISOString();
            sendBookSSE(runId, 'error_event', { message: 'Fatal: ' + err.message });
            sendBookSSE(runId, 'complete', { status: 'failed', error: err.message });
            console.error('[Book] Fatal error:', err);
        }
    })();
});

/**
 * GET /api/book/progress/:runId
 * SSE 스트림
 */
app.get('/api/book/progress/:runId', (req, res) => {
    const { runId } = req.params;
    const run = activeBookRuns.get(runId);
    if (!run) return res.status(404).json({ error: 'Run not found' });

    res.writeHead(200, {
        'Content-Type': 'text/event-stream',
        'Cache-Control': 'no-cache',
        'Connection': 'keep-alive',
        'Access-Control-Allow-Origin': '*'
    });

    if (run.status !== 'running') {
        res.write(`event: complete\ndata: ${JSON.stringify({ status: run.status })}\n\n`);
        res.end();
        return;
    }

    const clientId = Date.now().toString();
    if (!bookSseClients.has(runId)) bookSseClients.set(runId, new Map());
    bookSseClients.get(runId).set(clientId, res);

    req.on('close', () => {
        const clients = bookSseClients.get(runId);
        if (clients) clients.delete(clientId);
    });
});

/**
 * POST /api/book/stop/:runId
 * 실행 중지
 */
app.post('/api/book/stop/:runId', (req, res) => {
    const { runId } = req.params;
    const run = activeBookRuns.get(runId);
    if (!run) return res.status(404).json({ error: 'Run not found' });
    run.aborted = true;
    res.json({ success: true, message: '중지 요청됨' });
});

/**
 * GET /api/book/history
 * 생성 이력
 */
app.get('/api/book/history', (req, res) => {
    if (!fs.existsSync(BOOK_DIR)) return res.json([]);

    try {
        const dirs = fs.readdirSync(BOOK_DIR)
            .filter(d => {
                try { return fs.statSync(path.join(BOOK_DIR, d)).isDirectory(); }
                catch (e) { return false; }
            })
            .sort().reverse().slice(0, 20);

        const history = dirs.map(d => {
            const dirPath = path.join(BOOK_DIR, d);
            const metaPath = path.join(dirPath, 'meta.json');
            let summary = null;

            if (fs.existsSync(metaPath)) {
                try { summary = JSON.parse(fs.readFileSync(metaPath, 'utf-8')).summary; }
                catch (e) {}
            }
            if (!summary) {
                const resultsPath = path.join(dirPath, 'results.json');
                if (fs.existsSync(resultsPath)) {
                    try { summary = JSON.parse(fs.readFileSync(resultsPath, 'utf-8')).summary; }
                    catch (e) {}
                }
            }

            const m = d.match(/(\d{4})(\d{2})(\d{2})_(\d{2})(\d{2})(\d{2})/);
            const dateStr = m ? `${m[1]}-${m[2]}-${m[3]} ${m[4]}:${m[5]}:${m[6]}` : d;

            return {
                dir: d,
                date: dateStr,
                summary,
                hasBook: fs.existsSync(path.join(dirPath, 'book.html')),
                hasDocx: fs.existsSync(path.join(dirPath, 'book.docx')) || fs.existsSync(path.join(dirPath, 'report.docx')),
                hasJson: fs.existsSync(path.join(dirPath, 'results.json')),
                screenshotCount: (() => {
                    const ssDir = path.join(dirPath, 'screenshots');
                    try { return fs.readdirSync(ssDir).filter(f => f.endsWith('.jpg') || f.endsWith('.png')).length; }
                    catch (e) { return 0; }
                })()
            };
        });

        res.json(history);
    } catch (err) {
        res.status(500).json({ error: err.message });
    }
});

/**
 * GET /api/book/preview/:dir
 * HTML 책 미리보기 — trailing slash 리다이렉트 (이미지 상대경로 해결)
 */
app.get('/api/book/preview/:dir', (req, res, next) => {
    if (!req.originalUrl.endsWith('/')) {
        return res.redirect(301, req.originalUrl + '/');
    }
    next();
});
app.get('/api/book/preview/:dir/', (req, res) => {
    const { dir } = req.params;
    if (dir.includes('..')) return res.status(400).json({ error: 'Invalid path' });

    const bookPath = path.join(BOOK_DIR, dir, 'book.html');
    if (!fs.existsSync(bookPath)) return res.status(404).json({ error: 'Book not found' });

    res.sendFile(bookPath);
});

/**
 * GET /api/book/preview/:dir/screenshots/:file
 * 스크린샷 이미지 서빙 (HTML 책에서 참조)
 */
app.get('/api/book/preview/:dir/screenshots/:file', (req, res) => {
    const { dir, file } = req.params;
    if (dir.includes('..') || file.includes('..')) return res.status(400).json({ error: 'Invalid path' });

    const filePath = path.join(BOOK_DIR, dir, 'screenshots', file);
    if (!fs.existsSync(filePath)) return res.status(404).json({ error: 'Screenshot not found' });

    const ext = path.extname(file).toLowerCase();
    const contentType = ext === '.jpg' || ext === '.jpeg' ? 'image/jpeg' : 'image/png';
    res.setHeader('Content-Type', contentType);
    res.sendFile(filePath);
});

/**
 * GET /api/book/download/:dir/:file
 * 파일 다운로드
 */
app.get('/api/book/download/:dir/:file', (req, res) => {
    const { dir, file } = req.params;
    if (dir.includes('..') || file.includes('..')) return res.status(400).json({ error: 'Invalid path' });

    const allowed = ['book.html', 'book.docx', 'report.docx', 'results.json', 'meta.json'];
    if (!allowed.includes(file)) return res.status(403).json({ error: 'File not allowed' });

    const filePath = path.join(BOOK_DIR, dir, file);
    if (!fs.existsSync(filePath)) return res.status(404).json({ error: 'File not found' });

    const downloadName = `서울미녀_스크린샷매뉴얼_${dir}_${file}`;
    res.download(filePath, downloadName);
});

/**
 * GET /api/book/export-pdf/:dir
 * Playwright로 book.html을 A4 PDF로 변환 (서버사이드)
 */
app.get('/api/book/export-pdf/:dir', async (req, res) => {
    const { dir } = req.params;
    if (dir.includes('..')) return res.status(400).json({ error: 'Invalid path' });

    const bookHtmlPath = path.join(BOOK_DIR, dir, 'book.html');
    if (!fs.existsSync(bookHtmlPath)) return res.status(404).json({ error: 'Book not found' });

    let browser;
    try {
        browser = await chromium.launch({ headless: true });
        const page = await browser.newPage();

        // HTTP URL로 접근 (스크린샷 상대경로 해결)
        const previewUrl = `http://localhost:${PORT}/api/book/preview/${dir}/`;
        await page.goto(previewUrl, { waitUntil: 'networkidle', timeout: 30000 });

        // 모든 접힌 섹션 펼치기 + 마법사 단계 모두 표시 + lazy loading 이미지 → eager 변환
        await page.evaluate(() => {
            document.querySelectorAll('.page-section.collapsed').forEach(el => el.classList.remove('collapsed'));
            document.querySelectorAll('.book-chapter.collapsed').forEach(el => el.classList.remove('collapsed'));
            // 마법사 단계: 모든 step-screenshot 표시 (인쇄용)
            document.querySelectorAll('.step-screenshot').forEach(el => {
                el.style.display = 'block';
                el.classList.add('active');
            });
            // step-nav 숨기기 (인쇄에 불필요)
            document.querySelectorAll('.step-nav').forEach(el => el.style.display = 'none');
            // lazy loading 이미지를 eager로 변경하여 모두 로드
            document.querySelectorAll('img[loading="lazy"]').forEach(img => {
                img.loading = 'eager';
                const src = img.src;
                img.src = '';
                img.src = src;
            });
        });

        // 모든 이미지 로드 대기
        await page.waitForFunction(() => {
            const imgs = document.querySelectorAll('.screenshot-frame img');
            return Array.from(imgs).every(img => img.complete && img.naturalHeight > 0);
        }, { timeout: 30000 });

        // PDF용 스타일 주입
        await page.addStyleTag({ content: `
            .no-print, .export-controls, .back-to-top, .fixed-header, .book-header { display: none !important; }
            .book-cover { min-height: auto !important; padding: 80px 40px !important; page-break-after: always; }
            .book-toc { page-break-after: always; }
            /* 챕터: 새 페이지에서 시작하되, 제목과 첫 섹션이 함께 나오도록 */
            section[id^="chapter-"] { page-break-before: always; }
            .book-chapter-header { page-break-after: avoid; }
            /* 섹션: 첫 번째는 챕터 헤더와 함께, 이후부터 새 페이지 */
            .page-section { display: block !important; page-break-before: auto; page-break-inside: avoid; }
            .page-section + .page-section { page-break-before: always; }
            .page-body { display: block !important; }
            .screenshot-frame { page-break-inside: avoid; }
            .screenshot-frame img { max-width: 100% !important; max-height: 700px !important; }
            .book-appendix { page-break-before: always; }
            .collapse-all-btn, .ch-toggle-btn, .toggle-icon { display: none !important; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        `});

        await page.waitForTimeout(500);

        const pdf = await page.pdf({
            format: 'A4',
            margin: { top: '12mm', bottom: '12mm', left: '10mm', right: '10mm' },
            printBackground: true,
            displayHeaderFooter: true,
            headerTemplate: '<span></span>',
            footerTemplate: '<div style="font-size:9px;text-align:center;width:100%;color:#999;">Seoul Beauty 관리자 매뉴얼 — <span class="pageNumber"></span> / <span class="totalPages"></span></div>'
        });

        await browser.close();

        res.setHeader('Content-Type', 'application/pdf');
        res.setHeader('Content-Disposition', `attachment; filename="Seoul_Beauty_Admin_Manual.pdf"; filename*=UTF-8''Seoul_Beauty_%EA%B4%80%EB%A6%AC%EC%9E%90%EB%A7%A4%EB%89%B4%EC%96%BC.pdf`);
        res.send(Buffer.from(pdf));
    } catch (err) {
        if (browser) await browser.close().catch(() => {});
        console.error('PDF export error:', err);
        res.status(500).json({ error: err.message });
    }
});

/**
 * GET /api/book/export-word/:dir
 * 서버사이드 Word 내보내기 (docx 패키지로 진짜 .docx 생성)
 */
app.get('/api/book/export-word/:dir', async (req, res) => {
    const { dir } = req.params;
    if (dir.includes('..')) return res.status(400).json({ error: 'Invalid path' });

    const bookDir = path.join(BOOK_DIR, dir);
    const resultsPath = path.join(bookDir, 'results.json');
    const metaPath = path.join(bookDir, 'meta.json');
    const screenshotsDir = path.join(bookDir, 'screenshots');

    if (!fs.existsSync(resultsPath)) return res.status(404).json({ error: 'Results not found' });

    console.log('[Word Export] Starting for:', dir);

    try {
        const results = JSON.parse(fs.readFileSync(resultsPath, 'utf-8'));
        const meta = fs.existsSync(metaPath) ? JSON.parse(fs.readFileSync(metaPath, 'utf-8')) : {};
        const pages = results.results || results.pages || [];

        const genDate = meta.generatedAt ? new Date(meta.generatedAt).toLocaleDateString('ko-KR') : new Date().toLocaleDateString('ko-KR');
        const totalPass = pages.filter(p => (p.status || '').toUpperCase() === 'PASS').length;
        const totalWarn = pages.filter(p => (p.status || '').toUpperCase() === 'WARN').length;
        const totalFail = pages.filter(p => (p.status || '').toUpperCase() === 'FAIL').length;

        // 표지 섹션
        const coverChildren = [
            new Paragraph({ spacing: { before: 3000 }, children: [] }),
            new Paragraph({
                alignment: AlignmentType.CENTER,
                spacing: { after: 200 },
                children: [new TextRun({ text: 'Seoul Beauty', size: 56, color: '6C63FF', bold: true, font: 'Malgun Gothic' })]
            }),
            new Paragraph({
                alignment: AlignmentType.CENTER,
                spacing: { after: 200 },
                children: [new TextRun({ text: '관리자 매뉴얼', size: 44, color: '6C63FF', bold: true, font: 'Malgun Gothic' })]
            }),
            new Paragraph({
                alignment: AlignmentType.CENTER,
                spacing: { after: 100 },
                children: [new TextRun({ text: '전체 관리 페이지 스크린샷 북', size: 24, color: '666666', font: 'Malgun Gothic' })]
            }),
            new Paragraph({
                alignment: AlignmentType.CENTER,
                spacing: { before: 400, after: 100 },
                children: [new TextRun({ text: '━━━━━━━━━━━━━━━━', size: 20, color: '6C63FF' })]
            }),
            new Paragraph({
                alignment: AlignmentType.CENTER,
                spacing: { after: 100 },
                children: [new TextRun({ text: `생성일: ${genDate}`, size: 20, color: '666666', font: 'Malgun Gothic' })]
            }),
            new Paragraph({
                alignment: AlignmentType.CENTER,
                children: [new TextRun({ text: `총 ${pages.length}개 페이지 | PASS ${totalPass} / WARN ${totalWarn} / FAIL ${totalFail}`, size: 20, color: '666666', font: 'Malgun Gothic' })]
            }),
        ];

        // 목차 섹션
        const tocChildren = [
            new Paragraph({
                heading: HeadingLevel.HEADING_1,
                spacing: { after: 300 },
                children: [new TextRun({ text: '목차', size: 32, color: '4F46E5', bold: true, font: 'Malgun Gothic' })]
            }),
        ];
        pages.forEach((p, idx) => {
            tocChildren.push(new Paragraph({
                spacing: { after: 80 },
                indent: { left: 360 },
                children: [new TextRun({ text: `${idx + 1}. ${p.label || p.menuName || 'Page ' + (idx+1)}`, size: 22, font: 'Malgun Gothic' })]
            }));
        });

        // 페이지별 섹션들
        const pageSections = [];
        for (let i = 0; i < pages.length; i++) {
            const p = pages[i];
            const label = p.label || p.menuName || 'Page ' + (i+1);
            const status = (p.status || 'pass').toUpperCase();
            const sectionChildren = [];

            // 페이지 제목
            sectionChildren.push(new Paragraph({
                heading: HeadingLevel.HEADING_2,
                spacing: { after: 100 },
                children: [
                    new TextRun({ text: `${i+1}. ${label}`, size: 26, color: '4F46E5', bold: true, font: 'Malgun Gothic' }),
                    new TextRun({ text: `  [${status}]`, size: 18, color: status === 'PASS' ? '198754' : status === 'WARN' ? 'FFC107' : 'DC3545', font: 'Malgun Gothic' }),
                ]
            }));

            // URL
            if (p.url) {
                sectionChildren.push(new Paragraph({
                    spacing: { after: 80 },
                    children: [new TextRun({ text: `URL: ${p.url}`, size: 18, color: '666666', font: 'Malgun Gothic' })]
                }));
            }

            // 설명
            if (p.description) {
                sectionChildren.push(new Paragraph({
                    spacing: { after: 120 },
                    children: [new TextRun({ text: p.description, size: 20, font: 'Malgun Gothic' })]
                }));
            }

            // 스크린샷 이미지
            const ssFile = p.screenshotFile || p.screenshotPath;
            if (ssFile) {
                const imgPath = ssFile.startsWith('/') ? ssFile : path.join(screenshotsDir, path.basename(ssFile));
                if (fs.existsSync(imgPath)) {
                    try {
                        const imgBuf = fs.readFileSync(imgPath);
                        const ext = path.extname(imgPath).toLowerCase();
                        const imgType = (ext === '.png') ? 'png' : 'jpg';
                        // A4 가로 쓸 수 있는 영역: ~16cm = 609pt. 비율 유지하며 축소
                        sectionChildren.push(new Paragraph({
                            spacing: { before: 100, after: 200 },
                            children: [new ImageRun({
                                data: imgBuf,
                                transformation: { width: 620, height: 440 },
                                type: imgType,
                            })]
                        }));
                        console.log(`[Word Export] Image ${i+1}/${pages.length}: ${path.basename(imgPath)} (${imgBuf.length} bytes)`);
                    } catch (imgErr) {
                        console.error(`[Word Export] Image error ${imgPath}:`, imgErr.message);
                    }
                } else {
                    console.warn(`[Word Export] Image not found: ${imgPath}`);
                }
            }

            // 분석 정보
            const m = p.meta || p.analysis;
            if (m) {
                sectionChildren.push(new Paragraph({
                    spacing: { after: 100 },
                    children: [new TextRun({ text: `링크 ${m.linkCount || 0} | 폼 ${m.formCount || 0} | 테이블 ${m.tableCount || 0} | 버튼 ${m.buttonCount || 0} | 입력 ${m.inputCount || 0}`, size: 18, color: '999999', font: 'Malgun Gothic' })]
                }));
            }

            pageSections.push({
                properties: { page: { size: { width: 11906, height: 16838 }, margin: { top: 1134, bottom: 1134, left: 1134, right: 1134 } } },
                children: sectionChildren
            });
        }

        // Document 생성
        const doc = new Document({
            creator: 'Seoul Beauty Auto Manual Engine',
            title: 'Seoul Beauty 관리자 매뉴얼',
            description: '전체 관리 페이지 스크린샷 북',
            sections: [
                // 표지
                { properties: { page: { size: { width: 11906, height: 16838 }, margin: { top: 1134, bottom: 1134, left: 1134, right: 1134 } } }, children: coverChildren },
                // 목차
                { properties: { page: { size: { width: 11906, height: 16838 }, margin: { top: 1134, bottom: 1134, left: 1134, right: 1134 } } }, children: tocChildren },
                // 각 페이지
                ...pageSections,
            ],
        });

        const docBuffer = await Packer.toBuffer(doc);
        console.log(`[Word Export] Complete: ${docBuffer.length} bytes, ${pages.length} pages`);

        res.setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        res.setHeader('Content-Disposition', `attachment; filename="Seoul_Beauty_Admin_Manual.docx"; filename*=UTF-8''Seoul_Beauty_%EA%B4%80%EB%A6%AC%EC%9E%90%EB%A7%A4%EB%89%B4%EC%96%BC.docx`);
        res.send(docBuffer);
    } catch (err) {
        console.error('[Word Export] Error:', err);
        res.status(500).json({ error: err.message });
    }
});

/**
 * DELETE /api/book/history/:dir
 * 이력 삭제
 */
app.delete('/api/book/history/:dir', (req, res) => {
    const { dir } = req.params;
    if (dir.includes('..')) return res.status(400).json({ error: 'Invalid path' });

    const dirPath = path.join(BOOK_DIR, dir);
    if (!fs.existsSync(dirPath)) return res.status(404).json({ error: 'Not found' });

    try {
        fs.rmSync(dirPath, { recursive: true, force: true });
        res.json({ success: true, deleted: dir });
    } catch (err) {
        res.status(500).json({ error: err.message });
    }
});


// === 단일 URL 스크린샷 캡처 (수정 이력용) ===
const FIX_LOG_DIR = path.join(__dirname, '..', 'uploads', 'fix_log');
if (!fs.existsSync(FIX_LOG_DIR)) fs.mkdirSync(FIX_LOG_DIR, { recursive: true });

app.post('/api/book/capture-single', async (req, res) => {
    const { url, label } = req.body;
    if (!url || !label) {
        return res.status(400).json({ error: 'url and label are required' });
    }

    // 날짜별 디렉토리
    const dateStr = new Date().toISOString().slice(0, 10).replace(/-/g, '');
    const dateDir = path.join(FIX_LOG_DIR, dateStr);
    if (!fs.existsSync(dateDir)) fs.mkdirSync(dateDir, { recursive: true });

    const filename = label.replace(/[^a-zA-Z0-9_-]/g, '_') + '.jpg';
    const filePath = path.join(dateDir, filename);
    const relativePath = `uploads/fix_log/${dateStr}/${filename}`;

    console.log(`[Capture Single] url=${url}, label=${label}, path=${relativePath}`);

    let browser = null;
    try {
        // book-config.json에서 설정 읽기
        const configPath = path.join(__dirname, 'book-config.json');
        const config = JSON.parse(fs.readFileSync(configPath, 'utf-8'));

        browser = await chromium.launch({
            headless: true,
            args: ['--no-sandbox', '--disable-setuid-sandbox']
        });
        const context = await browser.newContext({
            viewport: { width: 1920, height: 1080 },
            ignoreHTTPSErrors: true
        });
        const page = await context.newPage();

        // 로그인
        const loginUrl = config.baseUrl + config.basePath + config.login.url;
        await page.goto(loginUrl, { waitUntil: 'networkidle', timeout: 30000 });
        await page.waitForSelector(config.login.usernameField, { timeout: 10000 });
        await page.fill(config.login.usernameField, config.login.username);
        await page.fill(config.login.passwordField, config.login.password);
        await page.click(config.login.submitButton);
        await page.waitForTimeout(3000);
        try {
            await page.waitForSelector(config.login.successIndicator, { timeout: 10000, state: 'attached' });
        } catch (e) { /* 폴백 */ }

        // 라이트모드 강제
        await page.evaluate(() => {
            localStorage.setItem('admin_theme', 'light');
            document.documentElement.setAttribute('data-bs-theme', 'light');
        });

        // 대상 URL로 이동
        const targetUrl = url.startsWith('http') ? url : config.baseUrl + config.basePath + url;
        await page.goto(targetUrl, { waitUntil: 'networkidle', timeout: 30000 });
        await page.waitForTimeout(2500);

        // 라이트모드 재적용
        await page.evaluate(() => {
            localStorage.setItem('admin_theme', 'light');
            document.documentElement.setAttribute('data-bs-theme', 'light');
        });
        await page.waitForTimeout(500);

        // 스크린샷
        await page.screenshot({ path: filePath, fullPage: true, type: 'jpeg', quality: 85 });

        await browser.close();
        browser = null;

        console.log(`[Capture Single] SUCCESS: ${relativePath}`);
        res.json({ success: true, path: relativePath, filename });
    } catch (err) {
        console.error('[Capture Single] ERROR:', err.message);
        if (browser) try { await browser.close(); } catch (e) {}
        res.status(500).json({ error: err.message });
    }
});

// === 수정 이력 스크린샷 서빙 ===
app.use('/uploads/fix_log', express.static(FIX_LOG_DIR));


// =========================================================
// 앱(사용자) 스크린샷 매뉴얼 북 API (app-book)
// =========================================================

const activeAppBookRuns = new Map();
const appBookSseClients = new Map();
const APP_BOOK_DIR = path.join(__dirname, '..', 'uploads', 'app-book');
if (!fs.existsSync(APP_BOOK_DIR)) fs.mkdirSync(APP_BOOK_DIR, { recursive: true });
const APP_BOOK_CONFIG = path.join(__dirname, 'app-book-config.json');
// === 매뉴얼 타입별 설정 레지스트리 ===
const MANUAL_CONFIGS = {
    'app-book': { config: path.join(__dirname, 'app-book-config.json'), dir: path.join(__dirname, '..', 'uploads', 'app-book') },
    'admin-book': { config: path.join(__dirname, 'admin-book-config.json'), dir: path.join(__dirname, '..', 'uploads', 'admin-book') },
    'doctor-book': { config: path.join(__dirname, 'doctor-book-config.json'), dir: path.join(__dirname, '..', 'uploads', 'doctor-book') },
    'nurse-book': { config: path.join(__dirname, 'nurse-book-config.json'), dir: path.join(__dirname, '..', 'uploads', 'nurse-book') },
    'patient-book': { config: path.join(__dirname, 'patient-book-config.json'), dir: path.join(__dirname, '..', 'uploads', 'patient-book') },
    'pharmacist-book': { config: path.join(__dirname, 'pharmacist-book-config.json'), dir: path.join(__dirname, '..', 'uploads', 'pharmacist-book') },
    'staff-book': { config: path.join(__dirname, 'staff-book-config.json'), dir: path.join(__dirname, '..', 'uploads', 'staff-book') },
    'editor-book': { config: path.join(__dirname, 'editor-book-config.json'), dir: path.join(__dirname, '..', 'uploads', 'editor-book') },
    'hms-pharmacist-book': { config: path.join(__dirname, 'hms-pharmacist-book-config.json'), dir: path.join(__dirname, '..', 'uploads', 'hms-pharmacist-book') },
    'hms-receptionist-book': { config: path.join(__dirname, 'hms-receptionist-book-config.json'), dir: path.join(__dirname, '..', 'uploads', 'hms-receptionist-book') },
    'hms-nurse-book': { config: path.join(__dirname, 'hms-nurse-book-config.json'), dir: path.join(__dirname, '..', 'uploads', 'hms-nurse-book') },
    'hms-patient-book': { config: path.join(__dirname, 'hms-patient-book-config.json'), dir: path.join(__dirname, '..', 'uploads', 'hms-patient-book') },
    'hms-doctor-book': { config: path.join(__dirname, 'hms-doctor-book-config.json'), dir: path.join(__dirname, '..', 'uploads', 'hms-doctor-book') },
    'hms-admin-book': { config: path.join(__dirname, 'hms-admin-book-config.json'), dir: path.join(__dirname, '..', 'uploads', 'hms-admin-book') },
    'hms-super-admin-book': { config: path.join(__dirname, 'hms-super-admin-book-config.json'), dir: path.join(__dirname, '..', 'uploads', 'hms-super-admin-book') },
};

function getManualConfig(type) {
    const entry = MANUAL_CONFIGS[type || 'app-book'];
    if (!entry) return null;
    if (!fs.existsSync(entry.dir)) fs.mkdirSync(entry.dir, { recursive: true });
    return entry;
}

// 사용 가능한 매뉴얼 타입 목록 API
app.get('/api/app-book/types', (req, res) => {
    const types = Object.keys(MANUAL_CONFIGS).map(key => {
        const configPath = MANUAL_CONFIGS[key].config;
        const exists = fs.existsSync(configPath);
        let title = key;
        if (exists) {
            try {
                const cfg = JSON.parse(fs.readFileSync(configPath, 'utf-8'));
                title = cfg.title || key;
            } catch(e) {}
        }
        return { key, title, configExists: exists };
    });
    res.json(types);
});

function sendAppBookSSE(runId, event, data) {
    const clients = appBookSseClients.get(runId);
    if (!clients) return;
    const msg = `event: ${event}\ndata: ${JSON.stringify(data)}\n\n`;
    for (const [, res] of clients) {
        try { res.write(msg); } catch (e) {}
    }
}

app.post('/api/app-book/generate', async (req, res) => {
    const configKey = req.body.configKey || 'app-book';
    const manualCfg = getManualConfig(configKey);
    if (!manualCfg) return res.status(400).json({ error: 'Unknown config type: ' + configKey });
    if (!fs.existsSync(manualCfg.config)) return res.status(400).json({ error: 'Config file not found: ' + configKey });

    for (const [id, run] of activeAppBookRuns) {
        if (run.status === 'running') {
            return res.status(409).json({ error: '매뉴얼 생성이 이미 실행 중입니다', runId: id });
        }
    }
    const runId = configKey.replace('-book','') + '_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
    const run = { id: runId, status: 'running', startTime: new Date().toISOString(), endTime: null, resultDir: null, configKey };
    activeAppBookRuns.set(runId, run);
    res.json({ runId, status: 'started', configKey });

    (async () => {
        try {
            const result = await generateBook(
                { enableAI: false, configPath: manualCfg.config, outputBaseDir: manualCfg.dir },
                (event, data) => {
                    sendAppBookSSE(runId, event, { ...data, current: data.current || 0, total: data.total || 18 });
                }
            );
            run.resultDir = result.outputDir;
            const bookHTML = renderBookHTML(result.bookData, 'screenshots');
            const bookHtmlPath = path.join(result.outputDir, 'book.html');
            fs.writeFileSync(bookHtmlPath, bookHTML, 'utf-8');

            try {
                const manualAppDir = path.join(MANUAL_DIR, 'screenshot_app');
                if (!fs.existsSync(manualAppDir)) fs.mkdirSync(manualAppDir, { recursive: true });
                const srcSS = path.join(result.outputDir, 'screenshots');
                const dstSS = path.join(manualAppDir, 'screenshots');
                if (fs.existsSync(dstSS)) fs.rmSync(dstSS, { recursive: true });
                fs.cpSync(srcSS, dstSS, { recursive: true });
                fs.copyFileSync(bookHtmlPath, path.join(manualAppDir, 'index.html'));
            } catch (copyErr) { console.error('[AppBook] Manual copy error:', copyErr.message); }

            try {
                delete require.cache[require.resolve('./report-generator')];
                const { generateReport } = require('./report-generator');
                const bookResults = JSON.parse(fs.readFileSync(path.join(result.outputDir, 'results.json'), 'utf-8'));
                const screenshotsDir = path.join(result.outputDir, 'screenshots');
                const systems = [...new Set(bookResults.results.map(r => r.chapterTitle))];
                const convertedResults = bookResults.results.map(r => ({
                    system: r.chapterTitle, page: r.label, url: r.url,
                    status: (r.status || 'pass').toUpperCase(),
                    screenshotPath: r.screenshotFile ? path.join(screenshotsDir, r.screenshotFile) : null,
                    loadTime: r.loadTime || 0, errors: r.errors || {}, analysis: r.analysis || {},
                    description: r.description || '',
                }));
                const convertedSummary = {
                    testDate: bookResults.summary.generatedAt || new Date().toISOString(),
                    total: bookResults.summary.total, pass: bookResults.summary.pass,
                    warn: bookResults.summary.warn, fail: bookResults.summary.fail,
                    systems, elapsedSeconds: bookResults.summary.elapsedSeconds,
                };
                await generateReport(result.outputDir, { summary: convertedSummary, results: convertedResults }, {});
                const reportDocx = path.join(result.outputDir, 'report.docx');
                const bookDocx = path.join(result.outputDir, 'book.docx');
                if (fs.existsSync(reportDocx) && !fs.existsSync(bookDocx)) fs.renameSync(reportDocx, bookDocx);
            } catch (docxErr) { console.error('[AppBook] DOCX error:', docxErr.message); }

            run.status = 'completed';
            run.endTime = new Date().toISOString();
            const dirName = path.basename(result.outputDir);
            sendAppBookSSE(runId, 'complete', {
                status: 'completed', summary: result.summary, resultDir: dirName,
                manualUrl: '/hairwang/gangnamunni/manual/screenshot_app/'
            });
            console.log('[AppBook] 완료: runId=' + runId + ', dir=' + dirName);
        } catch (err) {
            run.status = 'failed';
            run.endTime = new Date().toISOString();
            sendAppBookSSE(runId, 'error_event', { message: 'Fatal: ' + err.message });
            sendAppBookSSE(runId, 'complete', { status: 'failed', error: err.message });
            console.error('[AppBook] Fatal error:', err);
        }
    })();
});

app.get('/api/app-book/progress/:runId', (req, res) => {
    const run = activeAppBookRuns.get(req.params.runId);
    if (!run) return res.status(404).json({ error: 'Not found' });
    res.writeHead(200, {
        'Content-Type': 'text/event-stream', 'Cache-Control': 'no-cache',
        'Connection': 'keep-alive', 'Access-Control-Allow-Origin': '*'
    });
    const clientId = Date.now().toString();
    if (!appBookSseClients.has(req.params.runId)) appBookSseClients.set(req.params.runId, new Map());
    appBookSseClients.get(req.params.runId).set(clientId, res);
    req.on('close', () => { const c = appBookSseClients.get(req.params.runId); if (c) c.delete(clientId); });
});

app.post('/api/app-book/stop/:runId', (req, res) => {
    const run = activeAppBookRuns.get(req.params.runId);
    if (!run) return res.status(404).json({ error: 'Not found' });
    run.aborted = true; run.status = 'stopped';
    res.json({ status: 'stopping' });
});

app.get('/api/app-book/history', (req, res) => {
    const configKey = req.query.configKey || 'app-book';
    const manualCfg = getManualConfig(configKey);
    const baseDir = manualCfg ? manualCfg.dir : APP_BOOK_DIR;
    if (!fs.existsSync(baseDir)) return res.json([]);
    const dirs = fs.readdirSync(baseDir).filter(d => {
        const p = path.join(baseDir, d);
        return fs.statSync(p).isDirectory() && fs.existsSync(path.join(p, 'results.json'));
    }).sort().reverse();
    const history = dirs.map(dir => {
        try {
            const results = JSON.parse(fs.readFileSync(path.join(baseDir, dir, 'results.json'), 'utf-8'));
            const hasHtml = fs.existsSync(path.join(baseDir, dir, 'book.html'));
            const hasDocx = fs.existsSync(path.join(baseDir, dir, 'book.docx'));
            return { dir, summary: results.summary || {}, hasHtml, hasDocx, createdAt: results.summary?.generatedAt || dir };
        } catch (e) { return { dir, summary: {}, hasHtml: false, hasDocx: false, createdAt: dir }; }
    });
    res.json(history);
});

app.get('/api/app-book/preview/:dir', (req, res, next) => {
    const configKey = req.query.configKey || 'app-book';
    const manualCfg = getManualConfig(configKey);
    const baseDir = manualCfg ? manualCfg.dir : APP_BOOK_DIR;
    if (req.path.endsWith('/')) return next();
    res.redirect(req.path + '/');
});
app.get('/api/app-book/preview/:dir/', (req, res) => {
    const configKey = req.query.configKey || 'app-book';
    const manualCfg = getManualConfig(configKey);
    const baseDir = manualCfg ? manualCfg.dir : APP_BOOK_DIR;
    const htmlPath = path.join(baseDir, req.params.dir, 'book.html');
    if (!fs.existsSync(htmlPath)) return res.status(404).send('Not found');
    res.sendFile(htmlPath);
});

app.get('/api/app-book/preview/:dir/screenshots/:file', (req, res) => {
    const configKey = req.query.configKey || 'app-book';
    const manualCfg = getManualConfig(configKey);
    const baseDir = manualCfg ? manualCfg.dir : APP_BOOK_DIR;
    const filePath = path.join(baseDir, req.params.dir, 'screenshots', req.params.file);
    if (!fs.existsSync(filePath)) return res.status(404).send('Not found');
    res.sendFile(filePath);
});

app.get('/api/app-book/download/:dir/:file', (req, res) => {
    const configKey = req.query.configKey || 'app-book';
    const manualCfg = getManualConfig(configKey);
    const baseDir = manualCfg ? manualCfg.dir : APP_BOOK_DIR;
    const filePath = path.join(baseDir, req.params.dir, req.params.file);
    if (!fs.existsSync(filePath)) return res.status(404).json({ error: 'File not found' });
    res.download(filePath, req.params.file.replace('book', '서울미녀_앱매뉴얼_' + req.params.dir));
});


// === 앱북 Word/PDF 내보내기 ===
app.get('/api/app-book/export-word/:dir', async (req, res) => {
    const configKey = req.query.configKey || 'app-book';
    const manualCfg = getManualConfig(configKey);
    const baseDir = manualCfg ? manualCfg.dir : APP_BOOK_DIR;
    const dirPath = path.join(baseDir, req.params.dir);
    if (!fs.existsSync(dirPath)) return res.status(404).json({ error: 'Book not found' });
    const docxPath = path.join(dirPath, 'book.docx');
    if (fs.existsSync(docxPath)) {
        return res.download(docxPath, '서울미녀_앱매뉴얼_' + req.params.dir + '.docx');
    }
    // DOCX가 없으면 생성 시도
    try {
        delete require.cache[require.resolve('./report-generator')];
        const { generateReport } = require('./report-generator');
        const results = JSON.parse(fs.readFileSync(path.join(dirPath, 'results.json'), 'utf-8'));
        const screenshotsDir = path.join(dirPath, 'screenshots');
        const systems = [...new Set(results.results.map(r => r.chapterTitle))];
        const convertedResults = results.results.map(r => ({
            system: r.chapterTitle, page: r.label, url: r.url,
            status: (r.status || 'pass').toUpperCase(),
            screenshotPath: r.screenshotFile ? path.join(screenshotsDir, r.screenshotFile) : null,
            loadTime: r.loadTime || 0, errors: r.errors || {}, analysis: r.analysis || {},
            description: r.description || '',
        }));
        const convertedSummary = {
            testDate: results.summary.generatedAt || new Date().toISOString(),
            total: results.summary.total, pass: results.summary.pass,
            warn: results.summary.warn, fail: results.summary.fail,
            systems, elapsedSeconds: results.summary.elapsedSeconds,
        };
        await generateReport(dirPath, { summary: convertedSummary, results: convertedResults }, {});
        const reportDocx = path.join(dirPath, 'report.docx');
        if (fs.existsSync(reportDocx) && !fs.existsSync(docxPath)) fs.renameSync(reportDocx, docxPath);
        if (fs.existsSync(docxPath)) return res.download(docxPath, '서울미녀_앱매뉴얼_' + req.params.dir + '.docx');
        res.status(500).json({ error: 'DOCX 생성 실패' });
    } catch (err) {
        console.error('[AppBook] Export word error:', err.message);
        res.status(500).json({ error: err.message });
    }
});

app.get('/api/app-book/export-pdf/:dir', async (req, res) => {
    const configKey = req.query.configKey || 'app-book';
    const manualCfg = getManualConfig(configKey);
    const baseDir = manualCfg ? manualCfg.dir : APP_BOOK_DIR;
    const dirPath = path.join(baseDir, req.params.dir);
    if (!fs.existsSync(dirPath)) return res.status(404).json({ error: 'Book not found' });
    const pdfPath = path.join(dirPath, 'book.pdf');
    if (fs.existsSync(pdfPath)) {
        return res.download(pdfPath, '서울미녀_앱매뉴얼_' + req.params.dir + '.pdf');
    }
    // PDF 생성: book.html을 Playwright로 PDF 변환
    try {
        const htmlPath = path.join(dirPath, 'book.html');
        if (!fs.existsSync(htmlPath)) return res.status(404).json({ error: 'book.html not found' });
        const { chromium } = require('playwright');
        const browser = await chromium.launch({ headless: true });
        const page = await browser.newPage();
        await page.goto('file://' + htmlPath, { waitUntil: 'networkidle' });
        await page.pdf({ path: pdfPath, format: 'A4', printBackground: true, margin: { top: '10mm', bottom: '10mm', left: '10mm', right: '10mm' } });
        await browser.close();
        if (fs.existsSync(pdfPath)) return res.download(pdfPath, '서울미녀_앱매뉴얼_' + req.params.dir + '.pdf');
        res.status(500).json({ error: 'PDF 생성 실패' });
    } catch (err) {
        console.error('[AppBook] Export PDF error:', err.message);
        res.status(500).json({ error: err.message });
    }
});

app.delete('/api/app-book/history/:dir', (req, res) => {
    const configKey = req.query.configKey || 'app-book';
    const manualCfg = getManualConfig(configKey);
    const baseDir = manualCfg ? manualCfg.dir : APP_BOOK_DIR;
    const dirPath = path.join(baseDir, req.params.dir);
    if (!fs.existsSync(dirPath)) return res.status(404).json({ error: 'Not found' });
    try { fs.rmSync(dirPath, { recursive: true }); res.json({ success: true }); }
    catch (err) { res.status(500).json({ error: err.message }); }
});


// === 서버 시작 ===
app.listen(PORT, BIND_HOST, () => {
    console.log(`[Auto Manual Engine] http://${BIND_HOST}:${PORT}`);
    console.log(`[Auto Test API] /api/auto-test/*`);
    console.log(`[Book API] /api/book/*`);
    console.log(`[App Book API] /api/app-book/*`);
    console.log(`[Upload Dir] ${UPLOAD_DIR}`);
});
