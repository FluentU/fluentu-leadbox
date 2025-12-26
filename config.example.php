<?php

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

// Insert points for leadbox placement in post content
define('INSERT_POINTS', ['<h2', '<div class="fluen-after-content']);

/*
 * The following constants should be defined in wp-config.php:
 *
 * // FluentU LeadBox - PrintFriendly
 * define('PRINTFRIENDLY_API_KEY', 'your-api-key');
 * define('PRINTFRIENDLY_CSS_URL', 'https://your-site.com/path/to/printfriendly_pdf.css');
 *
 * // FluentU LeadBox - Dittofeed
 * define('DITTOFEED_API_URL', 'https://your-dittofeed-instance.com');
 * define('DITTOFEED_WRITE_KEY', 'your-base64-encoded-write-key');
 * define('DITTOFEED_APP_ENV', 'linux-production');
 *
 * // FluentU LeadBox - Dual-write mode (optional, for migration)
 * define('DUAL_WRITE_ENABLED', false);
 * define('EO_API_URL', 'api.emailoctopus.com');
 * define('EO_API_KEY', 'your-emailoctopus-api-key');
 * define('EO_LIST_ID', 'your-list-id');
 */
