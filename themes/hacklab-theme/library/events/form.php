<?php

namespace hacklabr;

function is_paid_event (int $post_id) {
    /*
    $event_type = get_post_meta($post_id, '_ethos_crm:fut_lk_tipoparceria', true);
    if ($event_type) {
        $event_type = json_encode($event_type)['Name'];
        if ($event_type === 'Conferência') {
            return true;
        }
    }
    */

    $is_paid = !empty(get_post_meta($post_id, '_ethos_crm:fut_pago'));
    return $is_paid;
}

function get_event_registration_fields () {
    $post_id = get_the_ID();

    $a11y_options = [
    ];

    $hierarchy_options = [
        '969830000' => _x('Analyst', 'hierarchical level', 'hacklabr'),
        '969830001' => _x('Advisor', 'hierarchical level', 'hacklabr'),
        '969830002' => _x('Coordinator', 'hierarchical level', 'hacklabr'),
        '969830003' => _x('Director', 'hierarchical level', 'hacklabr'),
        '969830004' => _x('Manager', 'hierarchical level', 'hacklabr'),
        '969830005' => _x('Supervisor', 'hierarchical level', 'hacklabr'),
        '969830006' => _x('Other', 'hierarchical level', 'hacklabr'),
    ];

    $source_options = [
        '0' => __('Advertisement', 'hacklabr'),
		'1' => __('Marketing email', 'hacklabr'),
		'2' => __('Referral', 'hacklabr'),
		'3' => __('Conference', 'hacklabr'),
		'4' => __('Events', 'hacklabr'),
		'5' => __('Web search', 'hacklabr'),
		'6' => _x('Other', 'lead source', 'hacklabr'),
    ];

    $fields = [
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
            'required' => false,
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
        'confirma_email' => [
            'type' => 'email',
            'class' => '-colspan-12',
            'label' => __('Confirm email', 'hacklabr'),
            'placeholder' => __('Enter the email', 'hacklabr'),
            'required' => true,
            'validate' => function ($value, $context) {
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return __('Invalid email', 'hacklabr');
                } elseif ($value !== $context['email']) {
                    return __('Emails do not match', 'hacklabr');
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
        'nome_fantasia' => [
            'type' => 'text',
            'class' => '-colspan-12',
            'label' => __('Business name', 'hacklabr'),
            'placeholder' => __('Enter the business name', 'hacklabr'),
            'required' => false,
        ],
        'cargo' => [
            'type' => 'text',
            'class' => '-colspan-12',
            'label' => __('Role', 'hacklabr'),
            'placeholder' => __('Enter the role in company', 'hacklabr'),
            'required' => false,
        ],
        'nivel_hierarquico' => [
            'type' => 'select',
            'class' => '-colspan-12',
            'label' =>__('Hierarchical level', 'hacklabr'),
            'options' => $hierarchy_options,
            'required' => false,
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
            'required' => false,
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

    if (is_paid_event($post_id)) {
        $fields['origem_lead'] = [
            'type' => 'select',
            'class' => '-colspan-12',
            'label' =>__('How did you know about Ethos?', 'hacklabr'),
            'options' => $source_options,
            'required' => false,
            'validate' => function ($value) use ($source_options) {
                if (!array_key_exists($value, $source_options)) {
                    return __('Invalid option', 'hacklabr');
                }
                return true;
            },
        ];

        $fields['voucher'] = [
            'type' => 'text',
            'class' => '-colspan-12',
            'label' => __('Voucher', 'hacklabr'),
            'placeholder' => __('Enter the voucher code, if you have any', 'hacklabr'),
            'required' => false,
        ];
    }

    if (class_exists('WPCaptcha_Functions')) {
        $fields['g-recaptcha-response'] = [
            'type' => 'recaptcha',
            'class' => '-colspan-12',
            'required' => true,
            'required_text' => __('Prove you are not a robot', 'hacklabr'),
            'validate' => function ($value) {
                return validate_recaptcha_field();
            },
        ];
    }

    return $fields;
}

function get_event_registration_params () {
    $params = sanitize_form_params();

    $user_id = get_current_user_id();

    if (!empty($user_id)) {
        $fields = get_event_registration_fields();
        $user_meta = get_user_meta($user_id);

        foreach ($fields as $key => $field) {
            if (empty($params[$key]) && !empty($user_meta[$key])) {
                $params[$key] = $user_meta[$key][0];
            }
        }

        if ($organization = get_organization_by_user($user_id)) {
            $post_meta = get_post_meta($organization->ID);

            foreach ($fields as $key => $field) {
                if (empty($params[$key]) && !empty($post_meta[$key])) {
                    $params[$key] = $post_meta[$key][0];
                }
            }
        }

        if (empty($params['confirma_email']) && !empty($user_meta['email'])) {
            $params['confirma_email'] = $user_meta['email'][0];
        }

        if (empty($params['nivel_hierarquico']) && !empty($user_meta['_ethos_crm:fut_pl_nivelhierarquico'])) {
            $params['nivel_hierarquico'] = $user_meta['_ethos_crm:fut_pl_nivelhierarquico'][0];
        }
    }

    return $params;
}

function register_event_registration_form () {
    if (is_singular('tribe_events')) {
        $fields = get_event_registration_fields();

        register_form('event-registration', __('Event registration', 'hacklabr'), [
            'fields' => $fields,
            'get_params' => 'hacklabr\\get_event_registration_params',
            'submit_label' => _x('Register', 'event', 'hacklabr'),
        ]);
    }
}
add_action('wp', 'hacklabr\\register_event_registration_form');

function render_event_registration_form (array $attrs) {
    return render_form_callback([ 'formId' => 'event-registration' ]);
}

function validate_event_registration_form (string $form_id, array $form, array $params) {
    if ($form_id === 'event-registration') {
        $validation = validate_form($form['fields'], $params);

        if ($validation !== true) {
            return;
        }

        $user = get_user_by('email', $params['email']);

        if (!$user) {
            // @TODO
        }

        // @TODO
    }
}
add_action('hacklabr\\form_action', 'hacklabr\\validate_event_registration_form', 10, 3);

function wrap_event_registration_form (string $form_html, array $form) {
    if ($form['id'] !== 'event-registration') {
        return $form_html;
    }

    $user_id = get_current_user_id();
    $event_id = get_the_ID();

    $hidden_fields = [
        "<input type='hidden' id='__user_id' value='{$user_id}'>",
    ];

    if ($fut_pf_id = get_post_meta($event_id, '_ethos_crm:fut_pf_id', true)) {
        $hidden_fields[] = "<input type='hidden' id='__fut_pf_id' value='{$fut_pf_id}'>";
    }

    $form_lines = explode("\n", $form_html);
    array_splice($form_lines, 1, 0, $hidden_fields);
    $form_html = implode("\n", $form_lines);

    return $form_html;
}
add_action('hacklabr\\form_output', 'hacklabr\\wrap_event_registration_form', 10, 2);
