<?php

namespace MobileAppForWooCommerce\Includes;

use Exception;
use Throwable;
use WC_Cart;
use WC_Customer;
use WC_Order;

trait Helper
{
    public function init_wc()
    {
        if (defined('WC_ABSPATH')) {
            // WC 3.6+ - Cart and other frontend functions are not included for REST requests.
            include_once WC_ABSPATH . 'includes/wc-cart-functions.php';
            include_once WC_ABSPATH . 'includes/wc-notice-functions.php';
            include_once WC_ABSPATH . 'includes/wc-template-hooks.php';
        }

        $cookie_name = apply_filters('woocommerce_cookie', 'wp_woocommerce_session_' . COOKIEHASH);

        if (isset($_COOKIE[$cookie_name])) {
            unset($_COOKIE[$cookie_name]);
            setcookie($cookie_name, '', time() - 3600, '/'); // empty value and old timestamp
        }

        if (is_null(WC()->session)) {
            $session_class = apply_filters('woocommerce_session_handler', 'WC_Session_Handler');

            WC()->session = new $session_class();

            WC()->session->init();
        }

        if (is_null(WC()->customer)) {
            try {
                WC()->customer = new WC_Customer(get_current_user_id(), true);
            } catch (Exception $e) {
            }
        }

        if (is_null(WC()->cart)) {
            WC()->cart = new WC_Cart();
            WC()->cart->get_cart();
        }
    }

    /**
     * @param $customer_id
     * @return false|mixed|WC_Order
     */
    public function get_customer_last_order($customer_id)
    {
        $wc_orders = wc_get_orders([
            'customer_id' => $customer_id,
            'status' => array_filter(array_keys(wc_get_order_statuses()), function ($status) {
                return $status !== 'wc-cancelled' and $status != 'wc-failed';
            }),
            'limit' => 1
        ]);

        if (empty($wc_orders)) return false;

        return $wc_orders[0];
    }


    /**
     * Get customer by consumer key
     *
     * @param : $consumer_key(string) This parameter will have "ck_" at the start
     *
     * @retunr : int | boolean
     */
    public function get_user_id_by_consumer_key($consumer_key)
    {
        global $wpdb;

        // woocommerce api keys table name.
        $api_keys_table_name = $wpdb->prefix . 'woocommerce_api_keys';

        // get user id.
        $user_data = $wpdb->get_row($wpdb->prepare("select user_id from {$api_keys_table_name} where consumer_key = %s;", wc_api_hash($consumer_key)));

        // if user id is empty return false.
        if (empty($user_data)) {
            return false;
        }

        return $user_data->user_id;
    }

    /**
     * @param $user_id
     * @param bool $set_cookie
     * @return bool
     */
    public function login_via_user_id($user_id, bool $set_cookie = true): bool
    {

        // get user data.
        $user = get_userdata($user_id);

        if ($user === false)
            return false;

        wp_set_current_user($user_id, $user->user_login);

        if ($set_cookie) wp_set_auth_cookie($user_id);

        if (class_exists('AF_R_F_Q_Main') and is_null(WC()->session)) {
            $session_class = apply_filters('woocommerce_session_handler', 'WC_Session_Handler');

            WC()->session = new $session_class();

            WC()->session->init();
        }

        do_action('wp_login', $user->user_login, $user);

        return true;
    }

    /**
     * @param $consumer_key
     * @return bool
     */
    public function is_user_logged_in($consumer_key = false)
    {
        if (!is_user_logged_in() and $consumer_key) {
            $user_id = $this->get_user_id_by_consumer_key($consumer_key);

            if ($user_id)
                $this->login_via_user_id($user_id);
        }

        return is_user_logged_in();
    }

    /**
     * @return bool
     */
    public function is_loggedin_active(): bool
    {
        return class_exists('Loggedin');
    }

    /**
     * @return bool
     */
    public function is_shopapper(): bool
    {
        if (isset($_SERVER['HTTP_SHOPAPPER']))
            return true;

        return isset($_GET['shopapper-page']) or (isset($_SERVER['HTTP_REFERER']) and strpos($_SERVER['HTTP_REFERER'], 'shopapper-page') !== false);
    }

    /**
     * @return mixed|string
     */
    public function get_consumer_key_from_request()
    {
        if (!$this->is_shopapper())
            return '';

        if (!empty($_SERVER['HTTP_SHOPAPPER_CONSUMER_KEY']))
            return $_SERVER['HTTP_SHOPAPPER_CONSUMER_KEY'];

        if (!empty($_GET['consumer_key']))
            return $_GET['consumer_key'];

        if (isset($_SERVER['HTTP_REFERER'])) {
            $parts = parse_url($_SERVER['HTTP_REFERER']);
            parse_str($parts['query'], $query);

            if (isset($query['consumer_key']))
                return $query['consumer_key'];
        }

        return '';
    }

    /**
     * @return mixed|string
     */
    public function get_webview_token_from_request()
    {
        if (!empty($_SERVER['HTTP_SHOPAPPER_WEB_VIEW_TOKEN']))
            return $_SERVER['HTTP_SHOPAPPER_WEB_VIEW_TOKEN'];

        if (!empty($_GET['shopapper-webview-token']))
            return $_GET['shopapper-webview-token'];

        return '';
    }

    /**
     * @return mixed|null
     */
    public function get_app_id_from_request()
    {
        $app_id = null;

        if (isset($_GET['app-id']))
            $app_id = $_GET['app-id'];
        else if (isset($_SERVER['HTTP_REFERER']) and strpos($_SERVER['HTTP_REFERER'], 'app-id') !== false) {

            $parts = parse_url($_SERVER['HTTP_REFERER']);

            parse_str($parts['query'], $query);

            if (!empty($query['app-id']))
                $app_id = $query['app-id'];
        }

        return $app_id;
    }

    /**
     * @return mixed|null
     */
    public function get_web_view_token()
    {
        $token = null;

        if (isset($_GET['shopapper-webview-token']))
            $token = $_GET['shopapper-webview-token'];
        else if (isset($_SERVER['HTTP_REFERER']) and strpos($_SERVER['HTTP_REFERER'], 'shopapper-webview-token') !== false) {

            $parts = parse_url($_SERVER['HTTP_REFERER']);

            parse_str($parts['query'], $query);

            if (!empty($query['shopapper-webview-token']))
                $token = $query['shopapper-webview-token'];
        }

        return $token;
    }

    /**
     * @return bool
     */
    public function display_header()
    {
        if(isset($_SERVER['HTTP_SHOPAPPER_SHOW_HEADER']) and $_SERVER['HTTP_SHOPAPPER_SHOW_HEADER'] === '1')
            return true;

        return !empty($_GET['shopapper-show-header']);
    }

    /**
     * @param $data
     * @return void
     */
    public function add_log($data)
    {
        wp_safe_remote_post('https://shopapper.com/wp-json/shopapper/admin/v1/logs', ['body' => array_merge($data, ['url' => get_site_url(), 'currency' => get_woocommerce_currency()])]);
    }

    /**
     * @return false|mixed|null
     */
    public static function get_token()
    {
        if (is_multisite()) {

            switch_to_blog(1);

            $token = get_option(Dashboard::$token_option_name, '');

            restore_current_blog();

            return $token;
        }

        return get_option(Dashboard::$token_option_name, '');

    }

    /**
     * @param $customer_id
     * @return \stdClass|WC_Order[]
     */
    public static function get_customer_shopapper_orders($customer_id)
    {
        return wc_get_orders([
            'meta_key' => 'shopapper_order',
            'meta_value' => 1,
            'meta_compare' => '=',
            'customer_id' => [$customer_id],
            'return' => 'objects'
        ]);
    }

    /**
     * @param $params
     * @return void
     */
    public static function send_auto_notification($params)
    {
        try {

            $url = sprintf("https://shopapper.com/wp-json/shopapper/admin/v1/client/send-auto-notification/?%s", http_build_query([
                'storeUrl' => get_site_url(),
                'clientToken' => self::get_token()
            ]));

            $args = [
                'headers' => [
                    'Content-type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'body' => json_encode($params)
            ];

            wp_remote_post($url, $args);

        } catch (Throwable $e) {

            error_log($e->getMessage());
        }
    }

    /**
     * @param $params
     * @return void
     */
    public static function send_notification($params)
    {
        try {

            $url = sprintf("https://shopapper.com/wp-json/shopapper/admin/v1/client/send-push-notification/?%s", http_build_query([
                'storeUrl' => get_site_url(),
                'clientToken' => self::get_token()
            ]));

            $args = [
                'headers' => [
                    'Content-type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'body' => json_encode($params)
            ];

            wp_remote_post($url, $args);

        } catch (Throwable $e) {

            error_log($e->getMessage());
        }
    }

    /**
     * @return bool
     */
    public static function is_push_notification_enabled()
    {
        return (!empty($_SERVER['HTTP_SHOPAPPER_NOTIFICATION']) and $_SERVER['HTTP_SHOPAPPER_NOTIFICATION'] === '1') or (isset($_SERVER['HTTP_REFERER']) and strpos($_SERVER['HTTP_REFERER'], 'shopapper-push-notification-allowed') !== false);
    }
}