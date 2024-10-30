<?php

namespace MobileAppForWooCommerce\Includes;

use WP_REST_Request;
use WP_REST_Response;

class CustomCssJs
{
    use Helper;
    const settings_option_key = 'shopapper_custom_css_js_settings';

    function __construct()
    {
        add_action('rest_api_init', [$this, 'init']);

        add_action('wp_head', [$this, 'head']);

        add_action('wp_footer', [$this, 'footer']);
    }

    public function init()
    {
        register_rest_route(MAFW_CLIENT_ROUTE, '/custom-css-js/settings', [
            'methods' => 'GET',
            'callback' => [$this, 'get_settings'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/custom-css-js/settings', [
            'methods' => 'POST',
            'callback' => [$this, 'update_settings'],
            'permission_callback' => '__return_true',
        ]);

    }

    public function get_settings(): WP_REST_Response
    {
        return new WP_REST_Response(['settings' => get_option(self::settings_option_key,
            [
                'css' => '',
                'js' => '',
                'in_footer' => true
            ]
        )]);
    }

    public function update_settings(WP_REST_Request $request): WP_REST_Response
    {
        update_option(self::settings_option_key, $request->get_params());

        return new WP_REST_Response([]);
    }

    public function head()
    {

        if (!$this->is_shopapper()) return;

        $settings = get_option(self::settings_option_key, [
            'css' => '',
            'js' => '',
            'in_footer' => true
        ]);

        if (!empty($settings['css']))
            echo '<style id="shopapper-custom-css">' . $settings['css'] . '</style>';

        if (!empty($settings['js']) and !$settings['in_footer'])
            echo '<script  id="shopapper-custom-js">' . $settings['js'] . '</script>';
    }

    public function footer()
    {
        if (!$this->is_shopapper()) return;

        $settings = get_option(self::settings_option_key, [
            'css' => '',
            'js' => '',
            'in_footer' => true
        ]);

        if (!empty($settings['js']) and $settings['in_footer'])
            echo '<script  id="shopapper-custom-js">' . $settings['js'] . '</script>';
    }
}

new CustomCssJs();