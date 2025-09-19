<?php

namespace hacklabr;

function get_request_occurrence_fields () {
    $privacy_policy_url =  get_privacy_policy_url();

    $fields = [
        'titulo' => [
            'type' => 'text',
            'class' => '-colspan-12',
            'label' => __('Title', 'hacklabr'),
            'placeholder' => __('Request title', 'hacklabr'),
            'required' => true,
        ],
        'descricao' => [
            'type' => 'textarea',
            'class' => '-colspan-12',
            'label' => __('Describe your requirements', 'hacklabr'),
            'placeholder' => __("Describe briefly your demand", 'hacklabr'),
            'required' => true,
        ],
        'politica_privacidade' => [
            'type' => 'checkbox',
            'class' => '-colspan-12',
            'label' => sprintf(__('I have read and agreed with the <a href="%s" target="_blank">Privacy Policy</a>', 'hacklabr'), $privacy_policy_url),
            'required' => true,
        ],
    ];

    return $fields;
}

function register_request_occurrence_form () {
    $fields = get_request_occurrence_fields();

    register_form('request-occurrence', __('Requests', 'hacklabr'), [
        'fields' => $fields,
    ]);
}
add_action('init', 'hacklabr\\register_request_occurrence_form');

function validate_request_occurrence_form ($form_id, $form, $params) {
    if ($form_id === 'request-occurrence') {
        $validation = validate_form($form['fields'], $params);

        if ($validation !== true) {
            return;
        }

        $current_user = get_current_user_id();

        /**
         * Checks if the current user has permission to edit other associates.
         * If so, retrieves the organization ID from the GET request and validates its post type.
         * If the organization is valid, fetches the user ID of the organization author.
         */
        if ( current_user_can( 'edit_others_associates' ) ) {
            $organization_id = isset( $_GET['organization'] ) ? intval( $_GET['organization'] ) : 0;

            if ( $organization_id && get_post_type( $organization_id ) === 'organizacao' ) {
                $current_user = get_post_field( 'post_author', $organization_id );
            }
        }

        $account_id = get_user_meta($current_user, '_ethos_crm_account_id', true);
        $contact_id = get_user_meta($current_user, '_ethos_crm_contact_id', true);

        $attributes = [
            'caseorigincode'   => 3 /* website */,
         // '_contactid_value' => create_crm_reference('contact', $contact_id),
            'customerid'       => create_crm_reference('account', $account_id),
            'description'      => $params['descricao'],
            'title'            => $params['titulo'],
        ];

        try {
            $incident_id = create_crm_entity('incident', $attributes);
            do_action('logger', 'Created request (incident) with ID = ' . $incident_id);

            $success_page = get_page_by_path('solicitacao-enviada') ?: get_page_by_path('boas-vindas');
            wp_safe_redirect(get_permalink($success_page));
            exit;
        } catch (\Exception $err) {
            do_action('logger', $err->getMessage());
        }
    }
}
add_action('hacklabr\\form_action', 'hacklabr\\validate_request_occurrence_form', 10, 3);
