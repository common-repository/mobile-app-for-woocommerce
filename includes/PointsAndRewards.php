<?php

namespace MobileAppForWooCommerce\Includes;

use Throwable;
use WC_Points_Rewards_Manager;
use WP_REST_Request;
use WP_REST_Response;

class PointsAndReward
{
    use Helper;

    static $settings_option_key = 'shopapper_points_rewards_settings';

    static $default_settings = ['enabled' => false, 'conversionRatePoint' => 1, 'conversionRateAmount' => 1];

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'init']);

        add_filter('wc_points_rewards_points_earned_for_purchase', [$this, 'wc_points_rewards_points_earned_for_purchase'], 90, 2);

        add_filter('ywpar_calculate_points_on_cart', [$this, 'ywpar_calculate_points_on_cart'], 99, 1);
    }

    public function init()
    {
        register_rest_route(MAFW_CLIENT_ROUTE, '/points-and-rewards/settings', [
            'methods' => 'GET',
            'callback' => [$this, 'get_settings'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/points-and-rewards/settings', [
            'methods' => 'POST',
            'callback' => [$this, 'update_settings'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function get_settings(): WP_REST_Response
    {
        return new WP_REST_Response(['settings' => get_option(self::$settings_option_key, self::$default_settings), 'currency' => function_exists('get_woocommerce_currency_symbol') ? html_entity_decode(get_woocommerce_currency_symbol()) : '$']);
    }

    public function update_settings(WP_REST_Request $request): WP_REST_Response
    {
        update_option(self::$settings_option_key, $request->get_params());

        return new WP_REST_Response(get_option(self::$settings_option_key, self::$default_settings));
    }

    public function wc_points_rewards_points_earned_for_purchase($points_earned, $object)
    {

        try {
            $settings = get_option(self::$settings_option_key, self::$default_settings);

            if (is_a($object, 'WC_Order') and $object->get_meta('shopapper_order') !== '1' or (!is_a($object, 'WC_Order') and !$this->is_shopapper()) or !$settings['enable']) return $points_earned;

            $app_points_earned = 0;

            if (is_a($object, 'WC_Order')) {

                foreach ($object->get_items() as $item) {

                    // If prices include tax, we include the tax in the points calculation
                    if ('no' === get_option('woocommerce_prices_include_tax')) {
                        // Get the un-discounted price paid and adjust our product price
                        $item_price = $object->get_item_subtotal($item, false, true);
                    } else {
                        // Get the un-discounted price paid and adjust our product price
                        $item_price = $object->get_item_subtotal($item, true, true);
                    }

                    $app_points_earned += $item_price * ($settings['conversionRatePoint'] / $settings['conversionRateAmount']);
                }

                // Reduce by any discounts.  One minor drawback: if the discount includes a discount on tax and/or shipping
                // It will cost the customer points, but this is a better solution than granting full points for discounted orders.
                $discount = $object->get_total_discount(!wc_prices_include_tax());

                $app_points_earned -= min(WC_Points_Rewards_Manager::calculate_points($discount), $app_points_earned);

                // Check if applied coupons have a points modifier and use it to adjust the points earned.
                $coupons = $object->get_coupon_codes();


            } else {

                foreach (WC()->cart->get_cart() as $item)
                    $app_points_earned += $item['data']->get_price() * ($settings['conversionRatePoint'] / $settings['conversionRateAmount']);

                /*
                * Reduce by any discounts.  One minor drawback: if the discount includes a discount on tax and/or shipping
                * it will cost the customer points, but this is a better solution than granting full points for discounted orders.
                */
                $discount = (wc_prices_include_tax()) ? WC()->cart->discount_cart + WC()->cart->discount_cart_tax : WC()->cart->discount_cart;

                $discount_amount = min(WC_Points_Rewards_Manager::calculate_points($discount), $app_points_earned);

                // Apply a filter that will allow users to manipulate the way discounts affect points earned.
                $app_points_earned = apply_filters('wc_points_rewards_discount_points_modifier', $app_points_earned - $discount_amount, $app_points_earned, $discount_amount, $discount);

                // Check if applied coupons have a points modifier and use it to adjust the points earned.
                $coupons = WC()->cart->get_applied_coupons();
            }

            $app_points_earned = WC_Points_Rewards_Manager::calculate_points_modification_from_coupons($app_points_earned, $coupons);

            $app_points_earned = WC_Points_Rewards_Manager::round_the_points($app_points_earned);


            return $points_earned + $app_points_earned;
        } catch (Throwable $e) {
            return $points_earned;
        }
    }

    /**
     * @param $conversation
     * @return array
     */
    public function ywpar_conversion_points_rate($conversation)
    {
        $settings = get_option(self::$settings_option_key, self::$default_settings);

        return ['points' => $settings['conversionRatePoint'], 'money' => $settings['conversionRateAmount']];

    }

    /**
     * @param $points
     * @return float|int|mixed
     */
    public function ywpar_calculate_points_on_cart($points)
    {
        $settings = get_option(self::$settings_option_key, self::$default_settings);

        if (!$this->is_shopapper() or !$settings['enable'] or empty($settings['conversionRatePoint']) or empty($settings['conversionRateAmount']))
            return $points;

        add_filter('ywpar_conversion_points_rate', [$this, 'ywpar_conversion_points_rate']);

        $items = WC()->cart->get_cart();

        $tot_points = 0;

        foreach ($items as $item => $values) {

            $product_point = 0;
            if (apply_filters('ywpar_calculate_points_for_product', true, $values, $item)) {
                $product_point = YITH_WC_Points_Rewards_Earning()->calculate_product_points($values['data'], false);
            }
            $total_product_points = floatval($product_point * $values['quantity']);

            if (WC()->cart->applied_coupons && YITH_WC_Points_Rewards()->get_option('remove_points_coupon') == 'yes' && isset(WC()->cart->discount_cart) && WC()->cart->discount_cart > 0) {
                if ($values['line_subtotal']) {
                    $total_product_points = ($values['line_total'] / $values['line_subtotal']) * $total_product_points;
                }
            }

            $tot_points += $total_product_points;
        }

        $tot_points = ($tot_points < 0) ? 0 : $tot_points;

        if (get_option('ywpar_points_round_type', 'down') == 'down' || apply_filters('ywpar_floor_points', false)) {
            $tot_points = floor($tot_points);
        } else {
            $tot_points = round($tot_points);
        }

        remove_filter('ywpar_conversion_points_rate', [$this, 'ywpar_conversion_points_rate']);

        return $points + $tot_points;
    }
}

new PointsAndReward();