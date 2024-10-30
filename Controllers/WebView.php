<?php

namespace MobileAppForWooCommerce\Controllers;

use MobileAppForWooCommerce\Includes\Helper;

class WebView
{
    use Helper;

    public function __construct()
    {
        add_action('init', [$this, 'login'], 0);

        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

        add_action('template_redirect', [$this, 'template_redirect']);

        add_filter('woocommerce_add_success', [$this, 'wc_add_notice_success'], 99, 1);

        add_filter('woocommerce_add_error', [$this, 'wc_add_notice_error'], 99, 1);
    }

    public function login()
    {
        $consumerKey = $this->get_consumer_key_from_request();

        if ($consumerKey) {

            $user_id = $this->get_user_id_by_consumer_key($consumerKey);

            if (!is_user_logged_in() or get_current_user_id() != $user_id)
                $this->login_via_user_id($user_id);
        }

        // simple jwt login support.
        if (!empty($_GET['shopapper-jwt']) and class_exists('\SimpleJWTLogin\Libraries\JWT\JWT')) {

            try {

                $user_id = null;

                $jwtSettings = new \SimpleJWTLogin\Modules\SimpleJWTLoginSettings(new \SimpleJWTLogin\Modules\WordPressData());

                $decoded = (array)\SimpleJWTLogin\Libraries\JWT\JWT::decode($_GET['shopapper-jwt'], \SimpleJWTLogin\Helpers\Jwt\JwtKeyFactory::getFactory($jwtSettings)->getPublicKey(), [$jwtSettings->getGeneralSettings()->getJWTDecryptAlgorithm()]);

                if (isset($decoded['id']))
                    $user_id = $decoded['id'];

                if ($user_id and (!is_user_logged_in() or get_current_user_id() != $user_id))
                    $this->login_via_user_id($user_id);

            } catch (\Throwable $e) {
            }

        }

    }

    public function enqueue_scripts()
    {
        if ($this->is_shopapper()) {

            if (!$this->display_header()) {
                wp_enqueue_script('shopapper-webview', MAFW_URL . 'assets/js/web_view.js', ['jquery'], '1.0.0', true);
                wp_enqueue_style('shopapper-webview', MAFW_URL . 'assets/css/web_view.css');
            }

            do_action('shopapper_enqueue_scripts');
        }

    }

    public function template_redirect()
    {
        $notice = null;

        $web_view_token = $this->get_webview_token_from_request();

        if (!empty($web_view_token)) {

            $notices = get_option(sprintf("shopapper_wc_notices_%s", $web_view_token), []);

            if (!empty($notices)) {

                $notice = $notices[0];

                delete_option(sprintf("shopapper_wc_notices_%s", $web_view_token));
            }

        } else if (is_user_logged_in()) {
            $notices = get_user_meta(get_current_user_id(), 'shopapper_wc_notices', true);

            if (!empty($notices)) {

                $notice = $notices[0];

                update_user_meta(get_current_user_id(), 'shopapper_wc_notices', []);
            }

        }

        if ($notice and !wc_has_notice($notice['message'], $notice['type'])) {

            $wc_notices = WC()->session->get('wc_notices', []);

            $wc_notices[$notice['type']][] = array(
                'notice' => $notice['message'],
                'data' => [],
            );

            WC()->session->set('wc_notices', $wc_notices);
        }

        if (wp_is_mobile() and isset($_SERVER['HTTP_REFERER']) and strpos($_SERVER['HTTP_REFERER'], 'shopapper-url') !== false and !isset($_GET['url'])) {

            $parts = parse_url($_SERVER['HTTP_REFERER']);

            parse_str($parts['query'], $query);

            if (isset($query['shopapper-url'])) {

                $landing_url = get_site_url(null, $_SERVER['REQUEST_URI']);

                $app_url = sprintf("%s?%s", $query['shopapper-url'], http_build_query(['url' => $landing_url]));

                header("Location: $app_url", true, 301);

                exit;
            }
        }
    }

    /**
     * @param $message
     * @param $type
     * @return mixed
     */
    public function save_shopapper_notice($message, $type)
    {
        if (!$this->is_shopapper())
            return $message;

        $web_view_token = $this->get_web_view_token();

        if (!empty($web_view_token)) {

            $notices = get_option(sprintf("shopapper_wc_notices_%s", $web_view_token), []);

            if (empty($notices))
                $notices = [];

            $notices [] = ['message' => $message, 'type' => $type];

            update_option(sprintf("shopapper_wc_notices_%s", $web_view_token), $notices, false);

            return $message;
        }

        if (is_user_logged_in()) {
            $notices = get_user_meta(get_current_user_id(), 'shopapper_wc_notices', true);

            if (empty($notices))
                $notices = [];

            $notices [] = ['message' => $message, 'type' => $type];

            update_user_meta(get_current_user_id(), 'shopapper_wc_notices', $notices);

            return $message;
        }

        return $message;
    }

    /**
     * @param $message
     * @return mixed
     */
    public function wc_add_notice_success($message)
    {
        return $this->save_shopapper_notice($message, 'success');
    }

    /**
     * @param $message
     * @return mixed
     */
    public function wc_add_notice_error($message)
    {
        return $this->save_shopapper_notice($message, 'error');
    }

}


new WebView();