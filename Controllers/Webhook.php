<?php

namespace MobileAppForWooCommerce\Controllers;

use MobileAppForWooCommerce\Includes\Helper;
use Throwable;
use Exception;
use WP_REST_Request;
use WP_REST_Response;

class Webhook
{
    use Helper;

    public function __construct()
    {
        add_action('rest_api_init', [$this, 'init']);
    }


    public function init()
    {

        register_rest_route(MAFW_CLIENT_ROUTE, '/webhook/send-push-notification', [
            'methods' => 'POST',
            'callback' => [$this, 'webhook_send_push_notification'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */

    public function webhook_send_push_notification(WP_REST_Request $request): WP_REST_Response
    {
        try {
            $query_params = $request->get_query_params();

            $clientToken = $query_params['clientToken'];

            $email = $request->get_param('email');

            $user = get_user_by('email', $email);

            if (empty($user)) return new WP_REST_Response(['message' => 'User not found'], 400);

            $userIds = array_map(function ($user) {
                return ["id" => intval($user->ID), "displayName" => $user->display_name];
            }, [$user]);

            $data = [
                'title' => $request->get_param('title'),
                'body' => $request->get_param('body'),
                'appId' => $request->get_param('appId'),
                'pushNotificationId' => $request->get_param('pushNotificationId'),
                'userIds' => $userIds
            ];

            $url = 'https://shopapper.com/wp-json/shopapper/admin/v1/client/send-push-notification?clientToken=' . $clientToken;

            $args = [
                'headers' => [
                    'Content-type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'body' => json_encode($data)
            ];

            wp_remote_post($url, $args);

            return new WP_REST_Response(['message' => 'Success'], 200);
        } catch (Throwable $e) {
            return new WP_REST_Response(['message' => $e->getMessage()], 500);
        }
    }
}

new Webhook();