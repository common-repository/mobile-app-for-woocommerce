<?php

function mafw_is_plugin_active($plugin)
{
    $active_plugins = (array)get_option('active_plugins', []);

    if (is_multisite())
        $active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', []));

    return in_array($plugin, $active_plugins) || array_key_exists($plugin, $active_plugins);
}

$active_plugins = (array)get_option('active_plugins', []);

if (is_multisite())
    $active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', []));

if (mafw_is_plugin_active('woocommerce/woocommerce.php'))
    include_once 'payment-gateways/payment-gateways-load.php';

include_once 'includes/Helper.php';
include_once 'includes/Twilio/autoload.php';
include_once 'includes/Dashboard.php';
include_once 'includes/Stock.php';
include_once 'includes/Coupon.php';
include_once 'includes/CustomCssJs.php';
include_once 'includes/PointsAndRewards.php';
include_once 'includes/Beans.php';
include_once 'includes/BackInStockNotifier.php';
include_once 'includes/Yobro.php';
include_once 'includes/PHWoocommerceBookings.php';
include_once 'includes/Updater.php';
include_once 'includes/ForceLogin.php';
include_once 'includes/CommerceGurus.php';
include_once 'includes/YITHQuestionAnswer.php';
include_once 'includes/Formidable.php';

if (mafw_is_plugin_active('points-and-rewards-for-woocommerce/points-rewards-for-woocommerce.php') or mafw_is_plugin_active('ultimate-woocommerce-points-and-rewards/ultimate-woocommerce-points-and-rewards.php'))
    include_once 'includes/Swings.php';

if (mafw_is_plugin_active('mycred/mycred.php'))
    include_once 'includes/MyCred.php';

include_once 'includes/Cron.php';

include_once 'Controllers/AuthController.php';
include_once 'Controllers/CartController.php';
include_once 'Controllers/CheckoutController.php';
include_once 'Controllers/StoreController.php';
include_once 'Controllers/WebView.php';
include_once 'Controllers/MediaController.php';
include_once 'Controllers/SubscriptionController.php';
include_once 'Controllers/Visibility.php';
include_once 'Controllers/Wad.php';
include_once 'Controllers/ProductController.php';
include_once 'Controllers/Webhook.php';

if (file_exists(WP_PLUGIN_DIR . '/woocommerce/woocommerce.php'))
    include_once WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
