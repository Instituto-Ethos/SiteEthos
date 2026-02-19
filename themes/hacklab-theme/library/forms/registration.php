<?php

namespace hacklabr;

function add_registration_status () {
    add_role('ethos_under_progress', __('Registration under progress', 'hacklabr'), [
        'read' => true,
        'upload_files' => true,
    ]);

    register_post_status('ethos_under_progress', [
        'label' => __('Registration under progress', 'hacklabr'),
        'exclude_from_search' => true,
        'post_status' => ['organizacao'],
        'public' => false,
        'publicly_queryable' => true,
        'show_in_admin_all_list' => false,
        'show_in_admin_status_list' => true,
    ]);
}
add_action('init', 'hacklabr\\add_registration_status');

function build_registration_step_link ($form_id, $kit, $transaction) {
    $page = get_page_by_form($form_id);
    $args = [ 'kit' => $kit, 'transaction' => $transaction ];
    return add_query_arg($args, get_permalink($page));
}

function generate_transaction_token ($length = 24) {
    $bytes = openssl_random_pseudo_bytes($length);
    return str_replace(['+','/','='], ['-','_',''], base64_encode($bytes));
}

function get_post_by_transaction ($post_type, $transaction = null) {
    if (empty($transaction)) {
        $transaction = filter_input(INPUT_GET, 'transaction', FILTER_SANITIZE_ADD_SLASHES) ?? null;

        if (empty($transaction)) {
            return null;
        }
    }

    return get_single_post([
        'post_type' => $post_type,
        'post_status' => ['draft', 'ethos_under_progress', 'publish'],
        'meta_query' => [
            [ 'key' => '_ethos_transaction', 'value' => $transaction ],
        ],
    ]);
}

function get_registration_step1_fields () {
    $revenue_options = [
        'small' => __('Micro and small (up to R$ 16 million)', 'hacklabr'),
        'medium' => __('Medium (R$ 16 to 300 million)', 'hacklabr'),
        'large' => __('Large (over R$ 300 million)', 'hacklabr'),
    ];

    $size_options = [
        'micro' => __('Micro-enterprise', 'hacklabr'),
        'small' => __('Small business', 'hacklabr'),
        'medium' => __('Medium business', 'hacklabr'),
        'large' => __('Large business', 'hacklabr'),
    ];

    $privacy_policy_url =  get_privacy_policy_url();
    $code_of_conduct_url = wp_get_upload_dir()['baseurl'] . '/2024/07/Codigo-de-Conduta_final.pdf';

    $fields = [
        'cnpj' => [
            'type' => 'masked',
            'class' => '-colspan-12',
            'label' => __('CNPJ number', 'hacklabr'),
            'mask' => '00.000.000/0000-00',
            'placeholder' => __("Enter the company' CNPJ number", 'hacklabr'),
            'required' => true,
            'validate' => function ($value, $context) {
                if (!is_numeric($value) || strlen($value) !== 14) {
                    return __('Invalid CNPJ number', 'hacklabr');
                }
                return true;
            },
        ],
        'razao_social' => [
            'type' => 'text',
            'class' => '-colspan-12',
            'label' => __('Legal name', 'hacklabr'),
            'placeholder' => __('Enter the legal name', 'hacklabr'),
            'required' => true,
        ],
        'nome_fantasia' => [
            'type' => 'text',
            'class' => '-colspan-12',
            'label' => __('Trade name', 'hacklabr'),
            'placeholder' => __('Enter the trade name', 'hacklabr'),
            'required' => true,
        ],
        'segmento' => [
            'type' => 'text',
            'class' => '-colspan-12',
            'label' => __('Sector / segment', 'hacklabr'),
            'placeholder' => __("Enter the company's sector / segment", 'hacklabr'),
            'required' => true,
        ],
        'cnae' => [
            'type' => 'masked',
            'class' => '-colspan-12',
            'label' => __('CNAE number', 'hacklabr'),
            'mask' => '0000-0/00',
            'placeholder' => __("Enter the company's CNAE number", 'hacklabr'),
            'required' => false,
            'validate' => function ($value, $context) {
                if (empty($value)) {
                    return true;
                } elseif (!is_numeric($value) || strlen($value) !== 7) {
                    return __('Invalid CNAE number', 'hacklabr');
                }
                return true;
            },
        ],
        'faturamento_anual' => [
            'type' => 'select',
            'class' => '-colspan-12',
            'label' =>__('Revenue from the previous year (R$)', 'hacklabr'),
            'options' => $revenue_options,
            'required' => true,
            'validate' => function ($value, $context) use ($revenue_options) {
                if (!array_key_exists($value, $revenue_options)) {
                    return __('Invalid revenue', 'hacklabr');
                }
                return true;
            },
        ],
        'inscricao_estadual' => [
            'type' => 'text',
            'class' => '-colspan-12',
            'label' => __('State registration', 'hacklabr'),
            'placeholder' => __('Enter the state registration', 'hacklabr'),
            'required' => false,
        ],
        'inscricao_municipal' => [
            'type' => 'text',
            'class' => '-colspan-12',
            'label' => __('Municipal registration', 'hacklabr'),
            'placeholder' => __('Enter the municipal registration', 'hacklabr'),
            'required' => false,
        ],
        'logomarca' => [
            'type' => 'file',
            'class' => '-colspan-12',
            'label' => __('Company logo', 'hacklabr'),
            'accept' => 'image/*',
            'hint' => __('Picture should be 164 x 164 pixels', 'hacklabr'),
            'required' => false,
        ],
        'website' => [
            'type' => 'url',
            'class' => '-colspan-12',
            'label' => __('Website', 'hacklabr'),
            'placeholder' => __('https://www.companysite.com', 'hacklabr'),
            'required' => false,
            'validate' => function ($value, $context) {
                if (empty($value)) {
                    return true;
                } else if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    return __('Invalid URL', 'hacklabr');
                }
                return true;
            },
        ],
        'num_funcionarios' => [
            'type' => 'number',
            'class' => '-colspan-12',
            'label' => __('Number of employees', 'hacklabr'),
            'placeholder' => __('Enter the number of company employees', 'hacklabr'),
            'required' => true,
            'validate' => function ($value, $context) {
                if (!is_numeric($value) || intval($value) <= 0) {
                    return __('Invalid number', 'hacklabr');
                }
                return true;
            },
        ],
        'porte' => [
            'type' => 'select',
            'class' => '-colspan-12',
            'label' => _x('Size', 'company size', 'hacklabr'),
            'options' => $size_options,
            'required' => false,
            'validate' => function ($value, $context) use ($size_options) {
                if (empty($value)) {
                    return true;
                } elseif (!array_key_exists($value, $size_options)) {
                    return _x('Invalid size', 'company size', 'hacklabr');
                }
                return true;
            },
        ],

        ...get_address_fields(),

        'termos_de_uso' => [
            'type' => 'checkbox',
            'class' => '-colspan-12',
            'label' => sprintf(__('I have read and agreed with the <a href="%s" target="_blank">Terms of Use and Privacy Policy</a>', 'hacklabr'), $privacy_policy_url),
            'required' => true,
        ],
        'codigo_de_conduta' => [
            'type' => 'checkbox',
            'class' => '-colspan-12',
            'label' => sprintf(__('I have read and agreed with the <a href="%s" target="_blank">Code of Conduct</a>', 'hacklabr'), $code_of_conduct_url),
            'required' => true,
        ],
    ];

    return $fields;
}

function get_registration_step1_params ($form) {
    $post = get_post_by_transaction('organizacao');

    $params = sanitize_form_params();

    if (!empty($post)) {
        $meta = get_post_meta($post->ID);

        foreach ($form['fields'] as $key => $field) {
            if (empty($params[$key]) && !empty($meta[$key])) {
                $params[$key] = $meta[$key][0];
            }
        }
    }

    return $params;
}

function get_registration_step2_fields () {
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
            'class' => '-colspan-12',
            'label' => __('CPF number', 'hacklabr'),
            'mask' => '000.000.000-00',
            'placeholder' => __('Enter the CPF number', 'hacklabr'),
            'required' => true,
            'validate' => function ($value, $context) {
                if (!is_numeric($value) || strlen($value) !== 11) {
                    return __('Invalid CPF number', 'hacklabr');
                }
                return true;
            },
        ],
        'cargo' => [
            'type' => 'text',
            'class' => '-colspan-12',
            'label' => __('Role', 'hacklabr'),
            'placeholder' => __('Enter the role in company', 'hacklabr'),
            'required' => true,
        ],
        'area' => [
            'type' => 'text',
            'class' => '-colspan-12',
            'label' => _x('Area', 'company', 'hacklabr'),
            'placeholder' => __('Enter the area in company', 'hacklabr'),
            'required' => true,
        ],
        'email' => [
            'type' => 'email',
            'class' => '-colspan-12',
            'label' => __('Email', 'hacklabr'),
            'placeholder' => __('Enter the email', 'hacklabr'),
            'required' => true,
            'validate' => function ($value, $context) {
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return __('Invalid email', 'hacklabr');
                } else {
                    $maybe_user = get_user_by('email', $value);
                    if (!empty($maybe_user)) {
                        $user = get_user_by_transaction();
                        if (empty($user) || $maybe_user->ID !== $user->ID) {
                            return __('Email is already in use', 'hacklabr');
                        }
                    }
                }
                return true;
            },
        ],
        'senha' => [
            'type' => 'password',
            'class' => '-colspan-12',
            'label' => __('Password', 'hacklabr'),
            'placeholder' => __('Enter the password', 'hacklabr'),
            'required' => true,
            'validate' => function ($value, $context) {
                if (strlen($value) < 8) {
					return __('Password should be at least 8 characters long', 'hacklabr');
				}
				return true;
            },
        ],
        'celular' => [
            'type' => 'masked',
            'class' => '-colspan-12',
            'label' => __('Cell phone number', 'hacklabr'),
            'mask' => '(00) 0000-0000|(00) 00000-0000',
            'placeholder' => __('Enter the cell phone number', 'hacklabr'),
            'required' => true,
            'validate' => function ($value, $context) {
                if (!is_numeric($value) || strlen($value) < 10 || strlen($value) > 11) {
                    return __('Invalid phone number', 'hacklabr');
                }
                return true;
            },
        ],
        'celular_is_whatsapp' => [
            'type' => 'checkbox',
            'class' => '-colspan-12',
            'label' => __('This is also a WhatsApp number', 'hacklabr'),
            'required' => false,
        ],
        'telefone' => [
            'type' => 'masked',
            'class' => '-colspan-12',
            'label' => __('Phone number', 'hacklabr'),
            'mask' => '(00) 0000-0000|(00) 00000-0000',
            'placeholder' => __('Enter the phone number', 'hacklabr'),
            'required' => false,
            'validate' => function ($value, $context) {
                if (empty($value)) {
                    return true;
                } elseif (!is_numeric($value) || strlen($value) < 10 || strlen($value) > 11) {
                    return __('Invalid phone number', 'hacklabr');
                }
                return true;
            },
        ],
    ];

    if (!empty($_GET['transaction'])) {
        $transaction = filter_input(INPUT_GET, 'transaction', FILTER_SANITIZE_ADD_SLASHES) ?? null;
        $user = get_user_by_transaction($transaction);

        if (!empty($user)) {
            unset($fields['senha']);
        }
    }

    return $fields;
}

function get_registration_step2_params ($form) {
    $user = get_user_by_transaction();

    $params = sanitize_form_params();

    if (!empty($user)) {
        $meta = get_user_meta($user->ID);

        foreach ($form['fields'] as $key => $field) {
            if (empty($params[$key]) && !empty($meta[$key])) {
                $params[$key] = $meta[$key][0];
            }
        }
    }

    return $params;
}

function get_registration_step3_fields () {
    $fields = [
        'nivel' => [
            'type' => 'pmpro_level',
            'class' => '-colspan-12 choose-plan',
            'label' => _x('Plan', 'membership', 'hacklabr'),
            'for_manager' => true,
            'required' => true,
            'validate' => function ($value, $context) {
                if (!is_numeric($value)) {
                    return _x('Invalid plan', 'membership', 'hacklabr');
                }
                return true;
            }
        ],
    ];

    return $fields;
}

function get_registration_step3_params () {
    $post = get_post_by_transaction('organizacao');

    $params = sanitize_form_params();

    if (empty($params['nivel']) && !empty($post)) {
        $group_id = (int) get_post_meta($post->ID, '_pmpro_group', true);

        if (!empty($group_id)) {
            $group = get_pmpro_group($group_id);
            $params['nivel'] = $group->group_parent_level_id;
        }
    }

    return $params;
}

function get_registration_step4_fields () {
    $advance_options = [
        'no' => _x('In advance', 'payment', 'hacklabr'),
        'yes' => _x('In stallments', 'payment', 'hacklabr'),
    ];

    $periodicity_options = [
        'monthly' => __('Monthly', 'hacklabr'),
        'semianually' => __('Semianually', 'hacklabr'),
        'yearly' => __('Yearly', 'hacklabr'),
    ];

    $receive_billing_options = [
        'email' => _x('Email', 'medium', 'hacklabr'),
        'post' => _x('Post mail', 'medium', 'hacklabr'),
    ];

    $receive_terms_options = [
        'email' => _x('Email', 'medium', 'hacklabr'),
        'post' => _x('Post mail', 'medium', 'hacklabr'),
        'icp' => _x('Electronic signature', 'medium', 'hacklabr'),
    ];

    $fields = [
        'pagto_sugerido' => [
            'type' => 'masked',
            'class' => '-colspan-12',
            'label' => __('Calculated payment value', 'hacklabr'),
            'mask' => '__currency__',
            'placeholder' => 'R$',
            'required' => false,
        ],
        'pgto_inicio' => [
            'type' => 'date',
            'class' => '-colspan-12',
            'label' => __('Expected date of first payment', 'hacklabr'),
            'placeholder' => __('Select a date', 'hacklabr'),
            'required' => false,
        ],
        'pagto_a_vista' => [
            'type' => 'select',
            'class' => '-colspan-12',
            'label' => __('Payment terms', 'hacklabr'),
            'options' => $advance_options,
            'required' => false,
            'validate' => function ($value, $context) use ($advance_options) {
                if (empty($value)) {
                    return true;
                } elseif (!array_key_exists($value, $advance_options)) {
                    return __('Invalid payment term', 'hacklabr');
                }
                return true;
            },
        ],
        'pagto_periodicidade' => [
            'type' => 'select',
            'class' => '-colspan-12',
            'label' => __('Payment frequency', 'hacklabr'),
            'options' => $periodicity_options,
            'required' => false,
            'validate' => function ($value, $context) use ($periodicity_options) {
                if (empty($value)) {
                    return true;
                } elseif (!array_key_exists($value, $periodicity_options)) {
                    return __('Invalid payment frequency', 'hacklabr');
                }
                return true;
            },
        ],
        'pagto_observacoes' => [
            'type' => 'textarea',
            'class' => '-colspan-12',
            'label' => __('Observations about payment terms', 'hacklabr'),
            'placeholder' => __('Describe here your observations', 'hacklabr'),
            'required' => false,
        ],
        'envio_termos' => [
            'type' => 'select',
            'class' => '-colspan-12',
            'label' => __('Means of receiving the association agreement', 'hacklabr'),
            'options' => $receive_terms_options,
            'required' => false,
            'validate' => function ($value, $context) use ($receive_terms_options) {
                if (empty($value)) {
                    return true;
                } elseif (!array_key_exists($value, $receive_terms_options)) {
                    return _x('Invalid mean', 'medium', 'hacklabr');
                }
                return true;
            },
        ],
        'envio_boleto' => [
            'type' => 'select',
            'class' => '-colspan-12',
            'label' => __('Means of receiving the banking slips', 'hacklabr'),
            'options' => $receive_billing_options,
            'required' => false,
            'validate' => function ($value, $context) use ($receive_billing_options) {
                if (empty($value)) {
                    return true;
                } elseif (!array_key_exists($value, $receive_billing_options)) {
                    return _x('Invalid mean', 'medium', 'hacklabr');
                }
                return true;
            },
        ],
        'envio_recibo' => [
            'type' => 'select',
            'class' => '-colspan-12',
            'label' => __('Means of receiving the receipts', 'hacklabr'),
            'options' => $receive_billing_options,
            'required' => false,
            'validate' => function ($value, $context) use ($receive_billing_options) {
                if (empty($value)) {
                    return true;
                } elseif (!array_key_exists($value, $receive_billing_options)) {
                    return _x('Invalid mean', 'medium', 'hacklabr');
                }
                return true;
            },
        ],
    ];

    if (class_exists('PMProGroupAcct_Group') && !empty($_GET['transaction'])) {
        $post = get_post_by_transaction('organizacao');

        $group_id = (int) get_post_meta($post->ID, '_pmpro_group', true);

        $membership_price = calculate_membership_price($group_id);

        $fields['pagto_sugerido']['default'] = $membership_price;
        $fields['pagto_sugerido']['disabled'] = true;
    }

    return $fields;
}

function get_registration_step5_fields () {
    $step2_fields = get_registration_step2_fields();

    $fields = [];

    foreach ($step2_fields as $key => $definition) {
        if ($key !== 'senha') {
            $fields[$key] = $definition;
        }
    }

    return $fields;
}

function register_registration_form () {
    $fields_step1 = get_registration_step1_fields();
    $fields_step2 = get_registration_step2_fields();
    $fields_step3 = get_registration_step3_fields();
    $fields_step4 = get_registration_step4_fields();
    $fields_step5 = get_registration_step5_fields();

    $kit = filter_input(INPUT_GET, 'kit', FILTER_SANITIZE_ADD_SLASHES) ?? null;
    $transaction = filter_input(INPUT_GET, 'transaction', FILTER_SANITIZE_ADD_SLASHES) ?? null;

    register_form('member-registration-1', __('Member registration - step 1', 'hacklabr'), [
        'fields' => $fields_step1,
        'get_params' => 'hacklabr\\get_registration_step1_params',
        'submit_label' => __('Continue', 'hacklabr'),
    ]);

    register_form('member-registration-2', __('Member registration - step 2', 'hacklabr'), [
        'fields' => $fields_step2,
        'get_params' => 'hacklabr\\get_registration_step2_params',
        'previous_url' => build_registration_step_link('member-registration-1', $kit, $transaction),
        'submit_label' => __('Continue', 'hacklabr'),
    ]);

    register_form('member-registration-3', __('Member registration - step 3', 'hacklabr'), [
        'fields' => $fields_step3,
        'get_params' => 'hacklabr\\get_registration_step3_params',
        'previous_url' => build_registration_step_link('member-registration-2', $kit, $transaction),
        'submit_label' => __('Continue', 'hacklabr'),
    ]);

    register_form('member-registration-4', __('Member registration - step 4', 'hacklabr'), [
        'fields' => $fields_step4,
        'get_params' => 'hacklabr\\get_registration_step1_params',
        'previous_url' => build_registration_step_link('member-registration-3', $kit, $transaction),
        'skip_url' => build_registration_step_link('member-registration-5', $kit, $transaction),
        'submit_label' => __('Continue', 'hacklabr'),
    ]);

    register_form('member-registration-5', __('Member registration - step 5', 'hacklabr'), [
        'fields' => $fields_step5,
        'submit_label' => __('Add contact', 'hacklabr'),
    ]);
}
add_action('init', 'hacklabr\\register_registration_form');

function get_user_by_transaction ($transaction = null) {
    if (empty($transaction)) {
        $transaction = filter_input(INPUT_GET, 'transaction', FILTER_SANITIZE_ADD_SLASHES) ?? null;

        if (empty($transaction)) {
            return null;
        }
    }

    return get_single_user([
        'meta_query' => [
            [ 'key' => '_ethos_transaction', 'value' => $transaction ],
        ],
    ]);
}

function set_post_featured_image ($post_id, $file_key) {
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $attachment_id = media_handle_upload($file_key, $post_id);

    if (is_numeric($attachment_id)) {
        set_post_thumbnail($post_id, $attachment_id);
    }
}

function validate_registration_form ($form_id, $form, $params) {
    $kit = filter_input(INPUT_GET, 'kit', FILTER_SANITIZE_ADD_SLASHES) ?? null;
    $transaction = filter_input(INPUT_GET, 'transaction', FILTER_SANITIZE_ADD_SLASHES) ?? null;

    if ($form_id === 'member-registration-1') {
        $validation = validate_form($form['fields'], $params);

        if ($validation !== true) {
            return;
        }

        $post_meta = $params;
        unset($post_meta['_hacklabr_form']);

        if (empty($transaction)) {
            $transaction = generate_transaction_token(24);
            $post_meta['_ethos_transaction'] = $transaction;

            $post_id = wp_insert_post([
                'post_type' => 'organizacao',
                'post_title' => $params['nome_fantasia'],
                'post_content' => '',
                'post_status' => 'ethos_under_progress',
                'meta_input' => $post_meta,
            ]);

            if (!empty($_FILES['_logomarca'])) {
                set_post_featured_image($post_id, '_logomarca');
            }
        } else {
            $post = get_post_by_transaction('organizacao', $transaction);

            wp_update_post([
                'ID' => $post->ID,
                'post_title' => $params['nome_fantasia'],
                'meta_input' => $post_meta,
            ]);

            if (!empty($_FILES['_logomarca'])) {
                set_post_featured_image($post->ID, '_logomarca');
            }

            \ethos\crm\update_organization($post->ID);
        }

        $next_page = build_registration_step_link('member-registration-2', $kit, $transaction);
        wp_safe_redirect($next_page);
        exit;
    }

    if ($form_id === 'member-registration-2' && !empty($transaction)) {
        $validation = validate_form($form['fields'], $params);

        if ($validation !== true) {
            return;
        }

        $post = get_post_by_transaction('organizacao', $transaction);
        $user = get_user_by_transaction($transaction);

        $user_meta = $params;
        unset($user_meta['_hacklabr_form']);

        if (empty($user)) {
            $user_meta['_ethos_transaction'] = $transaction;

            // Don't store plaintext password
            $password = $user_meta['senha'];
            unset($user_meta['senha']);

            $user_id = wp_insert_user([
                'display_name' => $params['nome_completo'],
                'user_email' => $params['email'],
                'user_login' => sanitize_title($params['nome_completo']),
                'user_pass' => $password,
                'role' => 'ethos_under_progress',
                'meta_input' => $user_meta,
            ]);

            wp_update_post([
                'ID' => $post->ID,
                'post_author' => $user_id,
            ]);
        } else {
            wp_update_user([
                'ID' => $user->ID,
                'display_name' => $params['nome_completo'],
                'user_email' => $params['email'],
                'meta_input' => $user_meta,
            ]);

            \ethos\crm\update_contact($user->ID);
        }

        $next_page = build_registration_step_link('member-registration-3', $kit, $transaction);
        wp_safe_redirect($next_page);
        exit;
    }

    if ($form_id === 'member-registration-3' && !empty($transaction)) {
        $validation = validate_form($form['fields'], $params);

        if ($validation !== true) {
            return;
        }

        $post = get_post_by_transaction('organizacao', $transaction);
        $user = get_user_by_transaction($transaction);

        $level_id = (int) $params['nivel'];

        $group_id = get_post_meta($post->ID, '_pmpro_group', true);

        if (empty($group_id)) {
            $group = create_pmpro_group($user->ID, $level_id);

            wp_update_user([
                'ID' => $user->ID,
                'role' => 'subscriber',
                'meta_input' => [
                    '_ethos_admin' => '1',
                    '_pmpro_group' => $group->id,
                    '_pmpro_role' => 'primary',
                ],
            ]);

            wp_update_post([
                'ID' => $post->ID,
                'post_status' => 'publish',
                'meta_input' => [
                    '_pmpro_group' => $group->id,
                ],
            ]);

            // @see add_post_to_sync_waiting_list
        } else {
            update_group_level($group_id, $level_id);
        }

        $next_page = build_registration_step_link('member-registration-4', $kit, $transaction);
        wp_safe_redirect($next_page);
        exit;
    }

    if ($form_id === 'member-registration-4' && !empty($transaction)) {
        $validation = validate_form($form['fields'], $params);

        if ($validation !== true) {
            return;
        }

        $post = get_post_by_transaction('organizacao', $transaction);

        $post_meta = $params;
        unset($post_meta['_hacklabr_form']);

        foreach ($post_meta as $meta_key => $meta_value) {
            update_post_meta($post->ID, $meta_key, $meta_value);
        }

        $next_page = build_registration_step_link('member-registration-5', $kit, $transaction);
        wp_safe_redirect($next_page);
        exit;
    }

    if ($form_id === 'member-registration-5' && !empty($transaction) && !empty($params['_role'])) {
        $validation = validate_form($form['fields'], $params);

        if ($validation !== true) {
            return;
        }

        $post = get_post_by_transaction('organizacao', $transaction);

        $group_id = (int) get_post_meta($post->ID, '_pmpro_group', true);

        $role = $params['_role'];
        unset($user_meta['_role']);

        $user_meta = array_merge($params, [
            '_pmpro_group' => $group_id,
            '_pmpro_role' => $role,
        ]);
        unset($user_meta['_hacklabr_form']);

        $password = wp_generate_password(16);

        $user_id = wp_insert_user([
            'display_name' => $params['nome_completo'],
            'user_email' => $params['email'],
            'user_login' => sanitize_title($params['nome_completo']),
            'user_pass' => $password,
            'role' => 'subscriber',
            'meta_input' => $user_meta,
        ]);

        add_user_to_pmpro_group($user_id, $group_id);

        \ethos\crm\create_contact($user_id, $post->ID);

        $next_page = build_registration_step_link('member-registration-5', $kit, $transaction);
        wp_safe_redirect($next_page);
        exit;
    }
}
add_action('hacklabr\\form_action', 'hacklabr\\validate_registration_form', 10, 3);

function update_registration_form_title ($title, $post_id = null) {
    if (is_page() && !empty($post_id)) {
        $hacklabr_form = get_post_meta($post_id, 'hacklabr_form', true);

        if (!empty($hacklabr_form) && str_starts_with($hacklabr_form, 'member-registration-')) {
            return __('Membership to Ethos Institute', 'hacklabr');
        }
    }

    return $title;
}
add_filter('the_title', 'hacklabr\\update_registration_form_title', 10, 2);
