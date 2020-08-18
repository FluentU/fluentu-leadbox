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

require_once('vendor/autoload.php');
require_once('config.php');


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
     * @return void
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
        $client->request('POST', 'v1/pdfs/create', [
            'form_params' => ['page_url' => $url . $param],
            'sink' =>  $path,
        ]);
        $pdf_download_url = trailingslashit(wp_get_upload_dir()['url']) . basename($url) . '.pdf';

        return update_post_meta($post_id, 'pdf_download_url', $pdf_download_url);
    }

    /**
     * Remove old Easy Leadbox shortcodes from post content
     *
     * @param  string $content the post content
     * @return string          content without the easy leadbox shortcode
     */
    public function removeShortCodes(string $content)
    {
        if (!is_single() || !get_post_meta(get_the_ID(), 'pdf_download_url', true)) {
            return $content;
        }

        return preg_replace('/\[easyleadbox id=[^\n\r]+\]/', '', $content, 1);
    }

    /**
     * Insert the 'click to get a PDF copy' link in the post content
     *
     * @param  string $content the post content
     * @return string          content with the link snippet
     */
    public function insertLinkSnippet(string $content)
    {
        if (!is_single() || !get_post_meta(get_the_ID(), 'pdf_download_url', true) || $_GET['output']) {
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
        $data = $_POST;

        // check the nonce
        if (false == check_ajax_referer('fluentu_leadbox_' . $data['post'], 'nonce', false)) {
            wp_send_json_error();
        }

        // add subscriber and send email
        if ($this->addSubscriber($data['email']) && $this->sendDownloadEmail($data['email'], $data['post'])) {
            wp_send_json_success(__('Thanks, check your inbox now!', 'fluentu-leadbox'));
        } else {
            wp_send_json_error();
        }
    }

    /**
     * Add email address to MailChimp list
     *
     * @param  string $email user's email address
     * @return int
     */
    protected function addSubscriber(string $email)
    {
        $ac = new \ActiveCampaign(AC_API_URL, AC_API_KEY);
        $list_id = AC_LIST_ID;

        $contact = [
            'email'              => $email,
            "p[{$list_id}]"      => $list_id,
            "status[{$list_id}]" => 1,
        ];

        $contact_sync = $ac->api('contact/sync', $contact);

        return $contact_sync->result_code;
    }

    /**
     * Send email with download link to user.
     *
     * @param  string $email   the user's email address
     * @param  int    $post_id the Post ID
     * @return bool
     */
    protected function sendDownloadEmail(string $email, int $post_id)
    {
        $download_url = get_post_meta($post_id, 'pdf_download_url', true);
        $subject = 'Download ' . get_the_title($post_id);
        $message = $subject . ' here: ' . $download_url;
        return wp_mail($email, $subject, $message);
    }
}

/**
 * Begins execution of the plugin.
 */
new FluentuLeadbox();
