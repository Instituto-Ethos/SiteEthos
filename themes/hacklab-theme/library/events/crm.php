<?php

namespace hacklabr;

function create_registration (array $params) {
    $systemuser = get_option('systemuser');

    $account_id = get_registration_account($params);
    $contact_id = get_registration_contact($params);
    $project_id = $params['project_id'];

    $attibutes = [
        'fut_lk_contato'   => create_crm_reference('contact', $contact_id),
        'fut_lk_fatura_pf' => create_crm_reference('contact', $contact_id),
        // 'fut_pl_cortesia'  => '',
        'fut_lk_projeto'   => create_crm_reference('fut_projeto', $project_id),
        // 'fut_participanteid' => '',
        'ownerid'          => create_crm_reference('systemuser', $systemuser),
        // 'statecode' => '',
    ];

    if (!empty($account_id)) {
        $attibutes['fut_lk_empresa']           = create_crm_reference('account', $account_id);
        $attibutes['fut_lk_empresa_associada'] = create_crm_reference('account', $account_id);
        $attibutes['fut_lk_fatura_pj']         = create_crm_reference('account', $account_id);
    }

    try {
        $entity_id = create_crm_entity('fut_participante', $attibutes);

        return [
            'status'    => 'success',
            'message'   => 'Entidade criada com sucesso no CRM.',
            'entity_id' => $entity_id,
        ];
    } catch (\Exception $e) {
        return [
            'status'  => 'error',
            'message' => "Erro ao inscrever-se no event {$project_id}: " . $e->getMessage()
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
        'fut_st_cnpjsemmascara'      => $cnpj,
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

    return create_crm_entity('lead', $attributes);
}

function get_event_registrations (int $post_id) {
    $fut_projeto_id = get_post_meta($post_id, 'entity_fut_projeto', true);

    $registrations = iterate_crm_entities('fut_participante', [
        'filters' => [
            'fut_lk_projeto' => $fut_projeto_id,
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

function get_registration_lead (array $params): string {
    // Case 1. Retrieve UUID from current user's organization
    if (($post_id = get_organization_by_user()) && ($lead_id = get_post_meta($post_id, '_ethos_crm_lead_id', true))) {
        return $lead_id;
    }

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
    $leads = get_crm_entities('lead', [
        'filters' => [
            'fut_st_cnpjsemmascara' => $params['cnpj'],
        ],
    ]);
    if (!empty($leads->Entities)) {
        return $leads->Entities[0]->Id;
    }

    // Case 4. If lead does not exist, create it, and return its UUID
    return create_registration_lead($params);
}

function register_for_event (array $params) {
    $post_id = get_the_ID();

    $params['project_id'] = get_post_meta($post_id, '_ethos_crm:fut_pf_id', true);

    create_registration($params);

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
