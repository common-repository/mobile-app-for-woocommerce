<?php

namespace MobileAppForWooCommerce\Controllers;

use MobileAppForWooCommerce\Includes\Helper;
use Exception;
use MobileAppForWooCommerce\Includes\Stock;
use Throwable;
use WC_REST_Products_Controller;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;

class ProductController
{
    use Helper;

    static $default_search_fields = ['sku', 'slug'];

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'init'], 0, 0);

        add_filter('woocommerce_rest_product_object_query', [$this, 'woocommerce_rest_product_object_query'], 10, 2);
        add_filter('woocommerce_rest_prepare_product_object', [$this, 'woocommerce_rest_prepare_product_object'], 10, 3);

    }

    public function init()
    {

        register_rest_route(MAFW_CLIENT_ROUTE, '/product/scanner-search-fields', [
            'methods' => 'GET',
            'callback' => [$this, 'get_scanner_search_fields'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/product/search', [
            'methods' => 'POST',
            'callback' => [$this, 'search'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/product/swp/search', [
            'methods' => 'GET',
            'callback' => [$this, 'swp_search'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/product/wp/search', [
            'methods' => 'POST',
            'callback' => [$this, 'wp_search'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/product/batch-update-stock', [
            'methods' => 'POST',
            'callback' => [$this, 'batch_update_stock'],
            'permission_callback' => '__return_true',
        ]);


        register_rest_route(MAFW_CLIENT_ROUTE, '/products', [
            'methods' => 'GET',
            'callback' => [$this, 'products'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/products/(?P<id>[\d]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'product'],
            'permission_callback' => [$this, 'validate']
        ]);
    }


    public function get_scanner_search_fields()
    {
        try {
            global $wpdb;

            $fields = ['sku', 'id', 'slug'];

            $meta_keys = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT meta_key
				FROM $wpdb->postmeta
				WHERE meta_key NOT BETWEEN '_' AND '_z'
				HAVING meta_key NOT LIKE %s
				ORDER BY meta_key
				LIMIT %d",
                    $wpdb->esc_like('_') . '%',
                    100
                )
            );

            return new WP_REST_Response(array_merge($fields, $meta_keys));
        } catch (Throwable $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }

    }

    public function search(WP_REST_Request $request): WP_REST_Response
    {
        try {


            $this->init_wc();

            $product_controller = new WC_REST_Products_Controller();

            $search = $request->get_param('search');

            if ($search['field'] === 'id')
                return new WP_REST_Response([$product_controller->prepare_object_for_response(wc_get_product($search['value']), new WP_REST_Request('get', '', ['context' => 'view']))->get_data()]);

            $args = [
                'order' => 'asc',
                'orderBy' => 'title',
                'numberposts' => 1,
                'post_type' => ['product', 'product_variation'],
                'fields' => 'ids'
            ];

            if (is_array($search['field'])) {
                $args['meta_query'] = [
                    'relation' => 'OR',
                ];

                foreach ($search['field'] as $field) {
                    if ($field === 'slug')
                        $args['name'] = $search['value'];
                    else
                        $args['meta_query'][] = [
                            'key' => $field === 'sku' ? '_sku' : $field,
                            'value' => $search['value']
                        ];
                }
            } else {
                if ($search['field'] === 'slug')
                    $args['name'] = $search['value'];
                else
                    $args['meta_query'] = [
                        [
                            'key' => $search['field'] === 'sku' ? '_sku' : $search['field'],
                            'value' => $search['value']
                        ]
                    ];
            }

            $products = get_posts($args);

            if (empty($products))
                return new WP_REST_Response([]);

            return new WP_REST_Response([$product_controller->prepare_object_for_response(wc_get_product($products[0]), new WP_REST_Request('get', '', ['context' => 'view']))->get_data()]);

        } catch (Throwable $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    public function batch_update_stock(WP_REST_Request $request): WP_REST_Response
    {
        try {

            if (!is_user_logged_in())
                throw new Exception('Authentication failed.');

            $this->init_wc();

            $products = $request->get_param('products');

            foreach ($products as $product) {

                $wc_product = wc_get_product($product['id']);

                $original_stock_quantity = $wc_product->get_stock_quantity();

                $wc_product->set_stock_quantity($product['stock_quantity']);

                $wc_product->save();

                Stock::log_stock_update($wc_product, $product['stock_quantity'], $original_stock_quantity, get_current_user_id());
            }

            return new WP_REST_Response([]);


        } catch (Throwable $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    public function woocommerce_rest_product_object_query($args, $request)
    {
        global $wpdb;

        $params = $request->get_params();

        $taxonomies = array(
            'product_cat' => 'collection',
            'pa_usage-labels' => 'usage_area',
            'pa_color' => 'color',
            'pa_material' => 'material',
            'pa_shape' => 'shape',
            'product_tag' => 'product_status'
        );

        $tax_query = [];

        // Set tax_query for each passed arg.
        foreach ($taxonomies as $taxonomy => $key) {
            if (!empty($params[$key])) {
                $tax_query[] = [
                    'taxonomy' => $taxonomy,
                    'field' => 'term_id',
                    'terms' => $request[$key],
                ];
            }
        }

        if (!empty($request->get_param('shopapper_search'))) {

            if (empty($args['meta_query']))
                $args['meta_query'] = [];

            $args['meta_query'][] = [
                'key' => '_sku',
                'value' => $wpdb->esc_like($request->get_param('shopapper_search')),
                'compare' => 'LIKE',
            ];

            $args['s'] = '';

            $args['search'] = '';


        }

        $args['tax_query'] = $tax_query;

        if ($request->get_param('orderby') === 'popularity') {

            $args['orderby'] = 'meta_value_num';

            $args['order'] = $request->get_param('order') ?? 'desc';

            $args['meta_key'] = 'total_sales';

            $request->set_param('orderby', 'meta_value_num');
        }

        return $args;
    }

    public function woocommerce_rest_prepare_product_object($response, $object, $request)
    {

        try {

            if (!empty($request->get_param('shopapper-mir-mosaic')) and $object->is_type('variable')) {

                $data = $response->get_data();

                $price_html = '';

                $default_variation_id = 0;

                $default_variation_price = 0;

                $available_variations = $object->get_available_variations();

                $stock_status = $data['stock_status'];

                $stocks_by_location = [];

                $location_ids = [];

                if (class_exists('MWWH_Order_Routing_Init') and $data['stock_status'] !== 'instock')
                    $location_ids = get_location_ids();

                if (!empty($available_variations)) {

                    foreach ($available_variations as $variations) {

                        if ($variations['attributes']['attribute_pa_sheet-size'] === '1') {

                            $default_variation_id = $variations['variation_id'];

                            $default_variation_price = $variations['display_price'];
                        }
                    }

                    if ($default_variation_id) {

                        $price = apply_filters('woocommerce_variable_price_html', wc_format_price_range(0, $default_variation_price) . $object->get_price_suffix(), $this);

                        $price_html = apply_filters('woocommerce_get_price_html', $price, $object);

                        $data['default_variation_price'] = $default_variation_price;

                        foreach ($location_ids as $location_id) {

                            $location_title = get_the_title($location_id);

                            $stocks_by_location[$location_id] = [
                                'location' => $location_title,
                                'stock' => floatval(get_post_meta($default_variation_id, "_stocks_location_{$location_id}", true))
                            ];

                            if ($stocks_by_location[$location_id]['stock'] > 0)
                                $stock_status = 'instock';

                        }
                    }
                }

                $data['price_html'] = $price_html;

                $data['stock_status'] = $stock_status;

                $data['stocks_by_location'] = $stocks_by_location;

                $response->set_data($data);
            }

        } catch (Throwable $e) {
        }

        return $response;
    }

    public function swp_search(WP_REST_Request $request): WP_REST_Response
    {
        try {

            if (!class_exists('SWP_Query'))
                throw new Exception('SWP not found');

            $args = [
                'engine' => apply_filters('searchwp\rest\engine', 'default', ['request' => $request]),
                'fields' => 'ids',
                'post_type' => 'product',
                'page' => 1,
                'posts_per_page' => 10,
            ];

            if (!empty($request['search'])) {
                $args['s'] = $request['search'];
            }

            if (!empty($request['page'])) {
                $args['page'] = $request['page'];
            }

            if (!empty($request['per_page'])) {
                $args['posts_per_page'] = $request['per_page'];
            }

            $args = apply_filters('searchwp\rest\args', $args, ['request' => $request]);

            $product_controller = new WC_REST_Products_Controller();

            $query = new \SWP_Query($args);

            $data = [];

            $product_controller_request = new WP_REST_Request('get', '', ['context' => 'view']);

            $product_controller_request->set_param('shopapper-mir-mosaic', $request->get_param('shopapper-mir-mosaic'));

            foreach ($query->posts as $post_id) {

                $wc_product = wc_get_product($post_id);

                if ($wc_product)
                    $data[] = $product_controller->prepare_object_for_response($wc_product, $product_controller_request)->get_data();
            }

            $response = new WP_REST_Response();

            $response->set_headers([
                'X-WP-Total' => $query->found_posts,
                'X-WP-TotalPages' => (int)ceil($query->found_posts / $args['posts_per_page']),
            ]);

            $response->set_data($data);

            return $response;
        } catch (Throwable $e) {

            return new Wp_rest_Response(['message' => $e->getMessage()], 500);
        }
    }

}

new ProductController();