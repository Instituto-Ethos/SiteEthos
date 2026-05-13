<?php

namespace hacklabr;

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

add_action('init', function () {
    if (class_exists('\WP_CLI')) {
        \WP_CLI::add_command('get-active-plans', 'hacklabr\\cli_get_active_plans');
    }
});
