/**
 * DB Reporter — E2E 테스트 결과를 MariaDB에 저장
 */
const mysql = require('mysql2/promise');
const config = require('../e2e-config.json');

let pool = null;

function getPool() {
    if (!pool) {
        pool = mysql.createPool({
            host: config.db.host,
            user: config.db.user,
            password: config.db.password,
            database: config.db.database,
            port: config.db.port,
            waitForConnections: true,
            connectionLimit: 5,
        });
    }
    return pool;
}

async function createRun(runId, testsSelected, options = {}) {
    const db = getPool();
    await db.execute(
        'INSERT INTO gangnamunni_e2e_runs (run_id, tests_selected, total_tests, options) VALUES (?, ?, ?, ?)',
        [runId, JSON.stringify(testsSelected), testsSelected.length, JSON.stringify(options)]
    );
}

async function updateResult(runId, testId, testName, category, status, data = {}) {
    const db = getPool();
    // UPSERT
    await db.execute(
        `INSERT INTO gangnamunni_e2e_results (run_id, test_id, test_name, category, status, error_msg, error_stack, screenshot_path, fix_suggestion, fix_link, duration_ms, metadata)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE status=VALUES(status), error_msg=VALUES(error_msg), error_stack=VALUES(error_stack), screenshot_path=VALUES(screenshot_path), fix_suggestion=VALUES(fix_suggestion), fix_link=VALUES(fix_link), duration_ms=VALUES(duration_ms), metadata=VALUES(metadata)`,
        [runId, testId, testName, category, status,
         data.error_msg || null, data.error_stack || null, data.screenshot_path || null,
         data.fix_suggestion || null, data.fix_link || null, data.duration_ms || 0,
         data.metadata ? JSON.stringify(data.metadata) : null]
    );
}

async function finishRun(runId, summary) {
    const db = getPool();
    await db.execute(
        'UPDATE gangnamunni_e2e_runs SET status=?, passed=?, failed=?, warned=?, skipped=?, finished_at=NOW(), duration_ms=? WHERE run_id=?',
        [summary.status || 'completed', summary.passed, summary.failed, summary.warned || 0, summary.skipped || 0, summary.duration_ms, runId]
    );
}

async function checkAborted(runId) {
    const db = getPool();
    const [rows] = await db.execute('SELECT status FROM gangnamunni_e2e_runs WHERE run_id=?', [runId]);
    return rows.length > 0 && rows[0].status === 'aborted';
}

async function close() {
    if (pool) { await pool.end(); pool = null; }
}

module.exports = { createRun, updateResult, finishRun, checkAborted, close, getPool };
