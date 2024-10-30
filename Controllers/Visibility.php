<?php


namespace MobileAppForWooCommerce\Controllers;

use MobileAppForWooCommerce\Includes\Dashboard;
use MobileAppForWooCommerce\Includes\Helper;


class Visibility
{
    use Helper;

    const hideFromWebSites = [
        'https://competitionfever.com',
        'http://dev.local.com'
    ];

    function __construct()
    {

        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);

        add_action('product_cat_edit_form_fields', [$this, 'add_category_form_fields']);
        add_action('product_cat_add_form_fields', [$this, 'add_category_form_fields']);

        add_action('created_product_cat', [$this, 'save_category_form_fields']);
        add_action('edited_product_cat', [$this, 'save_category_form_fields']);


        add_filter('woocommerce_product_data_tabs', [$this, 'product_data_tabs']);
        add_action('woocommerce_product_data_panels', [$this, 'product_data_panels']);

        add_action('woocommerce_process_product_meta', [$this, 'process_product_meta']);

        add_action('woocommerce_after_add_attribute_fields', [$this, 'after_add_attribute_fields']);
        add_action('woocommerce_after_edit_attribute_fields', [$this, 'after_add_attribute_fields']);

        add_action('woocommerce_attribute_added', [$this, 'attribute_updated']);
        add_action('woocommerce_attribute_updated', [$this, 'attribute_updated']);


        add_action('woocommerce_product_after_variable_attributes', [$this, 'add_custom_variation_field'], 10, 3);
        add_action('woocommerce_save_product_variation', [$this, 'save_custom_variation_field'], 10, 2);

        add_filter('woocommerce_rest_prepare_product_cat', [$this, 'rest_prepare_product_cat'], 10, 3);

        add_filter('woocommerce_rest_prepare_product_object', [$this, 'rest_prepare_product_object'], 10, 3);
        add_filter('woocommerce_rest_prepare_product_variation_object', [$this, 'rest_prepare_product_object'], 10, 3);

        add_filter('woocommerce_add_to_cart_validation', [$this, 'woocommerce_add_to_cart_validation'], 10, 3);

        add_action('woocommerce_after_checkout_validation', [$this, 'woocommerce_after_checkout_validation'], 10, 2);

        add_filter('wc_lottery_generate_random_ticket_numbers', [$this, 'wc_lottery_generate_random_ticket_numbers'], 90, 3);

        add_action('pre_get_posts', [$this, 'hide_shopapper_products']);

    }

    public function admin_enqueue_scripts()
    {
        wp_enqueue_style('shopapper-visibility', MAFW_URL . 'assets/css/visibility.css');
    }

    public function add_category_form_fields($tag)
    {

        $term_meta = is_object($tag) ? get_option("taxonomy_term_$tag->term_id", []) : ['shopapper_hide' => 'no'];

        ?>

        <tr class="form-field term-shopapper_hide">
            <th scope="row"><label for="term_meta[shopapper_hide]">Hide category from app?</label></th>
            <td>
                <input type="checkbox" name="term_meta[shopapper_hide]" value="yes"
                       id="term_meta[shopapper_hide]" <?php echo (isset($term_meta['shopapper_hide']) and $term_meta['shopapper_hide'] === 'yes') ? 'checked="checked"' : ''; ?>>
            </td>
        </tr>

        <?php
    }

    public function save_category_form_fields($term_id)
    {

        if (isset($_POST['term_meta'])) {

            $term_meta = get_option("taxonomy_term_$term_id");

            if (isset($_POST['term_meta']['shopapper_hide']))
                $term_meta['shopapper_hide'] = 'yes';
            else
                $term_meta['shopapper_hide'] = 'no';

            //save the option array
            update_option("taxonomy_term_$term_id", $term_meta);
        }
    }

    public function product_data_tabs($tabs)
    {
        $tabs['shopapper'] = [
            'label' => __('ShopApper', 'woocommerce'),
            'target' => 'shopapper_product_data',
            'class' => [],
            'priority' => 80,
        ];

        return $tabs;
    }

    public function product_data_panels()
    {

        ?>
        <div id="shopapper_product_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <?php
                //                woocommerce_wp_checkbox(
                //                    [
                //                        'id' => 'shopapper_hide',
                //                        'value' => get_post_meta(get_the_ID(), 'shopapper_hide', true),
                //                        'label' => __('Hide product from app?', 'woocommerce'),
                //                        'cbvalue' => 'yes',
                //                    ]
                //                );
                woocommerce_wp_checkbox(
                    [
                        'id' => 'shopapper_only_app',
                        'value' => get_post_meta(get_the_ID(), 'shopapper_only_app', true),
                        'label' => __('App Only?', 'woocommerce'),
                        'cbvalue' => 'yes',
                        'desc_tip' => true,
                        'description' => 'Product appears on the web but it prevents users from adding it to cart.'
                    ]
                );
                if (in_array(get_site_url(), self::hideFromWebSites))
                    woocommerce_wp_checkbox(
                        [
                            'id' => 'shopapper_hide_web',
                            'value' => get_post_meta(get_the_ID(), 'shopapper_hide_web', true),
                            'label' => __('Hide from web?', 'woocommerce'),
                            'cbvalue' => 'yes',
                            'desc_tip' => true,
                            'description' => 'It entirely hides the product from the web.'
                        ]
                    );
                ?>
            </div>
        </div>


        <?php
    }

    public function process_product_meta($post_id)
    {
        $product = wc_get_product($post_id);

        $product->update_meta_data('shopapper_hide', $_POST['shopapper_hide'] ?? 'no');

        $product->update_meta_data('shopapper_only_app', $_POST['shopapper_only_app'] ?? 'no');

        $product->update_meta_data('shopapper_hide_web', $_POST['shopapper_hide_web'] ?? 'no');

        $product->save();
    }

    public function after_add_attribute_fields()
    {
        $id = isset($_GET['edit']) ? absint($_GET['edit']) : 0;

        $hide_value = $id ? get_option("shopapper_product_attribute_hide-$id", 'no') : 'no';

        ?>
        <tr class="form-field ">
            <th scope="row" valign="top">
                <label for="shopapper_hide">Hide attribute from app?</label>
            </th>
            <td>
                <input name="shopapper_hide" id="shopapper_hide" type="checkbox"
                       value="yes" <?php echo $hide_value === 'yes' ? 'checked="checked"' : ''; ?>>
            </td>
        </tr>
        <?php
    }

    public function attribute_updated($id)
    {
        update_option("shopapper_product_attribute_hide-$id", $_POST['shopapper_hide'] ?? 'no');
    }

    public function add_custom_variation_field($loop, $variation_data, $variation)
    {
        woocommerce_wp_checkbox(array(
            'id' => 'shopapper_hide[' . $loop . ']',
            'label' => __('Hide variation from app?', 'woocommerce'),
            'value' => get_post_meta($variation->ID, 'shopapper_hide', true),
            'wrapper_class' => 'form-row form-row-full',
            'cbvalue' => 'yes',
        ));

        woocommerce_wp_checkbox(array(
            'id' => 'shopapper_only_app[' . $loop . ']',
            'label' => __('App Only?', 'woocommerce'),
            'value' => get_post_meta($variation->ID, 'shopapper_only_app', true),
            'wrapper_class' => 'form-row form-row-full',
            'cbvalue' => 'yes',
        ));
    }

    public function save_custom_variation_field($variation_id, $i)
    {
        update_post_meta($variation_id, 'shopapper_hide', $_POST['shopapper_hide'][$i] ?? 'no');

        update_post_meta($variation_id, 'shopapper_only_app', $_POST['shopapper_only_app'][$i] ?? 'no');
    }

    public function rest_prepare_product_cat($response, $item, $request)
    {

        $term_meta = get_option("taxonomy_term_$item->term_id");

        $response->set_data(array_merge($response->get_data(), ['shopapperHide' => $term_meta['shopapper_hide'] ?? 'no']));

        return $response;
    }

    public function rest_prepare_product_object($response, $object, $request)
    {

        $response_data = $response->get_data();

        foreach ($response_data['attributes'] as $key => $attribute) {
            $attribute_id = $attribute['id'];
            $response_data['attributes'][$key]['shopapperHide'] = get_option("shopapper_product_attribute_hide-$attribute_id", 'no');
        }

        $response->set_data(array_merge($response_data, ['shopapperHide' => $object->get_meta('shopapper_hide')]));

        return $response;
    }

    /**
     * @param $passed
     * @param $product_id
     * @param $quantity
     * @return false|mixed
     */
    public function woocommerce_add_to_cart_validation($passed, $product_id, $quantity)
    {

        if (get_post_meta($product_id, 'shopapper_only_app', true) === 'yes' and !$this->is_shopapper()) {

            $warning = __(get_option(Dashboard::$apply_only_warning, 'You cannot purchase via website. Please use mobile app.'), 'woocommerce');

            if (!wc_has_notice($warning))
                wc_add_notice($warning, 'error');

            return false;
        }

        return $passed;
    }

    /**
     * @param $data
     * @param $errors
     */
    public function woocommerce_after_checkout_validation($data, $errors)
    {
        if ($this->is_shopapper()) return;

        foreach (WC()->cart->cart_contents as $cart_content) {

            $product = $cart_content['data'];

            if ($product->get_meta('shopapper_only_app') === 'yes') {
                $errors->add('shopapper', __(get_option(Dashboard::$apply_only_warning, 'You cannot purchase via website. Please use mobile app.'), 'woocommerce'));

                break;
            }
        }
    }

    public function wc_lottery_generate_random_ticket_numbers($random_tickets, $product_id, $qty)
    {
        if (get_post_meta($product_id, 'shopapper_only_app', true) === 'yes' and !$this->is_shopapper()) {

            $warning = __(get_option(Dashboard::$apply_only_warning, 'You cannot purchase via website. Please use mobile app.'), 'woocommerce');

            if (!wc_has_notice($warning))
                wc_add_notice($warning, 'error');

            return false;
        }

        return $random_tickets;
    }

    /**
     * @param $query
     * @return void
     */
    public function hide_shopapper_products($query)
    {

        if (!is_admin() and $query->get('post_type') == 'product' and !$this->is_shopapper() and in_array(get_site_url(),self::hideFromWebSites)) {

            $meta_query = (array)$query->get('meta_query');

            $meta_query[] = [
                'relation' => 'OR',
                [
                    'key' => 'shopapper_hide_web',
                    'value' => 'yes',
                    'compare' => '!='
                ],
                [
                    'key' => 'shopapper_hide_web',
                    'compare' => 'NOT EXISTS'
                ]
            ];

            $query->set('meta_query', $meta_query);

        }
    }

}

new Visibility();