<?php
/**
 * Deprecated member password reset entrypoint.
 *
 * Canonical flow: /gangnamunni/auth/password_lost.php
 */
header('Location: auth/password_lost.php', true, 301);
exit;
