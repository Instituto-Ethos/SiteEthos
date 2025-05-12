<?php

namespace hacklabr;

function enqueue_event_registration_assets() {
    if ( is_singular( 'tribe_events' ) ) {
        wp_enqueue_script( 'form-scripts', get_stylesheet_directory_uri() . '/dist/blocks/form/index.js' );
    }
}
add_action( 'wp_enqueue_scripts', 'hacklabr\\enqueue_event_registration_assets' );

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
    if ( is_singular( 'tribe_events' ) ) {
        $fields = get_event_registration_fields();

        register_form( 'event-registration', __( 'Event registration', 'hacklabr' ), [
            'fields' => $fields,
            'get_params' => 'hacklabr\\get_event_registration_params',
            'submit_label' => _x( 'Register', 'event', 'hacklabr' ),
        ] );
    }

}
add_action( 'wp_head', 'hacklabr\\register_event_registration_form' );

function render_event_registration_form( array $attrs ) {
    return render_form_callback( [ 'formId' => 'event-registration' ] );
}
