<?php

/**
 * @link              https://github.com/elcobvg
 * @since             1.0.0
 * @package           FluentuLeadbox
 *
 * @wordpress-plugin
 * Plugin Name:       FluentU LeadBox Plugin
 * Plugin URI:        https://www.fluentu.com/
 * Description:       Simple proof-of-concept plugin for emailing PDF download links.
 * Version:           1.0.0
 * Author:            Elco Brouwer von Gonzenbach
 * Author URI:        https://github.com/elcobvg
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       fluentu-leadbox
 */

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

/**
 * Current plugin version.
 */
define('PLUGIN_NAME_VERSION', '1.0.0');

/**
 * Constants: hard-coded for now, should go in settings later.
 */
define('PRINTFRIENDLY_API_KEY', '8bfda2a949250c2f43c94770882004b8');
define('PRINTFRIENDLY_ENDPOINT', 'https://api.printfriendly.com/v2/pdf/create?api_key=');
define('MAILCHIMP_API_KEY', 'fa9f698dbdb357045ae237d9d968036d-us19');
define('MAILCHIMP_LIST_ID', 'aa64795034');
define('INSERT_POINT', '<h2');
define('LINK_SNIPPET', '<p class="fluentu-leadbox-link"><a href="/">Click to download PDF copy</a></p>');

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
     * Generate Printfriendly PDF downloadlink and save as metadata
     *
     * @param  int    $post_id the Post ID
     * @return void
     */
    public function generateDownloadLink(int $post_id)
    {
        $post_url = get_permalink($post_id);

        $options = [
            CURLOPT_HTTPHEADER      => ['Content-Type: application/x-www-form-urlencoded; charset=utf-8'],
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_TIMEOUT         => 30,
            CURLOPT_URL             => PRINTFRIENDLY_ENDPOINT . PRINTFRIENDLY_API_KEY,
            CURLOPT_POST            => 1,
            CURLOPT_POSTFIELDS      => 'page_url=' . $post_url
        ];

        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $data = json_decode(curl_exec($ch));
        curl_close($ch);
        
        if ($data->status === 'success') {
            $result = update_post_meta($post_id, 'pdf_download_url', $data->file_url);
        } else {
            error_log('Could not generate download link for '. $post_url);
        }
    }

    /**
     * Insert the 'click to get a PDF copy' link in the post content
     *
     * @param  string $content the post content
     * @return string          content with the link snippet
     */
    public function insertLinkSnippet(string $content)
    {
        if (!is_single() || !get_post_meta(get_the_ID(), 'pdf_download_url', true)) {
            return $content;
        }
        
        return preg_replace('/'. INSERT_POINT . '/i', LINK_SNIPPET . INSERT_POINT, $content, 1);
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
     * @return bool
     */
    protected function addSubscriber(string $email)
    {
        if (empty($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        // MailChimp API URL
        $memberID = md5(strtolower($email));
        $dataCenter = substr(MAILCHIMP_API_KEY, strpos(MAILCHIMP_API_KEY, '-') +1);
        $url = 'https://' . $dataCenter . '.api.mailchimp.com/3.0/lists/' . MAILCHIMP_LIST_ID . '/members/' . $memberID;
        
        // member information
        $json = json_encode([
            'email_address' => $email,
            'status'        => 'subscribed',
        ]);

        // send a HTTP POST request with curl
        $options = [
            CURLOPT_USERPWD         => 'user:' . MAILCHIMP_API_KEY,
            CURLOPT_HTTPHEADER      => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_TIMEOUT         => 30,
            CURLOPT_CUSTOMREQUEST   => 'PUT',
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_POSTFIELDS      => $json
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, $options);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
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
