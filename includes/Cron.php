<?php

namespace MobileAppForWooCommerce\Includes;

use DateTime;
use Throwable;

class Cron
{

    public function __construct()
    {
        if (!wp_next_scheduled('shopapper_hourly')) {

            try {
                $datetime = new DateTime();
                $datetime->setTime((int)$datetime->format('H') + 1, 0);
                $timestamp = $datetime->getTimestamp();
            } catch (Throwable $e) {
                $timestamp = time();
            }

            wp_schedule_event($timestamp, 'hourly', 'shopapper_hourly');
        }

        add_action('shopapper_hourly', [$this, 'hourly']);
    }

    /**
     * @return void
     */
    public function hourly()
    {
        try {

            date_default_timezone_set('UTC');

            $token = get_option(Dashboard::$token_option_name, '');

            if (is_multisite()) {

                switch_to_blog(1);

                $token = get_option(Dashboard::$token_option_name, '');

                restore_current_blog();
            }

            $response = wp_remote_post(
                sprintf("%s/get-recurrence-auto-notifications/?%s", MAFW_API_URL, http_build_query([
                    'storeUrl' => get_site_url(),
                    'clientToken' => $token
                ])), [
                'headers' => [
                    'Content-type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'body' => json_encode([
                    'token' => $token,
                ])
            ]);

            if (is_wp_error($response)) {
                error_log($response->get_error_message());
                return;
            }

            $auto_notification = json_decode($response['body']);

            if (empty($auto_notification))
                return;

            $users = class_exists('MobileAppForWooCommerce\Includes\Swings') ?  get_option(Swings::usersOptionKey, []) : [];

            foreach ($auto_notification->items as $auto_notification_item) {

                if (!in_array($auto_notification_item->event, ['swings_notification', 'recurring']))
                    continue;

                if ($auto_notification_item->event === 'swings_notification' and empty($users))
                    continue;

                if ($auto_notification_item->recurrence === 'weekly' and date('l') !== $auto_notification_item->recurrenceDay or sprintf("%s:00", date('H')) !== $auto_notification_item->recurrenceTime)
                    continue;

                if ($auto_notification_item->event === 'swings_notification') {

                    foreach ($users as $userId) {

                        $userData = get_userdata($userId);

                        if (!$userData)
                            continue;

                        Helper::send_notification([
                            'appId' => $auto_notification->appId,
                            'pushNotificationId' => $auto_notification->pushNotificationId,
                            'title' => $auto_notification_item->title,
                            'body' => str_replace(['!!display_name!!', '!!first_name!!', '!!last_name!!', '!!total_points!!'], [$userData->display_name, $userData->first_name, $userData->last_name, get_user_meta($userId, Swings::swingUserMetaKey, true)], $auto_notification_item->body),
                            'customerId' => $userId,
                            'userRoles' => [],
                            'userIds' => [['id' => $userId, 'displayName' => $userData->display_name]],
                            'scheduleDatetime' => null,
                        ]);
                    }

                    update_option(Swings::usersOptionKey, []);

                } else {

                    Helper::send_notification([
                        'appId' => $auto_notification->appId,
                        'pushNotificationId' => $auto_notification->pushNotificationId,
                        'title' => $auto_notification_item->title,
                        'body' => $auto_notification_item->body,
                        'userRoles' => $auto_notification_item->userRoles,
                        'userIds' => $auto_notification_item->userIds,
                        'scheduleDatetime' => null,
                    ]);
                }

            }

        } catch (Throwable $e) {
        }
    }
}

new Cron();