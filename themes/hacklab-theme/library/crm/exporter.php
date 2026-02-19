<?php

namespace ethos\crm;

function get_meta( $meta, $key, $fallback = '' ) {
    return $meta[ $key ][0] ?? $fallback;
}

function map_account_attributes( int $post_id ) {
    $systemuser = get_option( 'systemuser' );

    $post_meta = get_post_meta( $post_id );
    $is_imported = get_meta( $post_meta, '_ethos_from_crm' ) ?? false;

    $company_name = get_meta( $post_meta, 'nome_fantasia' );

    $attributes = [
        'ownerid'                   => \hacklabr\create_crm_reference( 'systemuser', $systemuser ),
        'address1_city'             => get_meta( $post_meta, 'end_cidade' ),
        'address1_line2'            => get_meta( $post_meta, 'end_complemento' ),
        'address1_line3'            => get_meta( $post_meta, 'end_bairro' ),
        'address1_postalcode'       => get_meta( $post_meta, 'end_cep' ),
        'entityimage_url'           => get_the_post_thumbnail_url( $post_id ),
        'fut_address1_logradouro'   => get_meta( $post_meta, 'end_logradouro' ),
        'fut_address1_nro'          => get_meta( $post_meta, 'end_numero' ),
        'fut_st_cnpj'               => get_meta( $post_meta, 'cnpj' ),
        'fut_st_complementoorigem'  => get_meta( $post_meta, 'segmento' ),
        'fut_st_inscricaoestadual'  => get_meta( $post_meta, 'inscricao_estadual' ),
        'fut_st_inscricaomunicipal' => get_meta( $post_meta, 'inscricao_municipal' ),
        'fut_st_razaosocial'        => get_meta( $post_meta, 'razao_social' ),
        'fut_pl_estado'             => BrazilianUF::fromCode( get_meta( $post_meta, 'end_estado', null ) ),
        'name'                      => $company_name,
        'numberofemployees'         => floatval( get_meta( $post_meta, 'num_funcionarios', 0 ) ),
        'revenue_base'              => floatval( get_meta( $post_meta, 'faturamento_anual', 0 ) ),
        'websiteurl'                => get_meta( $post_meta, 'website' ),
        'yominame'                  => $company_name,
    ];

    $group_id = get_meta( $post_meta, '_pmpro_group', null );
    if ( ! empty( $group_id ) ) {
        $group = \hacklabr\get_pmpro_group( $group_id );
        if ( ! empty( $group ) ) {
            $attributes['fut_pl_tipo_associacao'] = Plan::fromLevel( $group->group_parent_level_id )->value;
        }
    }

    if ( ! $is_imported ) {
        if ( $porte = get_meta( $post_meta, 'porte' ) ) {
            $attributes['fut_pl_porte'] = CompanySize::fromSlug( $porte );
        }
    }

    return $attributes;
}

function map_contact_attributes( int $user_id, int|null $post_id = null ) {
    $user_meta = get_user_meta( $user_id );
    $is_imported = get_meta( $user_meta, '_ethos_from_crm' ) ?? false;

    $full_name = get_meta( $user_meta, 'nome_completo' );

    $name_parts = explode( ' ', $full_name );
    $first_name = $name_parts[0];
    unset( $name_parts[0] );
    $last_name = implode( ' ', $name_parts );

    $email = get_meta( $user_meta, 'email' );

    $attributes = [
        'emailaddress1' => $email,
        'firstname'     => $first_name,
        'fullname'      => $full_name,
        'fut_st_cpf'    => get_meta( $user_meta, 'cpf' ),
        'jobtitle'      => get_meta( $user_meta, 'cargo' ),
        'lastname'      => $last_name,
        'mobilephone'   => get_meta( $user_meta, 'celular' ),
        'telephone1'    => get_meta( $user_meta, 'telefone' ),
        'yomifirstname' => $first_name,
        'yomifullname'  => $full_name,
        'yomilastname'  => $last_name,
    ];

    if ( $is_imported ) {
        // Contact was originally imported from CRM
        if ( str_contains( $email, '+' ) ) {
            unset( $attributes['emailaddress1'] );
        }
    } else {
        // Contact was originally created in WordPress
        $role = get_meta( $user_meta, '_pmpro_role' );
        $attributes['fut_bt_principal'] = $role === 'primary';
        $attributes['fut_bt_financeiro'] = $role === 'financial';
    }

    if ( ! empty( $post_id ) ) {
        $account_id = get_post_meta( $post_id, '_ethos_crm_account_id', true );
        if ( ! empty( $account_id ) ) {
            $attributes['parentcustomerid'] = \hacklabr\create_crm_reference( 'account', $account_id );
        }

        $lead_id = get_post_meta( $post_id, '_ethos_crm_lead_id', true );
        if ( ! empty( $lead_id ) ) {
            $attributes['originatingleadid'] = \hacklabr\create_crm_reference( 'lead', $lead_id );
        }
    }

    return $attributes;
}

function map_lead_attributes( int $post_id ) {
    $systemuser = get_option( 'systemuser' );

    $author_id = get_post_field( 'post_author', $post_id );
    $author_name = get_the_author_meta( 'display_name', $author_id );

    $name_parts = explode( ' ', $author_name );
    $first_name = $name_parts[0];
    unset( $name_parts[0] );
    $last_name = implode( ' ', $name_parts );

    $post_meta = get_post_meta( $post_id );

    $company_name = get_meta( $post_meta, 'nome_fantasia' );

    $attributes = [
        'ownerid'                    => \hacklabr\create_crm_reference( 'systemuser', $systemuser ),
        'address1_city'              => get_meta( $post_meta, 'end_cidade' ),
        'address1_line2'             => get_meta( $post_meta, 'end_complemento' ),
        'address1_line3'             => get_meta( $post_meta, 'end_bairro' ),
        'address1_postalcode'        => get_meta( $post_meta, 'end_cep' ),
        'companyname'                => $company_name,
        'entityimage_url'            => get_the_post_thumbnail_url( $post_id ),
        'firstname'                  => $company_name,
        'fullname'                   => $company_name,
        'fut_address1_logradouro'    => get_meta( $post_meta, 'end_logradouro' ),
        'fut_address1_nro'           => get_meta( $post_meta, 'end_numero' ),
        'fut_st_cnpj'                => \hacklabr\format_cnpj( get_meta( $post_meta, 'cnpj' ) ),
        'fut_st_complementoorigem'   => get_meta( $post_meta, 'segmento' ),
        'fut_st_inscricaoestadual'   => get_meta( $post_meta, 'inscricao_estadual' ),
        'fut_st_inscricaomunicipal'  => get_meta( $post_meta, 'inscricao_municipal' ),
        'fut_st_nome'                => $first_name,
        'fut_st_nomecompleto'        => $author_name,
        'fut_st_nomefantasiaempresa' => $company_name,
        'fut_st_sobrenome'           => $last_name,
        'leadsourcecode'             => 6, // Outros
        'websiteurl'                 => get_meta( $post_meta, 'website' ),
        'yomifirstname'              => $first_name,
        'yomifullname'               => $company_name,
        'yomilastname'               => $last_name,
    ];

    return $attributes;
}

function create_contact( int $user_id, int $post_id ) {
    $account_id = get_post_meta( $post_id, '_ethos_crm_account_id', true ) ?? null;
    $lead_id = get_post_meta( $post_id, '_ethos_crm_lead_id', true ) ?? null;

    try {
        $attributes = map_contact_attributes( $user_id, $post_id );

        $contact_id = \hacklabr\create_crm_entity( 'contact', $attributes );

        wp_update_user( [
            'ID' => $user_id,
            'meta_input' => [
                '_ethos_crm_account_id' => $account_id,
                '_ethos_crm_contact_id' => $contact_id,
                '_ethos_crm_lead_id' => $lead_id,
            ],
        ] );

        return $contact_id;
    } catch ( \Throwable $err ) {
        do_action( 'logger', $err->getMessage() );
    }

    return null;
}

function create_lead( int $post_id ) {
    $result = \hacklabr\send_lead_to_crm( $post_id );

    if ( $result['status'] === 'success' ) {
        return $result['entity_id'];
    } else {
        return null;
    }
}

function update_account( int $post_id, string $account_id ) {
    try {
        $attributes = map_account_attributes( $post_id );

        unset( $attributes['fut_pl_porte'] );
        unset( $attributes['ownerid'] );

        \hacklabr\update_crm_entity( 'account', $account_id, $attributes );
    } catch ( \Throwable $err ) {
        do_action( 'logger', [
            'message' => $err->getMessage(),
            'file'    => $err->getFile(),
            'line'    => $err->getLine(),
            'post_id' => $post_id,
        ] );
    }
}

function update_contact( int $user_id, int|null $post_id = null ) {
    $contact_id = get_user_meta( $user_id, '_ethos_crm_contact_id', true ) ?? null;

    if ( empty( $contact_id ) ) {
        return;
    }

    try {
        $attributes = map_contact_attributes( $user_id, $post_id );

        \hacklabr\update_crm_entity( 'contact', $contact_id, $attributes );
    } catch ( \Throwable $err ) {
        do_action( 'logger', $err->getMessage() );
    }
}

function update_lead( int $post_id, string $lead_id ) {
    try {
        $attributes = map_lead_attributes( $post_id );

        unset( $attributes['ownerid'] );

        \hacklabr\update_crm_entity( 'lead', $lead_id, $attributes );
    } catch ( \Throwable $err ) {
        do_action( 'logger', $err->getMessage() );
    }
}

function update_organization( int $post_id ) {
    $account_id = get_post_meta( $post_id, '_ethos_crm_account_id', true ) ?? null;
    $lead_id = get_post_meta( $post_id, '_ethos_crm_lead_id', true ) ?? null;

    if ( ! empty( $account_id ) ) {
        update_account( $post_id, $account_id );
    } else if ( ! empty( $lead_id ) ) {
        update_lead( $post_id, $lead_id );
    }
}
