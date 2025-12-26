<?php

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

define('PRINTFRIENDLY_API_KEY', '');
define('PRINTFRIENDLY_CSS_URL', get_stylesheet_directory_uri() . '/css/printfriendly_pdf.css');
// EmailOctopus (to be removed in Phase 2)
define('EO_API_URL', 'api.emailoctopus.com');
define('EO_API_KEY', '');
define('EO_LIST_ID', '');

// Dittofeed
define('DITTOFEED_API_URL', 'https://your-dittofeed-instance.com');
// IMPORTANT: Store the write key as base64-encoded (same format as FluentU web app)
define('DITTOFEED_WRITE_KEY', '');
define('DITTOFEED_APP_ENV', 'linux-production');

// Feature flag for dual-write mode
define('DUAL_WRITE_ENABLED', true);

define('INSERT_POINTS', ['<h2', '<div class="fluen-after-content']);
