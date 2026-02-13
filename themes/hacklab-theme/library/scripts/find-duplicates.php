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
        $account_id = get_user_meta($user->ID, '_ethos_crm_account_id', true);

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

        if (!isset($users_map[$account_id][$normalized_email])) {
            if (!isset($users_map[$account_id])) {
                $users_map[$account_id] = [];
            }
            $users_map[$account_id][$normalized_email] = [];
        }
        $users_map[$account_id][$normalized_email][] = $user->ID;
    }

    $duplicate_cases = 0;
    $duplicate_emails = 0;

    foreach ($users_map as $account_id => $users_by_account) {
        foreach ($users_by_account as $email => $user_ids) {
            $num_contacts = count($user_ids);
            if ($num_contacts > 1) {
                $duplicate_cases += 1;
                $duplicate_emails += $num_contacts;

                $account_post_id = \ethos\crm\get_post_id_by_account($account_id);
                $account_name = get_the_title($account_post_id);

                \WP_CLI::log("Found $num_contacts contacts with email = \"$email\" on account \"$account_name\" ($account_id)\n");

                foreach ($user_ids as $user_id) {
                    $user = get_user_by('ID', $user_id);
                    $user_meta = get_user_meta($user_id);
                    $user_slug = $user->user_login;
                    $contact_id = $user_meta['_ethos_crm_contact_id'][0] ?? '';

                    ksort($user_meta);

                    \WP_CLI::log("User ID: $user_id");
                    \WP_CLI::log("User slug: $user_slug");
                    \WP_CLI::log("Contact ID: $contact_id");
                    \WP_CLI::log("Contact data:");
                    foreach ($user_meta as $meta_key => $meta_value) {
                        if (!str_starts_with($meta_key, '_ethos_crm:')) {
                            continue;
                        }
                        $attr_key = substr($meta_key, 11);
                        $attr_value = str_replace(["\n", "\r"], ["\\n", "\\r"], implode(' | ', $meta_value));
                        \WP_CLI::log("    [$attr_key] => $attr_value");
                    }
                    \WP_CLI::log("");
                }
            }
        }
    }

    \WP_CLI::log("Found $duplicate_cases cases of duplication, consisting of $duplicate_emails contacts");
}

add_action('init', function () {
    if (class_exists('\WP_CLI')) {
        \WP_CLI::add_command('find-duplicates', 'hacklabr\\cli_find_duplicates');
    }
});
