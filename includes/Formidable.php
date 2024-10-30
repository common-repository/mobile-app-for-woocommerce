<?php

use MobileAppForWooCommerce\Includes\Helper;

class Formidable
{
    function __construct()
    {
        add_filter('frm_after_create_entry', [$this, 'frm_after_create_entry'], 90, 2);

    }

    public function frm_after_create_entry($entry_id, $form_id)
    {
        if (empty($_POST['item_meta']))
            return;

        $params = [];

        foreach ($_POST['item_meta'] as $field_id => $value) {

            $field = FrmField::getOne($field_id);

            if (!$field or $field->type === 'submit')
                continue;

            $params[$field->name] = $value;

        }

        if (!empty($params))
            Helper::send_auto_notification([
                'event' => 'formidable_form_submit',
                'formId' => $form_id,
                'params' => $params
            ]);
    }
}

new Formidable();