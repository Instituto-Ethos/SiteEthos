<?php

namespace hacklabr;

use function ethos\migration\cli_log;

/**
 * Extract a CSV of active accounts on CRM
 */
function cli_get_active_plans () {
    $date = substr(date_format(date_create('now'), 'c'), 0, 16);

    $csv = fopen(wp_upload_dir()['basedir'] . '/active-plans-' . $date . '.csv', 'w');

    fputcsv($csv, [
        'ID',
        'Nome',
        'Grupo Econômico',
        'Plano',
    ]);

    $accounts = \hacklabr\iterate_crm_entities('account', [
        'orderby' => 'name',
        'order' => 'ASC',
    ]);

    foreach ($accounts as $account) {
        if (\ethos\crm\is_active_account($account)) {
            $account_status = $account->FormattedValues['fut_pl_associacao'] ?? '';
            $is_group = $account_status === 'Grupo Econômico';

            fputcsv($csv, [
                $account->Id,
                $account->Attributes['name'] ?? '',
                $is_group ? 'Sim' : 'Não',
                $account->FormattedValues['fut_pl_tipo_associacao'] ?? '',
            ]);
        }
    }

    fclose($csv);
}

/**
 * Extract a CSV of  accounts potentially importable to WordPress
 */
function cli_get_potential_plans () {
    $accounts = \hacklabr\iterate_crm_entities('account', [
        'orderby' => 'name',
        'order' => 'ASC',
    ]);

    $count_total = 0;
    $count_allow = 0;
    $count_deny_plan = 0;
    $count_deny_cnpj = 0;
    $count_deny_contact = 0;

    foreach ($accounts as $account) {
        if (\ethos\crm\is_active_account($account)) {
            $attributes = $account->Attributes;

            $count_total++;

            if ( empty( $attributes['fut_pl_associacao'] ) ) {
                $count_deny_plan++;
            } elseif ( empty( $attributes['fut_st_cnpj'] ) ) {
                $count_deny_cnpj++;
            } elseif ( empty( $attributes['primarycontactid'] ) ) {
                $count_deny_contact++;
            } else {
                $count_allow++;
            }
        }
    }

    cli_log( sprintf(
        "Found %d active accounts: %d without plan; %d without CNPJ; %d without primary contact; %d can be imported.",
        $count_total,
        $count_deny_plan,
        $count_deny_cnpj,
        $count_deny_contact,
        $count_allow,
    ) );
}

add_action('init', function () {
    if (class_exists('\WP_CLI')) {
        \WP_CLI::add_command('get-active-plans', 'hacklabr\\cli_get_active_plans');
        \WP_CLI::add_command('get-potential-plans', 'hacklabr\\cli_get_potential_plans');
    }
});
