<?php

use MobileAppForWooCommerce\Includes\Helper;

class Yobro
{
    function __construct()
    {
        add_action('yobro_after_store_message', [$this, 'send_notification'], 10, 2);
    }

    public function send_notification($message)
    {
        Helper::send_auto_notification([
            'event' => 'yobro_new_message',
            'customer_id' => $message['reciever_id'],
            'body' => $message['message']
        ]);
    }
}

new Yobro();