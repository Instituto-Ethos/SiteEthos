<?php

namespace hacklabr;

function get_edit_contact_fields() {
    $fields = get_registration_step5_fields();

    $fields['email']['validate'] = function ($value, $context) {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return __('Invalid email', 'hacklabr');
        } else {
            $maybe_user = get_user_by('email', $value);
            if (!empty($maybe_user)) {
                $user_id = $context['_user_id'];
                if (empty($user_id) || $maybe_user->ID != $user_id) {
                    return __('Email is already in use', 'hacklabr');
                }
            }
        }
        return true;
    };

    return $fields;
}

function get_edit_organization_fields() {
    $fields = get_registration_step1_fields();

    unset($fields['termos_de_uso']);
    unset($fields['codigo_de_conduta']);
    unset($fields['logomarca']);

    $editable_fields = [
        'nome_fantasia', 'segmento', 'website', 'end_logradouro',
        'end_numero', 'end_complemento', 'end_bairro', 'end_cidade',
        'end_estado', 'end_cep'
    ];

    $non_editable_fields = [
        'cnpj', 'razao_social', 'cnae', 'faturamento_anual',
        'inscricao_municipal', 'inscricao_estadual', 'porte', 'num_funcionarios'
    ];

    foreach ($fields as $key => $field) {
        if (in_array($key, $non_editable_fields)) {
            $fields[$key]['disabled'] = true;
            $fields[$key]['required'] = false;
        } elseif (in_array($key, $editable_fields)) {
            $fields[$key]['disabled'] = false;
        }
    }

    return $fields;
}

function get_edit_organization_finance_fields() {
    $fields = get_registration_step4_fields();

    $current_user = get_current_user_id();

    if (class_exists('PMProGroupAcct_Group') && !empty($current_user)) {

        $group_id = (int) get_user_meta($current_user, '_pmpro_group', true);

        $membership_price = calculate_membership_price($group_id);

        $fields['pagto_sugerido']['default'] = $membership_price;
        $fields['pagto_sugerido']['disabled'] = true;
    }

    foreach ($fields as $key => $field) {
        $fields[$key]['disabled'] = true;
    }

    return $fields;
}

function get_organization_params($form_id, $fields) {
    return function () use ($form_id, $fields) {
        $user_id = get_current_user_id();

        /**
         * Checks if the current user has permission to edit other associates.
         * If so, retrieves the organization ID from the GET request and validates its post type.
         * If the organization is valid, fetches the user ID of the organization author.
         */
        if ( current_user_can( 'edit_others_associates' ) ) {
            $organization_id = isset( $_GET['organization'] ) ? intval( $_GET['organization'] ) : 0;

            if ( $organization_id && get_post_type( $organization_id ) === 'organizacao' ) {
                $user_id = get_post_field( 'post_author', $organization_id );
            }
        }

        $organization = get_organization_by_user($user_id);

        if (!empty($organization)) {
            add_action('hacklabr\\form_output', function ($form_html, $form) use ($form_id, $organization) {
                if ($form['id'] !== $form_id) {
                    return $form_html;
                }

                $form_lines = explode("\n", $form_html);
                array_splice($form_lines, 1, 0, [
                    '<input type="hidden" name="__organization_id" value="' . $organization->ID . '">',
                ]);
                return implode("\n", $form_lines);
            }, 10, 2);

            $meta = get_post_meta($organization->ID);
            $params = sanitize_form_params();

            foreach ($fields as $key => $field) {
                if (empty($params[$key]) && !empty($meta[$key])) {
                    $params[$key] = $meta[$key][0];
                }
            }

            return $params;
        }

        return [];
    };
}

function register_edit_organization_form() {
    $fields_contacts = get_edit_contact_fields();
    $fields_finance = get_edit_organization_finance_fields();
    $fields_organization = get_edit_organization_fields();

    register_form('edit-organization', __('Edit organization', 'hacklabr'), [
        'fields' => $fields_organization,
        'get_params' => get_organization_params('edit-organization', $fields_organization),
    ]);

    register_form('edit-organization-contacts', __('Edit contacts', 'hacklabr'), [
        'disabled' => true,
        'fields' => $fields_contacts,
        'submit_label' => __('Save'),
    ]);

    register_form('edit-organization-contacts__hidden', __('Edit contacts', 'hacklabr') . ' ' . __('(hidden)', 'hacklabr'), [
        'fields' => [],
        'hidden' => true,
    ]);

    register_form('edit-organization-finances', __('Edit organization finances', 'hacklabr'), [
        'fields' => $fields_finance,
        'submit_label' => false,
        'get_params' => get_organization_params('edit-organization-finances', $fields_finance),
    ]);
}
add_action('init', 'hacklabr\\register_edit_organization_form');

function get_account_admin_contacts($account_id) {
    $account = get_crm_entity_by_id('account', $account_id);

    $attributes = $account->Attributes;

    $contact_ids = [
        $attributes['primarycontactid']?->Id ?? null,
        $attributes['fut_lk_contato_alternativo']?->Id ?? null,
        $attributes['fut_lk_contato_alternativo2']?->Id ?? null,
    ];

    return array_filter($contact_ids);
}

function contacts_add_admin($user_id) {
    $group_id = (int) get_user_meta($user_id, '_pmpro_group', true);

    $ethos_admins = get_users([
        'meta_query' => [
            [ 'key' => '_pmpro_group', 'value' => $group_id ],
            [ 'key' => '_ethos_admin', 'value' => '1' ],
        ],
    ]);

    if (count($ethos_admins) < 3) {
        update_user_meta($user_id, '_ethos_admin', '1');

        notify_admin_addition($user_id);
    }
}

function contacts_add_approver($user_id) {
    $group_id = (int) get_user_meta($user_id, '_pmpro_group', true);

    $current_approvers = get_users([
        'meta_query' => [
            [ 'key' => '_pmpro_group', 'value' => $group_id ],
            [ 'key' => '_ethos_approver', 'value' => 1 ],
        ],
    ]);
    foreach ($current_approvers as $approver) {
        delete_user_meta($approver->ID, '_ethos_approver');
    }

    update_user_meta($user_id, '_ethos_approver', '1');

    notify_approver_change($user_id);
}

function contacts_delete_user($user_id) {
    // Required for using `wp_delete_user` function
    require_once(ABSPATH . 'wp-admin/includes/user.php');

    $is_ethos_admin = !empty(get_user_meta($user_id, '_ethos_admin', true));
    $is_ethos_approver = !empty(get_user_meta($user_id, '_ethos_approver', true));
    $is_current_user = get_current_user_id() == $user_id;
    if ($is_ethos_admin || $is_ethos_approver || $is_current_user) {
        return;
    }

    notify_user_deactivation($user_id);

    wp_delete_user($user_id, null);
}

function contacts_remove_admin($user_id) {
    $group_id = (int) get_user_meta($user_id, '_pmpro_group', true);

    $ethos_admins = get_users([
        'meta_query' => [
            [ 'key' => '_pmpro_group', 'value' => $group_id ],
            [ 'key' => '_ethos_admin', 'value' => '1' ],
        ],
    ]);

    if (count($ethos_admins) > 1) {
        delete_user_meta($user_id, '_ethos_admin', '1');

        notify_admin_removal($user_id);
    }
}

function contacts_remove_approver($user_id) {
    delete_user_meta($user_id, '_ethos_approver', '1');
}

function notify_admin_addition($user_id) {
    $account_id = get_user_meta($user_id, '_ethos_crm_account_id', true);
    $contact_id = get_user_meta($user_id, '_ethos_crm_contact_id', true);

    $contact_ids = get_account_admin_contacts($account_id);

    $contact_ids[] = $contact_id;
    $contact_ids = array_unique($contact_ids);

    notify_admins_change($account_id, $contact_ids);
}

function notify_admin_removal($user_id) {
    $account_id = get_user_meta($user_id, '_ethos_crm_account_id', true);
    $contact_id = get_user_meta($user_id, '_ethos_crm_contact_id', true);

    $contact_ids = get_account_admin_contacts($account_id);

    if ($contact_ids[0] == $contact_id) {
        $group_id = (int) get_user_meta($user_id, '_pmpro_group', true);

        $next_group_parent = get_single_user([
            'meta_query' => [
                [ 'key' => '_ethos_crm_contact_id', 'value' => $contact_ids[1] ],
            ]
        ]);

        if (!empty($next_group_parent)) {
            update_group_parent($group_id, $next_group_parent->ID);
        }
    }

    $contact_ids = array_filter($contact_ids, fn($id) => $id != $contact_id);

    notify_admins_change($account_id, $contact_ids);
}

function notify_admins_change($account_id, $contact_ids) {
    $primary_contact = $contact_ids[0];
    $secondary_contact_1 = $contact_ids[1] ?? null;
    $secondary_contact_2 = $contact_ids[2] ?? null;

    update_crm_entity('account', $account_id, [
        'primarycontactid' => create_crm_reference('contact', $primary_contact),
        'fut_lk_contato_alternativo' => $secondary_contact_1 ? create_crm_reference('contact', $secondary_contact_1) : null,
        'fut_lk_contato_alternativo2' => $secondary_contact_2 ? create_crm_reference('contact', $secondary_contact_2) : null,
    ]);
}

function notify_approver_change($user_id) {
    $account_id = get_user_meta($user_id, '_ethos_crm_account_id', true);
    $contact_id = get_user_meta($user_id, '_ethos_crm_contact_id', true);

    update_crm_entity('account', $account_id, [
        'i4d_aprovador_cortesia' => create_crm_reference('contact', $contact_id),
    ]);
}

function notify_user_deactivation($user_id) {
    $contact_id = get_user_meta($user_id, '_ethos_crm_contact_id', true);

    update_crm_entity('contact', $contact_id, [
        'statecode' => \ethos\crm\ContactStatus::Inactive->value,
    ]);
}

function validate_edit_organization_form($form_id, $form, $params) {
    $user_id = get_current_user_id();

    /**
     * Checks if the current user has permission to edit other associates.
     * If so, retrieves the organization ID from the GET request and validates its post type.
     * If the organization is valid, fetches the user ID of the organization author.
     */
    if ( current_user_can( 'edit_others_associates' ) ) {
        $organization_id = isset( $_GET['organization'] ) ? intval( $_GET['organization'] ) : 0;

        if ( $organization_id && get_post_type( $organization_id ) === 'organizacao' ) {
            $user_id = get_post_field( 'post_author', $organization_id );
        }
    }

    $is_ethos_admin = get_user_meta( $user_id, '_ethos_admin', true );

    if (empty($is_ethos_admin)) {
        return;
    }

    if ($form_id === 'edit-organization') {
        $validation = validate_form($form['fields'], $params);

        if ($validation !== true) {
            return;
        }

        $post_id = $params['_organization_id'];
        $post_meta = $params;

        unset($post_meta['_hacklabr_form']);
        unset($post_meta['_organization_id']);

        wp_update_post([
            'ID' => $post_id,
            'post_title' => $post_meta['nome_fantasia'],
            'meta_input' => $post_meta,
        ]);

        \ethos\crm\update_organization((int) $post_id);
    }

    if ($form_id === 'edit-organization-finances') {
        $validation = validate_form($form['fields'], $params);

        if ($validation !== true) {
            return;
        }

        $post_id = $params['_organization_id'];
        $post_meta = $params;

        unset($post_meta['_hacklabr_form']);
        unset($post_meta['_organization_id']);

        foreach ($params as $meta_key => $meta_value) {
            update_post_meta($post_id, $meta_key, $meta_value);
        }
    }

    if ($form_id === 'edit-organization-contacts') {
        $validation = validate_form($form['fields'], $params);

        if ($validation !== true) {
            return;
        }

        $user_id = (int) $params['_user_id'];
        $user_meta = $params;

        unset($user_meta['_action']);
        unset($user_meta['_hacklabr_form']);
        unset($user_meta['_user_id']);

        if (empty($user_id)) {
            $group_id = (int) get_user_meta($current_user, '_pmpro_group', true);
            $organization = get_organization_by_user();

            $user_meta = array_merge($user_meta, [
                '_pmpro_group' => $group_id,
                '_pmpro_role' => 'secondary',
            ]);

            $password = wp_generate_password(16);

            $user_id = wp_insert_user([
                'display_name' => $user_meta['nome_completo'],
                'user_email' => $user_meta['email'],
                'user_login' => sanitize_title($user_meta['nome_completo']),
                'user_pass' => $password,
                'role' => 'subscriber',
                'meta_input' => $user_meta,
            ]);

            add_user_to_pmpro_group($user_id, $group_id);

            \ethos\crm\create_contact($user_id, $organization->ID);
        } else {
            wp_update_user([
                'ID' => $user_id,
                'display_name' => $user_meta['nome_completo'],
                'user_email' => $user_meta['email'],
                'meta_input' => $user_meta,
            ]);

            \ethos\crm\update_contact($user_id);
        }

        $current_url = untrailingslashit($_SERVER['REQUEST_URI']);
        wp_safe_redirect(add_query_arg(['tab' => 2], $current_url));
        exit;
    }

    if ($form_id === 'edit-organization-contacts__hidden') {
        $action = $params['_action'];
        $user_id = (int) $params['_user_id'];

        if (empty($action) || empty($user_id)) {
            return;
        }

        if ($action === 'addAdmin') {
            contacts_add_admin($user_id);
        } elseif ($action === 'addApprover') {
            contacts_add_approver($user_id);
        } elseif ($action === 'deleteUser') {
            contacts_delete_user($user_id);
        } elseif ($action === 'removeAdmin') {
            contacts_remove_admin($user_id);
        } elseif ($action === 'removeApprover') {
            contacts_remove_approver($user_id);
        }

        $current_url = untrailingslashit($_SERVER['REQUEST_URI']);
        wp_safe_redirect(add_query_arg(['tab' => 2], $current_url));
        exit;
    }
}
add_action('hacklabr\\form_action', 'hacklabr\\validate_edit_organization_form', 10, 3);
