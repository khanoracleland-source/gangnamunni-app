'use strict';

const crypto = require('crypto');
const fs = require('fs');
const path = require('path');

const SECRET_PATH = path.resolve(__dirname, '..', '..', 'data', 'session', 'auto-test-api-secret');
const AUTO_TEST_ENABLED = String(process.env.AUTO_TEST_ENABLED || '').trim().toLowerCase() === 'true';

function loadSecret() {
    const envSecret = String(process.env.AUTO_TEST_API_SECRET || '').trim();
    if (envSecret.length >= 32) return envSecret;

    fs.mkdirSync(path.dirname(SECRET_PATH), { recursive: true, mode: 0o700 });
    if (!fs.existsSync(SECRET_PATH)) {
        try {
            fs.writeFileSync(SECRET_PATH, crypto.randomBytes(48).toString('hex'), { flag: 'wx', mode: 0o600 });
        } catch (error) {
            if (error.code !== 'EEXIST') throw error;
        }
    }
    const secret = fs.readFileSync(SECRET_PATH, 'utf8').trim();
    if (secret.length < 32) throw new Error('AUTO_TEST_API_SECRET is missing or invalid');
    return secret;
}

const SECRET = AUTO_TEST_ENABLED ? loadSecret() : null;

function base64urlDecode(value) {
    const padding = '='.repeat((4 - value.length % 4) % 4);
    return Buffer.from(value.replace(/-/g, '+').replace(/_/g, '/') + padding, 'base64');
}

function parseCookies(header) {
    const result = {};
    String(header || '').split(';').forEach(part => {
        const index = part.indexOf('=');
        if (index <= 0) return;
        try {
            result[part.slice(0, index).trim()] = decodeURIComponent(part.slice(index + 1).trim());
        } catch (error) {
            // 잘못 인코딩된 쿠키 때문에 전체 요청이 500 오류로 실패하지 않도록 한다.
        }
    });
    return result;
}

function verifyToken(token, scope) {
    const parts = String(token || '').split('.');
    if (parts.length !== 2) return null;
    const expected = crypto.createHmac('sha256', SECRET).update(parts[0]).digest();
    let provided;
    try { provided = base64urlDecode(parts[1]); } catch (error) { return null; }
    if (provided.length !== expected.length || !crypto.timingSafeEqual(provided, expected)) return null;

    let payload;
    try { payload = JSON.parse(base64urlDecode(parts[0]).toString('utf8')); } catch (error) { return null; }
    const now = Math.floor(Date.now() / 1000);
    if (!payload || payload.scope !== scope || !Number.isInteger(payload.exp) || payload.exp < now) return null;
    if (!Number.isInteger(payload.iat) || payload.iat > now + 60 || payload.iat < now - 3600) return null;
    if (!Number.isInteger(payload.sub) || payload.sub <= 0) return null;
    return payload;
}

function configuredOrigins() {
    const configured = String(process.env.AUTO_TEST_ALLOWED_ORIGINS || 'https://hms.seoulbeauty.app');
    return new Set(configured.split(',').map(value => value.trim()).filter(Boolean));
}

function createAutoTestSecurity({ scope, cookieName }) {
    const allowedOrigins = configuredOrigins();
    const rateBuckets = new Map();

    function originAllowed(origin) {
        return !origin || allowedOrigins.has(origin);
    }

    function middleware(req, res, next) {
        if (!AUTO_TEST_ENABLED) {
            res.setHeader('Cache-Control', 'no-store');
            res.setHeader('Retry-After', '3600');
            return res.status(503).json({ error: '자동 테스트 기능이 일시 중지되었습니다.' });
        }

        const origin = req.get('origin');
        if (!originAllowed(origin)) return res.status(403).json({ error: 'Origin not allowed' });

        res.setHeader('Cache-Control', 'no-store');
        res.setHeader('X-Content-Type-Options', 'nosniff');

        const cookies = parseCookies(req.headers.cookie);
        const auth = verifyToken(cookies[cookieName], scope);
        if (!auth) return res.status(401).json({ error: 'Admin authentication required' });
        req.autoTestAdmin = auth;

        if (!['GET', 'HEAD', 'OPTIONS'].includes(req.method)) {
            const csrf = String(req.get('x-csrf-token') || '');
            const csrfHash = crypto.createHash('sha256').update(csrf).digest('hex');
            const expectedHash = String(auth.csrf || '');
            const a = Buffer.from(csrfHash);
            const b = Buffer.from(expectedHash);
            if (!csrf || a.length !== b.length || !crypto.timingSafeEqual(a, b)) {
                return res.status(403).json({ error: 'Invalid CSRF token' });
            }

        }

        const rateClass = req.path === '/run'
            ? 'run'
            : req.path.startsWith('/download/')
                ? 'download'
                : ['GET', 'HEAD'].includes(req.method) ? 'read' : 'write';
        const limit = rateClass === 'run' ? 2 : rateClass === 'download' ? 10 : rateClass === 'write' ? 30 : 120;
        const key = `${auth.sub}:${rateClass}`;
        const now = Date.now();
        const bucket = (rateBuckets.get(key) || []).filter(ts => now - ts < 60000);
        if (bucket.length >= limit) return res.status(429).json({ error: 'Too many requests' });
        bucket.push(now);
        rateBuckets.set(key, bucket);
        if (rateBuckets.size > 1000) {
            for (const [bucketKey, timestamps] of rateBuckets) {
                if (!timestamps.some(ts => now - ts < 60000)) rateBuckets.delete(bucketKey);
            }
        }
        next();
    }

    const corsOptions = {
        origin(origin, callback) {
            // 거부 응답은 미들웨어가 403으로 처리하고 CORS 오류가 500으로 바뀌지 않도록 한다.
            callback(null, originAllowed(origin) ? Boolean(origin) : false);
        },
        credentials: true,
        methods: ['GET', 'HEAD', 'POST', 'DELETE', 'OPTIONS'],
        allowedHeaders: ['Content-Type', 'X-CSRF-Token'],
    };

    return { middleware, corsOptions };
}

function safeSegment(value, pattern = /^[a-zA-Z0-9_-]+$/) {
    return typeof value === 'string' && pattern.test(value);
}

function safePath(baseDir, ...segments) {
    const base = path.resolve(baseDir);
    const resolved = path.resolve(base, ...segments);
    if (resolved !== base && !resolved.startsWith(base + path.sep)) throw new Error('Invalid path');
    return resolved;
}

module.exports = { createAutoTestSecurity, safeSegment, safePath, AUTO_TEST_ENABLED };
