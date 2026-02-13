<?php

namespace hacklabr;

function get_request_crm_area (string $subject): string|null {
    switch ($subject) {
        case 'alteracao-plano':
        case 'declaracao-associacao':
            return '969830005'; // Relacionamento e Captação
        case 'eventos':
            return '969830002'; // Eventos
        case 'financeiro':
            return '969830004'; // Financeiro
        default:
            return null;
    }
}

function get_request_crm_project (string $subject): string|null {
    switch ($subject) {
        case 'conferencia':
            return '969830008'; // Conferência Ethos
        case 'cursos':
            return '969830014'; // Cursos
        case 'indicadores':
            return '969830001'; // Indicadores Ethos
        case 'palestras':
            return '969830013'; // Palestras
        default:
            return null;
    }
}

function get_request_crm_type (string $subject): string|null {
    switch ($subject) {
        case 'alteracao-plano':
        case 'declaracao-associacao':
            return '969830000'; // Associação
        case 'financeiro':
            return '969830011'; // Outros
        case 'indicadores':
            return '969830005'; // Indicadores Ethos - Geral
        case 'pactos':
            return '969830008'; // Pactos e Compromissos
        default:
            return null;
    }
}

function get_request_occurrence_fields () {
    $privacy_policy_url =  get_privacy_policy_url();

    $subject_options = [
        'alteracao-plano' => _x('Plan change', 'subject', 'hacklabr'),
        'declaracao-associacao' => _x('Statement of association', 'subject', 'hacklabr'),
        'financeiro' => _x('Financial', 'subject', 'hacklabr'),
        /*
        'fale-conosco' => _x('Talk to us', 'subject', 'hacklabr'),
        'indicadores' => _x('Ethos Indicators', 'subject', 'hacklabr'),
        'cursos' => _x('Courses', 'subject', 'hacklabr'),
        'eventos' => _x('Events', 'subject', 'hacklabr'),
        'palestras' => _x('Lectures', 'subject', 'hacklabr'),
        'pactos' => _x('Pacts', 'subject', 'hacklabr'),
        'conferencia' => _x('Conference', 'subject', 'hacklabr'),
        'outros' => _x('Other', 'subject', 'hacklabr'),
        */
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
        $params['assunto'] = 'financeiro';
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
            'caseorigincode' => 3, // Site
            'contactid'      => create_crm_reference('contact', $contact_id),
            'customerid'     => create_crm_reference('account', $account_id),
            'description'    => $params['descricao'],
            'title'          => $params['titulo'],
        ];

        $subject = $params['assunto'];

        $crm_area = get_request_crm_area($subject);
        if (!empty($crm_area)) {
            $attributes['fut_pl_area'] = $crm_area;
        }

        $crm_project = get_request_crm_project($subject);
        if (!empty($crm_project)) {
            $attributes['fut_pl_projeto'] = $crm_project;
        }

        $crm_type = get_request_crm_type($subject);
        if (!empty($crm_type)) {
            $attributes['fut_pl_tipo_de_atendimento'] = $crm_type;
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
