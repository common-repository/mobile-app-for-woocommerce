<?php

namespace MobileAppForWooCommerce\Includes;

class ForceLogin
{
    function __construct()
    {
        add_filter('v_forcelogin_bypass', [$this, 'v_forcelogin_bypass'], 90, 2);

        add_filter('rest_authentication_errors', [$this,'v_forcelogin_rest_access'], 100,1);

    }

    public function v_forcelogin_bypass($allowed, $url)
    {
        if (strpos($url, MAFW_CLIENT_ROUTE) !== false)
            return true;

        return $allowed;
    }

    public function v_forcelogin_rest_access($result)
    {
        if (strpos($_SERVER['REQUEST_URI'] ?? '', MAFW_CLIENT_ROUTE) !== false)
            return null;

        return $result;
    }
}

new ForceLogin();