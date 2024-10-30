<?php

add_action('add_meta_boxes_product', 'shopapper_mycred_woo_add_product_metabox');


function shopapper_mycred_woo_add_product_metabox()
{
    $product = wc_get_product(get_the_ID());
    if ($product->is_type('variable') != 'variable') {
        add_meta_box(
            'shopapper_mycred_woo_sales_setup',
            sprintf("ShopApper %s", mycred_label()),
            'shopapper_mycred_woo_product_metabox',
            'product',
            'side',
            'high'
        );
    }

}

function shopapper_mycred_woo_product_metabox($post)
{

    $product = wc_get_product(get_the_ID());
    if ($product->is_type('variable') != 'variable') {

        if (!current_user_can(apply_filters('mycred_woo_reward_cap', 'edit_others_posts'))) return;

        $types = mycred_get_types();
        $prefs = (array)mycred_get_post_meta($post->ID, 'shopapper_mycred_reward', true);

        foreach ($types as $point_type => $point_type_label) {
            if (!array_key_exists($point_type, $prefs))
                $prefs[$point_type] = '';
        }

        $count = 0;
        $cui = get_current_user_id();
        foreach ($types as $point_type => $point_type_label) {

            $count++;
            $mycred = mycred($point_type);

            if (!$mycred->user_is_point_admin($cui)) continue;

            $setup = $prefs[$point_type];

            ?>
            <p class="<?php if ($count == 1) echo 'first'; ?>"><label
                        for="shopapper_mycred-reward-purchase-with-<?php echo esc_attr($point_type); ?>"><input
                            class="toggle-shopapper-mycred-reward"
                            data-id="<?php echo esc_attr($point_type); ?>" <?php if ($setup != '') echo wp_kses_post('checked="checked"'); ?>
                            type="checkbox" name="shopapper_mycred_reward[<?php echo esc_attr($point_type); ?>][use]"
                            id="shopapper_mycred-reward-purchase-with-<?php echo esc_attr($point_type); ?>"
                            value="1"/> <?php echo wp_kses_post($mycred->template_tags_general(__('Reward with %plural%', 'mycred'))); ?>
                </label></p>
            <div class="shopapper_mycred-woo-wrap" id="shopapper-reward-<?php echo esc_attr($point_type); ?>"
                 style="display:<?php if ($setup == '') echo 'none'; else echo 'block'; ?>">
                <label><?php echo esc_html($mycred->plural()); ?></label> <input type="text" size="8"
                                                                                 name="shopapper_mycred_reward[<?php echo esc_attr($point_type); ?>][amount]"
                                                                                 value="<?php echo esc_attr($setup); ?>"
                                                                                 placeholder="<?php echo esc_attr($mycred->zero()); ?>"/>
            </div>
            <?php

        }
    }

    ?>
    <script type="text/javascript">
        jQuery(function ($) {

            $('.toggle-shopapper-mycred-reward').click(function () {
                var target = $(this).attr('data-id');
                $('#shopapper-reward-' + target).toggle();
            });

        });
    </script>
    <style type="text/css">
        #shopapper_mycred_woo_sales_setup .inside {
            margin: 0;
            padding: 0;
        }

        #shopapper_mycred_woo_sales_setup .inside > p {
            padding: 12px;
            margin: 0;
            border-top: 1px solid #ddd;
        }

        #shopapper_mycred_woo_sales_setup .inside > p .first {
            border-top: none;
        }

        #shopapper_mycred_woo_sales_setup .inside .shopapper_mycred-woo-wrap {
            padding: 6px 12px;
            line-height: 27px;
            text-align: right;
            border-top: 1px solid #ddd;
            background-color: #F5F5F5;
        }

        #shopapper_mycred_woo_sales_setup .inside .shopapper_mycred-woo-wrap label {
            display: block;
            font-weight: bold;
            float: left;
        }

        #shopapper_mycred_woo_sales_setup .inside .shopapper_mycred-woo-wrap input {
            width: 50%;
        }

        #shopapper_mycred_woo_sales_setup .inside .shopapper_mycred-woo-wrap p {
            margin: 0;
            padding: 0 12px;
            font-style: italic;
            text-align: center;
        }

        #shopapper_mycred_woo_vaiation .box {
            display: block;
            float: left;
            width: 49%;
            margin-right: 1%;
            margin-bottom: 12px;
        }

        #shopapper_mycred_woo_vaiation .box input {
            display: block;
            width: 100%;
        }
    </style>
    <?php

}

add_action('save_post_product', 'shopapper_mycred_woo_save_reward_settings');

function shopapper_mycred_woo_save_reward_settings($post_id)
{

    //Works only for multisite
    $override = (is_multisite() && mycred_override_settings() && !mycred_is_main_site());

    if ($override)
        $post_type = get_post_type($post_id);
    else
        $post_type = mycred_get_post_type($post_id);

    if (empty($_POST['shopapper_mycred_reward']) || $post_type != 'product') return;

    $new_setup = array();

    foreach (mycred_sanitize_array(wp_unslash($_POST['shopapper_mycred_reward'])) as $point_type => $setup) {

        if (empty($setup)) continue;

        $mycred = mycred($point_type);
        if (array_key_exists('use', $setup) && $setup['use'] == 1)
            $new_setup[$point_type] = $mycred->number($setup['amount']);

    }

    if (empty($new_setup))
        mycred_delete_post_meta($post_id, 'shopapper_mycred_reward');
    else
        mycred_update_post_meta($post_id, 'shopapper_mycred_reward', $new_setup);

}

add_action('woocommerce_product_after_variable_attributes', 'shopapper_mycred_woo_add_product_variation_detail', 20, 3);

function shopapper_mycred_woo_add_product_variation_detail($loop, $variation_data, $variation)
{

    $types = mycred_get_types();
    $user_id = get_current_user_id();
    $prefs = (array)mycred_get_post_meta($variation->ID, '_shopapper_mycred_reward', true);

    foreach ($types as $point_type => $point_type_label) {
        if (!array_key_exists($point_type, $prefs))
            $prefs[$point_type] = '';
    }

    ?>
    <style type="text/css">
        #shopapper_mycred_woo_sales_setup .inside {
            margin: 0;
            padding: 0;
        }

        #shopapper_mycred_woo_sales_setup .inside > p {
            padding: 12px;
            margin: 0;
            border-top: 1px solid #ddd;
        }

        #shopapper_mycred_woo_sales_setup .inside > p.first {
            border-top: none;
        }

        #shopapper_mycred_woo_sales_setup .inside .mycred-woo-wrap {
            padding: 6px 12px;
            line-height: 27px;
            text-align: right;
            border-top: 1px solid #ddd;
            background-color: #F5F5F5;
        }

        #shopapper_mycred_woo_sales_setup .inside .mycred-woo-wrap label {
            display: block;
            font-weight: bold;
            float: left;
        }

        #shopapper_mycred_woo_sales_setup .inside .mycred-woo-wrap input {
            width: 50%;
        }

        #shopapper_mycred_woo_sales_setup .inside .mycred-woo-wrap p {
            margin: 0;
            padding: 0 12px;
            font-style: italic;
            text-align: center;
        }

        #shopapper_mycred_woo_vaiation .box {
            display: block;
            float: left;
            width: 49%;
            margin-right: 1%;
            margin-bottom: 12px;
        }

        #shopapper_mycred_woo_vaiation .box input {
            display: block;
            width: 100%;
        }
    </style>
    <div class="" id="mycred_woo_vaiation">
        <?php

        foreach ($types as $point_type => $point_type_label) {

            $mycred = mycred($point_type);

            if (!$mycred->user_is_point_admin($user_id)) continue;

            $id = 'shopapper-mycred-rewards-variation-' . $variation->ID . str_replace('_', '-', $point_type);

            ?>
            <div class="box">
                <label for="<?php echo esc_attr($id); ?>"><?php echo wp_kses_post($mycred->template_tags_general(__('ShopApper Reward with %plural%', 'mycred'))); ?></label>
                <input type="text"
                       name="_shopapper_mycred_reward[<?php echo esc_attr($variation->ID); ?>][<?php echo esc_attr($point_type); ?>]"
                       id="<?php echo esc_attr($id); ?>" class="input-text"
                       placeholder="<?php esc_attr_e('Leave empty for no rewards', 'mycred'); ?>"
                       value="<?php echo esc_attr($prefs[$point_type]); ?>"/>
            </div>
            <?php

        }

        ?>
    </div>
    <?php

}

add_action('woocommerce_save_product_variation', 'shopapper_mycred_woo_save_product_variation_detail');

function shopapper_mycred_woo_save_product_variation_detail($post_id)
{

    if (empty($_POST['_shopapper_mycred_reward']) || !array_key_exists($post_id, $_POST['_shopapper_mycred_reward'])) return;

    $new_setup = array();
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    foreach ($_POST['_shopapper_mycred_reward'][$post_id] as $point_type => $value) {

        $value = sanitize_text_field($value);
        if (empty($value)) continue;

        $mycred = mycred($point_type);
        $value = $mycred->number($value);
        if ($value === $mycred->zero()) continue;

        $new_setup[$point_type] = $value;

    }

    if (empty($new_setup))
        mycred_delete_post_meta($post_id, '_shopapper_mycred_reward');
    else
        mycred_update_post_meta($post_id, '_shopapper_mycred_reward', $new_setup);

}


if (!function_exists('mycred_woo_payout_rewards')) :
    function mycred_woo_payout_rewards($order_id)
    {

        // Get Order
        $order = wc_get_order($order_id);

        global $woocommerce;

        $paid_with = (version_compare($woocommerce->version, '3.0', '>=')) ? $order->get_payment_method() : $order->payment_method;
        $buyer_id = (version_compare($woocommerce->version, '3.0', '>=')) ? $order->get_user_id() : $order->user_id;

        // If we paid with myCRED we do not award points by default
        if ($paid_with == 'mycred' && apply_filters('mycred_woo_reward_mycred_payment', false, $order) === false)
            return;

        // Get items
        $items = $order->get_items();
        $types = mycred_get_types();

        // Loop through each point type
        foreach ($types as $point_type => $point_type_label) {

            // Load type
            $mycred = mycred($point_type);

            // Check for exclusions
            if ($mycred->exclude_user($buyer_id)) continue;

            // Calculate reward
            $payout = $mycred->zero();
            foreach ($items as $item) {

                // Get the product ID or the variation ID
                $product_id = absint($item['product_id']);
                $variation_id = absint($item['variation_id']);
                $reward_amount = mycred_get_woo_product_reward($product_id, $variation_id, $point_type, $order->get_meta('shopapper_order') === '1');

                // Reward can not be empty or zero
                if ($reward_amount != '' && $reward_amount != 0)
                    $payout = ($payout + ($mycred->number($reward_amount) * $item['qty']));

            }

            // We can not payout zero points
            if ($payout === $mycred->zero()) continue;

            // Let others play with the reference and log entry
            $reference = apply_filters('mycred_woo_reward_reference', 'reward', $order_id, $point_type);
            $log = apply_filters('mycred_woo_reward_log', '%plural% reward for store purchase', $order_id, $point_type);

            // Make sure we only get points once per order
            if (!$mycred->has_entry($reference, $order_id, $buyer_id)) {

                // Execute
                $mycred->add_creds(
                    $reference,
                    $buyer_id,
                    $payout,
                    $log,
                    $order_id,
                    array('ref_type' => 'post'),
                    $point_type
                );

            }

        }

    }
endif;

/**
 * Get Product Reward
 * Returns either an array of point types and the reward value set for each or
 * the value set for a given point type. Will check for variable product rewards as well.
 * @since 1.7.6
 * @version 1.0
 */
if (!function_exists('mycred_get_woo_product_reward')) :
    function mycred_get_woo_product_reward($product_id = NULL, $variation_id = NULL, $requested_type = false, $is_shopapper_order = false)
    {

        $product_id = absint($product_id);
        $types = mycred_get_types();

        if ($product_id === 0) return false;

        if (function_exists('wc_get_product')) {

            $product = wc_get_product($product_id);

            // For variations, we need a variation ID
            if ($product->is_type('variable') && $variation_id !== NULL && $variation_id > 0) {

                if ($is_shopapper_order) {
                    $reward_setup = (array)mycred_get_post_meta($variation_id, '_shopapper_mycred_reward', true);
                    $parent_reward_setup = (array)mycred_get_post_meta($product_id, 'shopapper_mycred_reward', true);
                }


                if (empty($reward_setup)) {
                    $reward_setup = (array)mycred_get_post_meta($variation_id, '_mycred_reward', true);
                    $parent_reward_setup = (array)mycred_get_post_meta($product_id, 'mycred_reward', true);
                }

            } else {

                if ($is_shopapper_order)
                    $reward_setup = (array)mycred_get_post_meta($product_id, 'shopapper_mycred_reward', true);

                if (empty($reward_setup))
                    $reward_setup = (array)mycred_get_post_meta($product_id, 'mycred_reward', true);

                $parent_reward_setup = array();
            }

        }

        // Make sure all point types are populated in a reward setup
        foreach ($types as $point_type => $point_type_label) {

            if (empty($reward_setup) || !array_key_exists($point_type, $reward_setup))
                $reward_setup[$point_type] = '';

            if (empty($parent_reward_setup) || !array_key_exists($point_type, $parent_reward_setup))
                $parent_reward_setup[$point_type] = '';

        }

        // We might want to enforce the parent value for variations
        foreach ($reward_setup as $point_type => $value) {

            // If the variation has no value set, but the parent box has a value set, enforce the parent value
            // If the variation is set to zero however, it indicates we do not want to reward that variation
            if ($value == '' && isset($parent_reward_setup[$point_type]) && $parent_reward_setup[$point_type] != '' && $parent_reward_setup[$point_type] != 0)
                $reward_setup[$point_type] = $parent_reward_setup[$point_type];

        }

        // If we are requesting one particular types reward
        if ($requested_type !== false) {

            $value = '';
            if (array_key_exists($requested_type, $reward_setup))
                $value = $reward_setup[$requested_type];

            return $value;

        }

        return $reward_setup;

    }
endif;

function mafw_get_mycred_product_meta_key($isVariation = false)
{

    $is_shopapper = false;

    if (isset($_SERVER['HTTP_SHOPAPPER']))
        $is_shopapper = true;
    else $is_shopapper = (isset($_GET['shopapper-page']) or (isset($_SERVER['HTTP_REFERER']) and strpos($_SERVER['HTTP_REFERER'], 'shopapper-page') !== false));

    if ($is_shopapper) return $isVariation ? '_shopapper_mycred_reward' : 'shopapper_mycred_reward';

    return $isVariation ? '_mycred_reward' : 'mycred_reward';
}

if(!class_exists('mycred_woo_reward_product'))
{
    class mycred_woo_reward_product
    {

        public function __construct()
        {
            add_action('woocommerce_before_add_to_cart_form', array($this, 'woocommerce_before_add_to_cart_button'));

            add_action('woocommerce_order_status_completed', array($this, 'mycred_pro_reward_order_percentage'));

            add_action('woocommerce_checkout_before_customer_details', array($this, 'woocommerce_review_order_before_order_total'), 10);

            add_action('woocommerce_before_cart_table', array($this, 'woocommerce_review_order_before_order_total'), 10);

            add_filter('woocommerce_get_item_data', array($this, 'woocommerce_get_item_data'), 10, 2);

            add_action('wp_head', array($this, 'wp_head'));

            add_action('woocommerce_before_add_to_cart_quantity', array($this, 'display_dropdown_variation_add_cart'));

        }

        public function display_dropdown_variation_add_cart()
        {

            global $product;

            if ($product->is_type('variable') && get_option('reward_single_page_product') == 'yes') {

                ?>
                <script>
                    jQuery(document).ready(function ($) {

                        function call_rewards_points() {
                            function call_rewards_points() {
                                if ('' != jQuery('input.variation_id').val() && 0 != jQuery('input.variation_id').val()) {
                                    var var_id = jQuery('input.variation_id').val();
                                    template = '';
                                    if (typeof (mycred_variable_rewards[var_id]) != 'undefined' && mycred_variable_rewards[var_id] != null) {

                                        jQuery.each(mycred_variable_rewards[var_id], function (index, value) {

                                            if (value)
                                                template += '<span class="rewards_span"> ' + label_Earn + ' ' + value + ' ' + mycred_point_types[index] + '</span>';

                                        });

                                        document.getElementById("rewards_points_wrap").innerHTML = template;
                                    } else {
                                        document.getElementById("rewards_points_wrap").innerHTML = '';
                                    }
                                }
                            }
                        }

                        call_rewards_points();
                        jQuery('input.variation_id').change(function () {
                            console.log('test')
                            call_rewards_points()
                        });

                    });
                </script>
                <?php

            }

        }

        public function wp_head()
        {

            if (is_product()) {

                $mycred_rewards_array = array();

                $product = wc_get_product(get_the_ID());
                if ($product->is_type('variable')) {
                    $available_variations = $product->get_available_variations();
                    $mycred = mycred_get_types();
                    foreach ($available_variations as $variation) {
                        $variation_id = $variation['variation_id'];
                        $mycred_rewards = get_post_meta($variation_id, mafw_get_mycred_product_meta_key(true), true);
                        $parent_reward = (array)get_post_meta(get_the_ID(), mafw_get_mycred_product_meta_key(), true);
                        if (!empty($mycred_rewards)) {
                            $mycred_rewards_array[$variation_id] = $mycred_rewards;
                        } elseif (!empty($parent_reward)) {
                            $mycred_rewards_array[$variation_id] = $parent_reward;
                        }
                    }
                }

                if (!empty($mycred_rewards_array)) {
                    ?>
                    <script type="text/javascript">
                        var mycred_variable_rewards = <?php echo json_encode($mycred_rewards_array); ?>;
                        var mycred_point_types = <?php echo json_encode($mycred); ?>;
                        var label_Earn = <?php echo "'" . __("Earn ", 'mycredpartwoo') . "'"; ?>;

                    </script>
                    <?php
                }
            }

        }

        public function woocommerce_get_item_data($item_data, $cart_item)
        {

            $product = wc_get_product($cart_item['product_id']);
            if ($product->is_type('variable')) {
                $mycred_rewards = get_post_meta($cart_item['variation_id'], mafw_get_mycred_product_meta_key(true), true);
            } else {
                $mycred_rewards = get_post_meta($cart_item['product_id'], mafw_get_mycred_product_meta_key(), true);
            }

            if ($mycred_rewards) {

                if ((is_cart() && 'yes' == get_option('reward_cart_product_meta')) || (is_checkout() && 'yes' == get_option('reward_checkout_product_meta'))) {
                    foreach ($mycred_rewards as $mycred_reward_key => $mycred_reward_value) {

                        $is_plural_reward = ($mycred_reward_value < 2);

                        $value = '<span class="reward_span">' . $mycred_reward_value . ' ' . mycred_get_point_type_name($mycred_reward_key, $is_plural_reward) . '</span>';

                        $item_data[] = array(
                            'key' => __('<span style="reward_span">Earn</span>', 'mycredpartwoo'),
                            'value' => __($value, 'mycredpartwoo'),
                            'display' => '',
                        );

                    }
                }

            }

            return $item_data;
        }

        public function woocommerce_review_order_before_order_total()
        {

            do_action('woocommerce_set_cart_cookies', true);
            $mycred = new myCRED_Settings();
            $decimal_format = $mycred->format['decimals'];

            $total_reward_point = array();
            $message = '';

            foreach (WC()->cart->get_cart() as $cart_item) {
                // var_dump($cart_item);


                $product = wc_get_product($cart_item['product_id']);
                if ($product->is_type('variable')) {
                    $mycred_rewards = get_post_meta($cart_item['variation_id'], mafw_get_mycred_product_meta_key(true), true);
                } else {
                    $mycred_rewards = get_post_meta($cart_item['product_id'], mafw_get_mycred_product_meta_key(), true);
                }
                if ($mycred_rewards) {

                    foreach ($mycred_rewards as $mycred_reward_key => $mycred_reward_value) {

                        if (isset($total_reward_point[$mycred_reward_key])) {

                            $total_reward_point[$mycred_reward_key]['total'] = $total_reward_point[$mycred_reward_key]['total'] + $mycred_reward_value * $cart_item['quantity'];

                        } else {

                            $total_reward_point[$mycred_reward_key] = array('name' => $mycred_reward_key, 'total' => $mycred_reward_value * $cart_item['quantity']);
                        }
                    }
                }
            }

            $message .= __("Earn ", 'mycredpartwoo');
            $i = 1;
            $count = count($total_reward_point);

            if (!empty($total_reward_point)) {
                foreach ($total_reward_point as $mycred_reward_key => $mycred_reward_value) {

                    $mycred = mycred($mycred_reward_key);

                    if (1 == $count) {
                        $message .= $mycred->format_creds($mycred_reward_value['total']) . ' ' . $mycred->plural();
                    } else {
                        if ($i < $count) {
                            $message .= $mycred->format_creds($mycred_reward_value['total']) . ' ' . $mycred->plural() . ', ';
                        } else {
                            $message .= ' and ' . $mycred->format_creds($mycred_reward_value['total']) . ' ' . $mycred->plural();
                        }
                    }

                    $i++;

                }
            }

            wc_clear_notices();

            $reward_points_global = get_option('reward_points_global', true);

            //wp_die(WC()->cart->get_subtotal());

            if ('yes' === $reward_points_global) {
                /*** mufaddal start work from here */
                $type = get_option('mycred_point_type', true);
                $reward_points_global_type = get_option('reward_points_global_type', true);
                $exchange_rate = get_option('reward_points_exchange_rate', true);
                $reward_points_global_message = get_option('reward_points_global_message', true);
                $reward_points_global_type_val = get_option('reward_points_global_type_val', true);
                $reward_points_global_type_val = (float)$reward_points_global_type_val;
                $cost = WC()->cart->get_subtotal();
                //wp_die($type);

                if ('fixed' === $reward_points_global_type) {

                    $reward = number_format($reward_points_global_type_val, $decimal_format, '.', '');

                }

                if ('percentage' === $reward_points_global_type) {
                    $reward = $cost * ($reward_points_global_type_val / 100);
                    $reward = number_format($reward, $decimal_format, '.', '');
                }

                if ('exchange' === $reward_points_global_type) {

                    $reward = ($cost / $exchange_rate);
                    $reward = number_format($reward, $decimal_format, '.', '');

                }


                $message = str_replace("{points}", $reward, $reward_points_global_message);
                $message = str_replace("{type}", $type, $message);
                $message = str_replace("mycred_default", "Points", $message);
                if ($cost > 0 && !empty($reward_points_global_message)) {
                    wc_print_notice(__($message, 'mycredpartwoo'), $notice_type = 'notice');
                }

            } else {

                if ((is_cart() && 'yes' == get_option('reward_cart_product_total')) || (is_checkout() && 'yes' == get_option('reward_checkout_product_total'))) {
                    if (!empty($total_reward_point)) {
                        wc_print_notice(__($message, 'mycredpartwoo'), $notice_type = 'notice');
                    }
                }

            }

        }

        public function woocommerce_before_add_to_cart_button()
        {

            $product = wc_get_product(get_the_ID());

            if (get_option('reward_single_page_product') == 'yes') {
                if ($product->is_type('simple')) {
                    $mycred_rewards = get_post_meta(get_the_ID(), mafw_get_mycred_product_meta_key(), true);

                    $i = 1;

                    if (!empty($mycred_rewards)) {
                        $count = count($mycred_rewards);
                    }

                    if ($mycred_rewards) {

                        echo '<div id="rewards_points_wrap">';
                        foreach ($mycred_rewards as $mycred_reward_key => $mycred_reward_value) {

                            $is_plural_reward = ($mycred_reward_value < 2);

                            $mycred_point_type_name = mycred_get_point_type_name($mycred_reward_key, $is_plural_reward);

                            echo '<span class="rewards_span test"> ' . sprintf(__('Earn %s %s', 'mycredpartwoo'), $mycred_reward_value, $mycred_point_type_name) . '</span>';
                        }
                        echo '</div>';
                    }

                } else {
                    echo '<div id="rewards_points_wrap"></div>';
                }
            }


        }

        public function mycred_pro_reward_order_percentage($order_id)
        {

            if (!function_exists('mycred')) return;

            // Load myCRED
            $mycred = mycred();

            $reward_points_global = get_option('reward_points_global', true);

            if ('yes' === $reward_points_global) {
                //wp_die('pls stop');
                $reward_points_global_type = get_option('reward_points_global_type', true);
                $reward_points_global_type_val = get_option('reward_points_global_type_val', true);
                $exchange_rate = get_option('reward_points_exchange_rate', true);
                $reward_points_global_message = get_option('reward_points_global_message', true);
                $type = get_option('mycred_point_type', true);
            }


            // Get Order
            $order = new WC_Order($order_id);
            $cost = $order->get_subtotal();
            $user_id = get_post_meta($order_id, '_customer_user', true);
            $payment_method = get_post_meta($order_id, '_payment_method', true);

            // Do not payout if order was paid using points
            if ($payment_method == 'mycred') return;

            // Make sure user only gets points once per order
            if ($mycred->has_entry('reward', $order_id, $user_id)) return;

            // percentage based point
            if (isset($reward_points_global_type) && 'percentage' === $reward_points_global_type) {

                // Reward example 25% in points.
                $points = (float)$reward_points_global_type_val;
                $reward = $cost * ($points / 100);
                $reward = number_format($reward, 2, '.', '');

            }

            // fixed point
            if (isset($reward_points_global_type) && 'fixed' === $reward_points_global_type) {

                // Reward example 25% in points.
                $points = (float)$reward_points_global_type_val;
                $reward = number_format($points, 2, '.', '');

            }

            // exchange rate based points
            if (isset($reward_points_global_type) && 'exchange' === $reward_points_global_type) {

                // Reward example 25% in points.
                $points = (float)$exchange_rate;
                $reward = ($cost / $points);
                $reward = number_format($reward, 2, '.', '');
                //wp_die('rewards in exchange rate '. $reward);

            }


            // Add reward
            $mycred->add_creds('reward', $user_id, $reward, __('Reward for store purchase', 'mycredpartwoo'), $order_id, array('ref_type' => 'post'), isset($type) ? $type : null);


            if ('yes' === $reward_points_global) {
                if (isset($_GET['post_type']) && isset($_GET['bulk_action']) && $_GET['post_type'] != 'shop_order' && $_GET['bulk_action'] == 'marked_completed')
                    add_filter('mycred_exclude_user', array($this, 'stop_points_for_single_product'), 10, 3);
            }

        }

        public function stop_points_for_single_product($false, $user_id, $obj)
        {
            return true;
        }

    }

    $mycred_woo_reward_product = new mycred_woo_reward_product();
}