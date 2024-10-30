<?php

class PHWoocommerceBookings
{
    function __construct()
    {
        add_filter('updated_ReminderEmailStatus_meta', [$this, 'send_notification'], 10, 4);
    }

    public function send_notification($meta_id, $object_id, $meta_key, $meta_value)
    {
        try {

            $order = wc_get_order(wc_get_order_id_by_order_item_id($object_id));

            $customer_data = get_userdata($order->get_customer_id());

            $product_name = '';

            $order_item = $order->get_item($object_id);

            if ($order_item and is_callable([$this, 'get_product']) and $order_item->get_product())
                $product_name = $order_item->get_product()->get_name();

            Helper::send_auto_notification([
                'event' => 'ph_woocommerce_bookings_reminder',
                'customer_id' => $order->get_customer_id(),
                'first_name' => $customer_data->first_name,
                'last_name' => $customer_data->last_name,
                'product_name' => $product_name
            ]);

        } catch (Throwable $e) {
            error_log($e->getMessage());
        }
    }
}

new PHWoocommerceBookings();