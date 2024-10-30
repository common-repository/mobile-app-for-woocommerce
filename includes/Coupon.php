<?php

namespace MobileAppForWooCommerce\Includes;

use Throwable;
use WC_Coupon;
use WC_Discounts;
use WP_REST_Request;
use WP_REST_Response;

class Coupon
{
    use Helper;

    static $settings_option_key = 'shopapper_coupon_settings';

    static $user_coupon_used_meta_key = 'shopapper_auto_apply_coupon_used';

    static $default_settings = ['coupon' => '', 'rule' => 'first_order'];

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'init']);

        add_action('woocommerce_coupon_data_tabs',[$this,'woocommerce_coupon_data_tabs']);

        add_action('woocommerce_coupon_data_panels', [$this, 'woocommerce_coupon_data_panels'], 10,2);

        add_action('woocommerce_coupon_options_save', [$this, 'woocommerce_coupon_options_save'], 10, 2);

        add_action('wp_loaded', [$this, 'apply_coupon'], 30);

        add_filter('woocommerce_coupon_is_valid', [$this, 'validate_coupon'], 90, 3);

        add_action('woocommerce_new_order', [$this, 'update_user_coupon_meta'], 10, 2);

        add_action('woocommerce_order_status_cancelled', [$this, 'check_for_auto_apply_coupon'], 10, 1);

    }

    public function init()
    {
        register_rest_route(MAFW_CLIENT_ROUTE, '/coupon/settings', [
            'methods' => 'GET',
            'callback' => [$this, 'get_settings'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/coupon/settings', [
            'methods' => 'POST',
            'callback' => [$this, 'update_settings'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function get_settings(): WP_REST_Response
    {
        $coupon_posts = get_posts([
            'posts_per_page' => -1,
            'orderby' => 'name',
            'order' => 'asc',
            'post_type' => 'shop_coupon',
            'post_status' => 'publish',
        ]);

        $coupons = [];

        foreach ($coupon_posts as $coupon_post)
            $coupons[] = ['value' => $coupon_post->ID, 'label' => $coupon_post->post_title];

        return new WP_REST_Response([
            'settings' => get_option(self::$settings_option_key, self::$default_settings),
            'coupons' => $coupons
        ]);
    }

    public function update_settings(WP_REST_Request $request): WP_REST_Response
    {
        update_option(self::$settings_option_key, $request->get_params());

        return new WP_REST_Response([]);
    }

    /**
     * @param $tabs
     * @return mixed
     */
    public function woocommerce_coupon_data_tabs($tabs)
    {
        $tabs['shopapper'] = [
            'label' => __('ShopApper', 'woocommerce'),
            'target' => 'shopapper_coupon_options',
            'class' => [],
            'priority' => 80,
        ];

        return $tabs;
    }

    /**
     * @return void
     */
    public function woocommerce_coupon_data_panels()
    {
        ?>
        <div id="shopapper_coupon_options" class="panel woocommerce_options_panel">
            <div class="options_group">
                <?php

                woocommerce_wp_checkbox(
                    [
                        'id' => 'shopapper_only_app',
                        'value' => get_post_meta(get_the_ID(), 'shopapper_only_app', true),
                        'label' => __('App Only?', 'woocommerce'),
                        'cbvalue' => 'yes',
                        'desc_tip' => true,
                        'description' => 'Coupon can apply only from app.'
                    ]
                );
                ?>
            </div>
        </div>

        <?php

    }

    /**
     * @param $post_id
     * @param $coupon
     * @return void
     */
    public function woocommerce_coupon_options_save($post_id, $coupon)
    {
        $coupon->update_meta_data('shopapper_only_app', $_POST['shopapper_only_app'] ?? 'no');

        $coupon->save();
    }

    /**
     * Apply coupon on load.
     * @return void
     */
    public function apply_coupon()
    {
        if (!function_exists('WC') or !WC()->session or !$this->is_shopapper()) return;

        $settings = get_option(self::$settings_option_key, self::$default_settings);

        if (empty($settings['coupon'])) return;

        $user_used = get_user_meta(get_current_user_id(), self::$user_coupon_used_meta_key, true) === 'yes';

        if ((($settings['rule'] === 'first_order' or $settings['rule'] === 'first_order_notification_allowed') and $user_used) or ($settings['rule'] === 'first_order_notification_allowed' and !Helper::is_push_notification_enabled())) return;

        $coupon_code = wc_get_coupon_code_by_id($settings['coupon']);

        if (empty($coupon_code)) return;

        try {
            $the_coupon = new WC_Coupon($coupon_code);

            $discounts = new WC_Discounts(WC()->cart);

            $is_valid = $discounts->is_coupon_valid($the_coupon);

            if (is_wp_error($is_valid))
                return;
        } catch (Throwable $e) {

            return;
        }

        WC()->session->set_customer_session_cookie(true);

        if (!WC()->cart->has_discount($coupon_code))
            WC()->cart->add_discount($coupon_code);


    }

    /**
     * Validate coupon according to app checkout and rule.
     * @param $valid
     * @param $coupon
     * @param $instance
     * @return bool|mixed
     */
    public function validate_coupon($valid, $coupon, $instance)
    {
        $is_shopapper = $this->is_shopapper();

        if($coupon->get_meta('shopapper_only_app',true) === 'yes' and !$is_shopapper)
            return false;

        $settings = get_option(self::$settings_option_key, self::$default_settings);

        if (empty($settings['coupon'])) return $valid;

        if ($settings['coupon'] !== $coupon->get_id()) return $valid;

        // if it is not app checkout return false
        if (!$this->is_shopapper()) return false;

        if ($settings['rule'] === 'every_order') return true;

        if ($settings['rule'] === 'first_order_notification_allowed' and !Helper::is_push_notification_enabled())
            return false;

        return get_user_meta(get_current_user_id(), self::$user_coupon_used_meta_key, true) !== 'yes';

    }

    /**
     * Update user meta to prevent multiple using if rule is first_order.
     * @param $order_id
     * @param $order
     * @return void
     */
    public function update_user_coupon_meta($order_id, $order)
    {
        $settings = get_option(self::$settings_option_key, self::$default_settings);

        if (empty($settings['coupon'])) return;

        foreach ($order->get_coupon_codes() as $code) {
            if (wc_get_coupon_id_by_code($code) == $settings['coupon']) {
                update_user_meta($order->get_customer_id(), self::$user_coupon_used_meta_key, 'yes');
                break;
            }
        }
    }

    /**
     * Update user meta when order cancel to customer can use coupon.
     * @param $order_id
     * @return void
     */
    public function check_for_auto_apply_coupon($order_id)
    {
        $settings = get_option(self::$settings_option_key, self::$default_settings);

        if (empty($settings['coupon']) or $settings['rule'] === 'every_order') return;

        $order = wc_get_order($order_id);

        if (!$order) return;

        foreach ($order->get_coupon_codes() as $code) {
            if ($settings['coupon'] === wc_get_coupon_id_by_code($code)) {
                delete_user_meta($order->get_customer_id(), self::$user_coupon_used_meta_key);
                break;
            }
        }

    }


}

new Coupon();