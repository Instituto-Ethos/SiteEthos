<?php

namespace hacklabr;

function get_my_data_fields () {
    $fields = get_edit_contact_fields();

    return $fields;
}

function get_my_data_params ($form) {
    $user_id = 0;
    if ( current_user_can( 'edit_others_associates' ) ) {
        $organization_id = isset( $_GET['organization'] ) ? intval( $_GET['organization'] ) : 0;

        if ( $organization_id && get_post_type( $organization_id ) === 'organizacao' ) {
            $user_id = get_post_field( 'post_author', $organization_id );
        }
    } else {
        $user_id = get_current_user_id();
    }

    $params = sanitize_form_params();

    // Workaround to email validation in `get_edit_contact_fields`
    $params['_user_id'] = $user_id;

    if (!empty($user_id)) {
        $meta = get_user_meta($user_id);

        foreach ($form['fields'] as $key => $field) {
            if (empty($params[$key]) && !empty($meta[$key])) {
                $params[$key] = $meta[$key][0];
            }
        }
    }

    return $params;
}

function register_my_data_form () {
    $fields = get_my_data_fields();

    register_form('edit-my-data', __('My data', 'hacklabr'), [
        'fields' => $fields,
        'get_params' => 'hacklabr\\get_my_data_params',
        'submit_label' => __('Save changes', 'hacklabr'),
    ]);
}
add_action('init', 'hacklabr\\register_my_data_form');

function validate_my_data_form ($form_id, $form, $params) {
    $user_id = ! empty( $params['_user_id'] ) ? $params['_user_id'] : get_current_user_id();

    if ($form_id === 'edit-my-data' && !empty($user_id)) {
        $validation = validate_form($form['fields'], $params);

        if ($validation !== true) {
            return;
        }

        $user_meta = $params;
        unset($user_meta['_hacklabr_form']);
        unset($user_meta['_user_id']);

        wp_update_user([
            'ID' => $user_id,
            'display_name' => $params['nome_completo'],
            'user_email' => $params['email'],
            'meta_input' => $user_meta,
        ]);

        \ethos\crm\update_contact($user_id);
    }
}
add_action('hacklabr\\form_action', 'hacklabr\\validate_my_data_form', 10, 3);
