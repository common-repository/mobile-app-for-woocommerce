<?php

namespace MobileAppForWooCommerce\Controllers;

use MobileAppForWooCommerce\Includes\Beans;
use MobileAppForWooCommerce\Includes\Helper;
use Throwable;
use WC_Checkout;
use WP_REST_Request;
use WP_REST_Response;

class CheckoutController
{
    use Helper;


    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);

        add_action('woocommerce_checkout_create_order', [$this, 'woocommerce_checkout_create_order'], 10, 2);

        add_action('woocommerce_checkout_update_order_meta', [$this, 'woocommerce_checkout_update_order_meta'], 10, 2);

        add_action('woocommerce_order_status_cancelled', [$this, 'woocommerce_order_status_cancelled'], 10, 1);
    }

    /**
     * @description Register rest endpoint routes.
     */
    public function register_routes()
    {

        register_rest_route(MAFW_CLIENT_ROUTE, '/get-checkout-fields', [
            'methods' => 'GET',
            'callback' => [$this, 'get_checkout_fields'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/get-available-shipping-methods', [
            'methods' => 'POST',
            'callback' => [$this, 'get_available_shipping_methods'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/get-last-order', [
            'methods' => 'GET',
            'callback' => [$this, 'get_last_order'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * @return WP_REST_Response
     */
    public function get_checkout_fields()
    {
        try {

            $this->init_wc();

            $checkout = new WC_Checkout();

            return new WP_REST_Response([
                'fields' => $checkout->get_checkout_fields(),
                'need_shipping' => WC()->cart->needs_shipping_address(),
                'allowed_countries' => WC()->countries->get_allowed_countries(),
                'shipping_countries' => WC()->countries->get_shipping_countries(),
                'states' => WC()->countries->get_states(),
            ]);
        } catch (Throwable $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_available_shipping_methods(WP_REST_Request $request)
    {
        try {

            $this->init_wc();

            WC()->customer->set_props(
                array(
                    'billing_country' => !empty($request->get_param('billing_country')) ? wc_clean(wp_unslash($request->get_param('billing_country'))) : null,
                    'billing_state' => !empty($request->get_param('billing_state')) ? wc_clean(wp_unslash($request->get_param('billing_country'))) : null,
                )
            );

            if (wc_ship_to_billing_address_only()) {
                WC()->customer->set_props(
                    array(
                        'shipping_country' => !empty($request->get_param('billing_country')) ? wc_clean(wp_unslash($request->get_param('billing_country'))) : null,
                        'shipping_state' => !empty($request->get_param('billing_state')) ? wc_clean(wp_unslash($request->get_param('billing_country'))) : null,
                    )
                );
            } else {
                WC()->customer->set_props(
                    array(
                        'shipping_country' => !empty($request->get_param('shipping_country')) ? wc_clean(wp_unslash($request->get_param('shipping_country'))) : null,
                        'shipping_state' => !empty($request->get_param('shipping_state')) ? wc_clean(wp_unslash($request->get_param('shipping_state'))) : null,
                    )
                );
            }

            WC()->customer->set_calculated_shipping(true);

            WC()->customer->save();

            WC()->cart->calculate_shipping();

            WC()->cart->calculate_totals();

            $packages = WC()->shipping()->get_packages();

            if (empty($packages))
                return new WP_REST_Response(['methods' => []]);

            $shipping_methods = WC()->shipping()->get_shipping_methods();

            $rate_table = [];

            foreach ($shipping_methods as $shipping_method) {
                $shipping_method->init();

                foreach ($shipping_method->rates as $key => $val)
                    foreach ($packages[0]['rates'] as $rate_id => $rate)
                        if ($rate_id === $key)
                            $rate_table[$key] = $val->label;
            }

            return new WP_REST_Response(['methods' => $rate_table]);
        } catch (Throwable $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get last order of current user
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response : WP_REST_Response || WP_Error
     */
    public function get_last_order(WP_REST_Request $request)
    {
        $this->init_wc();

        // get customer by consumer key
        $current_user = wp_get_current_user();


        if ($current_user) {
            $user_id = $current_user->ID;
        } else {
            $user_id = $this->get_user_id_by_consumer_key($request->get_param('consumer_key'));
        }

        // if user not found with given consumer key return error.
        if (!$user_id) {
            return new WP_REST_Response('Customer not found!', 500);
        }

        //get customer last order
        $last_order = $this->get_customer_last_order($user_id);

        if (!$last_order)
            return new WP_REST_Response(['id' => 0]);

        ob_start();

        do_action('woocommerce_thankyou', $last_order->get_id());

        ob_get_clean();

        WC()->cart->empty_cart();

        return new WP_REST_Response(self::parse_order($last_order), 200);
    }

    /**
     * @param $order
     * @return array
     */
    public static function parse_order($order)
    {

        $order_items = [];

        $payment_gateways = [];

        if (WC()->payment_gateways())
            $payment_gateways = WC()->payment_gateways->payment_gateways();

        $payment_method = $order->get_payment_method();


        foreach ($order->get_items() as $order_item) {
            $order_items[] = [
                'title' => $order_item->get_name(),
                'quantity' => $order_item->get_quantity(),
                'subtotal' => $order_item->get_subtotal(),
                'total' => $order_item->get_subtotal(),
                'tax' => $order_item->get_total_tax(),
            ];
        }

        return [
            'id' => $order->get_id(),
            'date' => date(get_option('date_format'), strtotime($order->get_date_created())),
            'billingAddress' => $order->get_formatted_billing_address(),
            'shippingAddress' => $order->get_formatted_shipping_address(),
            'orderItems' => $order_items,
            'shippingMethod' => $order->get_shipping_method(),
            'usedCoupons' => $order->get_coupons(),
            'paymentMethod' => isset($payment_gateways[$payment_method]) ? $payment_gateways[$payment_method]->get_title() : $payment_method,
            'total' => $order->get_total(),
            'email' => $order->get_billing_email()
        ];
    }

    /**
     * Add shopapper meta to order.
     * @param $order
     * @param $data
     * @return void
     */
    public function woocommerce_checkout_create_order($order, $data)
    {
        if ($this->is_shopapper()) {

            $order->update_meta_data('shopapper_order', '1');

            try {
                if (Beans::is_active() and count(Helper::get_customer_shopapper_orders($order->get_customer_id())) === 0)
                    Beans::add_credit($order);
            } catch (Throwable $e) {
                error_log(print_r($e, true));
            }
        }
    }

    /**
     * @param $order_id
     * @param $data
     * @return void
     */
    public function woocommerce_checkout_update_order_meta($order_id, $data)
    {
        if (get_post_meta($order_id, 'shopapper_order', true) !== '1') return;

        $order = wc_get_order($order_id);

        $this->add_log(['appId' => $this->get_app_id_from_request(), 'event' => 'order_created', 'amount' => $order->get_total(), 'identity' => $order->get_id()]);
    }

    public function woocommerce_order_status_cancelled($order_id)
    {
        if (get_post_meta($order_id, 'shopapper_order', true) !== '1') return;

        $order = wc_get_order($order_id);

        if ($order)
            $this->add_log(['appId' => $this->get_app_id_from_request(), 'event' => 'order_cancelled', 'amount' => $order->get_total(), 'identity' => $order->get_id()]);


    }

}

new CheckoutController();