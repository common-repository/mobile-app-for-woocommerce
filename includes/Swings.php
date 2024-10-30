<?php

namespace MobileAppForWooCommerce\Includes;

class Swings
{
    const usersOptionKey = 'shopapper_swings_notification_user';

    const swingUserMetaKey = 'wps_wpr_points';

    public function __construct()
    {
        add_action('update_user_meta', [$this, 'update_user_meta'], 10, 4);
    }

    /**
     * @param $meta_id
     * @param $object_id
     * @param $meta_key
     * @param $meta_value
     * @return void
     */
    public function update_user_meta($meta_id, $object_id, $meta_key, $meta_value)
    {
        if ($meta_key !== self::swingUserMetaKey)
            return;

        $users = get_option(self::usersOptionKey, []);

        if (in_array($object_id, $users))
            return;

        $users[] = $object_id;

        update_option(self::usersOptionKey, $users);
    }
}

new Swings();