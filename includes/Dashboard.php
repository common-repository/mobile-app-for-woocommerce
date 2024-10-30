<?php

namespace MobileAppForWooCommerce\Includes;

use Throwable;
use WP_REST_Request;
use WP_REST_Response;
use function Sodium\add;

class Dashboard
{

    static $endpoint = 'dashboard';

    static $token_option_name = 'shopapper_dashboard_token';

    static $apply_only_warning = 'shopapper_only_app_warning';

    private $stock_update_product_id = 0;

    function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);

        add_action('admin_menu', [$this, 'admin_menu']);

        add_action('in_admin_header', [$this, 'in_admin_header']);

        add_action('rest_api_init', [$this, 'init']);

        add_action('woocommerce_order_status_changed', [$this, 'order_status_changed'], 10, 4);

        add_action('transition_post_status', [$this, 'publish_product'], 9999, 3);

        add_action('created_product_cat', [$this, 'publish_product_category'], 9999, 2);

        add_action('woocommerce_product_before_set_stock', [$this, 'before_stock_update']);

        add_action('woocommerce_product_set_stock', [$this, 'after_stock_update']);

    }

    public function in_admin_header()
    {
        if (!empty($_GET['page']) and $_GET['page'] === 'shopapper') {
            remove_all_actions('admin_notices');
            remove_all_actions('all_admin_notices');
        }
    }

    public function init()
    {
        register_rest_route(MAFW_CLIENT_ROUTE, self::$endpoint . '/save-token', [
            'methods' => 'POST',
            'callback' => [$this, 'save_token'],
            'args' => [
                'token' => ['required' => true],
            ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, self::$endpoint . '/get-order-statuses', [
            'methods' => 'GET',
            'callback' => [$this, 'get_order_statuses'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, self::$endpoint . '/get-user-roles', [
            'methods' => 'GET',
            'callback' => [$this, 'get_user_roles'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, self::$endpoint . '/get-users', [
            'methods' => 'GET',
            'callback' => [$this, 'get_users'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, self::$endpoint . '/get-user-metas', [
            'methods' => 'GET',
            'callback' => [$this, 'get_user_metas'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, self::$endpoint . '/get-product-categories', [
            'methods' => 'GET',
            'callback' => [$this, 'get_product_categories'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, self::$endpoint . '/warnings', [
            'methods' => 'GET',
            'callback' => [$this, 'get_warnings'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, self::$endpoint . '/warnings', [
            'methods' => 'POST',
            'callback' => [$this, 'save_warnings'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, self::$endpoint . '/get-pages', [
            'methods' => 'GET',
            'callback' => [$this, 'get_pages'],
            'permission_callback' => '__return_true',
        ]);

    }

    public function save_token(WP_REST_Request $request)
    {
        try {
            update_option(self::$token_option_name, $request->get_param('token'));


            if (is_multisite()) {
                switch_to_blog(1);

                update_option(self::$token_option_name, $request->get_param('token'));

                restore_current_blog();
            }

            return new WP_REST_Response([]);
        } catch (Throwable $e) {
            return new WP_REST_Response(['message' => 'Cannot authenticate'], 500);
        }
    }

    public function get_order_statuses()
    {
        return new WP_REST_Response(wc_get_order_statuses());
    }

    public function admin_enqueue_scripts()
    {

        wp_register_style('shopapper-fontawesome', 'https://use.fontawesome.com/releases/v5.7.1/css/all.css');

        wp_enqueue_style('shopapper-admin', MAFW_URL . 'assets/css/admin.css',[],MAFW_SCRIPT_VERSION);

        wp_register_style('shopapper-dashboard', MAFW_URL . 'assets/css/dashboard.css', [], MAFW_SCRIPT_VERSION);

        wp_register_script('shopapper-dashboard', MAFW_URL . 'assets/js/dashboard.js', [], MAFW_SCRIPT_VERSION);
    }

    public function admin_menu()
    {
        add_menu_page(
            'ShopApper',
            'ShopApper',
            'manage_options',
            'shopapper',
            [$this, 'admin_menu_content'], MAFW_URL . '/assets/images/icon.png'
        );


    }

    public function admin_menu_content()
    {

        wp_enqueue_style('shopapper-fontawesome');

        wp_enqueue_style('shopapper-dashboard');

        wp_enqueue_script('shopapper-dashboard');


        $token = get_option(self::$token_option_name, '');

        if (is_multisite()) {
            switch_to_blog(1);

            $token = get_option(self::$token_option_name, '');

            restore_current_blog();
        }


        ?>
        <input hidden id="shopApperApp" value='<?php echo json_encode(['token' => $token, 'url' => get_site_url()]); ?>'
               ?>
        <div id="shopapper-client-dashboard" style="margin-left: -20px;"></div>
        <?php

    }

    /**
     * @param $order_id
     * @param $from_status
     * @param $to_status
     * @param $order
     * @return void
     */
    public function order_status_changed($order_id, $from_status, $to_status, $order)
    {
        if (empty($order->get_customer_id()))
            return;

        $user_data = get_userdata($order->get_customer_id());

        Helper::send_auto_notification([
            'event' => 'order_status_change',
            'order_id' => $order_id,
            'order_number' => $order->get_order_number(),
            'order_status' => sprintf("wc-%s", $to_status),
            'order_date' => wc_format_datetime($order->get_date_created()),
            'order_time' => wc_format_datetime($order->get_date_created(), wc_time_format()),
            'customer_id' => $order->get_customer_id(),
            'first_name' => $user_data->first_name,
            'last_name' => $user_data->last_name,
        ]);
    }

    public function publish_product($new_status, $old_status, $post)
    {


        if ($post->post_type === 'product' and $new_status === 'publish' and $old_status !== 'publish') {

            try {

                $product = wc_get_product($post->ID);

                Helper::send_auto_notification([
                    'event' => 'product_publish',
                    'product_name' => $product->get_name(),
                    'categories' => $product->get_category_ids(),
                    'landing_url' => $product->get_permalink()
                ]);

            } catch (Throwable $e) {
                error_log($e->getMessage());
            }
        }
    }


    /**
     * @param $term_id
     * @param $term_taxonomy_id
     * @return void
     */
    public function publish_product_category($term_id, $term_taxonomy_id)
    {
        $term = get_term($term_id);

        Helper::send_auto_notification([
            'event' => 'product_category_publish',
            'product_category_name' => $term->name,
            'product_category_description' => $term->description,
            'landing_url' => get_term_link($term)
        ]);
    }

    /**
     * @return WP_REST_Response
     */
    public function get_user_roles()
    {
        global $wp_roles;

        $roles = [];

        foreach ($wp_roles->roles as $role_key => $role)
            $roles [] = ['value' => $role_key, 'title' => $role['name']];


        return new WP_REST_Response($roles);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_users(WP_REST_Request $request)
    {
        $args = [];

        $query_params = $request->get_query_params();

        if (!empty($query_params['s']))
            $args['search'] = sprintf("*%s*", $query_params['s']);

        $users = get_users($args);

        return new WP_REST_Response(array_map(function ($user) {
            return [
                'id' => $user->ID,
                'displayName' => $user->display_name,
            ];
        }, $users));
    }

    /**
     * @return WP_REST_Response
     */
    public function get_user_metas()
    {
        try {
            global $wpdb;

            $meta_keys = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT meta_key FROM $wpdb->usermeta WHERE meta_key NOT BETWEEN '_' AND '_z' HAVING meta_key NOT LIKE %s ORDER BY meta_key LIMIT %d", $wpdb->esc_like('_') . '%', 100));

            return new WP_REST_Response($meta_keys);
        } catch (Throwable $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @return WP_REST_Response
     */
    public function get_product_categories()
    {
        try {

            return new WP_REST_Response(get_terms('product_cat'));
        } catch (Throwable $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    public function get_warnings()
    {
        try {

            return new WP_REST_Response([
                'appOnlyWarning' => get_option(self::$apply_only_warning, '')
            ]);
        } catch (Throwable $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    public function save_warnings(WP_REST_Request $request)
    {
        try {

            update_option(self::$apply_only_warning, $request->get_param('appOnlyWarning'));

            return new WP_REST_Response([]);
        } catch (Throwable $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    public function get_pages(WP_REST_Request $request): WP_REST_Response
    {
        $args = [
            'numberposts' => -1,
            'post_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => 'publish',
            'post_type' => ['post', 'page', 'product']
        ];

        if (!empty($request->get_param('s')))
            $args['s'] = $request->get_param('s');

        $pages = get_posts($args);

        return new WP_REST_Response(array_map(function ($page) {
            $post_type = get_post_type_object($page->post_type);

            return [
                'id' => $page->ID,
                'title' => $page->post_title,
                'type' => $post_type->label,
                'url' => get_permalink($page->ID),
            ];
        }, $pages));
    }

    public function before_stock_update($wc_product)
    {
        if (empty(wc_get_product($wc_product->get_id())->get_stock_quantity()))
            $this->stock_update_product_id = $wc_product->get_id();

    }

    public function after_stock_update($wc_product)
    {
        if (!empty($this->stock_update_product_id) and $wc_product->get_id() === $this->stock_update_product_id and !empty($wc_product->get_stock_quantity())) {

            Helper::send_auto_notification([
                'event' => 'in_stock',
                'product_name' => $wc_product->get_name(),
                'categories' => $wc_product->get_category_ids(),
                'landing_url' => $wc_product->get_permalink()
            ]);
        }
    }

}

new Dashboard();