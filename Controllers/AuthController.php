<?php


namespace MobileAppForWooCommerce\Controllers;

use Exception;
use MobileAppForWooCommerce\Includes\Helper;
use Throwable;
use Twilio\Rest\Client;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_Session_Tokens;

class AuthController
{
    use Helper;

    static $phone_country_code_user_meta_key = 'shopapper_phone_country_code';

    static $phone_number_user_meta_key = 'shopapper_phone_number';

    public function __construct()
    {
        add_action('network_plugin_loaded', [$this, 'network_plugin_loaded']);

        add_action('rest_api_init', [$this, 'init']);

        add_filter('woocommerce_rest_check_permissions', [$this, 'rest_check_permissions'], 99, 4);

        add_action('show_user_profile', [$this, 'user_edit'], 99, 1);

        add_action('user_new_form', [$this, 'user_edit'], 99, 1);

        add_action('edit_user_profile', [$this, 'user_edit'], 99, 1);

        add_action('personal_options_update', [$this, 'user_save']);

        add_action('edit_user_profile_update', [$this, 'user_save']);

    }

    /**
     * @description To fix multi-site woocommerce auth bug.
     */
    public function network_plugin_loaded()
    {
        if (is_multisite() and strpos($_SERVER['REQUEST_URI'], MAFW_CLIENT_ROUTE) !== false)
            remove_filter('determine_current_user', 'wp_validate_auth_cookie');
    }

    public function init()
    {

        register_rest_route(MAFW_CLIENT_ROUTE, 'customers/(?P<id>[\d]+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_customer_data'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, 'customers/(?P<id>[\d]+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'edit_customer'],
            'args' => [
                'firstName' => ['required' => true],
                'lastName' => ['required' => true],
                'displayName' => ['required' => true],
                'email' => ['required' => true],
                'consumer_key' => ['required' => true],
                'consumer_secret' => ['required' => true],
            ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/login', [
            'methods' => 'POST',
            'callback' => [$this, 'login'],
            'args' => [
                'username' => ['required' => true],
                'password' => ['required' => true],
            ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/logout', [
            'methods' => 'POST',
            'callback' => [$this, 'logout'],
            'args' => [
                'id' => ['required' => true],
                'key' => ['required' => true],
            ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/phone-number-login', [
            'methods' => 'POST',
            'callback' => [$this, 'phone_number_login'],
            'args' => [
                'appId' => ['required' => true],
                'country_code' => ['required' => true],
                'phone' => ['required' => true],
                'formatted_phone' => ['required' => true],
                'service' => ['required' => true],
            ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/phone-number-verify', [
            'methods' => 'POST',
            'callback' => [$this, 'phone_number_verify'],
            'args' => [
                'appId' => ['required' => true],
                'code' => ['required' => true],
                'phone' => ['required' => true],
                'service' => ['required' => true],
            ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/register', [
            'methods' => 'POST',
            'callback' => [$this, 'register'],
            'args' => [
                'username' => ['required' => true],
                'email' => ['required' => true],
                'password' => ['required' => true],
            ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/phone-number-register', [
            'methods' => 'POST',
            'callback' => [$this, 'phone_number_register'],
            'args' => [
                'appId' => ['required' => true],
                'country_code' => ['required' => true],
                'phone' => ['required' => true],
                'formatted_phone' => ['required' => true],
                'service' => ['required' => true],
            ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/phone-number-register-verify', [
            'methods' => 'POST',
            'callback' => [$this, 'phone_number_register_verify'],
            'args' => [
                'appId' => ['required' => true],
                'country_code' => ['required' => true],
                'phone' => ['required' => true],
                'formatted_phone' => ['required' => true],
                'service' => ['required' => true],
            ],
            'permission_callback' => '__return_true',
        ]);


        // register change password endpoint
        register_rest_route(MAFW_CLIENT_ROUTE, '/change-password', [
            'methods' => 'POST',
            'callback' => [$this, 'change_password'],
            'args' => [
                'currentPassword' => ['required' => true],
                'newPassword' => ['required' => true],
                'newPasswordConfirm' => ['required' => true],
                'userId' => ['required' => true],
            ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/lost-password', [
            'methods' => 'POST',
            'callback' => [$this, 'lost_password'],
            'args' => [
                'email' => [
                    'required' => true,
                    'validate_callback' => function ($param, $request, $key) {
                        return filter_var($param, FILTER_VALIDATE_EMAIL);
                    }
                ],
            ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/delete-account', [
            'methods' => 'POST',
            'callback' => [$this, 'delete_account'],
            'args' => [
                'consumer_key' => ['required' => true],
            ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/google-login', [
            'methods' => 'POST',
            'callback' => [$this, 'google_login'],
            'args' => [
                'token' => ['required' => true],
            ],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(MAFW_CLIENT_ROUTE, '/facebook-login', [
            'methods' => 'POST',
            'callback' => [$this, 'facebook_login'],
            'args' => [
                'token' => ['required' => true],
            ],
            'permission_callback' => '__return_true',
        ]);
    }

    public function user_edit($user)
    {

        ?>
        <h3>Mobile App OTP Login Phone Number:</h3>
        <table class="form-table" role="presentation">
            <tbody>
            <tr class="shopapper-phone-number">
                <td>
                    <span style="font-weight: bold;">+</span><input name="shopapper-phone-country-code"
                                                                    id="shopapper-phone-country-code" type="text"
                                                                    value="<?php echo isset($user->ID) ? get_user_meta($user->ID, self::$phone_country_code_user_meta_key, true) : ''; ?>"
                                                                    style="width: 50px;"/>
                    <input name="shopapper-phone-number" id="shopapper-phone-number" type="text"
                           value="<?php echo isset($user->ID) ? get_user_meta($user->ID, self::$phone_number_user_meta_key, true) : ''; ?>"/>
                </td>
            </tr>
            </tbody>
        </table>
        <?php
    }

    public function user_save($user_id)
    {

        if (empty($_POST['_wpnonce']) or !wp_verify_nonce($_POST['_wpnonce'], 'update-user_' . $user_id) or !current_user_can('edit_user', $user_id)) {
            return;
        }

        update_user_meta($user_id, self::$phone_country_code_user_meta_key, $_POST['shopapper-phone-country-code']);

        update_user_meta($user_id, self::$phone_number_user_meta_key, $_POST['shopapper-phone-number']);
    }

    /**
     * @return array
     */
    static function get_api_key_tables()
    {
        global $wpdb;

        $tables = [];

        if (is_multisite()) {

            foreach (get_sites() as $site) {
                switch_to_blog($site->blog_id);

                $table_name = sprintf("%swoocommerce_api_keys", $wpdb->prefix);

                if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table_name))))
                    $tables[] = $table_name;

                restore_current_blog();

            }
        } else {
            $tables[] = MAFW_WC_API_KEY_TABLE;
        }

        return $tables;
    }

    /**
     * @param $consumer_key
     */
    private function remove_key($consumer_key)
    {
        global $wpdb;

        foreach ($this->get_api_key_tables() as $table_name) {

            $wpdb->delete($table_name, ['consumer_key' => $consumer_key], ['%s']);
        }

    }

    /**
     * @param $user_id
     * @return array|false
     */
    public function get_keys_of_customer($user_id)
    {
        global $wpdb;

        $consumer_values = $wpdb->get_row($wpdb->prepare('SELECT consumer_key, consumer_secret FROM ' . MAFW_WC_API_KEY_TABLE . ' WHERE user_id = %d', $user_id));

        if ($consumer_values)
            $this->remove_key($consumer_values->consumer_key);

        return $this->generate_api_key($user_id);
    }

    /**
     * @param $user_id
     * @return array|false
     */
    public function generate_api_key($user_id)
    {
        global $wpdb;

        $consumer_key = 'ck_' . wc_rand_hash();
        $consumer_secret = 'cs_' . wc_rand_hash();

        $data = [
            'user_id' => $user_id,
            'description' => 'Generated by ShopApper',
            'permissions' => 'read_write',
            'consumer_key' => wc_api_hash($consumer_key),
            'consumer_secret' => $consumer_secret,
            'truncated_key' => substr($consumer_key, -7),
        ];

        $inserted = false;

        foreach ($this->get_api_key_tables() as $table_name) {

            $inserted = $wpdb->insert(
                $table_name,
                $data,
                [
                    '%d',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                    '%s',
                ]
            );
        }

        $data['consumer_key'] = $consumer_key;

        if ($inserted)
            return $data;

        return false;
    }

    /**
     * @param $appId
     * @return mixed
     * @throws Exception
     */
    public function get_app_login_settings($appId)
    {
        $response = wp_remote_post("https://shopapper.com/wp-json/shopapper/admin/v1/client/login-settings", [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode(['appId' => $appId])
        ]);

        if ($response['response']['code'] !== 200)
            throw new Exception($response['response']['message']);

        return json_decode($response['body']);

    }

    /**
     * @throws Exception
     */
    public function find_customer_by_phone($phone, $settings = null)
    {

        if (empty($settings->phoneNumberLoginPhoneMeta)) throw new Exception('Missing configuration.');

        $users = get_users([
            'meta_key' => $settings->phoneNumberLoginPhoneMeta,
            'meta_value' => $phone
        ]);

        if (empty($users)) throw new Exception('Not found');

        return $users[0];
    }

    /**
     * @param $service
     * @param $phone
     * @param $credentials
     * @throws Exception
     */
    public function create_verification($service, $phone, $credentials)
    {
        switch ($service) {
            case 'twillio':
                $client = new Client($credentials->twillioAccountSid, $credentials->twillioAccountToken);

                $client->verify->v2->services($credentials->twillioServiceSid)
                    ->verifications
                    ->create($phone, "sms");
                break;
            default:
                throw new Exception('Unsupported SMS Service');
        }
    }

    /**
     * @param $service
     * @param $phone
     * @param $code
     * @param $credentials
     * @return bool
     * @throws Exception
     */
    public function verify_code($service, $phone, $code, $credentials)
    {
        switch ($service) {
            case 'twillio':

                $client = new Client($credentials->twillioAccountSid, $credentials->twillioAccountToken);

                $verification = $client->verify->v2->services($credentials->twillioServiceSid)
                    ->verificationChecks
                    ->create($code, ["to" => $phone]);

                if (!$verification->valid) throw new Exception('Invalid code');
                break;
            default:
                throw new Exception('Unsupported SMS Service');
        }

        return true;
    }

    /**
     * @param $username
     * @param $email
     * @param $password
     * @return int|WP_Error
     */
    public function register_customer($username, $email, $password)
    {
        if (is_multisite()) {

            switch_to_blog(1);

            $user_id = wc_create_new_customer($email, $username, $password, ['first_name' => $username, 'last_name' => $username]);

            $sites = get_sites();

            foreach ($sites as $site)
                add_user_to_blog($site->blog_id, $user_id, 'customer');

            restore_current_blog();

            return $user_id;
        }

        return wc_create_new_customer($email, $username, $password, ['first_name' => $username, 'last_name' => $username]);
    }

    /**
     * @param $permission
     * @param $context
     * @param $object_id
     * @param $post_type
     * @return bool
     */
    public function rest_check_permissions($permission, $context, $object_id, $post_type)
    {
        if ((($post_type == 'product' or $post_type === 'product_cat' or $post_type === 'product_variation') and $context == 'read') or ($post_type === 'product_review' and $context = 'create')) {
            return true;
        }

        return $permission;
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_customer_data(WP_REST_Request $request)
    {
        $customer_id = $request->get_param('id');

        $consumer_key = $request->get_param('consumer_key');

        if (!$this->is_user_logged_in($consumer_key))
            return new WP_REST_Response(['message' => 'Authentication failed...'], 500);

        $customer = get_userdata($customer_id);

        $last_order = wc_get_customer_last_order($customer_id);

        $user_roles_by_sites = [];

        if (is_multisite()) {
            foreach (get_sites() as $site) {
                switch_to_blog($site->blog_id);

                $_customer = get_userdata($customer_id);

                $user_roles_by_sites[get_site_url($site->blog_id)] = $_customer->roles;

                restore_current_blog();

            }
        }


        return new WP_REST_Response([
            'id' => $customer->ID,
            'email' => $customer->user_email,
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'display_name' => $customer->display_name,
            'avatar_url' => get_avatar_url($customer->user_email),
            'lastOrderId' => $last_order ? $last_order->get_id() : 0,
            'userRoles' => $customer->roles,
            'userRolesBySites' => $user_roles_by_sites
        ]);
    }

    public function edit_customer(WP_REST_Request $request): WP_REST_Response
    {
        try {

            if (!is_user_logged_in())
                throw new Exception('Bad Authentication');

            $customer_id = $request->get_param('id');

            if ($customer_id != get_current_user_id())
                throw new Exception('Bad Authentication');

            $customer_id = wp_update_user([
                'ID' => $request->get_param('id'), // this is the ID of the user you want to update.
                'first_name' => $request->get_param('firstName'),
                'last_name' => $request->get_param('lastName'),
                'display_name' => $request->get_param('displayName'),
                'email' => $request->get_param('email'),
            ]);

            if (is_wp_error($customer_id))
                throw new Exception($customer_id->get_error_message());

            $customer = get_userdata($customer_id);

            return new WP_REST_Response([
                'id' => $customer->ID,
                'email' => $customer->user_email,
                'first_name' => $customer->first_name,
                'last_name' => $customer->last_name,
                'display_name' => $customer->display_name,
                'avatar_url' => get_avatar_url($customer->user_email),
                'userRoles' => $customer->roles,
            ]);

        } catch (Throwable $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function login(WP_REST_Request $request)
    {
        $username = $request->get_param('username');
        $password = $request->get_param('password');

        $user = wp_authenticate($username, $password);

        if ($this->is_loggedin_active())
            $user = wp_authenticate_username_password($user, $username, $password);

        if (is_wp_error($user))
            return new WP_REST_Response(['message' => $user->get_error_message()], 500);

        if (isset($user)) {

            $keys = $this->get_keys_of_customer($user->ID);

            $session_token = '';

            if ($this->is_loggedin_active()) {

                $manager = WP_Session_Tokens::get_instance($user->ID);
                $expiration = time() + apply_filters('auth_cookie_expiration', 14 * DAY_IN_SECONDS, $user->ID, true);
                $session_token = $manager->create($expiration);
                update_user_meta($user->ID, $keys['consumer_key'], $session_token);
            }

            return new WP_REST_Response([
                'id' => $user->ID,
                'consumer_key' => $keys['consumer_key'],
                'consumer_secret' => $keys['consumer_secret'],
                'session_token' => $session_token
            ]);
        }
        return new WP_REST_Response(['message' => 'Customer not found'], 500);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function logout(WP_REST_Request $request)
    {
        try {

            if ($this->is_loggedin_active()) {

                $user_id = $request->get_param('id');

                $key = $request->get_param('key');

                $session_token = get_user_meta($user_id, $key, true);

                if (!empty($session_token)) {

                    $manager = WP_Session_Tokens::get_instance($user_id);

                    $manager->destroy($session_token);

                    $this->remove_key($key);

                    delete_user_meta($user_id, $key);
                }
            }

            return new WP_REST_Response([]);
        } catch (Throwable $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function phone_number_login(WP_REST_Request $request)
    {
        try {

            $login_settings = $this->get_app_login_settings($request->get_param('appId'));

            $this->find_customer_by_phone($request->get_param('phone'), $login_settings);

            $this->create_verification($request->get_param('service'), $request->get_param('formatted_phone'), $login_settings);

            return new WP_REST_Response([]);

        } catch (Throwable $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function phone_number_verify(WP_REST_Request $request)
    {
        try {

            $login_settings = $this->get_app_login_settings($request->get_param('appId'));

            $this->verify_code($request->get_param('service'), $request->get_param('formatted_phone'), $request->get_param('code'), $login_settings);

            $user = $this->find_customer_by_phone($request->get_param('phone'), $login_settings);

            $keys = $this->get_keys_of_customer($user->ID);

            return new WP_REST_Response([
                'id' => $user->ID,
                'consumer_key' => $keys['consumer_key'],
                'consumer_secret' => $keys['consumer_secret']
            ]);
        } catch (Throwable $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function register(WP_REST_Request $request)
    {
        $username = $request->get_param('username');

        $email = $request->get_param('email');

        $password = $request->get_param('password');

        $user_id = $this->register_customer($username, $email, $password);

        if (is_wp_error($user_id))
            return new WP_REST_Response(['message' => $user_id->get_error_message()], 500);

        update_user_meta($user_id, 'registered_by_shopapper', 1);

        return new WP_REST_Response([]);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function phone_number_register(WP_REST_Request $request)
    {
        try {

            $login_settings = $this->get_app_login_settings($request->get_param('appId'));

            $isExist = false;
            try {
                $isExist = $this->find_customer_by_phone($request->get_param('phone'), $login_settings);
            } catch (Throwable $e) {
            }

            if ($isExist)
                throw new Exception('Already exists');

            $this->create_verification($request->get_param('service'), $request->get_param('formatted_phone'), $login_settings);

            return new WP_REST_Response([]);

        } catch (Throwable $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function phone_number_register_verify(WP_REST_Request $request)
    {
        try {

            $login_settings = $this->get_app_login_settings($request->get_param('appId'));

            $this->verify_code($request->get_param('service'), $request->get_param('formatted_phone'), $request->get_param('code'), $login_settings);

            $username = str_replace('+', '', $request->get_param('formatted_phone'));

            $email = sprintf('%s@dummy.com', $request->get_param('formatted_phone'));

            $password = wp_generate_password();

            $user_id = $this->register_customer($username, $email, $password);

            if (is_wp_error($user_id))
                return new WP_REST_Response(['message' => $user_id->get_error_message()], 500);

            update_user_meta($user_id, 'registered_by_shopapper', 1);

            update_user_meta($user_id, $login_settings->phoneNumberLoginPhoneMeta, $request->get_param('phone'));

            update_user_meta($user_id, $login_settings->phoneNumberLoginCountryCodeMeta, $request->get_param('country_code'));

            do_action('shopapper_phone_number_user_register', $user_id);

            $keys = $this->get_keys_of_customer($user_id);

            return new WP_REST_Response([
                'id' => $user_id,
                'consumer_key' => $keys['consumer_key'],
                'consumer_secret' => $keys['consumer_secret']
            ]);

        } catch (Throwable $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function change_password(WP_REST_Request $request): WP_REST_Response
    {
        try {

            $user_data = get_userdata($request->get_param('userId'));

            if (!$user_data)
                throw new Exception('User cannot be found!');

            if (!wp_check_password($request->get_param('currentPassword'), $user_data->user_pass, $user_data->ID))
                throw new Exception('User cannot be found with given current password!');

            if ($request->get_param('newPassword') !== $request->get_param('newPasswordConfirm'))
                throw new Exception('Entered passwords are not match!');

            wp_set_password($request->get_param('newPassword'), $user_data->ID);

            return new WP_REST_Response('Password was successfully changed.');
        } catch (Throwable $e) {

            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function lost_password(WP_REST_Request $request)
    {

        try {

            $this->init_wc();

            $email = $request->get_param('email');

            $user = get_user_by('email', $email);

            if (!$user)
                throw new Exception('User not found!');

            $reset_key = get_password_reset_key($user);

            $wc_emails = WC()->mailer()->get_emails();

            $wc_emails['WC_Email_Customer_Reset_Password']->trigger($user->user_login, $reset_key);

            return new WP_REST_Response('Email successfully send.');
        } catch (Throwable $e) {

            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }

    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function delete_account(WP_REST_Request $request)
    {

        try {

            require_once(ABSPATH . 'wp-admin/includes/user.php');

            $userId = $this->get_user_id_by_consumer_key($request->get_param('consumerKey'));

            if (!$userId)
                throw new Exception('Cannot delete user account');

            $user_data = get_userdata($userId);

            $reAssignUserId = $request->get_param('reAssignUserId');

            wp_delete_user($userId, $reAssignUserId);

            if (!empty($request->get_param('adminNotificationEmail')))
                wp_mail($request->get_param('adminNotificationEmail'), 'An app user has deleted their account', 'A user has deleted their account from the mobile app. Their previous orders and data have been updated to appear as "Guest".<br>Username: ' . $user_data->display_name . '<br>Email: ' . $user_data->user_email);


            return new WP_REST_Response([]);
        } catch (Throwable $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function google_login(WP_REST_Request $request)
    {
        if (!$request->has_param('token')) return new WP_REST_Response(['message' => 'Bad request'], 400);

        $token = $request->get_param('token');

        $url = 'https://www.googleapis.com/oauth2/v3/tokeninfo?id_token=' . $token;

        $data = wp_remote_get($url);

        if (is_wp_error($data)) return new WP_REST_Response(['message' => 'Failed to retrieve user data from google'], 500);

        $response = json_decode(wp_remote_retrieve_body($data), true);

        if (array_key_exists('error_description', $response)) return new WP_REST_Response(['message' => 'Invalid google authentication token'], 500);

        $email = $response['email'];

        $user = get_user_by('email', $email);

        if (!$user) {
            $created = wc_create_new_customer($email, $email, wp_generate_password());

            if (is_wp_error($created)) return new WP_REST_Response(['message' => 'Failed to register google user'], 500);

            $user = get_user_by('email', $email);
        }

        $keys = $this->get_keys_of_customer($user->ID);

        $session_token = '';

        if ($this->is_loggedin_active()) {
            $manager = WP_Session_Tokens::get_instance($user->ID);

            $expiration = time() + apply_filters('auth_cookie_expiration', 14 * DAY_IN_SECONDS, $user->ID, true);

            $session_token = $manager->create($expiration);

            update_user_meta($user->ID, $keys['consumer_key'], $session_token);
        }

        return new WP_REST_Response([
            'id' => $user->ID,
            'consumer_key' => $keys['consumer_key'],
            'consumer_secret' => $keys['consumer_secret'],
            'session_token' => $session_token
        ]);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function facebook_login(WP_REST_Request $request)
    {
        if (!$request->has_param('token')) return new WP_REST_Response(['message' => 'Bad request'], 400);

        $token = $request->get_param('token');

        $url = 'https://graph.facebook.com/me?fields=id,first_name,last_name,email,picture&access_token=' . $token;

        $data = wp_remote_get($url);

        if (is_wp_error($data)) return new WP_REST_Response(['message' => 'Failed to retrieve user data from facebook'], 500);

        $response = json_decode(wp_remote_retrieve_body($data), true);

        if (array_key_exists('error', $response)) return new WP_REST_Response(['message' => 'Invalid facebook authentication token'], 500);

        $email = $response['email'];

        $user = get_user_by('email', $email);

        if (!$user) {
            $created = wc_create_new_customer($email, $email, wp_generate_password());

            if (is_wp_error($created)) return new WP_REST_Response(['message' => 'Failed to register facebook user'], 500);

            $user = get_user_by('email', $email);
        }

        $keys = $this->get_keys_of_customer($user->ID);

        $session_token = '';

        if ($this->is_loggedin_active()) {
            $manager = WP_Session_Tokens::get_instance($user->ID);

            $expiration = time() + apply_filters('auth_cookie_expiration', 14 * DAY_IN_SECONDS, $user->ID, true);

            $session_token = $manager->create($expiration);

            update_user_meta($user->ID, $keys['consumer_key'], $session_token);
        }

        return new WP_REST_Response([
            'id' => $user->ID,
            'consumer_key' => $keys['consumer_key'],
            'consumer_secret' => $keys['consumer_secret'],
            'session_token' => $session_token
        ]);
    }
}

new AuthController();