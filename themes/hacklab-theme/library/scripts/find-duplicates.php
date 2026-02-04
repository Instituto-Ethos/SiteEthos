<?php

namespace hacklabr;

function cli_find_duplicates () {
    $users = get_users([
        'number' => -1,
        'meta_query' => [
            [ 'key' => '_ethos_crm_contact_id', 'compare' => 'EXISTS' ],
        ],
    ]);

    $users_map = [];

    foreach ($users as $user) {
        $email = get_user_meta($user->ID, '_ethos_crm:emailaddress1', true);
        if (empty($email)) {
            continue;
        }

        $email = strtolower(trim($email));
        $email_parts = explode('@', $email);
        if (count($email_parts) != 2) {
            continue;
        }

        $email_user_parts = explode('+', $email_parts[0]);
        $normalized_email = $email_user_parts[0] . '@' . $email_parts[1];

        if (!isset($users_map[$normalized_email])) {
            $users_map[$normalized_email] = [];
        }
        $users_map[$normalized_email][] = $user->ID;
    }

    foreach ($users_map as $email => $user_ids) {
        $count = count($user_ids);
        if ($count > 1) {
            \WP_CLI::log("Found $count users with email = $email\n");

            foreach ($user_ids as $user_id) {
                $user = get_user_by('ID', $user_id);
                $user_meta = get_user_meta($user_id);
                $user_slug = $user->user_login;

                $company_id = get_user_meta($user_id, '_ethos_crm_account_id', true);
                $company_post_id = \ethos\crm\get_post_id_by_account($company_id);
                $company_name = get_the_title($company_post_id);

                ksort($user_meta);

                \WP_CLI::log("Company: $company_name ($company_id)");
                \WP_CLI::log("User ID: $user_id");
                \WP_CLI::log("User slug: $user_slug");
                \WP_CLI::log("User data:");
                foreach ($user_meta as $meta_key => $meta_values) {
                    $meta_value = implode(' | ', $meta_values);
                    \WP_CLI::log("    [$meta_key] => $meta_value");
                }
                \WP_CLI::log("");
            }
        }
    }
}

add_action('init', function () {
    if (class_exists('\WP_CLI')) {
        \WP_CLI::add_command('find-duplicates', 'hacklabr\\cli_find_duplicates');
    }
});
