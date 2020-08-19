<?php

/**
 * @link              https://github.com/FluentU/fluentu-leadbox
 * @since             1.0.0
 * @package           FluentuLeadbox
 *
 * @wordpress-plugin
 * Plugin Name:       FluentU LeadBox Plugin
 * Plugin URI:        https://github.com/FluentU/fluentu-leadbox
 * Description:       Simple plugin for generating PDFs from posts and emailing download links.
 * Version:           2.0.0
 * Author:            Elco Brouwer von Gonzenbach
 * Author URI:        https://github.com/elcobvg
 * Text Domain:       fluentu-leadbox
 */

require_once('config.php');
require_once('vendor/autoload.php');

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

/**
 * Current plugin version.
 */
define('PLUGIN_NAME_VERSION', '2.0.0');

/**
 * Main plugin class
 */
class FluentuLeadbox
{
    /**
     * Constructor sets up all necessary action hooks and filters
     *
     * @return void
     */
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'scripts']);
        add_action('save_post', [$this, 'generateDownloadLink']);
        add_filter('the_content', [$this, 'insertLinkSnippet']);
        add_filter('the_content', [$this, 'removeShortCodes']);
        add_filter('wp_footer', [$this, 'modalMarkup']);
        add_action('wp_ajax_nopriv_submit_leadbox', [$this, 'submitLeadbox']);
        add_action('wp_ajax_submit_leadbox', [$this, 'submitLeadbox']);
    }

    /**
     * Enqueue CSS and JavaScript
     *
     * @return void
     */
    public function scripts()
    {
        wp_enqueue_style('fluentu-leadbox', plugin_dir_url(__FILE__) . 'css/style.css');
        wp_enqueue_script('fluentu-leadbox', plugin_dir_url(__FILE__) . 'js/scripts.js', ['jquery'], null, true);
        wp_localize_script('fluentu-leadbox', 'options', [
            'action'    => 'submit_leadbox',
            'ajaxurl'   => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce('fluentu_leadbox_' . get_the_ID()),
            'post'      => get_the_ID(),
        ]);
    }

    /**
     * Generate Printfriendly PDF, store locally and save as metadata
     *
     * @param  int    $post_id the Post ID
     * @return mixed  Error message or false if no error
     */
    public function generateDownloadLink(int $post_id)
    {
        $client = new \GuzzleHttp\Client([
            'base_uri' => 'https://api.printfriendly.com',
            'auth' => [PRINTFRIENDLY_API_KEY, ''],
        ]);

        $url = get_permalink($post_id);
        $param = strpos($url, '?') ? '&output=pdf' : '?output=pdf';
        $path = trailingslashit(wp_get_upload_dir()['path']) . basename($url) . '.pdf';

        $response = $client->request('POST', 'v1/pdfs/create', [
            'form_params' => ['page_url' => $url . $param],
            'sink' =>  $path,
        ]);
        if ($response->getStatusCode() !== 200) {
            return $response->getReasonPhrase();
        }

        $pdf_download_url = trailingslashit(wp_get_upload_dir()['url']) . basename($url) . '.pdf';

        return update_post_meta($post_id, 'pdf_download_url', $pdf_download_url) ? false : 'Could not save PDF';
    }

    /**
     * Remove old Easy Leadbox shortcodes from post content
     *
     * @param  string $content the post content
     * @return string          content without the easy leadbox shortcode
     */
    public function removeShortCodes(string $content)
    {
        if (is_single()) {
            return preg_replace('/\[easyleadbox id=[^\n\r]+\]/', '', $content, 1);
        }

        return $content;
    }

    /**
     * Insert the 'click to get a PDF copy' link in the post content
     *
     * @param  string $content the post content
     * @return string          content with the link snippet
     */
    public function insertLinkSnippet(string $content)
    {
        if (!is_single() || $_GET['output']) {
            return $content;
        }
        $snippet = file_get_contents(plugin_dir_path(__FILE__) . '/tmpl/link-snippet.html');
        
        return preg_replace('/'. INSERT_POINT . '/i', $snippet . INSERT_POINT, $content, 1);
    }

    /**
     * Add HTML markup for leadbox modal dialog
     *
     * @return void
     */
    public function modalMarkup()
    {
        if (is_single()) {
            require('tmpl/leadbox.html');
        }
    }

    /**
     * Handle leadbox form submit AJAX action
     *
     * @return string Success or Error HTML message
     */
    public function submitLeadbox()
    {
        // check the nonce first
        if (false == check_ajax_referer('fluentu_leadbox_' . $_POST['post'], 'nonce', false)) {
            wp_send_json_error(__('No permission.', 'fluentu-leadbox'));
        }

        if ($error = $this->sendDownloadEmail($_POST['email'], $_POST['post'])) {
            wp_send_json_error(__($error, 'fluentu-leadbox'));
        }

        wp_send_json_success(__('Thanks, check your inbox now!', 'fluentu-leadbox'));
    }

    /**
     * Add email address to Active Campaign list
     *
     * @param  string $email user's email address
     * @return mixed  Error message or false if no error
     */
    protected function addSubscriber(string $email)
    {
        $ac = new \ActiveCampaign(AC_API_URL, AC_API_KEY);

        $contact = [
            'email'                         => $email,
            'p[' . AC_LIST_ID . ']'         => AC_LIST_ID,
            'status[' . AC_LIST_ID . ']'    => 1,
        ];

        $contact_sync = $ac->api('contact/sync', $contact);

        return $contact_sync->result_code ? false : 'Contact could not be added';
    }

    /**
     * Send email with download link to user.
     *
     * @param  string $email   the user's email address
     * @param  int    $post_id the Post ID
     * @return mixed  Error message or false if no error
     */
    protected function sendDownloadEmail(string $email, int $post_id)
    {
        if ($error = $this->addSubscriber($email)) {
            return $error;
        }

        $download_url = get_post_meta($post_id, 'pdf_download_url', true);

        if (!$download_url) {
            // Generate PDF 'on the fly' if it doesn't exist yet
            if ($error = $this->generateDownloadLink($post_id)) {
                return $error;
            }
            $download_url = get_post_meta($post_id, 'pdf_download_url', true);
        }

        $subject = 'Download ' . get_the_title($post_id);
        $message = $subject . ' here: ' . $download_url;

        return wp_mail($email, $subject, $message) ? false : 'Email could not be sent';
    }
}

/**
 * Begins execution of the plugin.
 */
new FluentuLeadbox();
