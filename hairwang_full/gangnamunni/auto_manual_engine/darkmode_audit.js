const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const BASE = 'https://hms.seoulbeauty.app/hairwang/gangnamunni/';
const SCREENSHOT_DIR = '/tmp/darkmode_audit';

const PAGES = [
  'dashboard.php',
  'admin_menu_guide.php',
  'admin_reviews.php',
  'admin_review_wizard.php',
  'admin_hospital_images.php',
  'admin_ads.php',
  'admin_categories.php',
  'admin_hospital_wizard.php',
  'admin_ad_wizard.php',
  'admin_doctor_wizard.php',
  'admin_nurse_wizard.php',
  'admin_patient_wizard.php',
  'admin_staff_wizard.php',
  'admin_medicine_wizard.php',
  'admin_appointment_wizard.php',
  'admin_excel_import_wizard.php',
  'admin_excel_export.php',
  'admin_members.php',
  'admin_mail.php',
  'admin_mail_templates.php',
  'admin_mail_signatures.php',
  'admin_mail_accounts.php',
  'admin_ai_settings.php',
  'admin_auto_test.php',
  'admin_auto_test_v2.php',
  'admin_theme.php',
  'admin_theme_advanced.php',
  'admin_navigation.php',
  'admin_editor_settings.php',
  'admin_maintenance.php',
  'admin_upgrade_plan.php',
  'admin_admins.php',
  'admin_wizard_guide.php',
  'admin_auto_manual.php',
  'admin_screenshot_book.php',
];

(async () => {
  if (!fs.existsSync(SCREENSHOT_DIR)) fs.mkdirSync(SCREENSHOT_DIR, {recursive:true});

  const browser = await chromium.launch({headless:true, args:['--no-sandbox']});
  const ctx = await browser.newContext({viewport:{width:1920,height:1080}});
  const page = await ctx.newPage();

  // Login
  await page.goto(BASE + 'dashboard.php', {waitUntil:'networkidle',timeout:30000});
  await page.fill('input[name="username"]', 'admin');
  await page.fill('input[name="password"]', 'admin123');
  await page.click('button[name="login"]');
  await page.waitForTimeout(3000);

  // Set dark mode
  await page.evaluate(() => {
    localStorage.setItem('admin_theme', 'dark');
  });

  const results = [];

  for (let i = 0; i < PAGES.length; i++) {
    const pg = PAGES[i];
    const name = pg.replace('.php','');
    try {
      await page.goto(BASE + pg, {waitUntil:'networkidle',timeout:20000});
      await page.waitForTimeout(1500);

      // Analyze white/bright elements that look bad in dark mode
      const analysis = await page.evaluate(() => {
        var issues = [];
        var allEls = document.querySelectorAll('*');
        var brightBgCount = 0;

        for (var idx = 0; idx < allEls.length; idx++) {
          var el = allEls[idx];
          var style = getComputedStyle(el);
          var bg = style.backgroundColor;
          var rect = el.getBoundingClientRect();

          if (rect.width === 0 || rect.height === 0) continue;
          if (style.display === 'none' || style.visibility === 'hidden') continue;

          var parseBg = bg.match(/rgb\((\d+),\s*(\d+),\s*(\d+)/);
          if (parseBg) {
            var r = parseInt(parseBg[1]);
            var g = parseInt(parseBg[2]);
            var b = parseInt(parseBg[3]);
            var brightness = (r * 299 + g * 587 + b * 114) / 1000;

            // Bright background (>220) with significant area (>5000px^2)
            if (brightness > 220 && rect.width * rect.height > 5000) {
              brightBgCount++;
              if (issues.length < 15) {
                var tag = el.tagName.toLowerCase();
                var cls = el.className ? el.className.toString().substring(0, 100) : '';
                var elId = el.id || '';
                issues.push({
                  type: 'bright_bg',
                  tag: tag,
                  id: elId,
                  cls: cls,
                  bg: bg,
                  brightness: Math.round(brightness),
                  area: Math.round(rect.width * rect.height),
                  x: Math.round(rect.x),
                  y: Math.round(rect.y),
                  w: Math.round(rect.width),
                  h: Math.round(rect.height)
                });
              }
            }
          }
        }

        return { brightBgCount: brightBgCount, issues: issues };
      });

      // Screenshot
      var screenshotPath = path.join(SCREENSHOT_DIR, name + '.jpg');
      await page.screenshot({path: screenshotPath, fullPage: true, type: 'jpeg', quality: 80});

      results.push({
        page: pg,
        brightBgCount: analysis.brightBgCount,
        issues: analysis.issues,
        screenshot: name + '.jpg'
      });

      console.log((i+1) + '/' + PAGES.length + ' ' + pg + ' bright=' + analysis.brightBgCount);

    } catch (err) {
      results.push({page: pg, error: err.message});
      console.log((i+1) + '/' + PAGES.length + ' ' + pg + ' ERROR: ' + err.message);
    }
  }

  await browser.close();

  // Save results
  fs.writeFileSync(path.join(SCREENSHOT_DIR, 'audit_results.json'), JSON.stringify(results, null, 2));
  console.log('');
  console.log('=== AUDIT COMPLETE ===');
  console.log('Screenshots: ' + SCREENSHOT_DIR);

  // Summary
  var problem_pages = results.filter(function(r) { return r.brightBgCount > 3; });
  console.log('Problem pages (bright>3): ' + problem_pages.length);
  for (var j = 0; j < problem_pages.length; j++) {
    var p = problem_pages[j];
    console.log('  ' + p.page + ' bright=' + p.brightBgCount + ' top-issues:');
    if (p.issues) {
      for (var k = 0; k < Math.min(p.issues.length, 5); k++) {
        var iss = p.issues[k];
        console.log('    ' + iss.tag + (iss.id ? '#'+iss.id : '') + ' .' + iss.cls.substring(0,50) + ' bg=' + iss.bg + ' area=' + iss.area);
      }
    }
  }
})();
