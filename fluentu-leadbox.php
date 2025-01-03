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
 * Version:           3.0.1
 * Author:            Elco Brouwer von Gonzenbach
 * Author URI:        https://github.com/elcobvg
 * Text Domain:       fluentu-leadbox
 */

require_once('config.php');

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

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
        add_action('save_post', [$this, 'clearPdfLink']);
        add_filter('the_content', [$this, 'insertLinkSnippet'], 100);
        add_filter('wp_footer', [$this, 'modalMarkup']);
        add_action('wp_ajax_nopriv_submit_leadbox', [$this, 'submitLeadbox']);
        add_action('wp_ajax_submit_leadbox', [$this, 'submitLeadbox']);
        add_filter('wp_mail_from_name', function ($name) {
            return 'FluentU';
        });
    }

    /**
     * Enqueue CSS and JavaScript
     *
     * @return void
     */
    public function scripts()
    {
        if (is_single()) {
            wp_enqueue_style('fluentu-leadbox', plugin_dir_url(__FILE__) . 'css/style.css', [], '2.7.3');
            wp_enqueue_script('fluentu-leadbox', plugin_dir_url(__FILE__) . 'js/scripts.js', [], '2.7.3', true);
            wp_localize_script('fluentu-leadbox', 'options', [
                'action'    => 'submit_leadbox',
                'ajaxurl'   => admin_url('admin-ajax.php'),
                'nonce'     => wp_create_nonce('fluentu_leadbox_' . get_the_ID()),
                'post'      => get_the_ID(),
            ]);
        }
    }

    /**
     * Remove current PDF link when saving post
     *
     * @param  int    $post_id the Post ID
     * @return mixed  Error message or false if no error
     */
    public function clearPdfLink(int $post_id)
    {
        return delete_post_meta($post_id, 'pdf_download_url') ? false : 'Could not delete PDF link';
    }

    /**
     * Insert the 'click to get a PDF copy' link in the post content
     * Also removes old Easy Leadbox shortcodes from post content
     *
     * @param  string $content the post content
     * @return string          content with the link snippet
     */
    public function insertLinkSnippet(string $content)
    {
        if (!is_single() || $_GET['output']) {
            // Only remove shortcodes on non-blog post pages
            return preg_replace('/\[easyleadbox id=[^\n\r]+\]/', '', $content);
        }

        $snippet = file_get_contents(plugin_dir_path(__FILE__) . '/tmpl/link-snippet.html');

        // Replace existing shortcodes with new leadbox
        $result = preg_replace('/\[easyleadbox id=[^\n\r]+\]/', $snippet, $content);

        if ($result === $content) {
            // If no shortcodes found, insert new leadboxes
            foreach (INSERT_POINTS as $pattern) {
                $result = preg_replace('/'. $pattern . '/i', $snippet . $pattern, $result, 1);
            }
        }

        return $result;
    }

    /**
     * Add HTML markup for leadbox modal dialog
     *
     * @return void
     */
    public function modalMarkup()
    {
        if (is_single()) {
            require('tmpl/lead-box.html');
        }
    }

    /**
     * Handle leadbox form submit AJAX action
     *
     * @return string Success or Error HTML message
     */
    public function submitLeadbox()
    {
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $post = filter_input(INPUT_POST, 'post', FILTER_SANITIZE_STRING);

        // check the nonce first
        if (false == check_ajax_referer('fluentu_leadbox_' . $post, 'nonce', false)) {
            wp_send_json_error(__('No permission.', 'fluentu-leadbox'));
        }

        // if honeypot check fails, directly send 'thank you' without processing form
        if ($this->verifyHoneypot() && $error = $this->sendDownloadEmail($email, $post)) {
            wp_send_json_error(__($error, 'fluentu-leadbox'));
        }

        wp_send_json_success(__('Thanks, check your inbox now!', 'fluentu-leadbox'));
    }

    /**
     * Verify 'honeypot': if not empty, it's a bot
     *
     * @return bool  True if honeypot is empty, i.e. success
     */
    protected function verifyHoneypot()
    {
        return !filter_input(INPUT_POST, 'confirm_email', FILTER_SANITIZE_STRING);
    }

    /**
     * Add email address to Email Octopus list
     * @see https://emailoctopus.com/api-documentation/v2#tag/Contact/operation/api_lists_list_idcontacts_put
     *
     * @param  string $email user's email address
     * @param  int    $post_id the Post ID
     * @return mixed  Error message or false if no error
     */
    protected function addSubscriber(string $email, int $post_id)
    {
        $contact = [
            'email_address' => $email,
            'tags'          => $this->generateTags($post_id),
            'status'        => 'subscribed',
        ];
        
        $url = 'https://' . EO_API_URL . '/lists/' . EO_LIST_ID . '/contacts';
        
        $response = wp_safe_remote_request($url, [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . EO_API_KEY,
            ],
            'method' => 'PUT',
            'timeout' => 25, 
            'body' => wp_json_encode($contact),
            'data_format' => 'body',

        ]);
        $result = json_decode(wp_remote_retrieve_body($response), true);
        
        // HTTP status 409 means resource already exists
        return $result['id'] || $result['status'] === 409 ? false : 'Contact could not be added';
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
        if ($error = $this->addSubscriber($email, $post_id)) {
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

        $subject = 'Download ' . html_entity_decode(get_the_title($post_id));
        $message = $this->formatMessage(get_the_title($post_id), $download_url);
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        return wp_mail($email, $subject, $message, $headers) ? false : 'Email could not be sent';
    }

    /**
     * Generate Printfriendly PDF, store locally and save as metadata
     *
     * @param  int    $post_id the Post ID
     * @return mixed  Error message or false if no error
     */
    protected function generateDownloadLink(int $post_id)
    {
        $url = get_permalink($post_id);
        $param = strpos($url, '?') ? '&output=pdf' : '?output=pdf';
        $file = strpos(rawurlencode(basename($url)), '%') === false ? basename($url) : $post_id;
        $path = trailingslashit(wp_get_upload_dir()['path']) . $file . '.pdf';
        $pdf_download_url = trailingslashit(wp_get_upload_dir()['url']) . $file . '.pdf';
        
        $response = wp_safe_remote_post(
            'https://api.printfriendly.com/v2/pdf/create?api_key=' . PRINTFRIENDLY_API_KEY,
            [
                'timeout'   => 300,
                'body'      => [
                    'page_url'  => $url . $param,
                    'css_url'   => PRINTFRIENDLY_CSS_URL,
                ],
            ]
        );

        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        // Fetch PDF and store locally
        if ($body['status'] === 'success' && wp_safe_remote_get($body['file_url'], [
            'timeout'  => 300,
            'stream'   => true,
            'filename' => $path
        ])) {
            return update_post_meta($post_id, 'pdf_download_url', $pdf_download_url) ? false : 'Could not save PDF';
        }
                       
        return __('Could not generate download link for '. $url, 'fluentu-leadbox');
    }

    /**
     * Format email message
     *
     * @param  string $title        The blog post title
     * @param  string $download_url Download URL for the PDF
     * @return string               Formatted HTML message
     */
    protected function formatMessage(string $title, string $download_url)
    {
        $message = file_get_contents(plugin_dir_path(__FILE__) . '/tmpl/mail.html');
        $message = str_replace('{{ url }}', get_site_url(), $message);
        $message = str_replace('{{ title }}', $title, $message);
        $message = str_replace('{{ pdf }}', $download_url, $message);

        return $message;
    }

    /**
     * Generate Email Octopus tags based on Blog categories
     *
     * @param  int    $post_id the Post ID
     * @return array
     */
    protected function generateTags(int $post_id)
    {
        $tags = [];
        $categories = wp_get_post_categories($post_id, ['fields' => 'names', 'parent' => 0]);
        foreach ($categories as $category) {
            if (strpos($category, ' ') === false && strpos($category, 'Educator') === false) {
                $category .= ' Learner';
            }
            $tags['BLOG - ' . preg_replace('/[ -]+/', '_', trim($category))] = true;
        }

        return $tags;
    }
}

/**
 * Begins execution of the plugin.
 */
new FluentuLeadbox();
