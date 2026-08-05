# Auto-test security configuration

The feature is temporarily disabled by default. The PHP pages, v1/v2 API routes,
and both direct runners return an unavailable response unless
`AUTO_TEST_ENABLED=true` is set explicitly. Remove that environment variable or
set it to any other value to keep the feature disabled.

Both v1 (`server.js`) and v2 (`v2/server-v2.js`) are protected by a short-lived,
signed admin session cookie issued by the PHP admin pages. The services and PHP
must share the same `AUTO_TEST_API_SECRET` (at least 32 characters). When it is
not provided, a random key is created in `data/session/auto-test-api-secret`.
For deployments where PHP and Node run as different OS users or containers,
setting the environment variable explicitly is required.

Required login secrets:

- `AUTO_TEST_HMS_ADMIN_PASSWORD`
- `AUTO_TEST_GANGNAMUNNI_ADMIN_PASSWORD`

Password values in both JSON configuration files must remain `${ENVIRONMENT_VARIABLE}`
references. The runners reject literal password values so credentials cannot be
silently reintroduced into source control.

Recommended production settings:

- `AUTO_TEST_API_SECRET`: a random value of at least 32 characters, shared by PHP and Node.
- `AUTO_TEST_ALLOWED_ORIGINS`: comma-separated admin origins; defaults to `https://hms.seoulbeauty.app`.
- `AUTO_TEST_BASE_URL`: staging/test target URL; defaults to the config file URL.
- `AUTO_TEST_ALLOW_PRODUCTION`: must be exactly `true` to target `hms.seoulbeauty.app` intentionally.
- `AUTO_TEST_BIND_HOST`: defaults to `127.0.0.1`. Only change when a private container network requires it.
- `AUTO_TEST_MAX_RUNTIME_MS`: 60000-3600000; defaults to 900000 (15 minutes).

After changing these variables, restart both Node services. Previously committed
admin passwords must also be rotated at their identity providers; removing them
from the current files cannot invalidate credentials present in Git history.
