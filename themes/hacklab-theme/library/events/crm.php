<?php

namespace hacklabr;

use \AlexaCRM\Xrm\EntityCollection;
use \hacklabr\batch\Dynamics_Batch_Builder;
use \hacklabr\batch\Dynamics_Batch_Reference;

/**
 * Availability (R1a): total registered = participant lines of the project
 * with status != Cancelada (969830007) — replace generates cancelled lines
 * systematically and counting them would inflate occupancy.
 */
function get_event_registration_count_filters (string $project_id): array {
    return [
        ['field' => '_fut_lk_projeto_value', 'value' => $project_id],
        ['field' => 'fut_set_statusoperacao', 'op' => 'ne', 'value' => 969830007],
    ];
}

/**
 * Reads the $count query result from an executed batch; falls back to
 * fetching up to slots+1 lines when the tenant omits @odata.count.
 */
function resolve_event_registration_count (Dynamics_Batch_Builder $batch, Dynamics_Batch_Reference $count_ref, string $project_id, int $total_slots): int {
    $result = $batch->get_result($count_ref);
    $data = $result['data'] ?? null;

    if ($data instanceof EntityCollection && $data->TotalRecordCount >= 0) {
        return $data->TotalRecordCount;
    }

    $client = get_client_on_dynamics();

    if (false === $client) {
        return 0;
    }

    $fallback = new Dynamics_Batch_Builder($client->getClient());
    $rows_ref = $fallback->add_query('fut_participante', get_event_registration_count_filters($project_id), [
        'per_page' => $total_slots + 1,
    ]);
    $fallback->execute();

    $result = $fallback->get_result($rows_ref);
    $data = $result['data'] ?? null;

    if ($data instanceof EntityCollection) {
        return count($data->Entities);
    }

    return 0;
}

function get_event_registration_count (string $project_id, int $total_slots): int {
    $client = get_client_on_dynamics();

    if (false === $client) {
        return 0;
    }

    $batch = new Dynamics_Batch_Builder($client->getClient());
    $count_ref = $batch->add_query('fut_participante', get_event_registration_count_filters($project_id), ['count' => true]);
    $batch->execute();

    return resolve_event_registration_count($batch, $count_ref, $project_id, $total_slots);
}

/**
 * Pure availability evaluation over a known filled-slot count
 * (registrations window, dates, capacity) — used both by the render path
 * and by create_registration with its prefetched count.
 */
function evaluate_event_availability (int $post_id, int $filled_slots): array {
    if (!registrations_are_open($post_id)) {
        return [
            'status'  => 'error',
            'form'    => 'hide',
            'message' => __('Registrations are closed.', 'hacklabr'),
        ];
    }

    $current_date = date('c');

    $registration_end = get_post_meta($post_id, '_ethos_crm:fut_dt_data_encerramento_inscricoes', true);
    if (empty($registration_end)) {
        $registration_end = get_post_meta($post_id, '_ethos_crm:fut_dt_dataehoratermino', true);
    }

    if ($current_date > $registration_end) {
        update_post_meta($post_id, '_ethos:event_status', 'PAST');

        return [
            'status'  => 'error',
            'form'    => 'hide',
            'message' => __('Registrations are closed.', 'hacklabr'),
        ];
    }

    $total_slots = intval(get_post_meta($post_id, '_ethos_crm:fut_int_nrovagastotal', true));

    if ($filled_slots >= $total_slots) {
        update_post_meta($post_id, '_ethos:event_status', 'FULL');

        return [
            'status'  => 'error',
            'form'    => 'hide',
            'message' => __('Registrations are closed.', 'hacklabr'),
        ];
    }

    return [
        'filled'    => $filled_slots,
        'available' => $total_slots - $filled_slots,
    ];
}

function check_event_availability (int $post_id, string $project_id): array {
    $total_slots = intval(get_post_meta($post_id, '_ethos_crm:fut_int_nrovagastotal', true));
    $filled_slots = get_event_registration_count($project_id, $total_slots);

    return evaluate_event_availability($post_id, $filled_slots);
}

/**
 * Decide o que fazer quando o contato já possui inscrição no evento.
 *
 *  Paga (3) / Empenhada (50) / Negociada (51) / Paga Diretamente (52) → block
 *  Cancelada (7)                                                     → prossegue
 *  Pendente (0/1) recente (<180d):
 *      mesmo tipo de desconto   → checkout (re-abre pagamento, novo intent)
 *      tipo de desconto difere  → replace (nova inscrição substitui a antiga)
 *  Pendente antiga (≥180d)                                           → replace (obsoleta)
 *
 * @return array|null ['action' => 'block'|'checkout'|'replace', 'participant_id' => uuid] ou null para prosseguir.
 */
function evaluate_participant_registration (EntityCollection $participants, string $incoming_discount_type): ?array {
    if (empty($participants->Entities)) {
        return null;
    }

    $obsolete_before = time() - 180 * DAY_IN_SECONDS;
    $recent_pending  = null;
    $obsolete        = null;

    foreach ($participants->Entities as $participant) {
        $status = $participant->Attributes['fut_set_statusoperacao'] ?? null;

        if (is_object($status) && isset($status->Value)) {
            $status = $status->Value;
        }

        if (in_array((int) $status, [969830003, 969830050, 969830051, 969830052], true)) {
            return ['action' => 'block', 'participant_id' => (string) $participant->Id];
        }

        if (969830007 === (int) $status) {
            continue;
        }

        // Pendente (Sem status / Aguardando): TTL de 180 dias.
        $createdon = $participant->Attributes['createdon'] ?? null;

        if ($createdon instanceof \DateTime) {
            $created_ts = $createdon->getTimestamp();
        } elseif (is_string($createdon) && false !== ($parsed = strtotime($createdon))) {
            $created_ts = $parsed;
        } else {
            $created_ts = time();
        }

        if ($created_ts < $obsolete_before) {
            $obsolete = $participant;
            continue;
        }

        $recent_pending = $participant;
    }

    if (null !== $recent_pending) {
        $existing_type = normalize_registration_discount_type((string) ($recent_pending->Attributes['fut_txt_tipodedesconto'] ?? ''));

        if ($existing_type === $incoming_discount_type) {
            return ['action' => 'checkout', 'participant_id' => (string) $recent_pending->Id];
        }

        return ['action' => 'replace', 'participant_id' => (string) $recent_pending->Id];
    }

    if (null !== $obsolete) {
        return ['action' => 'replace', 'participant_id' => (string) $obsolete->Id];
    }

    return null;
}

function check_participant_registration (string $project_id, ?string $contact_id, string $incoming_discount_type): ?array {
    if (empty($contact_id)) {
        return null;
    }

    $participants = get_registration_flow_context('participant_history');

    if (!$participants instanceof EntityCollection) {
        $participants = \hacklabr\batch\get_crm_entities_advanced('fut_participante', [
            ['field' => '_fut_lk_projeto_value', 'value' => $project_id],
            ['field' => '_fut_lk_contato_value', 'value' => $contact_id],
        ], [
            'cache'    => false,
            'select'   => ['fut_set_statusoperacao', 'createdon', 'fut_txt_tipodedesconto'],
            'per_page' => 20,
            'orderby'  => 'createdon',
            'order'    => 'DESC',
        ]);
    }

    return evaluate_participant_registration($participants, $incoming_discount_type);
}

function normalize_registration_discount_type (string $type): string {
    if ('' === $type) {
        return '';
    }

    if (str_starts_with($type, 'VOUCHER')) {
        return 'VOUCHER';
    }

    if (str_starts_with($type, 'CORTESIA')) {
        return 'CORTESIA';
    }

    return $type;
}

/**
 * --------------------------------------------------------------------------
 * R4 — resolução de identidade do contato
 * --------------------------------------------------------------------------
 * A consulta (or-group aninhado) já filtra a elegibilidade: match por CPF
 * (com ou sem máscara) ou e-mail pertencente a contato SEM CPF. A decisão
 * final é determinística e registrada no log:
 *   CPF > e-mail + nome exato > e-mail + menor Levenshtein > modifiedon desc
 */

function normalize_contact_name (string $name): string {
    $name = remove_accents($name);
    $name = mb_strtolower($name);
    $name = preg_replace('/\s+/', ' ', trim($name));

    return trim((string) $name);
}

function build_registration_contact_filters (array $params): array {
    $cpf_digits = (string) preg_replace('/\D/', '', trim($params['cpf'] ?? ''));
    $email = strtolower(trim($params['email'] ?? ''));

    $or_conditions = [];

    if (strlen($cpf_digits) === 11) {
        $or_conditions[] = ['field' => 'fut_st_cpf', 'value' => format_cpf($cpf_digits)];
        $or_conditions[] = ['field' => 'fut_st_cpf', 'value' => $cpf_digits];
    }

    if ('' !== $email) {
        $or_conditions[] = ['and' => [
            ['field' => 'emailaddress1', 'value' => $email],
            ['or' => [
                ['field' => 'fut_st_cpf', 'value' => null],
                ['field' => 'fut_st_cpf', 'value' => ''],
            ]],
        ]];
    }

    if (empty($or_conditions)) {
        return [];
    }

    return ['or' => $or_conditions];
}

/**
 * @return array|null ['id', 'cpf', 'email', 'fullname'] do contato eleito.
 */
function resolve_registration_contact (?EntityCollection $candidates, array $params): ?array {
    if (null === $candidates || empty($candidates->Entities)) {
        return null;
    }

    $form_cpf_digits = (string) preg_replace('/\D/', '', trim($params['cpf'] ?? ''));
    $form_email = strtolower(trim($params['email'] ?? ''));
    $form_name = normalize_contact_name(trim($params['nome_completo'] ?? ''));

    $cpf_matches = [];
    $email_matches = [];

    foreach ($candidates->Entities as $entity) {
        $attributes = $entity->Attributes ?? [];

        $contact = [
            'id'          => (string) $entity->Id,
            'cpf'         => trim((string) ($attributes['fut_st_cpf'] ?? '')),
            'email'       => strtolower(trim((string) ($attributes['emailaddress1'] ?? ''))),
            'fullname'    => (string) ($attributes['fullname'] ?? ''),
            'modified_ts' => 0,
        ];

        $modifiedon = $attributes['modifiedon'] ?? null;
        if ($modifiedon instanceof \DateTime) {
            $contact['modified_ts'] = $modifiedon->getTimestamp();
        } elseif (is_string($modifiedon) && false !== ($parsed = strtotime($modifiedon))) {
            $contact['modified_ts'] = $parsed;
        }

        $cpf_digits = (string) preg_replace('/\D/', '', $contact['cpf']);

        if ('' !== $form_cpf_digits && '' !== $cpf_digits && $cpf_digits === $form_cpf_digits) {
            $cpf_matches[] = $contact;
            continue;
        }

        if ('' !== $form_email && $contact['email'] === $form_email) {
            // Defesa client-side: e-mail elegível apenas para contato sem CPF.
            if ('' !== $cpf_digits) {
                do_action('ethos_crm:log', "R4: contato {$contact['id']} descartado - e-mail bate mas CPF divergente (email-discarded-cpf-mismatch)", 'debug');
                continue;
            }

            $email_matches[] = $contact;
        }
    }

    if (!empty($cpf_matches)) {
        usort($cpf_matches, fn (array $a, array $b): int => $b['modified_ts'] <=> $a['modified_ts']);

        if (count($cpf_matches) > 1) {
            do_action('ethos_crm:log', 'R4: CPF com ' . count($cpf_matches) . ' contatos no CRM - eleito o modifiedon mais recente (' . $cpf_matches[0]['id'] . ')', 'warning');
        }

        do_action('ethos_crm:log', "R4: contato resolvido por CPF (cpf-match): {$cpf_matches[0]['id']}", 'debug');

        return $cpf_matches[0];
    }

    if (!empty($email_matches)) {
        foreach ($email_matches as $match) {
            if ('' !== $form_name && normalize_contact_name($match['fullname']) === $form_name) {
                do_action('ethos_crm:log', "R4: contato resolvido por e-mail + nome exato (email-exact): {$match['id']}", 'debug');

                return $match;
            }
        }

        usort($email_matches, function (array $a, array $b) use ($form_name): int {
            $distance_a = levenshtein(normalize_contact_name($a['fullname']), $form_name);
            $distance_b = levenshtein(normalize_contact_name($b['fullname']), $form_name);

            return [$distance_a, $b['modified_ts']] <=> [$distance_b, $a['modified_ts']];
        });

        $best = $email_matches[0];
        $distance = levenshtein(normalize_contact_name($best['fullname']), $form_name);

        do_action('ethos_crm:log', "R4: contato resolvido por e-mail + nome aproximado (email-levenshtein d={$distance}): {$best['id']}", 'debug');

        return $best;
    }

    return null;
}

/**
 * --------------------------------------------------------------------------
 * Batch query readers
 * --------------------------------------------------------------------------
 */

function get_batch_query_collection (Dynamics_Batch_Builder $batch, Dynamics_Batch_Reference $ref): ?EntityCollection {
    $result = $batch->get_result($ref);

    if (null === $result || ($result['status'] ?? 'error') !== 'success') {
        return null;
    }

    $data = $result['data'] ?? null;

    return $data instanceof EntityCollection ? $data : null;
}

function get_batch_query_entities (Dynamics_Batch_Builder $batch, Dynamics_Batch_Reference $ref): array {
    $collection = get_batch_query_collection($batch, $ref);

    return null !== $collection ? $collection->Entities : [];
}

/**
 * --------------------------------------------------------------------------
 * Fluxo de inscrição
 * --------------------------------------------------------------------------
 */

function create_registration (int $post_id, array $params) {
    $project_id = get_post_meta($post_id, 'entity_fut_projeto', true);

    $paid_event = is_paid_event($post_id);
    $associates_event = is_associates_event($post_id);

    $client = get_client_on_dynamics();

    if (false === $client) {
        return [
            'status'  => 'error',
            'form'    => 'preserve',
            'message' => __('Registration failed.', 'hacklabr'),
        ];
    }

    // Resolução local (WP) — cases 1–2, sem roundtrips ao CRM.
    $account_id = get_registration_account_local($params);

    $lead_id = null;
    if (empty($account_id) && !empty($params['cnpj'])) {
        $lead_id = get_registration_lead_local($params);
    }

    $contact_id = get_registration_contact_local($params);

    $contact_uuid = null;
    if (is_string($contact_id)) {
        $contact_uuid = $contact_id;
    }

    $voucher_present = $paid_event && !empty($params['voucher']);
    $payments_ready = function_exists('\ethos\payments\validate_event_voucher');

    // ---- Prefetch (R2): um único $batch com todas as queries ----
    $query_batch = new Dynamics_Batch_Builder($client->getClient());

    $q_account = null;
    $q_lead = null;
    $q_contact = null;
    $q_contact_identity = null;
    $q_count = null;
    $q_history = null;
    $q_account_fields = null;
    $q_voucher = null;

    $cnpj_digits = (string) preg_replace('/\D/', '', trim($params['cnpj'] ?? ''));

    if (empty($account_id) && '' !== $cnpj_digits) {
        $q_account = $query_batch->add_query('account', [
            ['field' => 'fut_st_cnpjsemmascara', 'value' => $cnpj_digits],
        ], ['select' => ['accountid']]);
    }

    if (empty($account_id) && empty($lead_id) && '' !== $cnpj_digits) {
        $q_lead = $query_batch->add_query('lead', [
            'or' => [
                ['field' => 'fut_st_cnpj', 'value' => $cnpj_digits],
                ['field' => 'fut_st_cnpj', 'value' => format_cnpj($cnpj_digits)],
            ],
        ], ['select' => ['leadid'], 'per_page' => 1]);
    }

    if (null === $contact_uuid) {
        $contact_filters = build_registration_contact_filters($params);

        if (!empty($contact_filters)) {
            $q_contact = $query_batch->add_query('contact', $contact_filters, [
                'select'   => ['contactid', 'fullname', 'modifiedon', 'fut_st_cpf', 'emailaddress1'],
                'per_page' => 20,
                'orderby'  => 'modifiedon',
                'order'    => 'desc',
            ]);
        }
    } else {
        // Campos de identidade do contato conhecido via WP — base da
        // política de campos do R5.
        $q_contact_identity = $query_batch->add_query('contact', [
            ['field' => 'contactid', 'value' => $contact_uuid],
        ], ['select' => ['fut_st_cpf', 'emailaddress1']]);
    }

    // R1a — capacidade (sempre).
    $total_slots = intval(get_post_meta($post_id, '_ethos_crm:fut_int_nrovagastotal', true));
    $q_count = $query_batch->add_query('fut_participante', get_event_registration_count_filters($project_id), ['count' => true]);

    // R1b — histórico de participações (contato já conhecido via WP).
    if (null !== $contact_uuid) {
        $q_history = $query_batch->add_query('fut_participante', [
            ['field' => '_fut_lk_projeto_value', 'value' => $project_id],
            ['field' => '_fut_lk_contato_value', 'value' => $contact_uuid],
        ], [
            'select'   => ['fut_participanteid', 'fut_set_statusoperacao', 'createdon', 'fut_txt_tipodedesconto'],
            'per_page' => 20,
            'orderby'  => 'createdon',
            'order'    => 'desc',
        ]);
    }

    // R3 — cortesias / situação financeira da conta conhecida via WP.
    if (!empty($account_id)) {
        $q_account_fields = $query_batch->add_query('account', [
            ['field' => 'accountid', 'value' => $account_id],
        ], ['select' => get_registration_account_field_select()]);
    }

    if ($voucher_present && $payments_ready) {
        $q_voucher = $query_batch->add_query('fut_participante', [
            ['field' => 'fut_participanteid', 'value' => trim($params['voucher'])],
        ], ['select' => ['fut_participanteid', 'fut_set_statusoperacao', 'fut_int_quantidade_restante']]);
    }

    $query_batch->execute();

    $contact_identity = null;

    if (null !== $q_account) {
        $account_entities = get_batch_query_entities($query_batch, $q_account);

        if (!empty($account_entities)) {
            $account_id = (string) $account_entities[0]->Id;
        }
    }

    if (null !== $q_lead && empty($lead_id)) {
        $lead_entities = get_batch_query_entities($query_batch, $q_lead);

        if (!empty($lead_entities)) {
            $lead_id = (string) $lead_entities[0]->Id;
        }
    }

    if (null !== $q_contact) {
        $resolved_contact = resolve_registration_contact(get_batch_query_collection($query_batch, $q_contact), $params);

        if (null !== $resolved_contact) {
            $contact_uuid = $resolved_contact['id'];
            $contact_id = $resolved_contact['id'];
            $contact_identity = [
                'fut_st_cpf'     => $resolved_contact['cpf'],
                'emailaddress1'  => $resolved_contact['email'],
            ];
        }
    } elseif (null !== $q_contact_identity) {
        $identity_entities = get_batch_query_entities($query_batch, $q_contact_identity);

        if (!empty($identity_entities)) {
            $attributes = $identity_entities[0]->Attributes ?? [];
            $contact_identity = [
                'fut_st_cpf'    => trim((string) ($attributes['fut_st_cpf'] ?? '')),
                'emailaddress1' => strtolower(trim((string) ($attributes['emailaddress1'] ?? ''))),
            ];
        }
    }

    $history_collection = null;

    if (null !== $q_history) {
        $history_collection = get_batch_query_collection($query_batch, $q_history);
    }

    // ---- Segunda leva (case 3): histórico e cortesia dependem do id
    // resolvido no primeiro batch (OData não referencia resultados entre
    // requests de um mesmo $batch) — perda documentada no ADR.
    $second_batch = null;
    $account_fields_from_second = false;

    if ((null === $history_collection && null !== $contact_uuid) || (null === $q_account_fields && !empty($account_id))) {
        $second_batch = new Dynamics_Batch_Builder($client->getClient());

        if (null === $history_collection && null !== $contact_uuid) {
            $q_history = $second_batch->add_query('fut_participante', [
                ['field' => '_fut_lk_projeto_value', 'value' => $project_id],
                ['field' => '_fut_lk_contato_value', 'value' => $contact_uuid],
            ], [
                'select'   => ['fut_participanteid', 'fut_set_statusoperacao', 'createdon', 'fut_txt_tipodedesconto'],
                'per_page' => 20,
                'orderby'  => 'createdon',
                'order'    => 'desc',
            ]);
        }

        if (null === $q_account_fields && !empty($account_id)) {
            $q_account_fields = $second_batch->add_query('account', [
                ['field' => 'accountid', 'value' => $account_id],
            ], ['select' => get_registration_account_field_select()]);

            $account_fields_from_second = true;
        }

        $second_batch->execute();

        if (null !== $q_history && null === $history_collection) {
            $history_collection = get_batch_query_collection($second_batch, $q_history);
        }
    }

    if (null !== $history_collection) {
        set_registration_flow_context('participant_history', $history_collection);
    }

    $account_fields = null;

    if (null !== $q_account_fields) {
        $source_batch = $account_fields_from_second ? $second_batch : $query_batch;
        $account_field_entities = get_batch_query_entities($source_batch, $q_account_fields);

        if (!empty($account_field_entities)) {
            $account_fields = $account_field_entities[0]->Attributes ?? null;
        }
    }

    if (null !== $account_fields) {
        set_registration_flow_context('account_fields', $account_fields);
    }

    // ---- Validações (ordem e mensagens preservadas) ----
    if ($associates_event) {
        $user_is_associate = false;

        if (!empty($contact_uuid)) {
            $user_id = get_user_by_contact($contact_uuid);
            $user_is_associate = !empty($user_id);
        }

        if (!$user_is_associate) {
            return [
                'status'  => 'error',
                'form'    => 'clean',
                'message' => __('This event is exclusive for associates.', 'hacklabr'),
            ];
        }

        if (!empty($account_id)) {
            $financeira = resolve_crm_option_set_value($account_fields['fut_pl_situacaofinanceira'] ?? null);

            if (969830003 === $financeira /* Adimplente Congelada */) {
                return [
                    'status'  => 'error',
                    'form'    => 'clean',
                    'message' => __('Your registration could not be processed. Please contact your account manager to resolve any outstanding issues or report any errors.', 'hacklabr'),
                ];
            }
        }
    }

    // Duplicate handling is delegated to check_participant_registration(),
    // so availability runs anonymous.
    $filled_slots = resolve_event_registration_count($query_batch, $q_count, $project_id, $total_slots);
    $availability = evaluate_event_availability($post_id, $filled_slots);

    if (!empty($availability['status'])) {
        return $availability;
    }

    $courtesy_type = get_courtesy_type($post_id, $contact_uuid, $account_id);

    $voucher = null;
    if ($voucher_present) {
        if (!$payments_ready) {
            return [
                'status'  => 'error',
                'form'    => 'preserve',
                'message' => __('Online payment features are temporarily unavailable. Please contact us.', 'hacklabr'),
            ];
        }

        $voucher_source = null;

        if (null !== $q_voucher) {
            $voucher_entities = get_batch_query_entities($query_batch, $q_voucher);
            $voucher_source = $voucher_entities[0] ?? null;
        }

        $voucher = \ethos\payments\validate_event_voucher($params['voucher'], $voucher_source);

        if (is_wp_error($voucher)) {
            return [
                'status'  => 'error',
                'form'    => 'preserve',
                'message' => $voucher->get_error_message(),
            ];
        }
    }

    $incoming_discount_type = (null !== $voucher) ? 'VOUCHER'
        : ((969830000 !== $courtesy_type) ? 'CORTESIA' : '');

    $registration_check = check_participant_registration($project_id, $contact_uuid, $incoming_discount_type);
    $replace_participant_id = null;

    if (is_array($registration_check)) {
        if ('block' === $registration_check['action']) {
            return [
                'status'  => 'error',
                'form'    => 'clean',
                'message' => __('You are already registered in this event.', 'hacklabr'),
            ];
        }

        if ('checkout' === $registration_check['action']) {
            // Same discount type: re-open payment on the existing registration
            // (legacy "Acesso link pagamento" — a fresh Payment Intent).
            return [
                'status'     => 'success',
                'form'       => 'checkout',
                'message'    => __('You are registered to this event, but payment is pending.', 'hacklabr'),
                'entity_id'  => $registration_check['participant_id'],
                'contact_id' => $contact_id,
            ];
        }

        $replace_participant_id = $registration_check['participant_id'];
    }

    // ---- Escrita ----
    $builder = new Dynamics_Batch_Builder($client->getClient());

    // Cases de criação que dependem das resoluções acima.
    if (empty($account_id) && !empty($params['cnpj']) && empty($lead_id)) {
        $lead_id = create_registration_lead($params, $builder);
    }

    if (empty($contact_id) && !$associates_event) {
        $contact_id = create_registration_contact($params, $lead_id, $builder);
    }

    if ($contact_id instanceof Dynamics_Batch_Reference) {
        $contact_ref = $contact_id;
    } else {
        $contact_ref = create_crm_reference('contact', $contact_id);
    }

    // R5 — atualização do contato existente no mesmo changeset.
    // originatingleadid permanece como está (vínculo é definido na criação).
    $contact_update_attributes = null;

    if (is_string($contact_id) && !empty($contact_id)) {
        $contact_update_attributes = build_registration_contact_update_attributes($params, $contact_identity, $contact_id);
    }

    $attibutes = [
        'fut_lk_contato'        => $contact_ref,
        'fut_lk_fatura_pf'      => $contact_ref,
        'fut_pl_cortesia'       => $courtesy_type,
        'fut_lk_projeto'        => create_crm_reference('fut_projeto', $project_id),
        'fut_txt_nro_inscricao' => generate_registration_number($post_id, $availability['filled'] ?? 0),
    ];

    if ($paid_event) {
        $attibutes['fut_bl_exibecamposfinanceiros'] = true;
        $attibutes['fut_int_quantidade_adquirida'] = 1;
        $attibutes['fut_int_quantidade_restante'] = 0;

        if (null !== $voucher) {
            $attibutes['fut_set_statusoperacao'] = 969830003; // Pago
            $attibutes['fut_txt_tipodedesconto'] = 'VOUCHER';
            $attibutes['fut_txt_token_gerado']   = $voucher->source_id;
        } elseif (969830000 !== $courtesy_type) {
            $plan = null;

            if (!empty($contact_uuid)) {
                $courtesy_user = get_user_by_contact($contact_uuid);
                if (!empty($courtesy_user)) {
                    $plan = get_pmpro_plan($courtesy_user->ID);
                }
            }

            $attibutes['fut_set_statusoperacao'] = 969830003; // Pago
            $attibutes['fut_txt_tipodedesconto'] = 'CORTESIA-' . strtoupper((string) ($plan ?? 'ETHOS'));
            $attibutes['fut_txt_porcentagem']    = '100.00';
        } else {
            $attibutes['fut_set_statusoperacao'] = 969830000; // Sem status

            $student = (($params['estudante'] ?? '') === 'yes');

            if (function_exists('\ethos\payments\calculate_event_price')) {
                $price = \ethos\payments\calculate_event_price($post_id, $contact_uuid ?? '', $student, $account_id);

                $attibutes['fut_mon_precocheio'] = $price->full;
                $attibutes['fut_mon_valorpago']  = $price->net;

                if (!empty($price->discount)) {
                    $attibutes['fut_mon_desconto'] = $price->discount;
                }
            }
        }
    } else {
        $attibutes['fut_set_statusoperacao'] = 969830003; // Pago
    }

    if (!empty($account_id)) {
        $attibutes['fut_lk_empresa']           = create_crm_reference('account', $account_id);
        $attibutes['fut_lk_empresa_associada'] = create_crm_reference('account', $account_id);
        $attibutes['fut_lk_fatura_pj']         = create_crm_reference('account', $account_id);
    }

    if (!empty($params['acessibilidade'])) {
        $attibutes['fut_txt_necessidades_especiais'] = trim($params['acessibilidade']);
    }

    try {
        if (!empty($contact_update_attributes)) {
            $builder->add_update('contact', $contact_id, $contact_update_attributes);
        }

        $participant_ref = $builder->add_create('fut_participante', $attibutes);

        if (null !== $voucher) {
            // Consome o assento no mesmo changeset: com execute() transacional
            // o Dynamics aplica tudo ou reverte tudo.
            $builder->add_update('fut_participante', $voucher->source_id, [
                'fut_int_quantidade_restante' => $voucher->remaining - 1,
            ]);
        }

        if (null !== $replace_participant_id) {
            // Substituição: a pendência antiga é cancelada e a referência de
            // pagamento zerada, na mesma transação.
            $builder->add_update('fut_participante', $replace_participant_id, [
                'fut_set_statusoperacao'      => 969830007, // Cancelada
                'fut_txt_referenciapagseguro' => '0',
            ]);
        }

        $results = $builder->execute(
            $contact_id instanceof Dynamics_Batch_Reference
            || !empty($contact_update_attributes)
            || null !== $voucher
            || null !== $replace_participant_id
        );

        $participant_result = $builder->get_result($participant_ref);

        if ($participant_result && $participant_result['status'] === 'success') {
            if ($contact_id instanceof Dynamics_Batch_Reference) {
                $contact_result = $builder->get_result($contact_id);
                $contact_id = $contact_result['entity_id'] ?? null;
                $contact_uuid = $contact_id;
            }

            // R5 — espelhamento no WP após o sucesso do batch (update-only).
            mirror_registration_contact_to_user(
                is_string($contact_id) ? $contact_id : null,
                $params,
                $contact_update_attributes ?? []
            );

            $form = 'hide';

            if ($paid_event && (null === $voucher) && (969830000 === $courtesy_type)) {
                $form = 'checkout';
                $message = __('You are registered to this event, but payment is pending.', 'hacklabr');
            } else {
                $message = __('You are successfully registered to this event!', 'hacklabr');
            }

            return [
                'status'     => 'success',
                'form'       => $form,
                'message'    => $message,
                'entity_id'  => $participant_result['entity_id'],
                'contact_id' => $contact_id,
            ];
        }

        $error_message = __('Registration failed.', 'hacklabr');
        if (!empty($participant_result['message'])) {
            $error_message = $participant_result['message'];
        }

        return [
            'status'  => 'error',
            'form'    => 'preserve',
            'message' => $error_message,
        ];
    } catch (\Exception $e) {
        return [
            'status'  => 'error',
            'form'    => 'preserve',
            'message' => $e->getMessage(),
        ];
    }
}

function get_registration_address_map (): array {
    return [
        'end_bairro'      => 'address1_line3',
        'end_cep'         => 'address1_postalcode',
        'end_complemento' => 'address1_line2',
        'end_cidade'      => 'address1_city',
        'end_logradouro'  => 'fut_address1_logradouro',
        'end_numero'      => 'fut_address1_nro',
        'end_estado'      => 'fut_pl_estado',
    ];
}

function get_registration_address_attributes (array $params): array {
    $attributes = [];

    foreach (get_registration_address_map() as $param_key => $attribute_key) {
        $value = trim($params[$param_key] ?? '');

        if ('' === $value) {
            continue;
        }

        if ('end_estado' === $param_key) {
            $uf = \ethos\crm\BrazilianUF::fromCode($value);

            if (null === $uf) {
                continue;
            }

            $value = $uf;
        }

        $attributes[$attribute_key] = $value;
    }

    return $attributes;
}

/**
 * Bloco de atributos do contato a partir do formulário — compartilhado
 * entre a criação (case 4) e a atualização em voo (R5).
 */
function build_registration_contact_attributes (array $params, string|Dynamics_Batch_Reference|null $lead_id = null): array {
    $full_name = trim($params['nome_completo']);
    $name_parts = explode(' ',  $full_name);
    $first_name = $name_parts[0];
    unset($name_parts[0]);
    $last_name = implode(' ', $name_parts);

    $attributes = [
        'emailaddress1' => trim($params['email']),
        'firstname'     => $first_name,
        'fullname'      => $full_name,
        'fut_st_cpf'    => format_cpf(trim($params['cpf'])),
        'lastname'      => $last_name,
        'yomifirstname' => $first_name,
        'yomifullname'  => $full_name,
        'yomilastname'  => $last_name,
    ];

    $optional_fields = [
        'fut_pl_area'             => 'area',
        'fut_pl_nivelhierarquico' => 'nivel_hierarquico',
        'jobtitle'                => 'cargo',
        'telephone1'              => 'telefone',
    ];
    foreach ($optional_fields as $attribute_key => $param_key) {
        if ($value = trim($params[$param_key] ?? '')) {
            $attributes[$attribute_key] = $value;
        }
    }

    $attributes = array_merge($attributes, get_registration_address_attributes($params));

    if (!empty($lead_id)) {
        if ($lead_id instanceof Dynamics_Batch_Reference) {
            $attributes['originatingleadid'] = $lead_id;
        } else {
            $attributes['originatingleadid'] = create_crm_reference('lead', $lead_id);
        }
    }

    return $attributes;
}

function create_registration_contact (array $params, string|Dynamics_Batch_Reference|null $lead_id = null, ?Dynamics_Batch_Builder $builder = null): string|Dynamics_Batch_Reference {
    $attributes = build_registration_contact_attributes($params, $lead_id);

    if ($builder) {
        return $builder->add_create('contact', $attributes);
    } else {
        return create_crm_entity('contact', $attributes);
    }
}

/**
 * R5 — atributos de atualização do contato existente, aplicando a política
 * de campos:
 *   - Nome/yomi/cargo/área/nível/telefone e endereço: atualizam quando preenchidos.
 *   - fut_st_cpf: preenche se vazio; jamais sobrescreve valor divergente.
 *   - emailaddress1: protegido por padrão; sincroniza apenas para paridade
 *     WP↔CRM (usuário WP correspondente com o mesmo e-mail do formulário).
 *
 * Sem identidade conhecida ($contact_identity null), CPF e e-mail ficam de fora.
 */
function build_registration_contact_update_attributes (array $params, ?array $contact_identity, string $contact_id): array {
    $attributes = build_registration_contact_attributes($params);

    if (null === $contact_identity) {
        unset($attributes['fut_st_cpf'], $attributes['emailaddress1']);
        return $attributes;
    }

    $existing_cpf = (string) preg_replace('/\D/', '', (string) ($contact_identity['fut_st_cpf'] ?? ''));
    $existing_email = strtolower(trim((string) ($contact_identity['emailaddress1'] ?? '')));

    $form_cpf_digits = (string) preg_replace('/\D/', '', trim($params['cpf'] ?? ''));
    $form_email = strtolower(trim($params['email'] ?? ''));

    if (isset($attributes['fut_st_cpf'])) {
        if ('' !== $existing_cpf && $existing_cpf !== $form_cpf_digits) {
            unset($attributes['fut_st_cpf']);
        }
    }

    if (isset($attributes['emailaddress1'])) {
        $syncs_email = false;

        if ('' !== $form_email && $form_email !== $existing_email) {
            $user = get_user_by_contact($contact_id);

            if (!empty($user) && strtolower($user->user_email) === $form_email) {
                $syncs_email = true;
            }
        }

        if (!$syncs_email) {
            unset($attributes['emailaddress1']);
        }
    }

    return $attributes;
}

/**
 * R5 — espelhamento do contato no WordPress após o sucesso do batch.
 * Update-only: a criação de usuário segue a cargo do sync/first-access.
 */
function mirror_registration_contact_to_user (?string $contact_id, array $params, array $updated_attributes): void {
    if (empty($contact_id)) {
        return;
    }

    $user = get_user_by_contact($contact_id);

    if (empty($user)) {
        $email = trim($params['email'] ?? '');

        if ('' !== $email && is_email($email)) {
            $user = get_user_by('email', $email) ?: null;
        }
    }

    if (empty($user)) {
        return;
    }

    $user_id = $user->ID;
    $full_name = trim($params['nome_completo'] ?? '');

    $user_update = ['ID' => $user_id];

    if ('' !== $full_name) {
        $user_update['display_name'] = $full_name;
    }

    // E-mail do WP acompanha apenas quando o e-mail do CRM foi sincronizado (paridade).
    if (isset($updated_attributes['emailaddress1'])) {
        $user_update['user_email'] = trim($params['email']);
    }

    wp_update_user($user_update);

    $meta_input = [];

    if ('' !== $full_name) {
        $meta_input['nome_completo'] = $full_name;
    }

    foreach (['cpf', 'cargo', 'area', 'nivel_hierarquico', 'telefone'] as $key) {
        $value = trim($params[$key] ?? '');

        if ('' !== $value) {
            $meta_input[$key] = $value;
        }
    }

    foreach ($meta_input as $meta_key => $meta_value) {
        update_user_meta($user_id, $meta_key, $meta_value);
    }

    // Espelho dos atributos tocados no CRM — consistência com o dado que o
    // importer escreve, até o próximo sync.
    foreach ($updated_attributes as $attribute_key => $value) {
        if (is_scalar($value)) {
            update_user_meta($user_id, '_ethos_crm:' . $attribute_key, $value);
        }
    }
}

function create_registration_lead (array $params, ?Dynamics_Batch_Builder $builder = null): string|Dynamics_Batch_Reference {
    $systemuser = get_option('systemuser');

    $cnpj = trim($params['cnpj']);
    $company_name = trim($params['nome_fantasia'] ?? '');

    $full_name = trim($params['nome_completo']);
    $name_parts = explode(' ',  $full_name);
    $first_name = $name_parts[0];
    unset($name_parts[0]);
    $last_name = implode(' ', $name_parts);

    $attributes = [
        'companyname'                => $company_name,
        'firstname'                  => $first_name,
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

    $optional_fields = [
        // 'fut_pl_area'             => 'area',
        // 'fut_pl_nivelhierarquico' => 'nivel_hierarquico',
        'jobtitle'                => 'cargo',
        'telephone1'              => 'telefone',
    ];
    foreach ($optional_fields as $attribute_key => $param_key) {
        if ($value = trim($params[$param_key] ?? '')) {
            $attributes[$attribute_key] = $value;
        }
    }

    if (!empty($params['origem_lead'])) {
        $attributes['leadsourcecode'] = intval($params['origem_lead']);
    }

    $attributes = array_merge($attributes, get_registration_address_attributes($params));

    if ($builder) {
        return $builder->add_create('lead', $attributes);
    } else {
        return create_crm_entity('lead', $attributes);
    }
}

function generate_registration_number (int $post_id, int $count) {
    $prefix = get_post_meta($post_id, '_ethos_crm:fut_txt_prefixo', true);

    return $prefix . ($count + 1);
}

/**
 * Cases 1–2 (WordPress) da resolução de conta — sem roundtrips ao CRM.
 */
function get_registration_account_local (array $params): string|null {
    // Case 1. Retrieve UUID from current user's organization
    if (empty($params['cnpj'])) {
        if (($post_id = get_organization_by_user()) && ($account_id = get_post_meta($post_id, '_ethos_crm_account_id', true))) {
            return $account_id;
        } else {
            return null;
        }
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

    return null;
}

function get_registration_account (array $params): string|null {
    $account_id = get_registration_account_local($params);

    if (null !== $account_id) {
        return $account_id;
    }

    if (empty($params['cnpj'])) {
        return null;
    }

    // Case 3. Retrieve UUID directly from CRM accounts
    $accounts = get_crm_entities('account', [
        'filters' => [
            'fut_st_cnpjsemmascara' => $params['cnpj'],
        ],
    ]);
    if (!empty($accounts->Entities)) {
        cache_crm_entity($accounts->Entities[0]);
        return $accounts->Entities[0]->Id;
    }

    // Case 4. If account does not exist, return null
    return null;
}

/**
 * Cases 1–2 (WordPress) da resolução de contato — sem roundtrips ao CRM.
 */
function get_registration_contact_local (array $params): string|null {
    // Case 1. Retrieve UUID from current user's data
    if (($user_id = get_current_user_id()) && ($contact_id = get_user_meta($user_id, '_ethos_crm_contact_id', true))) {
        return $contact_id;
    }

    // Case 2. Retrieve UUID from other WordPress users
    $users = get_users([
        'meta_query' => [
            [ 'key' => 'cpf', 'value' => $params['cpf'] ],
            [ 'key' => '_ethos_crm_contact_id', 'compare' => 'EXISTS', 'value' => true ],
        ],
    ]);
    if (!empty($users) && ($contact_id = get_user_meta($users[0]->ID ?? 0, '_ethos_crm_contact_id', true))) {
        return $contact_id;
    }

    return null;
}

function get_registration_contact (array $params, string|Dynamics_Batch_Reference|null $lead_id = null, bool $allow_creation = true, ?Dynamics_Batch_Builder $builder = null): string|Dynamics_Batch_Reference|null {
    $contact_id = get_registration_contact_local($params);

    if (null !== $contact_id) {
        return $contact_id;
    }

    // Case 3. Retrieve UUID directly from CRM contacts
    $contacts = get_crm_entities('contact', [
        'filters' => [
            'fut_st_cpf' => format_cpf(trim($params['cpf'])),
        ],
    ]);
    if (!empty($contacts->Entities)) {
        cache_crm_entity($contacts->Entities[0]);
        return $contacts->Entities[0]->Id;
    }

    // Case 4. If contact does not exist, create it, and return its UUID
    if ($allow_creation) {
        return create_registration_contact($params, $lead_id, $builder);
    } else {
        return null;
    }
}

/**
 * Cases 1–2 (WordPress) da resolução de lead — sem roundtrips ao CRM.
 */
function get_registration_lead_local (array $params): string|null {
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
    }

    return null;
}

function get_registration_lead (array $params, ?Dynamics_Batch_Builder $builder = null): string|Dynamics_Batch_Reference|null {
    $lead_id = get_registration_lead_local($params);

    if (null !== $lead_id) {
        return $lead_id;
    }

    if (!empty($params['cnpj'])) {
        // Case 3. Retrieve UUID directly from CRM leads
        $leads = \hacklabr\batch\get_crm_entities_advanced('lead', [
            'or' => [
                ['field' => 'fut_st_cnpj', 'value' => $params['cnpj']],
                ['field' => 'fut_st_cnpj', 'value' => format_cnpj($params['cnpj'])],
            ],
        ], ['per_page' => 1]);
        if (!empty($leads->Entities)) {
            cache_crm_entity($leads->Entities[0]);
            return $leads->Entities[0]->Id;
        }

        // Case 4. If lead does not exist, create it, and return its UUID
        return create_registration_lead($params, $builder);
    }

    return null;
}

function registrations_are_open (int $post_id): bool {
    $crm_status = get_post_meta($post_id, '_ethos_crm:statuscode', true);
    $event_status = get_post_meta($post_id, '_ethos:event_status', true);

    if ($crm_status !== '1' || $event_status === 'FULL' || $event_status === 'PAST') {
        return false;
    }

    $allow_web = get_post_meta($post_id, '_ethos_crm:fut_bt_permiteweb', true);
    if ($allow_web !== '' && !in_array($allow_web, ['1', 'true', 'True'], true)) {
        return false;
    }

    return true;
}

function register_for_event (int $post_id, array $params) {
    global $hl_event_registration;
    $hl_event_registration = create_registration($post_id, $params);
}
