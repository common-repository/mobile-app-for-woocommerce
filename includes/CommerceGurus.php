<?php

class CommerceGurus
{
    function __construct()
    {

        add_action('rest_api_init', [$this, 'init']);
    }

    public function init()
    {

        register_rest_route(MAFW_CLIENT_ROUTE, '/commerce-gurus/wishlist', [
            'methods' => 'POST',
            'callback' => [$this, 'get'],
            'args' => [
                'userId' => ['required' => true],
                'consumer_key' => ['required' => true],
                'consumer_secret' => ['required' => true],
            ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/commerce-gurus/wishlist/add', [
            'methods' => 'POST',
            'callback' => [$this, 'add'],
            'args' => [
                'userId' => ['required' => true],
                'productId' => ['required' => true],
                'consumer_key' => ['required' => true],
                'consumer_secret' => ['required' => true],
            ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/commerce-gurus/wishlist/remove', [
            'methods' => 'POST',
            'callback' => [$this, 'remove'],
            'args' => [
                'userId' => ['required' => true],
                'productId' => ['required' => true],
                'consumer_key' => ['required' => true],
                'consumer_secret' => ['required' => true],
            ],
            'permission_callback' => '__return_true',
        ]);
    }

    public function get(WP_REST_Request $request): WP_REST_Response
    {
        try {

            global $wpdb;

            if(!is_user_logged_in())
                throw new Exception('Bad Authentication');

            $table = $wpdb->prefix . 'commercekit_wishlist_items';

            $wishlist_items = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d;", $request->get_param('userId')));

            $products = [];

            $product_controller = new WC_REST_Products_Controller();

            $product_controller_request = new WP_REST_Request('get', '', ['context' => 'view']);

            $product_controller_request->set_param('shopapper-mir-mosaic', $request->get_param('shopapper-mir-mosaic'));

            foreach ($wishlist_items as $wishlist_item) {

                $product = wc_get_product($wishlist_item->product_id);

                if ($product)
                    $products[] = $product_controller->prepare_object_for_response($product, $product_controller_request)->get_data();
            }

            return new WP_REST_Response($products);

        } catch (Throwable $e) {
            return new WP_REST_Response($e->getMessage(), 500);
        }
    }

    public function add(WP_REST_Request $request): WP_REST_Response
    {
        try {
            global $wpdb;

            if(!is_user_logged_in())
                throw new Exception('Bad Authentication');

            $table = $wpdb->prefix . 'commercekit_wishlist_items';

            if (is_null($wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d and product_id=%d", $request->get_param('userId'), $request->get_param('productId')))))
                $wpdb->insert($table, [
                    'user_id' => $request->get_param('userId'),
                    'list_id' => 0,
                    'product_id' => $request->get_param('productId'),
                    'created' => time(),
                ], ['%d', '%d', '%d', '%d']);

            return new WP_REST_Response('Product successfully added from wishlist.');

        } catch (Throwable $e) {
            return new WP_REST_Response($e->getMessage(), 500);
        }
    }

    public function remove(WP_REST_Request $request): WP_REST_Response
    {
        try {

            global $wpdb;

            if(!is_user_logged_in())
                throw new Exception('Bad Authentication');

            $table = $wpdb->prefix . 'commercekit_wishlist_items';

            $data = array(
                'user_id' => $request->get_param('userId'),
                'product_id' => $request->get_param('productId'),
            );
            $format = array('%d', '%d');

            $wpdb->delete($table, $data, $format);

            return new WP_REST_Response('Product successfully removed from wishlist.');

        } catch (Throwable $e) {

            return new WP_REST_Response($e->getMessage(), 500);
        }
    }
}

new CommerceGurus();