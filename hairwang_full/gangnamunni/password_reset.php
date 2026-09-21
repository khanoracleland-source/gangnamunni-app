<?php
/**
 * Deprecated member password reset page.
 *
 * This file used an obsolete gangnamunni_members password flow. Keep the URL
 * as a redirect so old links land on the supported member reset flow.
 */
header('Location: auth/password_lost.php', true, 301);
exit;
