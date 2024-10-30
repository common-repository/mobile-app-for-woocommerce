<?php


namespace MobileAppForWooCommerce\Controllers;


use Exception;
use MobileAppForWooCommerce\Includes\Helper;
use Throwable;
use WC_Data_Exception;
use WC_Subscriptions_Cart;
use WC_Subscriptions_Checkout;
use WP_REST_Request;
use WP_REST_Response;

class SubscriptionController
{
    use Helper;

    static $iap_subscription_meta = 'shopapper_iap_subscription_id';

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * @description Register rest endpoint routes.
     */
    public function register_routes()
    {

        register_rest_route(MAFW_CLIENT_ROUTE, '/create-subscription', [
            'methods' => 'POST',
            'callback' => [$this, 'create_subscription'],
            'permission_callback' => '__return_true',
        ]);


        register_rest_route(MAFW_CLIENT_ROUTE, '/pub-sub', [
            'methods' => 'POST',
            'callback' => [$this, 'pub_sub'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/app-store-server-notification', [
            'methods' => 'POST',
            'callback' => [$this, 'app_store_server_notification'],
            'permission_callback' => '__return_true',
        ]);
    }


    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function create_subscription(WP_REST_Request $request)
    {
        try {

            $this->init_wc();


            if (empty($request->get_param('payment_method')) or empty($request->get_param('iap_subscription_id')))
                throw new Exception('Missing fields');

            WC()->session->set('chosen_shipping_methods', [wc_clean(wp_unslash($request->get_param('shipping_method')))]);


            WC()->customer->set_props(
                [
                    'billing_first_name' => !empty($request->get_param('billing_first_name')) ? wc_clean(wp_unslash($request->get_param('billing_first_name'))) : null,
                    'billing_last_name' => !empty($request->get_param('billing_last_name')) ? wc_clean(wp_unslash($request->get_param('billing_last_name'))) : null,
                    'billing_company' => !empty($request->get_param('billing_company')) ? wc_clean(wp_unslash($request->get_param('billing_company'))) : null,
                    'billing_country' => !empty($request->get_param('billing_country')) ? wc_clean(wp_unslash($request->get_param('billing_country'))) : null,
                    'billing_state' => !empty($request->get_param('billing_state')) ? wc_clean(wp_unslash($request->get_param('billing_state'))) : null,
                    'billing_postcode' => !empty($request->get_param('billing_postcode')) ? wc_clean(wp_unslash($request->get_param('billing_postcode'))) : null,
                    'billing_city' => !empty($request->get_param('billing_city')) ? wc_clean(wp_unslash($request->get_param('billing_city'))) : null,
                    'billing_address_1' => !empty($request->get_param('billing_address_1')) ? wc_clean(wp_unslash($request->get_param('billing_address_1'))) : null,
                    'billing_address_2' => !empty($request->get_param('billing_address_2')) ? wc_clean(wp_unslash($request->get_param('billing_address_2'))) : null,
                    'billing_phone' => !empty($request->get_param('billing_phone')) ? wc_clean(wp_unslash($request->get_param('billing_phone'))) : null,
                    'billing_email' => !empty($request->get_param('billing_email')) ? wc_clean(wp_unslash($request->get_param('billing_email'))) : null,
                ]
            );

            if (wc_ship_to_billing_address_only()) {
                WC()->customer->set_props(
                    [
                        'shipping_first_name' => !empty($request->get_param('billing_first_name')) ? wc_clean(wp_unslash($request->get_param('billing_first_name'))) : null,
                        'shipping_last_name' => !empty($request->get_param('billing_last_name')) ? wc_clean(wp_unslash($request->get_param('billing_last_name'))) : null,
                        'shipping_company' => !empty($request->get_param('billing_company')) ? wc_clean(wp_unslash($request->get_param('billing_company'))) : null,
                        'shipping_country' => !empty($request->get_param('billing_country')) ? wc_clean(wp_unslash($request->get_param('billing_country'))) : null,
                        'shipping_state' => !empty($request->get_param('billing_state')) ? wc_clean(wp_unslash($request->get_param('billing_state'))) : null,
                        'shipping_postcode' => !empty($request->get_param('billing_postcode')) ? wc_clean(wp_unslash($request->get_param('billing_postcode'))) : null,
                        'shipping_city' => !empty($request->get_param('billing_city')) ? wc_clean(wp_unslash($request->get_param('billing_city'))) : null,
                        'shipping_address_1' => !empty($request->get_param('billing_address_1')) ? wc_clean(wp_unslash($request->get_param('billing_address_1'))) : null,
                        'shipping_address_2' => !empty($request->get_param('billing_address_2')) ? wc_clean(wp_unslash($request->get_param('billing_address_2'))) : null,
                    ]
                );
            } else {
                WC()->customer->set_props(
                    [
                        'shipping_first_name' => !empty($request->get_param('shipping_first_name')) ? wc_clean(wp_unslash($request->get_param('shipping_first_name'))) : null,
                        'shipping_last_name' => !empty($request->get_param('shipping_last_name')) ? wc_clean(wp_unslash($request->get_param('shipping_last_name'))) : null,
                        'shipping_company' => !empty($request->get_param('shipping_company')) ? wc_clean(wp_unslash($request->get_param('shipping_company'))) : null,
                        'shipping_country' => !empty($request->get_param('shipping_country')) ? wc_clean(wp_unslash($request->get_param('shipping_country'))) : null,
                        'shipping_state' => !empty($request->get_param('shipping_state')) ? wc_clean(wp_unslash($request->get_param('shipping_state'))) : null,
                        'shipping_postcode' => !empty($request->get_param('shipping_postcode')) ? wc_clean(wp_unslash($request->get_param('shipping_postcode'))) : null,
                        'shipping_city' => !empty($request->get_param('shipping_city')) ? wc_clean(wp_unslash($request->get_param('shipping_city'))) : null,
                        'shipping_address_1' => !empty($request->get_param('shipping_address_1')) ? wc_clean(wp_unslash($request->get_param('shipping_address_1'))) : null,
                        'shipping_address_2' => !empty($request->get_param('shipping_address_2')) ? wc_clean(wp_unslash($request->get_param('shipping_address_2'))) : null,]
                );
            }

            WC()->customer->set_calculated_shipping(true);

            WC()->customer->save();

            WC()->cart->calculate_shipping();

            WC()->cart->calculate_totals();

            return new WP_REST_Response(CheckoutController::parse_order($this->maybe_create_subscription($request->get_param('payment_method'), $request->get_param('iap_subscription_id'), $request->get_param('iap_transaction_id'))));

        } catch (Throwable $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @throws Exception
     */
    public function get_subscription_by_iap_subscription_id($iap_subscription_id)
    {
        $subscriptions = get_posts([
            'meta_key' => self::$iap_subscription_meta,
            'meta_value' => $iap_subscription_id,
            'fields' => 'ids',
            'post_type' => 'shop_subscription',
            'post_status' => 'any'
        ]);

        if (empty($subscriptions))
            throw new Exception('Cannot find subscription');

        $subscription = wcs_get_subscription($subscriptions[0]);

        if ($subscription === false)
            throw new Exception('Cannot find subscription');

        return $subscription;
    }

    /**
     * @throws Exception
     * @throws WC_Data_Exception
     */
    public function maybe_create_subscription($payment_method, $iap_subscription_id, $iap_transaction_id)
    {

        $cart = WC()->cart;

        try {
            $old_subscription = $this->get_subscription_by_iap_subscription_id($iap_subscription_id);

            $this->maybe_re_subscription($iap_transaction_id);

            $cart->empty_cart();

            return $old_subscription->get_last_order('all');

        } catch (Throwable $e) {
        }

        if (empty($cart->cart_contents))
            throw new Exception('Cart is empty');

        if (count($cart->cart_contents) > 1)
            throw new Exception('Cannot support multiple product subscription');

        if (!class_exists('WC_Subscriptions_Checkout'))
            throw new Exception('Woocommerce Subscription not installed');

        if (!WC_Subscriptions_Cart::cart_contains_subscription())
            throw new Exception('Only subscription products');

        $checkout = WC()->checkout();

        $customer = WC()->customer;

        WC_Subscriptions_Cart::calculate_subscription_totals($cart->get_total(), $cart);

        $order_id = $checkout->create_order([
            'customer_id' => get_current_user_id(),
            'payment_method' => $payment_method,
            'shipping_first_name' => $customer->get_shipping_first_name(),
            'shipping_last_name' => $customer->get_shipping_last_name(),
            'shipping_company' => $customer->get_shipping_company(),
            'shipping_address_1' => $customer->get_shipping_address_1(),
            'shipping_address_2' => $customer->get_shipping_address_2(),
            'shipping_city' => $customer->get_shipping_city(),
            'shipping_postcode' => $customer->get_shipping_postcode(),
            'shipping_state' => $customer->get_shipping_state(),
            'shipping_country' => $customer->get_shipping_country(),
            'billing_first_name' => $customer->get_billing_first_name(),
            'billing_last_name' => $customer->get_billing_last_name(),
            'billing_company' => $customer->get_billing_company(),
            'billing_address_1' => $customer->get_billing_address_1(),
            'billing_address_2' => $customer->get_billing_address_2(),
            'billing_city' => $customer->get_billing_city(),
            'billing_postcode' => $customer->get_billing_postcode(),
            'billing_state' => $customer->get_billing_state(),
            'billing_country' => $customer->get_billing_country(),
            'billing_phone' => $customer->get_billing_phone(),
            'billing_email' => $customer->get_billing_email(),
            'shipping_method' => WC()->session->get('shipping_method')
        ]);

        if (is_wp_error($order_id))
            throw new Exception($order_id->get_error_message());

        $order = wc_get_order($order_id);

        $order->set_payment_method($payment_method);

        $order->set_created_via('ShopApper');

        $order->set_transaction_id($iap_transaction_id);

        $order->calculate_totals();

        $order->payment_complete();

        $subscription = WC_Subscriptions_Checkout::create_subscription($order, reset(WC()->cart->recurring_carts), []);

        if (is_wp_error($subscription))
            throw new Exception($subscription->get_error_message());

        $subscription->set_payment_method($payment_method);

        $subscription->update_status('active');

        $subscription->update_meta_data(self::$iap_subscription_meta, $iap_subscription_id);

        $subscription->save();

        do_action('woocommerce_checkout_subscription_created', $subscription, $order, reset(WC()->cart->recurring_carts));

        $cart->empty_cart();

        return $order;
    }

    /**
     * @throws Exception
     */
    public function maybe_renew_subscription($payment_method, $iap_subscription_id, $iap_transaction_id)
    {
        $subscription = $this->get_subscription_by_iap_subscription_id($iap_subscription_id);

        if (empty($payment_method) or empty($iap_transaction_id))
            throw new Exception('Missing fields');

        $order = wcs_create_renewal_order($subscription);

        $order->set_payment_method($payment_method);

        $order->set_created_via('ShopApper');

        $order->add_order_note("Order created by ShopApper");

        $order->set_transaction_id($iap_transaction_id);

        $order->calculate_totals();

        $order->payment_complete();

        $subscription->set_payment_method($payment_method);

        $subscription->update_status('active');

    }

    /**
     * @throws Exception
     */
    public function maybe_expire_subscription($iap_subscription_id)
    {
        $subscription = $this->get_subscription_by_iap_subscription_id($iap_subscription_id);

        if ($subscription->can_be_updated_to('expired'))
            $subscription->update_status('expired');

    }

    /**
     * @param $iap_subscription_id
     * @throws Exception
     */
    public function maybe_on_hold_subscription($iap_subscription_id)
    {
        $subscription = $this->get_subscription_by_iap_subscription_id($iap_subscription_id);

        if ($subscription->can_be_updated_to('on-hold'))
            $subscription->update_status('on-hold');

    }

    /**
     * @throws Exception
     */
    public function maybe_cancel_subscription($iap_subscription_id)
    {
        $subscription = $this->get_subscription_by_iap_subscription_id($iap_subscription_id);

        if ($subscription->can_be_updated_to('pending-cancel'))
            $subscription->update_status('pending-cancel');
    }


    /**
     * @param $iap_subscription_id
     * @throws Exception
     */
    public function maybe_re_subscription($iap_subscription_id)
    {
        $subscription = $this->get_subscription_by_iap_subscription_id($iap_subscription_id);

        if ($subscription->can_be_updated_to('active'))
            $subscription->update_status('active');

    }


    public function pub_sub(WP_REST_Request $request)
    {

        try {
            $message = $request->get_param('message');

            $data = json_decode(base64_decode($message['data']));

            if (empty($data->subscriptionNotification))
                return;

            $subscriptionNotification = $data->subscriptionNotification;


            switch ((int)$subscriptionNotification->notificationType) {
                case 2:
                    $this->maybe_renew_subscription('shopapper-google-play-billing', $subscriptionNotification->purchaseToken, $subscriptionNotification->purchaseToken);
                    break;
                case 3:
                    $this->maybe_cancel_subscription($subscriptionNotification->purchaseToken);
                    break;
                case 13:
                    $this->maybe_expire_subscription($subscriptionNotification->purchaseToken);
                    break;
                case 5:
                case 10:
                    $this->maybe_on_hold_subscription($subscriptionNotification->purchaseToken);
                    break;
                case 7:
                    $this->maybe_re_subscription($subscriptionNotification->purchaseToken);
                    break;
                default:
                    error_log(sprintf("Pub/Sub: %d unhandled notification type", $subscriptionNotification->notificationType));
                    break;
            }
        } catch (Throwable $error) {
            error_log(sprintf("Pub/Sub Error: %s", $error->getMessage()));
        }
    }

    public function app_store_server_notification(WP_REST_Request $request)
    {
        try {

            $originalOrderId = $request->get_param('unified_receipt')['pending_renewal_info'][0]['original_transaction_id'];

            switch ($request->get_param('notification_type')) {
                case 'DID_RENEW':
                    $this->maybe_renew_subscription('shopapper-google-play-billing', $originalOrderId, $originalOrderId);
                    break;
                case 'CANCEL':
                    $this->maybe_cancel_subscription($originalOrderId);
                    break;
                case 'DID_FAIL_TO_RENEW':
                    $this->maybe_on_hold_subscription($originalOrderId);
                    break;
                case 'DID_RECOVER':
                    $this->maybe_re_subscription($originalOrderId);
                    break;
                default:
                    error_log(sprintf("App Store Server Notification: %s unhandled notification type", $request->get_param('notification_type')));
                    break;
            }
        } catch (Throwable $error) {
            error_log(sprintf("App Store Server Notification Error: %s", $error->getMessage()));
        }
    }

}

new SubscriptionController();