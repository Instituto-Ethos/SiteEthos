<?php

namespace hacklabr;

function get_event_registration_fields() {
    $a11y_options = [
    ];

    $hierarchy_options = [
        '969830000' => _x( 'Analyst', 'hierarchical level', 'hacklabr' ),
        '969830001' => _x( 'Advisor', 'hierarchical level', 'hacklabr' ),
        '969830002' => _x( 'Coordinator', 'hierarchical level', 'hacklabr' ),
        '969830003' => _x( 'Director', 'hierarchical level', 'hacklabr' ),
        '969830004' => _x( 'Manager', 'hierarchical level', 'hacklabr' ),
        '969830005' => _x( 'Supervisor', 'hierarchical level', 'hacklabr' ),
        '969830006' => _x( 'Other', 'hierarchical level', 'hacklabr' ),
    ];

    return [
        'nome_completo' => [
            'type' => 'text',
            'class' => '-colspan-12',
            'label' => __('Full name', 'hacklabr'),
            'placeholder' => __('Enter the full name', 'hacklabr'),
            'required' => true,
        ],
        'cpf' => [
            'type' => 'masked',
            'class' => '-colspan-6',
            'label' => __('CPF number', 'hacklabr'),
            'mask' => '000.000.000-00',
            'placeholder' => __('Enter the CPF number', 'hacklabr'),
            'required' => true,
            'validate' => function ($value) {
                if (!is_numeric($value) || strlen($value) !== 11) {
                    return __('Invalid CPF number', 'hacklabr');
                }
                return true;
            },
        ],
        'telefone' => [
            'type' => 'masked',
            'class' => '-colspan-6',
            'label' => __('Phone number', 'hacklabr'),
            'mask' => '(00) 0000-0000|(00) 00000-0000',
            'placeholder' => __('Enter the phone number', 'hacklabr'),
            'required' => true,
            'validate' => function ($value) {
                if (!is_numeric($value) || strlen($value) < 10 || strlen($value) > 11) {
                    return __('Invalid phone number', 'hacklabr');
                }
                return true;
            },
        ],
        'email' => [
            'type' => 'email',
            'class' => '-colspan-12',
            'label' => __('Email', 'hacklabr'),
            'placeholder' => __('Enter the email', 'hacklabr'),
            'required' => true,
            'validate' => function ($value) {
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return __('Invalid email', 'hacklabr');
                }
                return true;
            },
        ],
        'cnpj' => [
            'type' => 'masked',
            'class' => '-colspan-12',
            'label' => __('CNPJ number', 'hacklabr'),
            'mask' => '00.000.000/0000-00',
            'placeholder' => __("Enter the company' CNPJ number", 'hacklabr'),
            'required' => true,
            'validate' => function ($value) {
                if (!is_numeric($value) || strlen($value) !== 14) {
                    return __('Invalid CNPJ number', 'hacklabr');
                }
                return true;
            },
        ],
        'nome_empresa' => [
            'type' => 'text',
            'class' => '-colspan-12',
            'label' => __('Business name', 'hacklabr'),
            'placeholder' => __('Enter the business name', 'hacklabr'),
            'required' => true,
        ],
        'cargo' => [
            'type' => 'text',
            'class' => '-colspan-12',
            'label' => __('Role', 'hacklabr'),
            'placeholder' => __('Enter the role in company', 'hacklabr'),
            'required' => true,
        ],
        'nivel_hierarquico' => [
            'type' => 'select',
            'class' => '-colspan-12',
            'label' =>__('Hierarchical level', 'hacklabr'),
            'options' => $hierarchy_options,
            'required' => true,
            'validate' => function ($value) use ($hierarchy_options) {
                if (!array_key_exists($value, $hierarchy_options)) {
                    return __('Invalid hierarchical level', 'hacklabr');
                }
                return true;
            },
        ],
        'area' => [
            'type' => 'text',
            'class' => '-colspan-12',
            'label' => _x('Area', 'company', 'hacklabr'),
            'placeholder' => __('Enter the area in company', 'hacklabr'),
            'required' => true,
        ],
        'acessibilidade' => [
            'type' => 'select',
            'class' => '-colspan-12',
            'label' =>__('Accessibility', 'hacklabr'),
            'options' => $a11y_options,
            'required' => false,
            'validate' => function ($value) use ($a11y_options) {
                if (!array_key_exists($value, $a11y_options)) {
                    return __('Invalid accessibility option', 'hacklabr');
                }
                return true;
            },
        ],
    ];
}

function get_event_registration_params() {
    $params = sanitize_form_params();

    $user_id = get_current_user_id();

    if ( ! empty( $user_id ) ) {
        $fields = get_event_registration_fields();
        $meta = get_user_meta( $user_id );

        foreach ( $fields as $key => $field ) {
            if ( empty( $params[ $key ] ) && ! empty( $meta[ $key ] ) ) {
                $params[ $key ] = $meta[ $key ][0];
            }
        }
    }

    return $params;
}

function register_event_registration_form() {
    $fields = get_event_registration_fields();

    register_form( 'event-registration', __( 'Event registration', 'hacklabr' ), [
        'fields' => $fields,
        'get_params' => 'hacklabr\\get_event_registration_params',
        'submit_label' => _x( 'Register', 'event', 'hacklabr' ),
    ] );

    add_shortcode( 'event-registration', 'hacklabr\\render_event_registration_form' );
}
add_action( 'init', 'hacklabr\\register_event_registration_form' );

function register_for_event( array $params ) {
    $contact_id = $params['contact_id'];
    $project_id = $params['project_id'];

    $attibutes = [
        // 'createdbyyominame' => '',
        // 'createdonbehalfbyyominame' => '',
        'fut_lk_contato' => create_crm_reference( 'contato', $contact_id ),
        // 'fut_lk_empresa' => create_crm_reference( 'account', '' ),
        // 'fut_lk_empresa_associada' => create_crm_reference( 'account', '' ),
        // 'fut_lk_fatura_pf' => create_crm_reference( 'contato', $contact_id ),
        // 'fut_lk_fatura_pj' => create_crm_reference( 'account', $contact_id ),
        // 'fut_parceria_participante_id' => create_crm_reference( 'fut_parceria', '' ),
        // 'fut_pl_cortesia' => '',
        'fut_lk_projeto' => create_crm_reference( 'fut_projeto', $project_id ),
        // 'fut_participanteid' => '',
        // 'modifiedbyyominame' => '',
        // 'modifiedonbehalfbyyominame' => '',
        // 'organizationid' => create_crm_reference( 'organization', '' ),
        // 'organizationidname' => '',
        // 'statecode' => '',
        // 'transactioncurrencyid' => create_crm_reference( 'transactioncurrency', '' ),
    ];

    try {
        $entity_id = create_crm_entity( 'fut_participante', $attibutes );

        return [
            'status'    => 'success',
            'message'   => 'Entidade criada com sucesso no CRM.',
            'entity_id' => $entity_id,
        ];
    } catch ( \Exception $e ) {
        return [
            'status'  => 'error',
            'message' => "Erro ao inscrever-se no event {$project_id}: " . $e->getMessage()
        ];
    }
}

function render_event_registration_form( array $attrs ) {
    return render_form_callback( [ 'formId' => 'event-registration' ] );
}
