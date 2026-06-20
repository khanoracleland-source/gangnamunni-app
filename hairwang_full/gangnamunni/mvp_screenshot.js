
const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

const BASE = 'https://hms.seoulbeauty.app/hairwang/gangnamunni/';
const DIR = '/var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas/hairwang_full/gangnamunni/qa_v1.1_screenshots';

const pages = [
  {name: 'index', url: 'index.php'},
  {name: 'hospitals', url: 'hospitals.php'},
  {name: 'detail', url: 'detail.php?id=8093'},
  {name: 'reviews', url: 'reviews.php'},
  {name: 'review_write', url: 'review_write.php'},
  {name: 'booking', url: 'booking.php?id=8093'},
  {name: 'mypage', url: 'mypage.php'},
  {name: 'my_bookings', url: 'my_bookings.php'},
];

(async () => {
  const browser = await puppeteer.launch({
    headless: 'new',
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']
  });

  for (const p of pages) {
    try {
      // PC
      const pcPage = await browser.newPage();
      await pcPage.setViewport({width: 1280, height: 800});
      await pcPage.goto(BASE + p.url, {waitUntil: 'networkidle2', timeout: 15000});
      await pcPage.screenshot({path: path.join(DIR, ), fullPage: false});
      await pcPage.close();

      // Mobile
      const mobPage = await browser.newPage();
      await mobPage.setViewport({width: 375, height: 667});
      await mobPage.goto(BASE + p.url, {waitUntil: 'networkidle2', timeout: 15000});
      await mobPage.screenshot({path: path.join(DIR, ), fullPage: false});
      await mobPage.close();

      console.log('OK: ' + p.name);
    } catch(e) {
      console.log('FAIL: ' + p.name + ' - ' + e.message);
    }
  }
  await browser.close();
  console.log('DONE');
})();
