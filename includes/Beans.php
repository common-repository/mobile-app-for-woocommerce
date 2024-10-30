<?php

namespace MobileAppForWooCommerce\Includes;

use WP_REST_Request;
use WP_REST_Response;

class Beans
{
    static $settings_option_key = 'shopapper_beans_settings';

    function __construct()
    {
        add_action('rest_api_init', [$this, 'init']);
    }

    public function init()
    {
        register_rest_route(MAFW_CLIENT_ROUTE, '/beans/settings', [
            'methods' => 'GET',
            'callback' => [$this, 'get_settings'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/beans/settings', [
            'methods' => 'POST',
            'callback' => [$this, 'update_settings'],
            'permission_callback' => '__return_true',
        ]);

    }

    public function get_settings(): WP_REST_Response
    {
        return new WP_REST_Response(['settings' => get_option(self::$settings_option_key,
            [
                'authKey' => '',
                'rule' => '',
                'quantity' => 0,
                'description' => ''
            ]
        )]);
    }

    public function update_settings(WP_REST_Request $request): WP_REST_Response
    {
        update_option(self::$settings_option_key, $request->get_params());

        return new WP_REST_Response([]);
    }

    public static function is_active()
    {
        return mafw_is_plugin_active('beans-woocommerce-loyalty-rewards/beans-woocommerce.php');
    }

    public static function add_credit($order)
    {
        $customer = get_userdata($order->get_customer_id());

        if (!$customer)
            return;

        $options = get_option(self::$settings_option_key,
            [
                'authKey' => '',
                'rule' => '',
                'quantity' => 0,
                'description' => ''
            ]
        );

        if (!$options['authKey'] || !$options['rule'] || !$options['quantity']) return;

        $url = 'https://api.trybeans.com/v3/liana/credit/';

        $authorizationHeader = 'Basic ' . base64_encode($options['authKey']);

        $data = [
            'account' => $customer->user_email,
            'uid' => strtotime("now"),
            'rule' => $options['rule'],
            'quantity' => $options['quantity'],
            'description' => $options['description'],
        ];

        $args = [
            'headers' => [
                'Content-type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => $authorizationHeader,
            ],
            'body' => json_encode($data)
        ];

        wp_remote_post($url, $args);
    }
}

new Beans();