/** URL prefix the app is served under (next.config.ts basePath). */
export const BASE_PATH = (process.env.NEXT_PUBLIC_BASE_PATH ?? "").trim() || "/admin-next";

/** PHP admin dashboard: it shows the login form when nobody is signed in. */
export const PHP_ADMIN_URL = process.env.NEXT_PUBLIC_ADMIN_PHP_LOGIN_URL || "/hairwang/gangnamunni/dashboard.php";

/** The PHP dashboard ends the admin session when it is opened with ?logout=1. */
export const PHP_LOGOUT_URL = `${PHP_ADMIN_URL}${PHP_ADMIN_URL.includes("?") ? "&" : "?"}logout=1`;

/** Same key and values ("light" | "dark") as the PHP admin, so both panels share the setting. */
export const THEME_STORAGE_KEY = "admin_theme";

/** How often the dashboard numbers are fetched again while the tab is visible. */
export const REFRESH_INTERVAL_MS = 60_000;
