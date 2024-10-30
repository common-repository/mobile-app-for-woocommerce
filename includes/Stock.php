<?php

namespace MobileAppForWooCommerce\Includes;

use Exception;
use Throwable;
use WP_REST_Request;
use WP_REST_Response;

class Stock
{

    static $settings_option_key = 'shopapper_stock_settings';

    static $logs_option_key = 'shopapper_stock_logs';

    function __construct()
    {
        add_action('woocommerce_update_product', [$this, 'update_product'], 10, 2);

        add_action('rest_api_init', [$this, 'init']);

    }

    public function init()
    {

        register_rest_route(MAFW_CLIENT_ROUTE, '/stock/settings', [
            'methods' => 'GET',
            'callback' => [$this, 'get_settings'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/stock/settings', [
            'methods' => 'POST',
            'callback' => [$this, 'update_settings'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/stock/logs', [
            'methods' => 'GET',
            'callback' => [$this, 'get_logs'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/stock/export-logs', [
            'methods' => 'GET',
            'callback' => [$this, 'export_logs'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/stock/send-logs', [
            'methods' => 'POST',
            'callback' => [$this, 'send_logs'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/stock/clear-logs', [
            'methods' => 'POST',
            'callback' => [$this, 'clear_logs'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function get_settings(): WP_REST_Response
    {
        global $wpdb;

        $meta_keys = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT meta_key
				FROM $wpdb->postmeta
				WHERE meta_key NOT BETWEEN '_' AND '_z'
				HAVING meta_key NOT LIKE %s
				ORDER BY meta_key
				LIMIT %d",
                $wpdb->esc_like('_') . '%',
                100
            )
        );

        return new WP_REST_Response(['settings' => get_option(self::$settings_option_key,
            [
                'enable' => false,
                'email' => '',
                'email_subject' => '',
                'email_content' => '',
                'export_file_type' => 'csv',
                'export_columns' => ['sku', 'stock_quantity'],
                'clear_after_send' => true,
            ]
        ), 'meta_keys' => $meta_keys]);
    }

    public function update_settings(WP_REST_Request $request): WP_REST_Response
    {
        update_option(self::$settings_option_key, $request->get_params());

        return new WP_REST_Response([]);
    }

    public function get_logs(): WP_REST_Response
    {
        return new WP_REST_Response(array_values(get_option(self::$logs_option_key, [])));
    }

    /**
     * @throws Exception
     */
    static function create_log_file(): array
    {
        $logs = get_option(self::$logs_option_key, []);

        if (empty($logs))
            throw new Exception('Logs empty');

        $uploads = wp_upload_dir();

        $folder = sprintf('%s/shopapper/stock/logs', $uploads['basedir']);

        if (!is_dir($folder) and !wp_mkdir_p($folder))
            throw new Exception('Cannot create log folder');

        $filename = sprintf('%s-StockUpdateLog-%s.csv', get_bloginfo('name'), date('Y-m-d-H-i-s'));

        $file = fopen(sprintf('%s/%s', $folder, $filename), 'w+');

        $settings = get_option(self::$settings_option_key, ['export_columns' => ['sku', 'stock_quantity']]);

        $logs = get_option(self::$logs_option_key, []);

        foreach ($logs as $product_id => $log) {
            $row = [];

            foreach ($settings['export_columns'] as $column) {
                if (isset($log[$column]))
                    $row[] = $log[$column];
                else {

                    $row[] = get_post_meta($product_id, $column, true);
                }
            }

            fputcsv($file, $row);
        }

        fclose($file);

        return ['url' => sprintf('%s/shopapper/stock/logs/%s', $uploads['baseurl'], $filename), 'path' => sprintf('%s/%s', $folder, $filename)];

    }

    public function export_logs(): WP_REST_Response
    {
        try {

            $log_file = self::create_log_file();

            return new WP_REST_Response(['url' => $log_file['url']]);
        } catch (Throwable $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    public function clear_logs(): WP_REST_Response
    {
        update_option(self::$logs_option_key, []);

        return new WP_REST_Response([]);
    }

    public function send_logs(): WP_REST_Response
    {
        try {

            $settings = get_option(self::$settings_option_key);

            if (empty($settings['email']))
                throw new Exception('Please enter email');

            $log_file = self::create_log_file();

            $send = wp_mail($settings['email'], empty($settings['email_subject']) ? 'Stock Update Log' : $settings['email_subject'], empty($settings['email_content']) ? 'Stock Update Log' : $settings['email_content'], '', [$log_file['path']]);

            if (!$send)
                throw new Exception(sprintf("Cannot send email to: %s", $settings['email']));

            if (!isset($settings['clear_after_send']) or $settings['clear_after_send'] === true)
                update_option(self::$logs_option_key, []);

            return new WP_REST_Response(['logs' => array_values(get_option(self::$logs_option_key, [])), 'message' => sprintf("Email report successfully sent to: %s", $settings['email'])]);
        } catch (Throwable $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }


    }

    static function log_stock_update($product, $stock_quantity, $original_stock_quantity, $user_id)
    {
        if (empty($stock_quantity) or empty($original_stock_quantity) or $stock_quantity === $original_stock_quantity) return;

        $settings = get_option(self::$settings_option_key, ['enable' => false]);

        if (empty($settings['enable'])) return;

        $logs = get_option(self::$logs_option_key, []);

        $user_data = get_userdata($user_id);

        $logs[$product->get_id()] = [
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'sku' => $product->get_sku(),
            'slug' => $product->get_slug(),
            'stock_quantity' => $stock_quantity,
            'original_stock_quantity' => $original_stock_quantity,
            'datetime' => time(),
            'user_id' => $user_data->ID,
            'user_display_name' => $user_data->display_name,
        ];

        update_option(self::$logs_option_key, $logs);
    }

    public function update_product($product_id, $product)
    {
        if (isset($_POST['_stock']) and $_POST['_original_stock'])
            self::log_stock_update($product, $_POST['_stock'], $_POST['_original_stock'], get_current_user_id());

    }
}

new Stock();