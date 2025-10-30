<?php

namespace ethos\crm;

use \AlexaCRM\Xrm\Entity;

/**
 * Better than calling `array_filter` than `array_unique` because the latter
 * preserve keys
 */
function array_unique_values( array $array ) {
    $return = [];

    foreach ( $array as $el ) {
        if ( ! empty( $el ) && ! in_array( $el, $return ) ) {
            $return[] = $el;
        }
    }

    return $return;
}

function sanitize_number( string $string ) {
    if ( empty( $string ) ) {
        return '';
    }
    return str_replace( [ '+', '-' ], '', filter_var( $string, FILTER_SANITIZE_NUMBER_INT ) );
}

function generate_unique_email( string $email, Entity $account ) {
    $email_parts = explode( '@', $email );
    $folder = sanitize_title( $account->Attributes['name'] );
    return $email_parts[0] . '+' . $folder . '@' . $email_parts[1];
}

function is_parent_company( Entity $account ) {
    return strlen( $account->Attributes['fut_txt_childnode'] ?? '' ) > 169;
}

function is_subsidiary_company( Entity $account ) {
    return $account->Attributes['fut_bt_pertencegrupo'] ?? false;
}

function compute_contact_role( Entity $contact ) {
    $attributes = $contact->Attributes;

    if ( $attributes['fut_bt_principal'] ?? false ) {
        return 'primary';
    } elseif ( $attributes['fut_bt_financeiro'] ?? false ) {
        return 'financial';
    } else {
        return 'secondary';
    }
}

function get_account_by_contact( Entity $contact ) {
    $account_id = $contact->Attributes['parentcustomerid']?->Id ?? null;
    if ( empty( $account_id ) ) {
        return null;
    }
    return \hacklabr\get_crm_entity_by_id( 'account', $account_id ) ?? null;
}

function is_active_account( Entity $account ) {
    $account_status = $account->FormattedValues['fut_pl_associacao'] ?? '';
    return in_array( $account_status, ['Associado', 'Grupo Econômico'] );
}

function is_active_contact( Entity $contact, Entity|null $account = null ) {
    if ( ! ContactStatus::isActive( $contact->Attributes['statecode'] ) ) {
        return false;
    }

    if ( empty( $account ) ) {
        $account = get_account_by_contact( $contact );

        if ( empty( $account ) ) {
            return false;
        }
    }
    return is_active_account( $account );
}

function parse_account_into_post_meta( Entity $account ) {
    $account_id = $account->Id;
    $attributes = $account->Attributes;
    $formatted = $account->FormattedValues;

    $revenue_base = $attributes['revenue_base'] ?? 0;
    if ( $revenue_base < 10_000_000 ) {
        $revenue = 'small';
    } elseif ( $revenue_base < 300_000_000 ) {
        $revenue = 'medium';
    } else {
        $revenue = 'large';
    }

    $size = CompanySize::toSlug( $attributes['fut_pl_porte'] ?? null );

    if ( ! empty( $attributes['entityimage_url'] ) ) {
        $logo = \hacklabr\get_crm_server_url() . $attributes['entityimage_url'];
    } else {
        $logo = '';
    }

    $logradouro = trim( $attributes['fut_address1_logradouro'] ?? '' );
    if ( ! empty( $attributes['fut_lk_tipologradouro']?->Name ) ) {
        $logradouro_prefix = trim( $attributes['fut_lk_tipologradouro']->Name );

        if ( ! str_starts_with( strtolower( $logradouro ), strtolower( $logradouro_prefix ) ) ) {
            $logradouro = $logradouro_prefix . ' ' . $logradouro;
        }
    }

    if ( ! empty( $attributes['originatingleadid'] ) ) {
        $lead_id = $attributes['originatingleadid']->Id;
    } else {
        $lead_id = null;
    }

    $post_meta = [
        '_ethos_crm_account_id' => $account_id,
        '_ethos_crm_lead_id' => $lead_id,

        'cnpj' => trim( $attributes['fut_st_cnpjsemmascara'] ?? '' ),
        'razao_social' => trim( $attributes['fut_st_razaosocial'] ?? '' ),
        'nome_fantasia' => trim( $attributes['name'] ?? '' ),
        'segmento' => trim( $attributes['fut_lk_setor']?->Name ?? '' ),
        'cnae' => sanitize_number( $formatted['fut_lk_cnae'] ?? '' ),
        'faturamento_anual' => $revenue,
        'inscricao_estadual' => trim( $attributes['fut_st_inscricaoestadual'] ?? '' ),
        'inscricao_municipal' => trim( $attributes['fut_st_inscricaomunicipal'] ?? '' ),
        'logomarca' => $logo,
        'website' => trim( $attributes['websiteurl'] ?? '' ),
        'num_funcionarios' => $attributes['numberofemployees'] ?? 0,
        'porte' => $size,
        'end_logradouro' => $logradouro,
        'end_numero' => trim( $attributes['fut_address1_nro'] ?? '' ),
        'end_complemento' => trim( $attributes['address1_line2'] ?? '' ),
        'end_bairro' => trim( $attributes['address1_line3'] ?? '' ),
        'end_cidade' => trim( $attributes['address1_city'] ?? '' ),
        'end_estado' => $formatted['fut_pl_estado'] ?? '',
        'end_cep' => sanitize_number( $attributes['address1_postalcode'] ?? '' ),
    ];

    foreach ( $attributes as $key => $value ) {
        $meta_key = '_ethos_crm:' . $key;
        $meta_value = \hacklabr\format_meta_value( $value );
        if ( $meta_value !== null ) {
            $post_meta[ $meta_key ] = $meta_value;
        }
    }

    return $post_meta;
}

function parse_contact_into_user_meta( Entity $contact, Entity|null $account ) {
    $contact_id = $contact->Id;
    $attributes = $contact->Attributes;
    $formatted = $contact->FormattedValues;

    $phones = [
        sanitize_number( $attributes['mobilephone'] ?? '' ),
        sanitize_number( $attributes['telephone1'] ?? '' ),
        sanitize_number( $attributes['telephone2'] ?? '' ),
    ];
    $phones = array_unique_values( $phones );

    $email = trim( $attributes['emailaddress1'] ?? '@' );
    if ( ! empty( $account ) && is_subsidiary_company( $account ) ) {
        $email = generate_unique_email( $email, $account );
    }

    $user_meta = [
        '_ethos_crm_account_id' => $attributes['parentcustomerid']?->Id ?? '',
        '_ethos_crm_contact_id' => $contact_id,
        '_pmpro_role' => compute_contact_role( $contact ),

        'nome_completo' => trim( $attributes['fullname'] ?? '' ),
        'cpf' => sanitize_number( $attributes['fut_st_cpf'] ?? '' ),
        'cargo' => trim( $attributes['jobtitle'] ?? '' ),
        'area' => trim( $formatted['fut_pl_area'] ?? '' ),
        'email' => $email,
        'celular' => $phones[ 0 ] ?? '',
        'celular_is_whatsapp' => '',
        'telefone' => $phones[ 1 ] ?? '',
    ];

    foreach ( $attributes as $key => $value ) {
        $meta_key = '_ethos_crm:' . $key;
        $meta_value = \hacklabr\format_meta_value( $value );
        if ( $meta_value !== null ) {
            $user_meta[ $meta_key ] = $meta_value;
        }
    }

    return $user_meta;
}

function delete_from_account( \WP_Post $post ) {
    // Required for using `wp_delete_user` function
    require_once( ABSPATH . 'wp-admin/includes/user.php' );

    global $wpdb;

    $group_id = (int) get_post_meta( $post->ID, '_pmpro_group', true );

    $wpdb->delete( $wpdb->prefix . 'pmprogroupacct_group_members', [
        'group_id' => $group_id,
    ], [ '%d' ] );

    $wpdb->delete( $wpdb->prefix . 'pmprogroupacct_group', [
        'id' => $group_id,
    ], [ '%d' ] );

    $users = get_users( [
        'meta_query' => [
            [ 'key' => '_pmpro_group', 'value' => $group_id ],
        ],
    ] );

    foreach ( $users as $user ) {
        wp_delete_user( $user->ID, null );
    }

    wp_delete_post( $post->ID, true );
}

function create_from_account( Entity $account ) {
    $attributes = $account->Attributes;

    $post_meta = parse_account_into_post_meta( $account );
    $post_meta['_ethos_from_crm'] = 1;

    $post_parent = 0;
    if ( is_subsidiary_company( $account ) ) {
        $post_parent = get_post_id_by_account( $attributes['parentaccountid']->Id ) ?? 0;
    }

    $post_id = wp_insert_post( [
        'post_type' => 'organizacao',
        'post_title' => $post_meta['nome_fantasia'],
        'post_content' => '',
        'post_status' => 'publish',
        'post_parent' => $post_parent,
        'meta_input' => $post_meta,
    ] );

    // @TODO Set featured image

    if ( ! empty( $attributes['primarycontactid'] ) && ! empty( $attributes['fut_pl_tipo_associacao'] ) ) {
        $group_id = create_primary_contact( $post_id, $account );

        if ( ! empty( $attributes['fut_lk_contato_alternativo'] ) || ! empty( $attributes['fut_lk_contato_alternativo2'] ) ) {
            create_secondary_contacts( $account, $group_id );
        }

        if ( ! empty( $attributes['i4d_aprovador_cortesia'] ) ) {
            create_approver( $account, $group_id );
        }
    }

    do_action( 'ethos_crm:log', "Created post with ID = $post_id", 'debug' );

    return $post_id;
}

function add_user_to_group( int $user_id, int $group_id ) {
    try {
        $membership = \hacklabr\add_user_to_pmpro_group( $user_id, $group_id );

        update_user_meta( $user_id, '_pmpro_group', $group_id );

        \hacklabr\approve_user( $user_id, $membership->group_child_level_id );
    } catch ( \Throwable $err ) {}

    return $group_id;
}

function create_primary_contact( int $post_id, Entity $account ) {
    $account_id = $account->Id;
    $attributes = $account->Attributes;

    $user_id = get_contact( $attributes['primarycontactid']->Id, $account_id ) ?? 0;

    $revenue = get_post_meta( $post_id, 'faturamento_anual', true ) ?: 'small';
    $level_id = Plan::from( $attributes['fut_pl_tipo_associacao'] )->toLevel( $revenue, true );

    $group = \hacklabr\create_pmpro_group( $user_id, $level_id );

    wp_update_user([
        'ID' => $user_id,
        'meta_input' => [
            '_ethos_admin' => '1',
            '_pmpro_group' => $group->id,
            '_pmpro_role' => 'primary',
        ],
    ]);

    wp_update_post([
        'ID' => $post_id,
        'post_author' => $user_id,
        'meta_input' => [
            '_pmpro_group' => $group->id,
        ],
    ]);

    \hacklabr\approve_user( $user_id, $level_id );

    return (int) $group->id;
}

function create_secondary_contacts( Entity $account, int $group_id ) {
    $account_id = $account->Id;
    $attributes = $account->Attributes;

    if ( ! empty( $attributes['fut_lk_contato_alternativo'] ) ) {
        $user_id = get_contact( $attributes['fut_lk_contato_alternativo']->Id, $account_id ) ?? 0;
        add_user_to_group( $user_id, $group_id );
        update_user_meta( $user_id, '_ethos_admin', 1 );
    }

    if ( ! empty( $attributes['fut_lk_contato_alternativo2'] ) ) {
        $user_id = get_contact( $attributes['fut_lk_contato_alternativo2']->Id, $account_id ) ?? 0;
        add_user_to_group( $user_id, $group_id );
        update_user_meta( $user_id, '_ethos_admin', 1 );
    }
}

function create_approver( Entity $account, int $group_id ) {
    $account_id = $account->Id;
    $attributes = $account->Attributes;

    $user_id = get_contact( $attributes['i4d_aprovador_cortesia']->Id, $account_id ) ?? 0;
    add_user_to_group( $user_id, $group_id );
    update_user_meta( $user_id, '_ethos_approver', 1 );
}

function update_from_account( Entity $account, \WP_Post $post ) {
    $post_meta = parse_account_into_post_meta( $account );

    $post_id = wp_update_post( [
        'ID' => $post->ID,
        'post_title' => $post_meta['nome_fantasia'],
        'meta_input' => $post_meta,
    ] );

    $group_id = (int) get_post_meta( $post_id, '_pmpro_group', true );
    $group = \hacklabr\get_pmpro_group( $group_id );

    $attributes = $account->Attributes;

    /**
     * Updates the primary contact, secondary contacts, and approver for a PMPro group based on the provided account information.
     *
     * This function is responsible for managing the user associations for a PMPro group based on the data from the provided account entity.
     * It will update the primary contact, add or remove secondary contacts, and update the approver for the group as necessary.
     *
     * @param Entity $account The account entity containing the updated contact information.
     * @param \PMProGroupAcct_Group $group The PMPro group to update.
     */
    if ( ! empty( $group ) ) {
        if ( ! empty( $attributes['primarycontactid'] ) ) {
            replace_primary_contact( $account, $group );
        }

        if ( ! empty( $attributes['fut_lk_contato_alternativo'] ) || ! empty( $attributes['fut_lk_contato_alternativo2'] ) ) {
            replace_secondary_contacts( $account, $group );
        }

        if ( ! empty( $attributes['i4d_aprovador_cortesia'] ) ) {
            replace_approver( $account, $group );
        }
    }

    return $post_id;
}

function replace_primary_contact( Entity $account, \PMProGroupAcct_Group $group ) {
    $account_id = $account->Id;
    $attributes = $account->Attributes;

    $current_parent = $group->group_parent_user_id;

    $user_id = get_contact( $attributes['primarycontactid']->Id, $account_id ) ?? 0;
    update_user_meta( $user_id, '_ethos_admin', 1 );

    if ( $current_parent !== $user_id ) {
        \hacklabr\update_group_parent( $group->id, $user_id );
    }
}

function replace_secondary_contacts( Entity $account, \PMProGroupAcct_Group $group ) {
    $account_id = $account->Id;
    $attributes = $account->Attributes;

    $group_id = $group->id;
    $current_parent = $group->group_parent_user_id;
    $current_parent_contact = get_user_meta( $current_parent, '_ethos_crm_contact_id', true );

    $new_contacts = [
        $attributes['fut_lk_contato_alternativo']?->Id ?? null,
        $attributes['fut_lk_contato_alternativo2']?->Id ?? null,
    ];
    $new_contacts = array_unique_values( $new_contacts );

    foreach ( $new_contacts as $new_contact ) {
        $user_id = get_contact( $new_contact, $account_id );
        add_user_to_group( $user_id, $group_id );
        update_user_meta( $user_id, '_ethos_admin', 1 );
    }

    $new_contacts[] = $current_parent_contact;

    $old_users = get_users([
        'meta_query' => [
            [ 'key' => '_pmpro_group', 'value' => $group_id ],
            [ 'key' => '_ethos_admin', 'value' => 1 ],
            [ 'key' => '_ethos_crm_contact_id', 'compare' => 'NOT IN', 'value' => $new_contacts ],
        ],
    ]);

    foreach ( $old_users as $old_user ) {
        delete_user_meta( $old_user->ID, '_ethos_admin' );
    }
}

function replace_approver( Entity $account, \PMProGroupAcct_Group $group ) {
    $account_id = $account->Id;
    $attributes = $account->Attributes;

    $group_id = $group->id;

    $old_approver = get_single_user([
        'meta_query' => [
            [ 'key' => '_pmpro_group', 'value' => $group_id ],
            [ 'key' => '_ethos_approver', 'value' => 1 ],
        ],
    ]);

    $new_approver = get_contact( $attributes['i4d_aprovador_cortesia']->Id, $account_id );

    if ( $old_approver?->ID != $new_approver ) {
        if ( ! empty( $old_approver ) ) {
            delete_user_meta( $old_approver->ID, '_ethos_approver' );
        }

        update_user_meta( $new_approver, '_ethos_approver', 1 );
    }
}

function import_account( Entity $account, bool $force_update = false ) {
    $account_id = $account->Id;

    $account_name = $account->Attributes['name'] ?? '';

    $existing_post = get_single_post( [
        'post_type' => 'organizacao',
        'meta_query' => [
            [ 'key' => '_ethos_crm_account_id', 'value' => $account_id ],
        ],
    ] );

    if ( empty( $existing_post ) ) {
        if ( is_active_account( $account ) ) {
            do_action( 'ethos_crm:log', "Creating account $account_name - $account_id", 'debug' );
            create_from_account( $account );
        } else {
            do_action( 'ethos_crm:log', "Skipping account $account_name - $account_id", 'debug' );
        }
    } else {
        if ( is_active_account( $account ) ) {
            if ( $force_update ) {
                do_action( 'ethos_crm:log', "Updating account $account_name - $account_id", 'debug' );
                update_from_account( $account, $existing_post );
            } else {
                do_action( 'ethos_crm:log', "Skipping account $account_name - $account_id", 'debug' );
            }
        } else {
            do_action( 'ethos_crm:log', "Deleting account $account_name - $account_id", 'debug' );
            delete_from_account( $existing_post );
        }
    }
}

function delete_from_contact( \WP_User $user ) {
    // Required for using `wp_delete_user` function
    require_once( ABSPATH . 'wp-admin/includes/user.php' );

    global $wpdb;

    $wpdb->delete( $wpdb->prefix . 'pmprogroupacct_group_members', [
        'group_child_user_id' => $user->ID,
    ], ['%d'] );

    wp_delete_user( $user->ID, null );
}

function create_from_contact( Entity $contact, Entity $account ) {
    $user_meta = parse_contact_into_user_meta( $contact, $account );
    $user_meta['_ethos_from_crm'] = 1;

    $existing_user_by_email = get_user_by( 'email', $user_meta['email'] );

    if ( empty( $existing_user_by_email ) ) {
        $password = wp_generate_password( 16 );

        $user_id = wp_insert_user( [
            'display_name' => $user_meta['nome_completo'],
            'user_email' => $user_meta['email'],
            'user_login' => generate_unique_user_login( $user_meta['nome_completo'] ),
            'user_pass' => $password,
            'role' => 'subscriber',
            'meta_input' => $user_meta,
        ] );

        if ( ! is_wp_error( $user_id ) ) {
            do_action( 'ethos_crm:create_user', $user_id, $account );
        }
    } else {
        $user_id = wp_update_user( [
            'ID' => $existing_user_by_email->ID,
            'meta_input' => $user_meta,
        ] );
    }

    if ( is_wp_error( $user_id ) ) {
        do_action( 'ethos_crm:log', $user_id->get_error_message(), 'error' );
        return null;
    }

    $post_id = get_post_id_by_account( $account->Id );
    $group_id = (int) get_post_meta( $post_id, '_pmpro_group', true );
    add_user_to_group( $user_id, $group_id );

    if ( empty( $existing_user_by_email ) ) {
        do_action( 'ethos_crm:log', "Created user with ID = $user_id", 'debug' );
    } else {
        do_action( 'ethos_crm:log', "Upgraded user with ID = $user_id", 'debug' );
    }

    return $user_id;
}

function update_from_contact( Entity $contact, Entity $account, \WP_User $user ) {
    $user_meta = parse_contact_into_user_meta( $contact, $account );

    $user_id = wp_update_user( [
        'ID' => $user->ID,
        'display_name' => $user_meta['nome_completo'],
        'user_email' => $user_meta['email'],
        'meta_input' => $user_meta,
    ] );

    return $user_id;
}

function import_contact( Entity $contact, Entity|null $account = null, bool $force_update = false ) {
    $contact_id = $contact->Id;
    $attributes = $contact->Attributes;

    $contact_name = $attributes['fullname'] ?? '';

    if ( empty( $account ) ) {
        $account = get_account_by_contact( $contact );

        if ( empty( $account ) ) {
            do_action( 'ethos_crm:log', "Skipping contact $contact_name - $contact_id", 'debug' );
            return null;
        }
    }

    $existing_user = get_single_user( [
        'meta_query' => [
            [ 'key' => '_ethos_crm_account_id', 'value' => $account->Id ],
            [ 'key' => '_ethos_crm_contact_id', 'value' => $contact_id ],
        ],
    ] );

    if ( empty( $existing_user ) ) {
        if ( is_active_contact( $contact ) ) {
            do_action( 'ethos_crm:log', "Creating contact $contact_name - $contact_id", 'debug' );
            create_from_contact( $contact, $account );
        } else {
            do_action( 'ethos_crm:log', "Skipping contact $contact_name - $contact_id", 'debug' );
        }
    } else {
        if ( is_active_contact( $contact ) ) {
            if ( $force_update ) {
                do_action( 'ethos_crm:log', "Updating contact $contact_name - $contact_id", 'debug' );
                update_from_contact( $contact, $account, $existing_user );
            } else {
                do_action( 'ethos_crm:log', "Skipping contact $contact_name - $contact_id", 'debug' );
            }
        } else {
            do_action( 'ethos_crm:log', "Deleting contact $contact_name - $contact_id", 'debug' );
            delete_from_contact( $existing_user );
        }
    }
}

function import_fut_projeto (Entity $entity) {
    $post_id = \hacklabr\event_exists_on_wp( $entity->Id );

    if ( empty( $post_id ) ) {
        \hacklabr\create_event_on_wp( $entity );
    } else {
        \hacklabr\update_event_on_wp( $post_id, $entity );
    }
}
