<?php

namespace hacklabr;

function check_event_availability (int $post_id, string $project_id, string $contact_id): array {
    if (!registrations_are_open($post_id)) {
        return [
            'clear'   => true,
            'status'  => 'error',
            'message' => __('Registrations are closed.', 'hacklabr'),
        ];
    }

    $registration_end = get_post_meta($post_id, '_ethos_crm:fut_dt_data_encerramento_inscricoes', true);
    $current_date = date('c');

    if ($current_date > $registration_end) {
        update_post_meta($post_id, '_ethos:event_status', 'PAST');

        return [
            'clear'   => true,
            'status'  => 'error',
            'message' => __('Registrations are closed.', 'hacklabr'),
        ];
    }

    $registered_ids = get_event_registrations($project_id);
    $filled_slots = count($registered_ids);
    $total_slots = intval(get_post_meta($post_id, '_ethos_crm:fut_int_nrovagastotal', true));

    if ($filled_slots >= $total_slots) {
        update_post_meta($post_id, '_ethos:event_status', 'FULL');

        return [
            'clear'   => true,
            'status'  => 'error',
            'message' => __('Registrations are closed.', 'hacklabr'),
        ];
    } elseif (in_array($contact_id, $registered_ids)) {
        return [
            'clear'   => false,
            'status'  => 'error',
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

    $account_id = get_registration_account($params);
    $contact_id = get_registration_contact($params);

    $availability = check_event_availability($post_id, $project_id, $contact_id);
    if (!empty($availability['status'])) {
        return $availability;
    }

    $attibutes = [
        'fut_lk_contato'        => create_crm_reference('contact', $contact_id),
        'fut_lk_fatura_pf'      => create_crm_reference('contact', $contact_id),
        'fut_pl_cortesia'       => 969830000, // @TODO
        'fut_lk_projeto'        => create_crm_reference('fut_projeto', $project_id),
        'fut_txt_nro_inscricao' => generate_registration_number($post_id, $availability['filled'] ?? 0),
    ];

    if (!empty($account_id)) {
        $attibutes['fut_lk_empresa']           = create_crm_reference('account', $account_id);
        $attibutes['fut_lk_empresa_associada'] = create_crm_reference('account', $account_id);
        $attibutes['fut_lk_fatura_pj']         = create_crm_reference('account', $account_id);
    }

    if (!empty($params['acessibilidade'])) {
        $attibutes['fut_txt_necessidades_especiais'] = trim($params['acessibilidade']);
    }

    try {
        $entity_id = create_crm_entity('fut_participante', $attibutes);

        return [
            'clear'     => true,
            'status'    => 'success',
            'message'   => __('You are successfully registered to this event!', 'hacklabr'),
            'entity_id' => $entity_id,
        ];
    } catch (\Exception $e) {
        return [
            'clear'   => false,
            'status'  => 'error',
            'message' => $e->getMessage(),
        ];
    }
}

function create_registration_contact (array $params) {
    $lead_id = get_registration_lead($params);

    $full_name = trim($params['nome_completo']);
    $name_parts = explode(' ',  $full_name);
    $first_name = $name_parts[0];
    unset($name_parts[0]);
    $last_name = implode(' ', $name_parts);

    $attributes = [
        'emailaddress1'           => trim($params['email']),
        'firstname'               => $first_name,
        'fullname'                => $full_name,
        'fut_pl_area'             => trim($params['area']),
        'fut_pl_nivelhierarquico' => trim($params['nivel_hierarquico']),
        'fut_st_cpf'              => format_cpf(trim($params['cpf'])),
        'jobtitle'                => trim($params['cargo']),
        'lastname'                => $last_name,
        'originatingleadid'       => create_crm_reference('lead', $lead_id),
        'telephone1'              => trim($params['telefone']),
        'yomifirstname'           => $first_name,
        'yomifullname'            => $full_name,
        'yomilastname'            => $last_name,
    ];

    return create_crm_entity('contact', $attributes);
}

function create_registration_lead (array $params) {
    $systemuser = get_option('systemuser');

    $cnpj = trim($params['cnpj']);
    $company_name = trim($params['nome_fantasia']);

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

    if (!empty($params['origem_lead'])) {
        $attributes['leadsourcecode'] = intval($params['origem_lead']);
    }

    return create_crm_entity('lead', $attributes);
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
    if (($post_id = get_organization_by_user()) && ($account_id = get_post_meta($post_id, '_ethos_crm_account_id', true))) {
        return $account_id;
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
        return $accounts->Entities[0]->Id;
    }

    // Case 4. If account does not exist, return null
    return null;
}

function get_registration_contact (array $params): string {
    // Case 1. Retrieve UUID from current user's data
    if (($user_id = get_current_user_id()) && ($contact_id = get_user_meta($user_id, '_ethos_crm_contact_id', true))) {
        return $contact_id;
    }

    // Case 2. Retrieve UUID from other WordPress users
    $users = get_users([
        'meta_query' => [
            [ 'key' => 'cpf', 'value' => $params['cpf'] ],
        ],
    ]);
    if (!empty($users) && ($contact_id = get_user_meta($users[0]->ID, '_ethos_crm_contact_id', true))) {
        return $contact_id;
    }

    // Case 3. Retrieve UUID directly from CRM contacts
    $contacts = get_crm_entities('contact', [
        'filters' => [
            'emailaddress1' => $params['email'],
        ],
    ]);
    if (!empty($contacts->Entities)) {
        return $contacts->Entities[0]->Id;
    }

    // Case 4. If contact does not exist, create it, and return its UUID
    return create_registration_contact($params);
}

function get_registration_lead (array $params): string|null {
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
        // $leads = get_crm_entities('lead', [
        //     'filters' => [
        //         'fut_st_cnpjsemmascara' => $params['cnpj'],
        //     ],
        // ]);
        // if (!empty($leads->Entities)) {
        //     return $leads->Entities[0]->Id;
        // }
    }

    // Case 4. If lead does not exist, create it, and return its UUID
    // return create_registration_lead($params);
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
        wp_update_user([
            'ID' => $user_id,
            'meta_input' => [
                'area'              => trim($params['area']),
                'nivel_hierarquico' => trim($params['nivel_hierarquico']),
            ],
        ]);
    }
}
