
const puppeteer = require("puppeteer");
const path = require("path");
const fs = require("fs");

const BASE = "https://hms.seoulbeauty.app/hairwang/gangnamunni";
const OUT = "/var/www/html/multi-hospital-management-system-saas-hospital/hms-saas-v5.8.0/hms-saas/hairwang_full/gangnamunni/screenshots_client_test";

const pages = [
  { name: "index", url: "/index.php" },
  { name: "hospitals", url: "/hospitals.php" },
  { name: "detail_8093", url: "/detail.php?id=8093" },
  { name: "detail_8118", url: "/detail.php?id=8118" },
  { name: "booking_8118", url: "/booking.php?id=8118" },
  { name: "mypage", url: "/mypage.php" },
  { name: "map", url: "/map.php" },
  { name: "event_detail", url: "/event_detail.php?id=1" },
  { name: "review_write", url: "/review_write.php?hospital_id=8118" },
];

const adminPages = [
  { name: "admin_dashboard", url: "/dashboard.php" },
  { name: "admin_ads", url: "/admin_ads.php" },
  { name: "admin_hospitals", url: "/admin_hospitals.php" },
  { name: "admin_cpv_settings", url: "/admin_cpv_settings.php" },
  { name: "admin_cpv_dashboard", url: "/admin_cpv_dashboard.php" },
  { name: "admin_wallet", url: "/admin_wallet.php" },
  { name: "partner_wallet", url: "/partner_wallet.php" },
  { name: "partner_hospital", url: "/partner_hospital.php" },
];

async function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

async function capture(page, name, url, viewport) {
  const suffix = viewport.width > 500 ? "pc" : "mobile";
  await page.setViewport(viewport);
  try {
    await page.goto(BASE + url, { waitUntil: "networkidle2", timeout: 30000 });
    await sleep(1500);
    await page.screenshot({ path: path.join(OUT, `${name}_${suffix}.png`), fullPage: false });
    console.log(`OK: ${name}_${suffix}`);
  } catch(e) {
    console.log(`FAIL: ${name}_${suffix} - ${e.message}`);
  }
}

async function loginAdmin(page) {
  await page.setViewport({ width: 1280, height: 800 });
  try {
    await page.goto(BASE + "/dashboard.php", { waitUntil: "networkidle2", timeout: 30000 });
    await sleep(1000);
    // Check if login form exists
    const loginForm = await page.$('input[name="login_id"]');
    if (loginForm) {
      await page.type('input[name="login_id"]', 'admin');
      await page.type('input[name="login_pw"]', 'admin123');
      const submitBtn = await page.$('button[type="submit"]');
      if (submitBtn) {
        await submitBtn.click();
        await sleep(3000);
      }
    }
    console.log("Admin login attempted");
  } catch(e) {
    console.log("Admin login error: " + e.message);
  }
}

(async () => {
  const browser = await puppeteer.launch({
    headless: "new",
    args: ["--no-sandbox", "--disable-setuid-sandbox", "--disable-dev-shm-usage"]
  });

  const page = await browser.newPage();
  page.setDefaultTimeout(30000);

  // Front pages
  for (const p of pages) {
    await capture(page, p.name, p.url, { width: 1280, height: 800 });
    await capture(page, p.name, p.url, { width: 375, height: 667 });
  }

  // Admin login
  await loginAdmin(page);

  // Admin pages
  for (const p of adminPages) {
    await capture(page, p.name, p.url, { width: 1280, height: 800 });
    await capture(page, p.name, p.url, { width: 375, height: 667 });
  }

  await browser.close();
  console.log("DONE");
})();
