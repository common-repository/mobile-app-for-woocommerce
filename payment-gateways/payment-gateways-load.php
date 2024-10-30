<?php

add_action('init', 'mafw_add_cors_http_header', 90);

if (!function_exists('mafw_add_cors_http_header')) {

    function mafw_add_cors_http_header()
    {

        remove_action('template_redirect', 'wc_send_frame_options_header');

        header("Access-Control-Allow-Origin: *");
    }
}

add_action('plugins_loaded', 'mafw_init', 0);

if (!function_exists('mafw_init')) {
    function mafw_init()
    {
        include_once 'GooglePlayBilling.php';

        include_once 'Storekit.php';
    }
}

add_filter('woocommerce_payment_gateways', 'mafw_woocommerce_payment_gateways');

if (!function_exists('mafw_woocommerce_payment_gateways')) {
    function mafw_woocommerce_payment_gateways($methods)
    {
        $methods[] = 'MobileAppForWooCommerce\PaymentGateWays\GooglePlayBilling';

        $methods[] = 'MobileAppForWooCommerce\PaymentGateWays\Storekit';

        return $methods;
    }
}


add_filter('woocommerce_available_payment_gateways', 'mafw_woocommerce_available_payment_gateways');

if (!function_exists('mafw_woocommerce_available_payment_gateways')) {
    function mafw_woocommerce_available_payment_gateways($methods)
    {
        unset($methods['shopapper-google-play-billing']);

        unset($methods['shopapper-storekit']);

        return $methods;
    }
}

add_filter('wcs_view_subscription_actions', 'mafw_wcs_view_subscription_actions', 10, 2);

if (!function_exists('mafw_wcs_view_subscription_actions')) {
    function mafw_wcs_view_subscription_actions($actions, $subscription)
    {

        if ($subscription->get_payment_method() !== 'shopapper-google-play-billing' and $subscription->get_payment_method() !== 'shopapper-storekit')
            return $actions;

        unset($actions['cancel']);

        return $actions;
    }
}

add_filter('wc_session_use_secure_cookie', '__return_true');
