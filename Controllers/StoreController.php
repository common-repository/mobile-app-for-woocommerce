<?php

namespace MobileAppForWooCommerce\Controllers;

use MobileAppForWooCommerce\Includes\Dashboard;
use MobileAppForWooCommerce\Includes\Helper;
use WP_REST_Request;
use WP_REST_Response;

class StoreController
{
    use Helper;

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'init']);
    }

    public function init()
    {
        register_rest_route(MAFW_CLIENT_ROUTE, '/store', [
            'methods' => 'GET',
            'callback' => [$this, 'get_store_data'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/get-pages', [
            'methods' => 'GET',
            'callback' => [$this, 'get_pages'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/get-page-by-url', [
            'methods' => 'GET',
            'callback' => [$this, 'get_page_by_url'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/get-products', [
            'methods' => 'GET',
            'callback' => [$this, 'get_products'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/get-product-by-id', [
            'methods' => 'GET',
            'callback' => [$this, 'get_product_by_id'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/get-product-categories', [
            'methods' => 'GET',
            'callback' => [$this, 'get_product_categories'],
            'permission_callback' => '__return_true',
        ]);

    }

    public function get_store_data(WP_REST_Request $request): WP_REST_Response
    {
        $tellix = [];

        if (class_exists('\WeptileTellix\Tellix') and !empty($request->get_param('token')) and $request->get_param('token') === Helper::get_token())
            $tellix = \WeptileTellix\Tellix::get_settings();

        $mycred_point_type_name_singular = 'Point';

        $mycred_point_type_name_plural = 'Points';

        $mycred_is_active = mafw_is_plugin_active('mycred/mycred.php');

        if (function_exists('mycred_get_point_type_name')) {
            $mycred_point_type_name_singular = mycred_get_point_type_name('mycred_default');

            $mycred_point_type_name_plural = mycred_get_point_type_name('mycred_default', false);
        }

        return new WP_REST_Response([
            'url' => get_site_url(),
            'password_reset_link' => wp_lostpassword_url(),
            'default_category' => get_option('default_product_cat', 0),
            'currency' => get_option('woocommerce_currency', 'GBP'),
            'currency_pos' => get_option('woocommerce_currency_pos'),
            'price_thousand_sep' => get_option('woocommerce_price_thousand_sep'),
            'price_decimal_sep' => get_option('woocommerce_price_decimal_sep'),
            'price_num_decimals' => get_option('woocommerce_price_num_decimals'),
            'checkout_url' => function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : '',
            'hide_out_of_stock_items' => get_option('woocommerce_hide_out_of_stock_items'),
            'stock_format' => get_option('woocommerce_stock_format'),
            'low_stock' => get_option('woocommerce_notify_low_stock_amount'),
            'login_urls' => [wp_login_url(), get_permalink(wc_get_page_id('myaccount'))],
            'logout_urls' => [wp_logout_url(), wc_logout_url()],
            'cart_url' => wc_get_cart_url(),
            'tellix' => $tellix,
            'myCred' => [
                'productDisplay' => get_option('reward_single_page_product') === 'yes' and $mycred_is_active,
                'cartDisplay' => get_option('reward_cart_product_meta') === 'yes' and $mycred_is_active,
                'singularText' => sprintf(__('Earn {value} %s', 'mycredpartwoo'), $mycred_point_type_name_singular),
                'pluralText' => sprintf(__('Earn {value} %s', 'mycredpartwoo'), $mycred_point_type_name_plural),
            ],


        ]);
    }

    public function get_pages(WP_REST_Request $request): WP_REST_Response
    {
        $args = [
            'numberposts' => -1,
            'post_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => 'publish',
            'post_type' => ['post', 'page', 'product']
        ];

        if (!empty($request->get_param('s')))
            $args['s'] = $request->get_param('s');

        $pages = get_posts($args);

        return new WP_REST_Response(array_map(function ($page) {
            $post_type = get_post_type_object($page->post_type);

            return [
                'id' => $page->ID,
                'title' => $page->post_title,
                'type' => $post_type->label,
                'url' => get_permalink($page->ID),
            ];
        }, $pages));
    }

    public function get_page_by_url(WP_REST_Request $request): WP_REST_Response
    {
        $post_id = url_to_postid($request->get_param('url'));

        if (empty($post_id))
            return new WP_REST_Response(['message' => 'Cannot find page'], 500);

        $post = get_post($post_id);

        $post_type = get_post_type_object($post->post_type);

        return new WP_REST_Response(['id' => $post->ID,
            'title' => $post->post_title,
            'type' => $post_type->label,
            'url' => get_permalink($post->ID),]);
    }

    public function get_products(WP_REST_Request $request): WP_REST_Response
    {

        $args = [
            'numberposts' => -1,
            'post_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => 'publish',
            'post_type' => ['product', 'product_variation']
        ];

        if (!empty($request->get_param('s')))
            $args['s'] = $request->get_param('s');

        $pages = get_posts($args);

        return new WP_REST_Response(array_map(function ($page) {
            return [
                'id' => $page->ID,
                'title' => $page->post_title
            ];
        }, $pages));
    }

    public function get_product_by_id(WP_REST_Request $request): WP_REST_Response
    {
        $product = get_post($request->get_param('id'));

        return new WP_REST_Response([
            'id' => $product->ID,
            'title' => $product->post_title
        ]);
    }

    public function get_product_categories(): WP_REST_Response
    {
        $product_categories = get_terms('product_cat', [
            'orderby' => 'name',
            'order' => 'asc',
            'hide_empty' => false,
        ]);

        $categories = [];

        foreach ($product_categories as $category)
            $categories[] = ['id' => $category->term_id, 'title' => $category->name];

        return new WP_REST_Response($categories);
    }
}

new StoreController();