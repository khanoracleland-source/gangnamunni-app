/**
 * 플러그인: scenario-test
 * 시나리오(흐름) 테스트 (Phase 2)
 * 현재는 page-load와 동일 동작 (stub)
 */

const PageLoadPlugin = require('./page-load');

class ScenarioTestPlugin extends PageLoadPlugin {
    async run() {
        // Phase 2: steps[] 기반 순차 실행 구현 예정
        // 현재는 첫 URL만 접근
        const config = { ...this.config };
        if (!config.url && config.steps) {
            const gotoStep = config.steps.find(s => s.startsWith('goto:'));
            if (gotoStep) config.url = gotoStep.replace('goto:', '');
        }
        if (!config.url) config.url = '/';
        this.config = config;
        return await super.run();
    }
}

module.exports = ScenarioTestPlugin;
