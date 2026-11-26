<?php

namespace hacklabr;

use \ethos\crm\Plan;

/**
 * Nível PMPro neutro do novo modelo de associação.
 *
 * No novo modelo o plano contratado vive no CRM (parceria) e o nível do
 * grupo PMPro não carrega semântica: serve apenas de encanamento para o
 * PMProGroupAcct (coluna group_parent_level_id), o fluxo de aprovação e o
 * guard de login. 11 = "Institucional Micro (gerente)": preço 0, sem
 * billing, válido para qualquer porte (o fluxo novo não deriva nível por
 * porte). Consumers de plano devem usar get_pmpro_plan() (híbrido: parceria
 * no CRM → meta de interesse → legado por nível), nunca o nível diretamente.
 *
 * Usado pelo wizard (step 3) e pelo importer (dual-read) — mesma peça.
 */
const ETHOS_NEUTRAL_PMPRO_LEVEL = 11;

/**
 * Normaliza um GUID do Dynamics (case, chaves, espaços) para comparação
 * estável entre fontes distintas: OData do wizard, lookup do sync e meta
 * _ethos_plano_interesse.
 */
function normalizar_guid ($guid): string {
    $guid = strtolower( trim( (string) $guid ) );

    return trim( $guid, '{}' );
}

function add_user_to_pmpro_group (int $user_id, int $group_id) {
    $group = get_pmpro_group($group_id);

    $parent_level_id = $group->group_parent_level_id;
    $child_level_id = get_pmpro_child_level($parent_level_id);

    $existing_member = \PMProGroupAcct_Group_Member::get_group_members([
		'group_child_user_id'  => $user_id,
		'group_child_level_id' => $child_level_id,
		'group_id'             => $group_id,
    ]);

    if (!empty($existing_member)) {
        $membership = $existing_member[0];
    } else {
        $membership = \PMProGroupAcct_Group_Member::create($user_id, $child_level_id, $group->id);
    }

    assert($membership instanceof \PMProGroupAcct_Group_Member);

    // Avoid retriggering PMPro side effects for users that already have this level.
    $current_level = \pmpro_getMembershipLevelForUser($user_id);
    $current_level_id = (int) ($current_level->id ?? 0);

    if ($current_level_id !== $child_level_id) {
        \pmpro_changeMembershipLevel($child_level_id, $user_id);
    }

    return $membership;
}

function approve_user( int $user_id, int $level_id ) {
    // Re-approving an already approved member can resend PMPro emails.
    if (\PMPro_Approvals::isApproved($user_id, $level_id)) {
        return true;
    }

    return \PMPro_Approvals::approveMember( $user_id, $level_id, true );
}

function calculate_membership_price (int $group_id) {
    $group = get_pmpro_group($group_id);

    $users = get_users( [
        'meta_query' => [
            ['key' => '_pmpro_group', 'value' => $group_id ],
        ],
    ] );

    $total = 0;

    $level = \pmpro_getLevel($group->group_parent_level_id);

    if (!empty($level)) {

        $total += $level->initial_payment ?: 0;

        if (count($users) > 1){
            $child_level_id = get_pmpro_child_level($group->group_parent_level_id);
            $child_level = \pmpro_getLevel($child_level_id);

            $total += (count($users) - 1) * ($child_level->initial_payment ?: 0);
        }
    }

    return $total;
}

function create_pmpro_group (int $user_id, int $level_id = 11) {
    $group = \PMProGroupAcct_Group::get_group_by_parent_user_id_and_parent_level_id($user_id, $level_id);

    if (empty($group)) {
        $group = \PMProGroupAcct_Group::create($user_id, $level_id, 100);
    }

    assert($group instanceof \PMProGroupAcct_Group);

    \pmpro_changeMembershipLevel($level_id, $user_id);

    return $group;
}

function get_pmpro_group (int $group_id) {
    return new \PMProGroupAcct_Group($group_id);
}

function get_pmpro_child_level (int $level_id) {
    if ($level_id === 8 || $level_id === 9) {
        return $level_id + 12;
    } else {
        return $level_id;
    }
}

function get_pmpro_level_options (int $organization_id, bool $for_manager = true) {
    $revenue = get_post_meta($organization_id, 'faturamento_anual', true) ?: 'small';

    return [
        'conexao' => Plan::Conexao->toLevel($revenue, $for_manager),
        'essencial' => Plan::Essencial->toLevel($revenue, $for_manager),
        'vivencia' => Plan::Vivencia->toLevel($revenue, $for_manager),
        'institucional' => Plan::Institucional->toLevel($revenue, $for_manager),
    ];
}

function get_plan_slug_from_crm (int $user_id): ?string {
    /*
     * Novo modelo: plano da parceria no CRM, via saldo da organização do
     * usuário (plugin de integração, com cache curto). Janela de transição
     * (cadastro no site → parceria fechada no CRM): plano de interesse
     * gravado pelo wizard. Sem plugin ativo, sem organização ou sem ambos
     * → null (cai no legado).
     */
    if ( ! function_exists( 'hacklabr\consultar_saldo' ) ) {
        return null;
    }

    $organization = get_organization_by_user( $user_id );

    if ( empty( $organization ) ) {
        return null;
    }

    $cnpj = get_post_meta( $organization->ID, 'cnpj', true );

    if ( ! empty( $cnpj ) ) {
        $saldo = \hacklabr\consultar_saldo( $cnpj );

        if ( ! is_wp_error( $saldo ) && ! empty( $saldo->plano->nome ) ) {
            return sanitize_title( $saldo->plano->nome );
        }
    }

    /*
     * Atalho da meta de interesse (wizard do novo modelo): resolve o GUID
     * gravado na organização na lista de planos disponíveis do CRM (cache
     * longo no plugin). Também cobre a API de saldo indisponível com a
     * parceria ainda inexistente — o grupo já criado com nível neutro não
     * deriva plano legado errado.
     */
    $plano_interesse = normalizar_guid( get_post_meta( $organization->ID, '_ethos_plano_interesse', true ) );

    if ( ! empty( $plano_interesse ) && function_exists( 'hacklabr\obter_planos_disponiveis' ) ) {
        foreach ( \hacklabr\obter_planos_disponiveis() ?? [] as $plano ) {
            if ( normalizar_guid( $plano->id ) === $plano_interesse ) {
                return sanitize_title( $plano->nome );
            }
        }
    }

    return null;
}

function get_pmpro_plan (int $user_id) {
    /*
     * Fonte híbrida (transição para o novo modelo): o plano da parceria
     * prevalece quando existir (slug derivado do nome, ex.
     * 'ethos-transformar'); consumers desconhecem o slug e falham fechados
     * (cortesias 0, blocos exibem o nome). Membros atuais sem parceria no
     * CRM seguem no legado (nível PMPro), sem regressão.
     */
    $plano_parceria = get_plan_slug_from_crm( $user_id );

    if ( null !== $plano_parceria ) {
        return $plano_parceria;
    }

    $group_id = (int) get_user_meta($user_id, '_pmpro_group', true);

    if (empty($group_id)) {
        return null;
    }

    $group = get_pmpro_group($group_id);
    $level_id = $group->group_parent_level_id;

    /*
     * Grupo sem nível vinculado (ex.: usuário de teste, grupo recém-criado
     * ou membership expirado): sem plano definido, não há o que derivar —
     * retornar null mantém o contrato dos consumers (tratam vazio).
     */
    if (empty($group) || empty($level_id)) {
        return null;
    }

    return Plan::fromLevel($level_id)->toSlug();
}

function update_group_level (int $group_id, int $level_id = 11) {
    global $wpdb;

    $group = get_pmpro_group($group_id);

    if ($group->group_parent_level_id == $level_id) {
        return $group;
    }

    $child_members = $group->get_active_members(false);
    $child_level_id = get_pmpro_child_level($level_id);

    $wpdb->update($wpdb->prefix . 'pmprogroupacct_groups',
    [
        'group_parent_level_id' => $level_id,
    ], [
        'id' => $group_id,
    ], ['%d'], ['%d']);

    \pmpro_changeMembershipLevel($level_id, $group->group_parent_user_id);

    foreach ($child_members as $child_member) {
        \pmpro_changeMembershipLevel($child_level_id, $child_member->group_child_user_id);
    }

    return $group;
}

function update_group_parent (int $group_id, int $user_id) {
    global $wpdb;

    $group = get_pmpro_group($group_id);

    if ($group->group_parent_user_id == $user_id) {
        return $group;
    }

    $wpdb->update($wpdb->prefix . 'pmprogroupacct_groups',
    [
        'group_parent_user_id' => $user_id,
    ], [
        'id' => $group_id,
    ], ['%d'], ['%d']);

    $wpdb->delete($wpdb->prefix . 'pmprogroupacct_group_members', [
        'group_child_user_id' => $user_id,
    ], ['%d']);

    return $group;
}

function require_approval_for_login ($user) {
    if (!class_exists('PMPro_Approvals') || empty($user) || is_wp_error($user)) {
        return $user;
    }

    assert($user instanceof \WP_User);

    $group_id = get_user_meta($user->ID, '_pmpro_group', true);

    if (!empty($group_id)) {
        $group = get_pmpro_group($group_id);
        $level_id = $group->group_parent_level_id;

        /*
         * Grupo sem nível vinculado (usuário de teste, grupo órfão ou
         * membership expirado): não há membership a aprovar — o check de
         * aprovação não se aplica (a guarda de conta ativa permanece valendo).
         */
        if (!empty($level_id)) {
            $child_level_id = get_pmpro_child_level($level_id);

            if (!\PMPro_Approvals::isApproved($user->ID, $level_id) && !\PMPro_Approvals::isApproved($user->ID, $child_level_id)) {
                return new \WP_Error('failed', 'Associação ainda não foi aprovada.');
            }
        }
    }

    return $user;
}
add_filter('wp_authenticate_user', 'hacklabr\\require_approval_for_login');

function require_active_account_for_login(\WP_User|\WP_Error $user) {
    if (!class_exists('PMPro_Approvals') || empty($user) || is_wp_error($user)) {
        return $user;
    }

    assert($user instanceof \WP_User);

    if (in_array('subscriber', (array) $user->roles, true)) {
        $has_active_account = false;

        $account_id = get_user_meta($user->ID, '_ethos_crm_account_id', true);

        if (!empty($account_id)) {
            $post_id = \ethos\crm\get_post_id_by_account($account_id, false);

            if (!empty($post_id) && !is_wp_error($post_id)) {
                $account_status = get_post_status($post_id);
                $has_active_account = $account_status === 'publish';
            }
        }

        if (!$has_active_account) {
            return new \WP_Error('failed', 'Associação não está ativa.');
        }

        /*
         * Novo modelo de associação: vigência da parceria (dataFim), via
         * API de saldo do CRM (cache curto; a API devolve a parceria
         * vigente). Vigência encerrada → acesso expira junto. Sem dados
         * no CRM (membros em transição/legados) → não bloqueia.
         */
        if (function_exists('hacklabr\consultar_saldo')) {
            $organization = get_organization_by_user($user->ID);

            if (!empty($organization)) {
                $cnpj = get_post_meta($organization->ID, 'cnpj', true);

                if (!empty($cnpj)) {
                    $saldo = \hacklabr\consultar_saldo($cnpj);

                    if (!is_wp_error($saldo) && !empty($saldo->parceria->dataFim)) {
                        $fim = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s\Z', $saldo->parceria->dataFim);

                        if ($fim && time() >= $fim->getTimestamp()) {
                            return new \WP_Error('failed', sprintf(
                                'Sua associação expirou em %s. Entre em contato para renovar.',
                                $fim->format('d/m/Y')
                            ));
                        }
                    }
                }
            }
        }
    }

    return $user;
}
add_filter('wp_authenticate_user', 'hacklabr\\require_active_account_for_login');

function register_organization_cpt () {
    register_post_type('organizacao', [
        'label' => __('Organizations', 'hacklabr'),
        'public' => true,
        'publicly_queryable' => false,
        'show_in_rest' => true,
        'menu_icon' => 'dashicons-building',
        'supports' => ['author', 'custom-fields', 'editor', 'thumbnail', 'title'],
    ]);
}
add_action('init', 'hacklabr\\register_organization_cpt');

function get_organization_by_user ($user_id = null) {
    if (empty($user_id)) {
        $user_id = get_associated_user_id();
    }

    $group_id = get_user_meta($user_id, '_pmpro_group', true);

    if (empty($group_id)) {
        return null;
    }

    return get_single_post([
        'post_type' => 'organizacao',
        'meta_query' => [
            [ 'key' => '_pmpro_group', 'value' => $group_id ],
        ],
    ]);
}
