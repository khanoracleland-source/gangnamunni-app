/**
 * report-generator.js - 강화된 테스트 보고서 생성기
 *
 * test-runner.js의 결과(results.json + screenshots)를 기반으로
 * 전문적인 Word(DOCX) 문서와 PDF를 생성
 *
 * 보고서 구성:
 *   1. 표지 (제목, 날짜, 버전)
 *   2. 요약 테이블 (전체 통과/실패 집계)
 *   3. 시스템별 페이지 상세 (스크린샷 + 에러 정보 + 수정 프롬프트)
 *   4. 에러 상세 목록 (실패한 페이지만)
 *
 * 사용법:
 *   const { generateReport } = require('./report-generator');
 *   await generateReport(outputDir, resultsJson);
 *   await generateReport(outputDir, resultsJson, notes); // 수정 프롬프트 포함
 *
 * @since 2026-02-13
 * @version 1.0.0
 */

const {
    Document, Packer, Paragraph, TextRun, ImageRun,
    HeadingLevel, AlignmentType, PageBreak,
    Table, TableRow, TableCell, WidthType, BorderStyle,
    Header, Footer, PageNumber, NumberFormat,
    ShadingType
} = require('docx');
const fs = require('fs');
const path = require('path');

// =========================================================
// 색상/스타일 상수
// =========================================================
const COLORS = {
    primary: '2F5496',     // 진한 파란색 (헤더)
    pass: '198754',        // 초록 (통과)
    warn: 'FFC107',        // 노랑 (경고)
    fail: 'DC3545',        // 빨강 (실패)
    passBg: 'C6EFCE',      // 연초록 배경
    warnBg: 'FFEB9C',      // 연노랑 배경
    failBg: 'FFC7CE',      // 연빨강 배경
    lightGray: 'F2F2F2',   // 연회색 (짝수행)
    white: 'FFFFFF',
    black: '000000',
    darkGray: '333333',
};

// =========================================================
// 메인 함수: 보고서 생성
// =========================================================

/**
 * DOCX 보고서 생성 (수정 프롬프트 포함 가능)
 *
 * @param {string} outputDir - 테스트 결과 디렉토리 경로
 * @param {object} resultsJson - { summary, results } 구조
 * @param {object|null} notes - { "페이지URL": "수정 프롬프트 텍스트" } (optional)
 * @returns {Promise<{docxPath: string}>}
 */
async function generateReport(outputDir, resultsJson, notes = null) {
    const { summary, results } = resultsJson;
    const testDate = new Date(summary.testDate);
    const dateStr = testDate.toLocaleDateString('ko-KR', {
        year: 'numeric', month: 'long', day: 'numeric'
    });

    // ── DOCX 생성 ──
    const sections = [];

    // 표지 섹션
    sections.push(createCoverSection(summary, dateStr));

    // 요약 섹션
    sections.push(createSummarySection(summary, results));

    // 시스템별 상세 섹션 (수정 프롬프트 포함)
    const systemGroups = groupBySystem(results);
    for (const [systemName, pages] of Object.entries(systemGroups)) {
        sections.push(createSystemSection(systemName, pages, notes));
    }

    // 에러 상세 섹션 (실패 항목이 있을 때만)
    const failedResults = results.filter(r => r.status === 'FAIL');
    if (failedResults.length > 0) {
        sections.push(createErrorDetailSection(failedResults));
    }

    const doc = new Document({
        sections,
        creator: 'Seoul Beauty Auto Test System',
        title: '서울미녀 플랫폼 - 시스템 테스트 보고서',
        description: `자동 테스트 보고서 - ${dateStr}`,
        styles: {
            default: {
                document: {
                    run: {
                        font: 'Malgun Gothic',
                        size: 20, // 10pt
                    },
                },
            },
        },
    });

    // DOCX 파일 저장
    const docxPath = path.join(outputDir, 'report.docx');
    const docxBuffer = await Packer.toBuffer(doc);
    fs.writeFileSync(docxPath, docxBuffer);

    return { docxPath };
}

// =========================================================
// 섹션 1: 표지
// =========================================================

/**
 * 보고서 표지 섹션 생성
 * - 제목, 부제, 일시, 버전, 시스템 수, 페이지 수
 */
function createCoverSection(summary, dateStr) {
    return {
        properties: {
            page: {
                margin: { top: 1440, right: 1440, bottom: 1440, left: 1440 },
            },
        },
        headers: {
            default: new Header({
                children: [
                    new Paragraph({
                        alignment: AlignmentType.RIGHT,
                        children: [
                            new TextRun({
                                text: 'Seoul Beauty Platform',
                                font: 'Malgun Gothic',
                                size: 16,
                                color: '999999',
                            }),
                        ],
                    }),
                ],
            }),
        },
        footers: {
            default: new Footer({
                children: [
                    new Paragraph({
                        alignment: AlignmentType.CENTER,
                        children: [
                            new TextRun({ text: 'Page ', font: 'Malgun Gothic', size: 16 }),
                            new TextRun({ children: [PageNumber.CURRENT], font: 'Malgun Gothic', size: 16 }),
                            new TextRun({ text: ' / ', font: 'Malgun Gothic', size: 16 }),
                            new TextRun({ children: [PageNumber.TOTAL_PAGES], font: 'Malgun Gothic', size: 16 }),
                        ],
                    }),
                ],
            }),
        },
        children: [
            // 상단 여백
            ...Array(8).fill(new Paragraph({ children: [] })),

            // 제목
            new Paragraph({
                alignment: AlignmentType.CENTER,
                spacing: { after: 200 },
                children: [
                    new TextRun({
                        text: '서울미녀 플랫폼',
                        bold: true,
                        size: 56,
                        font: 'Malgun Gothic',
                        color: COLORS.primary,
                    }),
                ],
            }),

            // 부제
            new Paragraph({
                alignment: AlignmentType.CENTER,
                spacing: { after: 400 },
                children: [
                    new TextRun({
                        text: '시스템 테스트 보고서',
                        bold: true,
                        size: 40,
                        font: 'Malgun Gothic',
                        color: COLORS.darkGray,
                    }),
                ],
            }),

            // 구분선
            new Paragraph({
                alignment: AlignmentType.CENTER,
                spacing: { after: 400 },
                children: [
                    new TextRun({
                        text: '━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━',
                        color: COLORS.primary,
                        size: 20,
                    }),
                ],
            }),

            // 정보
            createInfoLine('점검일시', dateStr),
            createInfoLine('대상 서버', 'contabo1 (5.104.82.35)'),
            createInfoLine('도메인', 'https://hms.seoulbeauty.app'),
            createInfoLine('테스트 시스템', `${summary.systems.length}개 시스템`),
            createInfoLine('테스트 페이지', `${summary.totalPages}개 페이지`),
            createInfoLine('소요 시간', `${summary.elapsedSeconds}초`),
            createInfoLine('결과',
                `통과 ${summary.pass} / 경고 ${summary.warn} / 실패 ${summary.fail}`),

            // 하단 여백
            ...Array(4).fill(new Paragraph({ children: [] })),

            // 생성자
            new Paragraph({
                alignment: AlignmentType.CENTER,
                children: [
                    new TextRun({
                        text: 'Seoul Beauty Dev Team',
                        size: 20,
                        font: 'Malgun Gothic',
                        color: '999999',
                    }),
                ],
            }),
            new Paragraph({
                alignment: AlignmentType.CENTER,
                children: [
                    new TextRun({
                        text: 'Automated by Playwright Test Engine v1.0',
                        size: 16,
                        font: 'Malgun Gothic',
                        color: 'BBBBBB',
                        italics: true,
                    }),
                ],
            }),
        ],
    };
}

/**
 * 정보 라인 생성 (라벨: 값)
 */
function createInfoLine(label, value) {
    return new Paragraph({
        alignment: AlignmentType.CENTER,
        spacing: { after: 100 },
        children: [
            new TextRun({
                text: `${label}: `,
                bold: true,
                size: 22,
                font: 'Malgun Gothic',
                color: COLORS.darkGray,
            }),
            new TextRun({
                text: value,
                size: 22,
                font: 'Malgun Gothic',
                color: '666666',
            }),
        ],
    });
}

// =========================================================
// 섹션 2: 요약 테이블
// =========================================================

/**
 * 테스트 요약 섹션 - 전체 결과 테이블 + 시스템별 통계
 */
function createSummarySection(summary, results) {
    const children = [];

    // 페이지 나누기
    children.push(new Paragraph({ children: [new PageBreak()] }));

    // 섹션 제목
    children.push(new Paragraph({
        heading: HeadingLevel.HEADING_1,
        spacing: { after: 200 },
        children: [
            new TextRun({
                text: '1. 테스트 결과 요약',
                bold: true,
                size: 32,
                font: 'Malgun Gothic',
                color: COLORS.primary,
            }),
        ],
    }));

    // 전체 통과율 표시
    const passRate = summary.totalPages > 0
        ? ((summary.pass / summary.totalPages) * 100).toFixed(1)
        : '0.0';

    children.push(new Paragraph({
        spacing: { after: 200 },
        children: [
            new TextRun({ text: '전체 통과율: ', size: 24, font: 'Malgun Gothic', bold: true }),
            new TextRun({
                text: `${passRate}%`,
                size: 32,
                font: 'Malgun Gothic',
                bold: true,
                color: summary.fail > 0 ? COLORS.fail : COLORS.pass,
            }),
            new TextRun({
                text: `  (${summary.pass}/${summary.totalPages} 페이지)`,
                size: 20,
                font: 'Malgun Gothic',
                color: '666666',
            }),
        ],
    }));

    // 전체 결과 테이블
    children.push(createResultTable(results));

    return {
        properties: {},
        children,
    };
}

/**
 * 전체 결과 요약 테이블 생성
 * 컬럼: #, 시스템, 페이지, HTTP, 시간, 상태, 요소, 에러요약
 */
function createResultTable(results) {
    // 헤더 행
    const headerRow = new TableRow({
        tableHeader: true,
        children: ['#', '시스템', '페이지', 'HTTP', '시간', '상태', '요소(링크/이미지/폼)', '에러 요약'].map(text =>
            new TableCell({
                shading: { fill: COLORS.primary, type: ShadingType.CLEAR },
                children: [new Paragraph({
                    alignment: AlignmentType.CENTER,
                    children: [new TextRun({
                        text, bold: true, size: 16, font: 'Malgun Gothic', color: COLORS.white
                    })],
                })],
            })
        ),
    });

    // 데이터 행
    const dataRows = results.map((r, i) => {
        const statusColor = r.status === 'PASS' ? COLORS.pass :
                           r.status === 'WARN' ? 'B8860B' : COLORS.fail;
        const statusBg = r.status === 'PASS' ? COLORS.passBg :
                        r.status === 'WARN' ? COLORS.warnBg : COLORS.failBg;
        const rowBg = i % 2 === 0 ? COLORS.white : COLORS.lightGray;

        // 페이지 분석 요소 수
        const a = r.pageAnalysis || {};
        const elemInfo = a.linkCount !== undefined
            ? `${a.linkCount || 0}/${a.imageCount || 0}/${a.formCount || 0}`
            : '-';

        return new TableRow({
            children: [
                // #
                createTableCell(String(i + 1), { align: 'center', bg: rowBg }),
                // 시스템 (짧게)
                createTableCell(r.system.replace(/ \(.*\)/, ''), { bg: rowBg, size: 16 }),
                // 페이지명
                createTableCell(r.page, { bg: rowBg, size: 16 }),
                // HTTP 상태
                createTableCell(String(r.httpStatus || '-'), {
                    align: 'center', bg: rowBg,
                    color: r.httpStatus >= 400 ? COLORS.fail : COLORS.darkGray
                }),
                // 소요시간
                createTableCell(r.loadTimeMs ? `${r.loadTimeMs}ms` : '-', { align: 'right', bg: rowBg, size: 16 }),
                // 상태
                createTableCell(r.status, {
                    align: 'center', bg: statusBg, color: statusColor, bold: true
                }),
                // 요소 수 (링크/이미지/폼)
                createTableCell(elemInfo, { align: 'center', bg: rowBg, size: 16 }),
                // 에러 요약
                createTableCell(r.errors?.summary || '-', {
                    bg: rowBg,
                    size: 14,
                    color: r.status === 'FAIL' ? COLORS.fail : COLORS.darkGray
                }),
            ],
        });
    });

    return new Table({
        width: { size: 100, type: WidthType.PERCENTAGE },
        rows: [headerRow, ...dataRows],
    });
}

// =========================================================
// 섹션 3: 시스템별 상세
// =========================================================

/**
 * 시스템별 페이지 상세 섹션
 * - 각 페이지의 스크린샷 + URL + 설명 + 에러 정보
 */
function createSystemSection(systemName, pages, notes = null) {
    const children = [];

    // 페이지 나누기 + 시스템 제목
    children.push(new Paragraph({ children: [new PageBreak()] }));
    children.push(new Paragraph({
        heading: HeadingLevel.HEADING_1,
        spacing: { after: 200 },
        children: [
            new TextRun({
                text: systemName,
                bold: true,
                size: 32,
                font: 'Malgun Gothic',
                color: COLORS.primary,
            }),
        ],
    }));

    // 시스템 통계
    const pass = pages.filter(p => p.status === 'PASS').length;
    const fail = pages.filter(p => p.status === 'FAIL').length;
    const warn = pages.filter(p => p.status === 'WARN').length;

    children.push(new Paragraph({
        spacing: { after: 300 },
        children: [
            new TextRun({ text: `총 ${pages.length}페이지: `, size: 20, font: 'Malgun Gothic' }),
            new TextRun({ text: `통과 ${pass}`, size: 20, font: 'Malgun Gothic', color: COLORS.pass, bold: true }),
            new TextRun({ text: ` / 경고 ${warn}`, size: 20, font: 'Malgun Gothic', color: 'B8860B' }),
            new TextRun({ text: ` / 실패 ${fail}`, size: 20, font: 'Malgun Gothic', color: COLORS.fail }),
        ],
    }));

    // 페이지별 상세
    for (let i = 0; i < pages.length; i++) {
        const page = pages[i];

        // 페이지 제목
        const statusLabel = page.status === 'PASS' ? '[통과]' :
                           page.status === 'WARN' ? '[경고]' : '[실패]';
        const statusColor = page.status === 'PASS' ? COLORS.pass :
                           page.status === 'WARN' ? 'B8860B' : COLORS.fail;

        children.push(new Paragraph({
            heading: HeadingLevel.HEADING_2,
            spacing: { before: 300, after: 100 },
            children: [
                new TextRun({
                    text: `${i + 1}. ${page.page} `,
                    size: 26,
                    font: 'Malgun Gothic',
                    bold: true,
                }),
                new TextRun({
                    text: statusLabel,
                    size: 22,
                    font: 'Malgun Gothic',
                    bold: true,
                    color: statusColor,
                }),
            ],
        }));

        // URL + 설명 + HTTP + 소요시간
        children.push(new Paragraph({
            spacing: { after: 50 },
            children: [
                new TextRun({ text: 'URL: ', bold: true, size: 18, font: 'Malgun Gothic', color: '666666' }),
                new TextRun({ text: page.url, size: 18, font: 'Malgun Gothic', color: COLORS.primary }),
            ],
        }));

        if (page.description) {
            children.push(new Paragraph({
                spacing: { after: 50 },
                children: [
                    new TextRun({ text: '설명: ', bold: true, size: 18, font: 'Malgun Gothic', color: '666666' }),
                    new TextRun({ text: page.description, size: 18, font: 'Malgun Gothic' }),
                ],
            }));
        }

        children.push(new Paragraph({
            spacing: { after: 100 },
            children: [
                new TextRun({ text: `HTTP ${page.httpStatus} | ${page.loadTimeMs}ms`, size: 18, font: 'Malgun Gothic', color: '999999' }),
            ],
        }));

        // 페이지 분석 결과 (상세 정보)
        if (page.pageAnalysis && !page.pageAnalysis.error) {
            const a = page.pageAnalysis;
            const analysisItems = [];
            if (a.pageTitle) analysisItems.push(`제목: ${a.pageTitle}`);
            analysisItems.push(`링크 ${a.linkCount || 0}개 | 이미지 ${a.imageCount || 0}개 | 폼 ${a.formCount || 0}개 | 테이블 ${a.tableCount || 0}개 | 버튼 ${a.buttonCount || 0}개`);
            analysisItems.push(`DOM노드 ${a.domNodeCount || 0}개 | JS ${a.scriptCount || 0}개 | CSS ${a.stylesheetCount || 0}개`);
            if (a.missingAltImages > 0) analysisItems.push(`alt 누락 이미지: ${a.missingAltImages}개`);
            if ((a.contentLength || 0) < 100) analysisItems.push(`텍스트가 매우 짧음 (${a.contentLength}자)`);

            children.push(new Paragraph({
                spacing: { after: 50 },
                shading: { fill: 'F0F4FF', type: ShadingType.CLEAR },
                children: analysisItems.flatMap((item, idx) => [
                    ...(idx > 0 ? [new TextRun({ text: '\n', font: 'Malgun Gothic' })] : []),
                    new TextRun({
                        text: item,
                        size: 16,
                        font: 'Malgun Gothic',
                        color: '555555',
                    }),
                ]),
            }));
        }

        // 스크린샷 이미지 삽입
        if (page.screenshotPath && fs.existsSync(page.screenshotPath)) {
            try {
                const imgBuffer = fs.readFileSync(page.screenshotPath);
                children.push(new Paragraph({
                    spacing: { after: 200 },
                    children: [
                        new ImageRun({
                            data: imgBuffer,
                            transformation: {
                                width: 680,  // A4 기준 가로폭에 맞춤
                                height: 382, // 16:9 비율
                            },
                            type: 'png',
                        }),
                    ],
                }));
            } catch (e) {
                children.push(new Paragraph({
                    children: [
                        new TextRun({
                            text: `[스크린샷 로드 실패: ${e.message}]`,
                            italics: true, color: COLORS.fail, size: 18, font: 'Malgun Gothic',
                        }),
                    ],
                }));
            }
        }

        // 에러 정보 (있을 때만)
        if (page.errors?.hasErrors || page.status !== 'PASS') {
            children.push(createErrorBox(page));
        }

        // 수정 프롬프트 (notes가 있을 때)
        const noteKey = page.url || '';
        if (notes && notes[noteKey]) {
            children.push(createNoteBox(notes[noteKey]));
        }
    }

    return { properties: {}, children };
}

// =========================================================
// 섹션 4: 에러 상세
// =========================================================

/**
 * 에러 상세 목록 섹션 (FAIL 항목만)
 */
function createErrorDetailSection(failedResults) {
    const children = [];

    children.push(new Paragraph({ children: [new PageBreak()] }));
    children.push(new Paragraph({
        heading: HeadingLevel.HEADING_1,
        spacing: { after: 200 },
        children: [
            new TextRun({
                text: '에러 상세 목록',
                bold: true, size: 32, font: 'Malgun Gothic', color: COLORS.fail,
            }),
        ],
    }));

    for (const result of failedResults) {
        children.push(new Paragraph({
            heading: HeadingLevel.HEADING_2,
            spacing: { before: 200, after: 100 },
            children: [
                new TextRun({
                    text: `${result.system} > ${result.page}`,
                    bold: true, size: 24, font: 'Malgun Gothic',
                }),
            ],
        }));

        children.push(new Paragraph({
            children: [
                new TextRun({ text: result.url, size: 18, font: 'Malgun Gothic', color: '666666' }),
            ],
        }));

        // HTTP 에러
        if (result.errors?.httpErrors?.length > 0) {
            for (const err of result.errors.httpErrors) {
                children.push(createErrorLine(`HTTP ${err.status}`, `${err.url} (${err.resourceType})`));
            }
        }

        // PHP 에러
        if (result.errors?.phpErrors?.length > 0) {
            for (const err of result.errors.phpErrors) {
                children.push(createErrorLine(`PHP [${err.severity}]`, err.message));
            }
        }

        // JS 에러
        if (result.errors?.jsErrors?.length > 0) {
            for (const err of result.errors.jsErrors) {
                children.push(createErrorLine('JS Error', `${err.message} (${err.source}:${err.line})`));
            }
        }

        // 페이지 크래시
        if (result.errors?.pageErrors?.length > 0) {
            for (const err of result.errors.pageErrors) {
                children.push(createErrorLine('Page Crash', `${err.name}: ${err.message}`));
            }
        }
    }

    return { properties: {}, children };
}

// =========================================================
// 헬퍼 함수
// =========================================================

/**
 * 에러 박스 (연분홍 배경 + 에러 목록)
 */
function createErrorBox(page) {
    const errors = page.errors || {};
    const lines = [];

    if (errors.summary && errors.summary !== '에러 없음') {
        lines.push(`요약: ${errors.summary}`);
    }

    // 주요 에러만 표시 (최대 5개)
    const allErrors = [
        ...(errors.httpErrors || []).map(e => `HTTP ${e.status}: ${e.url}`),
        ...(errors.phpErrors || []).map(e => `PHP: ${e.message}`),
        ...(errors.jsErrors || []).map(e => `JS: ${e.message}`),
        ...(errors.pageErrors || []).map(e => `Crash: ${e.message}`),
    ];

    if (allErrors.length > 5) {
        lines.push(...allErrors.slice(0, 5));
        lines.push(`... 외 ${allErrors.length - 5}건`);
    } else {
        lines.push(...allErrors);
    }

    return new Paragraph({
        spacing: { after: 200 },
        shading: { fill: COLORS.failBg, type: ShadingType.CLEAR },
        children: lines.flatMap((line, i) => [
            ...(i > 0 ? [new TextRun({ text: '\n', font: 'Malgun Gothic' })] : []),
            new TextRun({
                text: line.substring(0, 200),
                size: 16,
                font: 'Malgun Gothic',
                color: COLORS.fail,
            }),
        ]),
    });
}

/**
 * 에러 라인 (아이콘 + 설명)
 */
function createErrorLine(label, detail) {
    return new Paragraph({
        spacing: { after: 50 },
        indent: { left: 360 },
        children: [
            new TextRun({
                text: `[${label}] `,
                bold: true, size: 18, font: 'Malgun Gothic', color: COLORS.fail,
            }),
            new TextRun({
                text: (detail || '').substring(0, 300),
                size: 18, font: 'Malgun Gothic', color: COLORS.darkGray,
            }),
        ],
    });
}

/**
 * 수정 프롬프트 박스 (연한 노란 배경)
 */
function createNoteBox(noteText) {
    return new Paragraph({
        spacing: { before: 100, after: 200 },
        shading: { fill: 'FFF3CD', type: ShadingType.CLEAR },
        indent: { left: 200, right: 200 },
        children: [
            new TextRun({
                text: '수정 프롬프트: ',
                bold: true, size: 18, font: 'Malgun Gothic', color: COLORS.primary,
            }),
            new TextRun({
                text: noteText,
                size: 18, font: 'Malgun Gothic', color: COLORS.darkGray,
            }),
        ],
    });
}

/**
 * 테이블 셀 생성 헬퍼
 */
function createTableCell(text, options = {}) {
    const {
        align = 'left',
        bg = COLORS.white,
        color = COLORS.darkGray,
        bold = false,
        size = 18,
    } = options;

    const alignment = align === 'center' ? AlignmentType.CENTER :
                     align === 'right' ? AlignmentType.RIGHT : AlignmentType.LEFT;

    return new TableCell({
        shading: { fill: bg, type: ShadingType.CLEAR },
        children: [new Paragraph({
            alignment,
            children: [new TextRun({
                text: text || '-',
                size,
                font: 'Malgun Gothic',
                color,
                bold,
            })],
        })],
    });
}

/**
 * 결과를 시스템별로 그룹핑
 */
function groupBySystem(results) {
    const groups = {};
    for (const r of results) {
        const key = r.system || 'Unknown';
        if (!groups[key]) groups[key] = [];
        groups[key].push(r);
    }
    return groups;
}

module.exports = { generateReport };
