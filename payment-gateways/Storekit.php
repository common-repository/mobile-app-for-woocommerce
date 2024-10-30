<?php

namespace MobileAppForWooCommerce\PaymentGateWays;

use WC_Payment_Gateway;

class Storekit extends WC_Payment_Gateway
{
    public function __construct()
    {

        $this->id = 'shopapper-storekit';

        $this->method_title = 'ShopApper Storekit';
        $this->method_description = 'Description';
        $this->icon = MAFW_URL . 'assets/images/storekit.jpg';
        $this->has_fields = false;
        $this->order_button_text = 'Proceed';
        $this->supports = [
            'products',
            'subscriptions',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_date_changes'
        ];

        $this->init_form_fields();

        $this->init_settings();

        $this->title = $this->get_option('title');

        $this->description = $this->get_option('description');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => 'Enable/Disable',
                'label' => 'Enable ShopApper Storekit',
                'type' => 'checkbox',
                'default' => 'no'
            ),
            'title' => array(
                'title' => 'Title',
                'type' => 'text',
                'description' => 'This message will show to the user during checkout.',
                'default' => 'ShopApper Storekit'
            ),
            'description' => array(
                'title' => 'Description',
                'type' => 'text',
                'description' => 'This controls the description which the user sees during checkout.',
                'default' => 'Description',
                'desc_tip' => true,
            ),
        );
    }
}