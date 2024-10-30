<?php


namespace MobileAppForWooCommerce\Controllers;

class ShopApperWad
{
    function __construct()
    {
        add_filter('woocommerce_rest_prepare_product_object', [$this, 'rest_prepare_product_object'], 10, 3);
        add_filter('woocommerce_rest_prepare_product_variation_object', [$this, 'rest_prepare_product_object'], 10, 3);
    }

    public function rest_prepare_product_object($response, $object, $request)
    {
        if (!class_exists('Wad'))
            return $response;

        $response_data = $response->get_data();

        global $wad_last_products_fetch;

        $wad_last_products_fetch = $object->get_type() === 'variation' ? [wp_get_post_parent_id($object->get_id())] : [$object->get_id()];

        $response_data['sale_price'] = $object->get_sale_price() === '' ? $object->get_sale_price() : (float)$object->get_sale_price();

        $response_data['price'] = $object->get_price() === '' ? $object->get_price() : (float)$object->get_price();

        $response_data['regular_price'] = $object->get_regular_price() === '' ? $object->get_regular_price() : (float)$object->get_regular_price();

        $response->set_data($response_data);

        return $response;
    }
}

new ShopApperWad();