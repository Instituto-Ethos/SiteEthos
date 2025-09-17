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

    $area_options = [
        '969830000' => _x('Administration', 'area', 'hacklabr'),
		'969830001' => _x('Commercial', 'area', 'hacklabr'),
		'969830002' => _x('Communication', 'area', 'hacklabr'),
		'969830003' => _x('Financial', 'area', 'hacklabr'),
		'969830014' => _x('Juridical', 'area', 'hacklabr'),
		'969830004' => _x('Marketing', 'area', 'hacklabr'),
		'969830005' => _x('Environment', 'area', 'hacklabr'),
		'969830010' => _x('Production', 'area', 'hacklabr'),
		'969830011' => _x('Human Resources', 'area', 'hacklabr'),
		'969830007' => _x('Institutional Relations', 'area', 'hacklabr'),
		'969830008' => _x('Social Responsibility', 'area', 'hacklabr'),
		'969830009' => _x('Sustainability', 'area', 'hacklabr'),
		'969830012' => _x('Supply', 'area', 'hacklabr'),
		'969830013' => _x('IT', 'area', 'hacklabr'),
		'969830006' => _x('Other', 'area', 'hacklabr'),
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
            'required' => false,
            'validate' => function ($value) {
                $cnpj = preg_replace('/\D/', '', $value);
                if ($cnpj === '') {
                    return true;
                }

                if (strlen($cnpj) !== 14) {
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
            'type' => 'select',
            'class' => '-colspan-12',
            'label' => _x('Area', 'company', 'hacklabr'),
            'options' => $area_options,
            'required' => false,
            'validate' => function ($value) use ($area_options) {
                if (!array_key_exists($value, $area_options)) {
                    return __('Invalid area', 'hacklabr');
                }
                return true;
            },
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

        if ((empty($params['area']) || !is_numeric($params['area'])) && !empty($user_meta['_ethos_crm:fut_pl_area'])) {
            $params['area'] = $user_meta['_ethos_crm:fut_pl_area'][0];
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

        $post_id = get_the_ID();

        register_for_event($post_id, $params);
    }
}
add_action('hacklabr\\form_action', 'hacklabr\\validate_event_registration_form', 10, 3);

function form_spinner() {
    ob_start();
?>
    <div class="form__loading -colspan-12" x-show="formLoading">
        <img src="<?= get_stylesheet_directory_uri() ?>/assets/images/spinner.svg" alt="">
        <p>
            <strong><?= _e('Await', 'hacklabr') ?></strong>
            <span><?php _e('It may take some minutes.', 'hacklabr') ?></span>
        </p>
    </div>
<?php
    return ob_get_clean();
}

function wrap_event_registration_form (string $form_html, array $form) {
    global $hl_event_registration;

    if ($form['id'] !== 'event-registration') {
        return $form_html;
    }

    $post_id = get_the_ID();

    $heading = "<h3>" . __('Event registration', 'hacklabr') . "</h3>\n";

    $message_template = "<div class='form__message form__message--%s'>%s</div>\n";
    $message = '';
    if (!empty($hl_event_registration)) {
        $message = sprintf($message_template, $hl_event_registration['status'], $hl_event_registration['message']);
    } elseif (!registrations_are_open($post_id)) {
        $message = sprintf($message_template, 'error', __('Registrations are closed.', 'hacklabr'));
        return $heading . $message;
    }

    $form_html = $heading . $message . $form_html;

    $search = 'enctype="multipart/form-data"';
    $form_html = str_replace($search, $search . ' x-data="{ formLoading: false }" @submit="formLoading = true"', $form_html);

    $search = '<div class="form__buttons">';
    $form_html = str_replace($search, form_spinner() . "\n" . $search, $form_html);

    $search = 'type="submit"';
    $form_html = str_replace($search, $search . ' x-show="!formLoading"', $form_html);

    return $form_html;
}
add_action('hacklabr\\form_output', 'hacklabr\\wrap_event_registration_form', 10, 2);
