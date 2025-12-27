<?php

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

// Insert points for leadbox placement in post content
define('INSERT_POINTS', ['<h2', '<div class="fluen-after-content']);

// CSS for PrintFriendly PDF styling (must be publicly accessible URL)
define('PRINTFRIENDLY_CSS_URL', get_stylesheet_directory_uri() . '/css/printfriendly_pdf.css');

/*
 * The following constants should be defined in wp-config.php:
 *
 * // FluentU LeadBox - PrintFriendly
 * define('PRINTFRIENDLY_API_KEY', 'your-api-key');
 *
 * // FluentU LeadBox - Dittofeed
 * define('DITTOFEED_API_URL', 'https://your-dittofeed-instance.com');
 * define('DITTOFEED_WRITE_KEY', 'your-base64-encoded-write-key');
 * define('DITTOFEED_APP_ENV', 'linux-production');
 */
