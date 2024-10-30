<?php

class Updater
{
    public static array $actions = [
        '0.4.4' => ['clear_shopapper_wc_notices']
    ];

    function __construct()
    {
        add_action('upgrader_process_complete', [$this,'plugins_update_completed'], 10, 2);
    }

    /**
     * @param $upgrader_object
     * @param $options
     * @return void
     */
    public function plugins_update_completed($upgrader_object, $options)
    {
        // If an update has taken place and the updated type is plugins and the plugins element exists
        if ($options['action'] == 'update' and $options['type'] == 'plugin' and isset($options['plugins'])) {
            foreach ($options['plugins'] as $plugin) {
                if ($plugin !== MAFW_BASENAME)
                    continue;

                foreach (self::$actions as $version => $callbacks) {
                    if ($version === MAFW_VERSION) {
                        foreach ($callbacks as $callback)
                            self::$callback();
                    }
                }
            }
        }
    }

    /**
     * @return void
     */
    public static function clear_shopapper_wc_notices()
    {
        global $wpdb;

        $wpdb->query($wpdb->prepare("delete FROM $wpdb->options WHERE option_name like 'shopapper_wc_notices_%'"));
    }
}

new Updater();