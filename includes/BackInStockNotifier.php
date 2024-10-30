<?php

use MobileAppForWooCommerce\Includes\Helper;

class BackInStockNotifier
{
    function __construct()
    {
        add_action('cwg_instock_after_instock_mail', [$this, 'send_notification'], 10, 2);
    }

    public function send_notification($to, $subscriber_id)
    {
        $wc_product = wc_get_product(get_post_meta($subscriber_id, 'cwginstock_product_id', true));

        if (!$wc_product)
            return;

        $customer_id = get_post_meta($subscriber_id, 'cwginstock_user_id', true);

        if (empty($customer_id))
            return;

        Helper::send_auto_notification([
            'event' => 'back_in_stock_notifier',
            'customer_id' => $customer_id,
            'subscriber_name' => get_post_meta($subscriber_id, 'cwginstock_subscriber_name', true),
            'product_name' => $wc_product->get_name(),
            'landing_url' => $wc_product->get_permalink()
        ]);
    }
}

new BackInStockNotifier();