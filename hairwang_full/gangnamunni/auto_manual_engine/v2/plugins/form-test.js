/**
 * 플러그인: form-test
 * 폼/CRUD 기능 테스트 (Phase 2)
 * 현재는 page-load와 동일 동작 (stub)
 */

const PageLoadPlugin = require('./page-load');

class FormTestPlugin extends PageLoadPlugin {
    async run() {
        // Phase 2: 폼 요소 탐색 + 유효성 검사 테스트 구현 예정
        // 현재는 기본 페이지 로딩만 수행
        const config = { ...this.config };
        if (config.target && !config.url) config.url = config.target;
        this.config = config;
        return await super.run();
    }
}

module.exports = FormTestPlugin;
