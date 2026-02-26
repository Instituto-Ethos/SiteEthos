<?php

namespace hacklabr;

function calculate_event_price (int $post_id, string $contact_id, bool $student = false): object {
    $full_price = get_event_price($post_id);

    $user = get_user_by_contact($contact_id);
    if (empty($user)) {
        if ($student) {
            $discount = round_amount($full_price / 2);
            $net_price = round_amount($full_price - $discount);
        } else {
            $discount = null;
            $net_price = $full_price;
        }
    } else {
        $discount_rate = get_discount_rate($user->ID);
        $discount = round_amount($full_price * $discount_rate);
        $net_price = round_amount($full_price - $discount);
    }

    return (object) [
        'discount' => $discount,
        'full'     => $full_price,
        'net'      => $net_price,
    ];
}

function can_join_event (int $user_id, int $post_id): bool {
    $plan = get_pmpro_plan($user_id);

    if (event_is_dialog_circle($post_id)) {
        return !empty($plan);
    }

    if (event_is_lecture($post_id)) {
        //@TODO Check availability
        return !empty($plan);
    }

    if (event_is_onboarding($post_id)) {
        return !empty($plan);
    }

    if (event_is_working_group($post_id)) {
        //@TODO Check availability
        return !empty($plan) && $plan !== 'conexao';
    }

    return true;
}

function event_is_course (int $post_id): bool {
    $event_type = get_post_meta($post_id, '_ethos_crm:fut_pl_tipo_de_evento', true);
    return $event_type === 969830001 /* CURSO */;
}

function event_is_dialog_circle (int $post_id): bool {
    return event_matches_name($post_id, ['roda de diálogo']);
}

function event_is_lecture (int $post_id): bool {
    $event_type = get_post_meta($post_id, '_ethos_crm:fut_pl_tipo_de_evento', true);
    return $event_type === 969830004 /* PALESTRA */;
}

function event_is_onboarding (int $post_id): bool {
    return event_matches_name($post_id, ['embarque']);
}

function event_is_working_group (int $post_id): bool {
    $partnership_json = get_post_meta($post_id, '_ethos_crm:fut_lk_tipoparceria');
    if (empty($partnership_json)) {
        return false;
    }

    $partnership_type = json_decode($partnership_json, false);
    if (strtolower($partnership_type->Name ?? '') === 'grupo de trabalho') {
        return true;
    }

    return event_matches_name($post_id, ['grupo de trabalho', 'gt']);
}

function event_matches_name (int $post_id, array $names): bool {
    $public_name = strtolower(get_post_meta($post_id, '_ethos_crm:fut_name', true) ?? '');
    $internal_name = strtolower(get_post_meta($post_id, '_ethos_crm:fut_st_nomeinterno', true) ?? '');

    foreach ($names as $name) {
        if (str_starts_with($public_name, $name) || str_starts_with($internal_name, $name)) {
            return true;
        }
    }

    return false;
}

function get_contact_by_cpf_or_email (string $email, string $cpf): \WP_User|null {
    $metas = [
        'email' => $email,
        'cpf'   => $cpf,
    ];

    foreach ($metas as $key => $value) {
        $users = get_users([
            'meta_query' => [
                [ 'key' => $key, 'value' => $value ],
            ],
        ]);

        if (!empty($users)) {
            return $users[0];
        }
    }

    return null;
}

function get_courtesy_limits (int $post_id, string|null $plan): int {
    if (empty($plan)) {
        return 0;
    }

    if (event_is_course($post_id)) {
        if (event_matches_name($post_id, ['jornada']) || event_matches_name($post_id, ['imersão'])) {
            return 0;
        } else {
            return 3;
        }
    }

    if (event_is_lecture($post_id)) {
        return match ($plan) {
            'vivencia' => 2,
            'institucional' => 3,
            default => 0,
        };
    }

    // @TODO Get courtesies by contract?

    return match ($plan) {
        'conexao' => 0,
        'essencial' => 10,
        'vivencia' => 18,
        'institucional' => 20,
        default => 0,
    };
}

function get_courtesy_type (int $post_id, string $contact_id, string $account_id): int {
    $user = get_user_by_contact($contact_id);
    if (empty($user)) {
        return 969830000; // NÃO
    }

    $plan = get_pmpro_plan($user->ID);
    if (empty($plan)) {
        return 969830000; // NÃO
    }

    $max_courtesies = get_courtesy_limits($post_id, $plan);
    if ($max_courtesies == 0) {
        return 969830000; // NÃO
    }

    $used_courtesies = get_used_courtesies($post_id, $account_id);
    if ($used_courtesies < $max_courtesies) {
        return match ($plan) {
            'conexao' => 969830002, // CONEXÃO
            'essencial' => 969830003, // ESSENCIAL
            'vivencia' => 969830004, // VIVÊNCIA
            'institucional' => 969830001, // CORTESIA ETHOS
        };
    }

    return 969830000; // NÃO
}

function get_discount_rate (int $user_id): float {
    $plan = get_pmpro_plan($user_id);
    if (empty($plan)) {
        return 0.0;
    }

    // @TODO Get rates by contract?

    return match ($plan) {
        'conexao' => 0.1,
        'essencial' => 0.15,
        'vivencia' => 0.2,
        'institucional' => 0.25,
        default => 0.0,
    };
}

function get_event_price (int $post_id): float {
    $raw_value = get_post_meta($post_id, '_ethos_crm:fut_valorinscricao_base');

    if (empty($raw_value)) {
        $raw_value = get_post_meta($post_id, '_ethos_crm:fut_valorinscricao');
    }

    if (empty($raw_value)) {
        $float_value = 0.0;
    } else {
        $float_value = floatval($raw_value);
    }

    return apply_filters('hacklabr/ethos_event_price', $float_value, $post_id);
}

function get_used_courtesies (int $post_id, string $account_id): int {
    $account = get_crm_entity_by_id('account', $account_id, [ 'cache' => false ]);

    if (event_is_course($post_id)) {
        return $account->Attributes->fut_int_cortesias_cursos ?? 0;
    }

    if (event_is_lecture($post_id)) {
        return $account->Attributes->fut_int_cortesias_palestras ?? 0;
    }

    return $account->Attributes->fut_int_cortesias_conferencias ?? 0;
}

function get_user_by_contact (string $contact_id): \WP_User|null {
    $users = get_users([
        'meta_query' => [
            [ 'key' => '_ethos_crm_contact_id', 'value' => $contact_id ],
        ]
    ]);

    if (!empty($users)) {
        return $users[0];
    }

    return null;
}

function round_amount (float $amount): float {
    return round($amount, 2);
}
