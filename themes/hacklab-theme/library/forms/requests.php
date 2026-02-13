<?php

namespace hacklabr;

function get_request_occurrence_fields () {
    $privacy_policy_url =  get_privacy_policy_url();

    $subject_options = [
        '969830000' => _x('Association', 'incident', 'hacklabr'),
        '969830001' => _x('Denunciations', 'incident', 'hacklabr'),
        '969830002' => _x('Questions about content', 'incident', 'hacklabr'),
        '969830003' => _x('Compliments', 'incident', 'hacklabr'),
        '969830004' => _x('Press', 'incident', 'hacklabr'),
        '969830005' => _x('Ethos Indicators', 'incident', 'hacklabr'),
        '969830006' => _x('Registrations', 'incident', 'hacklabr'),
        '969830007' => _x('LGPD', 'incident', 'hacklabr'),
        '969830008' => _x('Pacts and Compromises', 'incident', 'hacklabr'),
        '969830009' => _x('Complaints', 'incident', 'hacklabr'),
        '969830010' => _x('Suggestions', 'incident', 'hacklabr'),
        '490750001' => _x('Ethos Services Offerings', 'incident', 'hacklabr'),
        '490750002' => _x('Work with Us', 'incident', 'hacklabr'),
        '969830011' => _x('Other', 'incident', 'hacklabr'),
        '490750000' => _x('Joint Initiatives', 'incident', 'hacklabr'),
    ];

    $fields = [
        'assunto' => [
            'type' => 'select',
            'class' => '-colspan-12',
            'label' =>__('Subject', 'hacklabr'),
            'options' => $subject_options,
            'required' => true,
            'validate' => function ($value, $context) use ($subject_options) {
                if (!array_key_exists($value, $subject_options)) {
                    return __('Invalid subject', 'hacklabr');
                }
                return true;
            },
        ],
        'financeiro' => [
            'type' => 'hidden',
            'class' => '-hidden',
            'required' => false,
        ],
        'relacionamento' => [
            'type' => 'hidden',
            'class' => '-hidden',
            'required' => false,
        ],
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

function get_request_occurrence_params () {
    $params = sanitize_form_params();

    if (empty($params['financeiro']) & !empty($_GET['financeiro'])) {
        $params['assunto'] = '969830011';
        $params['financeiro'] = filter_input(INPUT_GET, 'financeiro');
    }

    if (empty($params['relacionamento']) & !empty($_GET['relacionamento'])) {
        $params['assunto'] = '969830000';
        $params['relacionamento'] = filter_input(INPUT_GET, 'relacionamento');
    }

    if (empty($params['assunto']) & !empty($_GET['assunto'])) {
        $params['assunto'] = filter_input(INPUT_GET, 'assunto');
    }

    return $params;
}

function register_request_occurrence_form () {
    $fields = get_request_occurrence_fields();

    register_form('request-occurrence', __('Requests', 'hacklabr'), [
        'fields' => $fields,
        'get_params' => 'hacklabr\\get_request_occurrence_params',
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
            'caseorigincode'             => 3, // Site
            'contactid'                  => create_crm_reference('contact', $contact_id),
            'customerid'                 => create_crm_reference('account', $account_id),
            'description'                => $params['descricao'],
            'fut_pl_tipo_de_atendimento' => $params['assunto'],
            'title'                      => $params['titulo'],
        ];

        if ($params['financeiro']) {
            $attributes['fut_pl_area'] = '969830004'; // Financeiro
        }

        if ($params['relacionamento']) {
            $attributes['fut_pl_area'] = '969830005'; // Relacionamento e Captação
        }

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
