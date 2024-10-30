<?php

namespace MobileAppForWooCommerce\Controllers;

use Exception;
use MobileAppForWooCommerce\Includes\Helper;
use Throwable;
use WC_REST_Products_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_Session_Tokens;

class CartController
{

    use Helper;

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'init']);

        add_filter('woocommerce_rest_is_request_to_rest_api', [$this, 'woocommerce_rest_is_request_to_rest_api']);

        add_filter('woocommerce_get_cart_page_id', [$this, 'woocommerce_get_cart_page_id'], 99);

        add_action('woocommerce_add_to_cart', [$this, 'woocommerce_add_to_cart'], 10, 6);
    }


    public function init()
    {
        register_rest_route(MAFW_CLIENT_ROUTE, '/cart', [
            'methods' => 'GET',
            'callback' => [$this, 'get_cart'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/clear', [
            'methods' => 'POST',
            'callback' => [$this, 'clear_cart'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/update-cart', [
            'methods' => 'POST',
            'callback' => [$this, 'update_cart'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/cart-item', [
            [
                'methods' => 'POST',
                'callback' => [$this, 'add_item'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'update_item'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'remove_item'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/search', [
            'methods' => 'GET',
            'callback' => [$this, 'get_searched_product'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * @return WP_REST_Response
     */
    public function clear_cart(WP_REST_Request $request)
    {
        if (!$this->is_user_logged_in($request->get_param('consumer_key')))
            return new WP_REST_Response(['message' => 'Authentication failed.'], 500);

        $this->init_wc();

        WC()->cart->empty_cart();

        WC()->session->set('cart', []);

        if (WC()->cart->is_empty()) {
            return new WP_REST_Response('Cart is cleared.', 200);
        }
        return new WP_REST_Response('Cart clear failed.', 500);
    }

    public function validate_session_token()
    {

        if (!$this->is_loggedin_active()) return true;

        $consumer_key = $_GET['consumer_key'];

        if (empty($consumer_key)) return true;

        $session_token = get_user_meta(get_current_user_id(), $consumer_key, true);

        if (empty($session_token)) return true;

        $manager = WP_Session_Tokens::get_instance(get_current_user_id());

        return !is_null($manager->get($session_token));
    }

    /**
     * @return WP_REST_Response
     */
    public function get_cart(WP_REST_Request $request)
    {
        if (!$this->is_user_logged_in($request->get_param('consumer_key')))
            return new WP_REST_Response(['message' => 'Authentication failed.'], 500);

        if (!$this->validate_session_token())
            return new WP_REST_Response(['message' => 'Authentication failed.'], 500);

        $this->init_wc();

        return new WP_REST_Response($this->get_cart_data(), 200);
    }

    public static function get_pre_order_info($product)
    {
        if (get_post_meta($product->get_id(), '_pre_order_date', true) !== null) {

            $availableFrom = new \DateTime(get_post_meta($product->get_id(), '_pre_order_date', true));
            $now = new \DateTime();

            $diff = $now->diff($availableFrom)->format('%a');

            if ($availableFrom > $now && $diff > 0) {

                $notice = get_option('wc_preorders_cart_product_text', 'Note: this item will be available for shipping in {days_left} days');

                return str_replace(['{days_left}', '{date_format}'], [$diff, $availableFrom->format(get_option('date_format'))], $notice);

            }
        }

        return '';
    }

    /**
     * @return array
     */
    public function get_cart_data()
    {
        $product_controller = new WC_REST_Products_Controller();

        $cart_items = [];

        foreach (WC()->cart->get_cart() as $cart_item) {
            $product = $product_controller->prepare_object_for_response(wc_get_product($cart_item['product_id']), new WP_REST_Request('', '', ['context' => 'view']));

            $cart_item['product'] = $product->get_data();

            $cart_item['info'] = self::get_pre_order_info($cart_item['data']);

            $cart_items[] = $cart_item;
        }

        return [
            'items' => $cart_items,
            'subtotal' => WC()->cart->get_subtotal(),
            'total' => WC()->cart->get_total(''),
            'count' => WC()->cart->get_cart_contents_count(),
            'customer_id' => get_current_user_id()
        ];
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_searched_product(WP_REST_Request $request)
    {
        return new WP_REST_Response(wc_get_product($request->get_param('id'))->get_data(), 200);
    }

    /**
     * @param WP_REST_Request $request
     * @param null $itemsIndex
     * @return WP_REST_Response
     */
    public function add_item(WP_REST_Request $request, $itemsIndex = null)
    {
        try {

            if (!$this->is_user_logged_in($request->get_param('consumer_key')))
                return new WP_REST_Response(['message' => 'Authentication failed.'], 500);

            $this->init_wc();

            if (!is_null($itemsIndex) and !empty($request->get_param('items')[$itemsIndex])) {

                $product_id = $request->get_param('items')[$itemsIndex]['product_id'];
                $quantity = $request->get_param('items')[$itemsIndex]['quantity'];
                $variation_id = $request->get_param('items')[$itemsIndex]['variation_id'];
                $variation = $request->get_param('items')[$itemsIndex]['variation'];

            } else {

                $product_id = $request->get_param('product_id');
                $quantity = $request->get_param('quantity');
                $variation_id = $request->get_param('variation_id');
                $variation = $request->get_param('variation');

            }

            $wc_product = wc_get_product($variation_id ?: $product_id);

            try {

                if (!empty($request->get_param('clear_cart')) and $request->get_param('clear_cart'))
                    WC()->cart->empty_cart();

                $item_key = WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation, []);

                if (!$item_key) {

                    $error_message = 'Unable to add product to cart';

                    $wc_notices = wc_get_notices('error');

                    if (!empty($wc_notices))
                        $error_message = end($wc_notices)['notice'];

                    throw new Exception($error_message);
                }

                $this->add_log(['appId' => $request->get_param('app_id'), 'event' => 'cart_item_added', 'amount' => $wc_product->get_price() * $quantity, 'identity' => $wc_product->get_name(), 'quantity' => $quantity]);

            } catch (Throwable $e) {

                return new WP_REST_Response(['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);

            }

            $data = WC()->cart->get_cart_item($item_key);

            do_action('wc_cart_rest_add_to_cart', $item_key, $data);

            if (is_array($data)) {
                return new WP_REST_Response(['message' => 'Added to cart', 'cart' => $this->get_cart_data()], 200);
            } else {
                return new WP_REST_Response(['message' => sprintf('You cannot add "%s" to your cart.', $wc_product->get_name())], 500);
            }
        } catch (Throwable $e) {
            var_dump($e->getMessage());
            return new WP_REST_Response(['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
        }
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function update_item(WP_REST_Request $request)
    {
        if (!$this->is_user_logged_in($request->get_param('consumer_key')))
            return new WP_REST_Response(['message' => 'Authentication failed.'], 500);

        $this->init_wc();

        $cart_item_key = wc_clean($request->get_param('key'));

        $quantity = absint($request->get_param('quantity'));

        $cart_item = WC()->cart->get_cart_item($cart_item_key);

        // if cart item exist update it otherwise add new item.
        if ($cart_item) {

            if (WC()->cart->set_quantity($cart_item_key, $quantity))
                return new WP_REST_Response(['cart' => $this->get_cart_data()], 200);
            else
                return new WP_REST_Response(['message' => 'Unable to update item quantity in cart.'], 500);
        } else {
            return $this->add_item($request);
        }
    }

    public function update_cart(WP_REST_Request $request)
    {
        if (!$this->is_user_logged_in($request->get_param('consumer_key')))
            return new WP_REST_Response(['message' => 'Authentication failed.'], 500);

        $this->init_wc();

        $cart_items = $request->get_param('items');

        foreach ($cart_items as $index => $cart_item) {

            // if cart item exist update it otherwise add new item.
            if (!empty($cart_item['key']) and WC()->cart->get_cart_item($cart_item['key'])) {
                if (!WC()->cart->set_quantity($cart_item['key'], $cart_item['quantity']))
                    return new WP_REST_Response(['message' => 'Unable to update item quantity in cart.'], 500);
            } else {
                $this->add_item($request, $index);
            }
        }

        return new WP_REST_Response(['cart' => $this->get_cart_data()], 200);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function remove_item(WP_REST_Request $request)
    {
        if (!$this->is_user_logged_in($request->get_param('consumer_key')))
            return new WP_REST_Response(['message' => 'Authentication failed.'], 500);

        $this->init_wc();

        $cart_item_key = wc_clean($request->get_param('key'));

        if (WC()->cart->remove_cart_item($cart_item_key)) {
            return new WP_REST_Response(['message' => 'Item has been removed from cart.', 'cart' => $this->get_cart_data()], 200);
        }

        return new WP_REST_Response(['message' => 'Unable to remove the item from cart.'], 500);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function restore_item(WP_REST_Request $request)
    {
        $this->init_wc();

        $cart_item_key = wc_clean($request->get_param('cart_item_key'));

        if (WC()->cart->restore_cart_item($cart_item_key)) {
            return new WP_REST_Response('Item has been restored to the cart.', 200);
        }

        return new WP_REST_Response(['message' => 'Unable to restore item to the cart.'], 500);
    }

    /**
     * @param $is_rest_api
     * @return bool
     */
    public function woocommerce_rest_is_request_to_rest_api($is_rest_api)
    {
        $request_uri = esc_url_raw(wp_unslash($_SERVER['REQUEST_URI']));

        if (false !== strpos($request_uri, MAFW_CLIENT_ROUTE)) return true;

        return $is_rest_api;
    }

    /**
     * @param $cart_page_id
     * @return int
     */
    public function woocommerce_get_cart_page_id($cart_page_id)
    {
        $cart_page = get_post($cart_page_id);

        if (is_null($cart_page) or $cart_page->post_status !== 'publish') return '';

        return $cart_page_id;
    }

    public function woocommerce_add_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data)
    {
        if (!$this->is_shopapper()) return;

        $product = wc_get_product($variation_id ?: $product_id);

        $this->add_log(['appId' => $this->get_app_id_from_request(), 'event' => 'cart_item_added', 'amount' => $product->get_price() * $quantity, 'identity' => $product->get_name(), 'quantity' => $quantity]);
    }

}

new CartController();