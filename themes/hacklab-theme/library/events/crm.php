<?php

namespace hacklabr;

use \hacklabr\batch\Dynamics_Batch_Builder;
use \hacklabr\batch\Dynamics_Batch_Reference;

function check_event_availability (int $post_id, string $project_id, string|null $contact_id = null): array {
    if (!registrations_are_open($post_id)) {
        return [
            'status'  => 'error',
            'form'    => 'hide',
            'message' => __('Registrations are closed.', 'hacklabr'),
        ];
    }

    $current_date = date('c');

    $registration_end = get_post_meta($post_id, '_ethos_crm:fut_dt_data_encerramento_inscricoes', true);
    if (empty($registration_end)) {
        $registration_end = get_post_meta($post_id, '_ethos_crm:fut_dt_dataehoratermino', true);
    }

    if ($current_date > $registration_end) {
        update_post_meta($post_id, '_ethos:event_status', 'PAST');

        return [
            'status'  => 'error',
            'form'    => 'hide',
            'message' => __('Registrations are closed.', 'hacklabr'),
        ];
    }

    $registered_ids = get_event_registrations($project_id);
    $filled_slots = count($registered_ids);
    $total_slots = intval(get_post_meta($post_id, '_ethos_crm:fut_int_nrovagastotal', true));

    if ($filled_slots >= $total_slots) {
        update_post_meta($post_id, '_ethos:event_status', 'FULL');

        return [
            'status'  => 'error',
            'form'    => 'hide',
            'message' => __('Registrations are closed.', 'hacklabr'),
        ];
    } elseif ($contact_id && in_array($contact_id, $registered_ids)) {
        return [
            'status'  => 'error',
            'form'    => 'clean',
            'message' => __('You are already registered in this event.', 'hacklabr'),
        ];
    } else {
        return [
            'filled'    => $filled_slots,
            'available' => $total_slots - $filled_slots,
        ];
    }
}

function create_registration (int $post_id, array $params) {
    $project_id = get_post_meta($post_id, 'entity_fut_projeto', true);

    $paid_event = is_paid_event($post_id);
    $associates_event = is_associates_event($post_id);

    $client = get_client_on_dynamics();
    $builder = new Dynamics_Batch_Builder($client->getClient());

    $lead_id = null;
    if (!empty($params['cnpj'])) {
        $lead_id = get_registration_lead($params, $builder);
    }

    $account_id = get_registration_account($params);
    $contact_id = get_registration_contact($params, $lead_id, !$associates_event, $builder);

    $contact_uuid = null;
    if (!$contact_id instanceof Dynamics_Batch_Reference) {
        $contact_uuid = $contact_id;
    }

    if ($associates_event) {
        $user_is_associate = false;

        if (!empty($contact_uuid)) {
            $user_id = get_user_by_contact($contact_uuid);
            $user_is_associate = !empty($user_id);
        }

        if (!$user_is_associate) {
            return [
                'status'  => 'error',
                'form'    => 'clean',
                'message' => __('This event is exclusive for associates.', 'hacklabr'),
            ];
        }

        if (!empty($account_id)) {
            $account = get_crm_entity_by_id('account', $account_id);

            if (($account->Attributes['fut_pl_situacaofinanceira'] ?? 0) === 969830003 /* Adimplente Congelada */) {
                return [
                    'status'  => 'error',
                    'form'    => 'clean',
                    'message' => __('Your registration could not be processed. Please contact your account manager to resolve any outstanding issues or report any errors.', 'hacklabr'),
                ];
            }
        }
    }

    $availability = check_event_availability($post_id, $project_id, $contact_uuid);
    if (!empty($availability['status'])) {
        return $availability;
    }

    if ($contact_id instanceof Dynamics_Batch_Reference) {
        $contact_ref = $contact_id;
    } else {
        $contact_ref = create_crm_reference('contact', $contact_id);
    }

    $attibutes = [
        'fut_lk_contato'        => $contact_ref,
        'fut_lk_fatura_pf'      => $contact_ref,
        'fut_pl_cortesia'       => get_courtesy_type($post_id, $contact_uuid, $account_id),
        'fut_lk_projeto'        => create_crm_reference('fut_projeto', $project_id),
        'fut_txt_nro_inscricao' => generate_registration_number($post_id, $availability['filled'] ?? 0),
    ];

    if ($paid_event) {
        $attibutes['fut_bl_exibecamposfinanceiros'] = true;
        $attibutes['fut_set_statusoperacao'] = 969830000; // Sem status
    } else {
        $attibutes['fut_set_statusoperacao'] = 969830003; // Pago
    }

    if (!empty($account_id)) {
        $attibutes['fut_lk_empresa']           = create_crm_reference('account', $account_id);
        $attibutes['fut_lk_empresa_associada'] = create_crm_reference('account', $account_id);
        $attibutes['fut_lk_fatura_pj']         = create_crm_reference('account', $account_id);
    }

    if (!empty($params['acessibilidade'])) {
        $attibutes['fut_txt_necessidades_especiais'] = trim($params['acessibilidade']);
    }

    try {
        $participant_ref = $builder->add_create('fut_participante', $attibutes);
        $results = $builder->execute($contact_id instanceof Dynamics_Batch_Reference);

        $participant_result = $builder->get_result($participant_ref);

        if ($participant_result && $participant_result['status'] === 'success') {
            if ($contact_id instanceof Dynamics_Batch_Reference) {
                $contact_result = $builder->get_result($contact_id);
                $contact_id = $contact_result['entity_id'] ?? null;
            }

            if ($paid_event) {
                $message = __('You are registered to this event, but payment is pending.', 'hacklabr');
            } else {
                $message = __('You are successfully registered to this event!', 'hacklabr');
            }

            return [
                'status'     => 'success',
                'form'       => $paid_event ? 'checkout' : 'hide',
                'message'    => $message,
                'entity_id'  => $participant_result['entity_id'],
                'contact_id' => $contact_id,
            ];
        }

        $error_message = __('Registration failed.', 'hacklabr');
        if (!empty($participant_result['message'])) {
            $error_message = $participant_result['message'];
        }

        return [
            'status'  => 'error',
            'form'    => 'preserve',
            'message' => $error_message,
        ];
    } catch (\Exception $e) {
        return [
            'status'  => 'error',
            'form'    => 'preserve',
            'message' => $e->getMessage(),
        ];
    }
}

function create_registration_contact (array $params, string|Dynamics_Batch_Reference|null $lead_id = null, ?Dynamics_Batch_Builder $builder = null): string|Dynamics_Batch_Reference {
    $full_name = trim($params['nome_completo']);
    $name_parts = explode(' ',  $full_name);
    $first_name = $name_parts[0];
    unset($name_parts[0]);
    $last_name = implode(' ', $name_parts);

    $attributes = [
        'emailaddress1' => trim($params['email']),
        'firstname'     => $first_name,
        'fullname'      => $full_name,
        'fut_st_cpf'    => format_cpf(trim($params['cpf'])),
        'lastname'      => $last_name,
        'yomifirstname' => $first_name,
        'yomifullname'  => $full_name,
        'yomilastname'  => $last_name,
    ];

    $optional_fields = [
        'fut_pl_area'             => 'area',
        'fut_pl_nivelhierarquico' => 'nivel_hierarquico',
        'jobtitle'                => 'cargo',
        'telephone1'              => 'telefone',
    ];
    foreach ($optional_fields as $attribute_key => $param_key) {
        if ($value = trim($params[$param_key] ?? '')) {
            $attributes[$attribute_key] = $value;
        }
    }

    if (!empty($lead_id)) {
        if ($lead_id instanceof Dynamics_Batch_Reference) {
            $attributes['originatingleadid'] = $lead_id;
        } else {
            $attributes['originatingleadid'] = create_crm_reference('lead', $lead_id);
        }
    }

    if ($builder) {
        return $builder->add_create('contact', $attributes);
    } else {
        return create_crm_entity('contact', $attributes);
    }
}

function create_registration_lead (array $params, ?Dynamics_Batch_Builder $builder = null): string|Dynamics_Batch_Reference {
    $systemuser = get_option('systemuser');

    $cnpj = trim($params['cnpj']);
    $company_name = trim($params['nome_fantasia'] ?? '');

    $full_name = trim($params['nome_completo']);
    $name_parts = explode(' ',  $full_name);
    $first_name = $name_parts[0];
    unset($name_parts[0]);
    $last_name = implode(' ', $name_parts);

    $attributes = [
        'companyname'                => $company_name,
        'firstname'                  => $company_name,
        'fullname'                   => $company_name,
        'fut_st_cnpj'                => format_cnpj($cnpj),
        'fut_st_nome'                => $first_name,
        'fut_st_nomecompleto'        => $full_name,
        'fut_st_nomefantasiaempresa' => $company_name,
        'fut_st_sobrenome'           => $last_name,
        'leadsourcecode'             => 4, // Eventos
        'ownerid'                    => create_crm_reference('systemuser', $systemuser),
        'yomifirstname'              => $first_name,
        'yomifullname'               => $company_name,
        'yomilastname'               => $last_name,
    ];

    $optional_fields = [
        // 'fut_pl_area'             => 'area',
        // 'fut_pl_nivelhierarquico' => 'nivel_hierarquico',
        'jobtitle'                => 'cargo',
        'telephone1'              => 'telefone',
    ];
    foreach ($optional_fields as $attribute_key => $param_key) {
        if ($value = trim($params[$param_key] ?? '')) {
            $attributes[$attribute_key] = $value;
        }
    }

    if (!empty($params['origem_lead'])) {
        $attributes['leadsourcecode'] = intval($params['origem_lead']);
    }

    $address_map = [
        'end_bairro'      => 'address1_line3',
        'end_cep'         => 'address1_postalcode',
        'end_complemento' => 'address1_line2',
        'end_cidade'      => 'address1_city',
        'end_logradouro'  => 'fut_address1_logradouro',
        'end_numero'      => 'fut_address1_nro',
    ];

    foreach ($address_map as $param_key => $attr_key) {
        if (!empty($params[$param_key])) {
            $attributes[$attr_key] = $params[$param_key];
        }
    }

    if ($builder) {
        return $builder->add_create('lead', $attributes);
    } else {
        return create_crm_entity('lead', $attributes);
    }
}

function generate_registration_number (int $post_id, int $count) {
    $prefix = get_post_meta($post_id, '_ethos_crm:fut_txt_prefixo', true);

    return $prefix . ($count + 1);
}

function get_event_registrations (string $project_id) {
    $registrations = iterate_crm_entities('fut_participante', [
        'filters' => [
            'fut_lk_projeto' => $project_id,
        ],
    ]);

    $account_ids = [];
    foreach ($registrations as $registration) {
        $account_ids[] = $registration->Attributes['fut_lk_contato']->Id;
    }

    return $account_ids;
}

function get_registration_account (array $params): string|null {
    // Case 1. Retrieve UUID from current user's organization
    if (empty($params['cnpj'])) {
        if (($post_id = get_organization_by_user()) && ($account_id = get_post_meta($post_id, '_ethos_crm_account_id', true))) {
            return $account_id;
        } else {
            return null;
        }
    }

    // Case 2. Retrieve UUID from other WordPress organizations
    $posts = get_posts([
        'meta_query' => [
            [ 'key' => 'cnpj', 'value' => $params['cnpj'] ],
        ],
    ]);
    if (!empty($posts) && ($account_id = get_post_meta($posts[0]->ID, '_ethos_crm_account_id', true))) {
        return $account_id;
    }

    // Case 3. Retrieve UUID directly from CRM accounts
    $accounts = get_crm_entities('account', [
        'filters' => [
            'fut_st_cnpjsemmascara' => $params['cnpj'],
        ],
    ]);
    if (!empty($accounts->Entities)) {
        cache_crm_entity($accounts->Entities[0]);
        return $accounts->Entities[0]->Id;
    }

    // Case 4. If account does not exist, return null
    return null;
}

function get_registration_contact (array $params, string|Dynamics_Batch_Reference|null $lead_id = null, bool $allow_creation = true, ?Dynamics_Batch_Builder $builder = null): string|Dynamics_Batch_Reference|null {
    // Case 1. Retrieve UUID from current user's data
    if (($user_id = get_current_user_id()) && ($contact_id = get_user_meta($user_id, '_ethos_crm_contact_id', true))) {
        return $contact_id;
    }

    // Case 2. Retrieve UUID from other WordPress users
    $users = get_users([
        'meta_query' => [
            [ 'key' => 'cpf', 'value' => $params['cpf'] ],
            [ 'key' => '_ethos_crm_contact_id', 'compare' => 'EXISTS', 'value' => true ],
        ],
    ]);
    if (!empty($users) && ($contact_id = get_user_meta($users[0]->ID ?? 0, '_ethos_crm_contact_id', true))) {
        return $contact_id;
    }

    // Case 3. Retrieve UUID directly from CRM contacts
    $contacts = get_crm_entities('contact', [
        'filters' => [
            'fut_st_cpf' => format_cpf(trim($params['cpf'])),
        ],
    ]);
    if (!empty($contacts->Entities)) {
        cache_crm_entity($contacts->Entities[0]);
        return $contacts->Entities[0]->Id;
    }

    // Case 4. If contact does not exist, create it, and return its UUID
    if ($allow_creation) {
        return create_registration_contact($params, $lead_id, $builder);
    } else {
        return null;
    }
}

function get_registration_lead (array $params, ?Dynamics_Batch_Builder $builder = null): string|Dynamics_Batch_Reference|null {
    // Case 1. Retrieve UUID from current user's organization
    if (($post_id = get_organization_by_user()) && ($lead_id = get_post_meta($post_id, '_ethos_crm_lead_id', true))) {
        return $lead_id;
    }

    if (!empty($params['cnpj'])) {
        // Case 2. Retrieve UUID from other WordPress organizations
        $posts = get_posts([
            'meta_query' => [
                [ 'key' => 'cnpj', 'value' => $params['cnpj'] ],
            ],
        ]);
        if (!empty($posts) && ($lead_id = get_post_meta($posts[0]->ID, '_ethos_crm_lead_id', true))) {
            return $lead_id;
        }

        // Case 3. Retrieve UUID directly from CRM leads
        $leads = \hacklabr\batch\get_crm_entities_advanced('lead', [
            'or' => [
                ['field' => 'fut_st_cnpj', 'value' => $params['cnpj']],
                ['field' => 'fut_st_cnpj', 'value' => format_cnpj($params['cnpj'])],
            ],
        ], ['per_page' => 1]);
        if (!empty($leads->Entities)) {
            cache_crm_entity($leads->Entities[0]);
            return $leads->Entities[0]->Id;
        }

        // Case 4. If lead does not exist, create it, and return its UUID
        return create_registration_lead($params, $builder);
    }

    return null;
}

function registrations_are_open (int $post_id): bool {
    $crm_status = get_post_meta($post_id, '_ethos_crm:statuscode', true);
    $event_status = get_post_meta($post_id, '_ethos:event_status', true);

    if ($crm_status !== '1' || $event_status === 'FULL' || $event_status === 'PAST') {
        return false;
    }

    return true;
}

function register_for_event (int $post_id, array $params) {
    global $hl_event_registration;
    $hl_event_registration = create_registration($post_id, $params);

    if ($user_id = get_current_user_id()) {
        $meta_input = [];

        foreach (['area', 'nivel_hierarquico'] as $key) {
            if (!empty($params[$key])) {
                $meta_input[$key] = trim($params[$key]);
            }
        }

        if (!empty($meta_input)) {
            wp_update_user([
                'ID' => $user_id,
                'meta_input' => $meta_input,
            ]);
        }
    }
}
