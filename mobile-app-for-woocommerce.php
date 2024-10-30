<?php
/**
 * Plugin Name:       Mobile App for WooCommerce: ShopApper WooCommerce Mobile App Builder Service
 * Plugin URI:        https://shopapper.com/
 * Description:       WordPress Plugin to build WooCommerce Android & iOS mobile apps
 * Version:           0.4.31
 * Requires PHP:      7.2
 * Author:            Weptile
 * Author URI:        https://weptile.com/
 */
global $wpdb;

defined('ABSPATH') or die('Access denied');

const MAFW_CLIENT_ROUTE = 'shopapper/client/v1';

const MAFW_VERSION = '0.4.19';
const MAFW_SCRIPT_VERSION = '0.4.19';
const MAFW_API_URL = 'https://shopapper.com/wp-json/shopapper/admin/v1/client';

define('MAFW_PATH', plugin_dir_path(__FILE__));

define('MAFW_URL', plugin_dir_url(__FILE__));

define('MAFW_BASENAME', plugin_basename(__FILE__));

define('MAFW_WC_API_KEY_TABLE', $wpdb->prefix . 'woocommerce_api_keys');


include_once 'mobile-app-for-woocommerce-load.php';






