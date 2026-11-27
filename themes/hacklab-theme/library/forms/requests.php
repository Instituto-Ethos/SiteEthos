<?php

namespace hacklabr;

function get_request_subject_options (): array {
    $subject_options = [
        'alteracao-plano' => _x('Plan change', 'subject', 'hacklabr'),
        'declaracao-associacao' => _x('Statement of association', 'subject', 'hacklabr'),
        'indicadores' => _x('Ethos Indicators', 'subject', 'hacklabr'),
        'cursos' => _x('Courses', 'subject', 'hacklabr'),
        'eventos' => _x('Events', 'subject', 'hacklabr'),
        'palestras' => _x('Lectures', 'subject', 'hacklabr'),
        'publicacoes' => _x('Publications', 'subject', 'hacklabr'),
        'pactos' => _x('Pacts', 'subject', 'hacklabr'),
        'conferencia' => _x('Conference', 'subject', 'hacklabr'),
        'financeiro' => _x('Financial', 'subject', 'hacklabr'),
        'outros' => _x('Other', 'subject', 'hacklabr'),
    ];

    return $subject_options;
}

function get_request_crm_area (string $subject): string|null {
    switch ($subject) {
        case 'alteracao-plano':
        case 'declaracao-associacao':
            return '969830005'; // Relacionamento e Captação
        case 'conferencia':
        case 'eventos':
            return '969830002'; // Eventos
        case 'cursos':
        case 'indicadores':
        case 'outros':
        case 'pactos':
        case 'palestras':
        case 'publicacoes':
            return '490750000'; // Institucional
        case 'financeiro':
            return '969830004'; // Financeiro
        default:
            return null;
    }
}

function get_request_crm_project (string $subject): string|null {
    switch ($subject) {
        case 'conferencia':
            return '969830008'; // Conferência Ethos
        case 'cursos':
            return '969830014'; // Cursos
        case 'indicadores':
            return '969830001'; // Indicadores Ethos
        case 'palestras':
            return '969830013'; // Palestras
        default:
            return null;
    }
}

function get_request_crm_type (string $subject): string|null {
    switch ($subject) {
        case 'alteracao-plano':
        case 'declaracao-associacao':
            return '969830000'; // Associação
        case 'conferencia':
        case 'cursos':
        case 'eventos':
        case 'financeiro':
        case 'outros':
        case 'palestras':
        case 'publicacoes':
            return '969830011'; // Outros
        case 'indicadores':
            return '969830005'; // Indicadores Ethos - Geral
        case 'pactos':
            return '969830008'; // Pactos e Compromissos
        default:
            return null;
    }
}

/**
 * Assunto do request corrente: POST do submit/re-render, ou pré-seleção
 * por query string (?assunto=alteracao-plano — entry point do botão
 * "Alterar plano" do bloco my-plan). Superglobals (não filter_input) para
 * o campo aparecer já no render; o valor é validado contra whitelist.
 */
function get_request_current_subject (): string {
    $subject = $_POST['_assunto'] ?? '';

    if (!is_string($subject) || '' === $subject) {
        $subject = $_GET['assunto'] ?? '';
    }

    return is_string($subject) ? trim($subject) : '';
}

/**
 * Options do campo "Plano desejado" da solicitação de mudança de plano:
 * planos de associação disponíveis no CRM (mesma fonte do wizard do novo
 * modelo) + orientação do comercial. A mudança sempre converge para o novo
 * modelo (o comercial opera a troca via renovação da parceria). Fail
 * closed: sem dados do CRM, segue apenas a orientação — a descrição livre
 * complementa o pedido.
 */
function get_request_plan_options (): array {
    $options = [];

    if (function_exists('hacklabr\obter_planos_disponiveis')) {
        foreach (\hacklabr\obter_planos_disponiveis() ?? [] as $plano) {
            if (!empty($plano->id) && !empty($plano->nome)) {
                $options[$plano->id] = $plano->nome;
            }
        }
    }

    $options['orientacao'] = __('Quero orientação do time comercial', 'hacklabr');

    return $options;
}

function get_request_occurrence_fields () {
    $privacy_policy_url =  get_privacy_policy_url();

    $subject_options = get_request_subject_options();

    /*
     * Campo condicional do novo modelo: "Plano desejado" só aparece (e só
     * é obrigatório) quando o assunto é mudança de plano. No render
     * inicial vem da pré-seleção ?assunto= (entry point do bloco
     * my-plan); no re-render de erro, do POST — o init remonta os campos
     * com o required já ligado.
     */
    $is_plan_change = ('alteracao-plano' === get_request_current_subject());

    $fields = [
        'assunto' => [
            'type' => 'select',
            'class' => '-colspan-12',
            'label' =>__('Subject', 'hacklabr'),
            'options' => $subject_options,
            'required' => true,
            'validate' => function ($value, $context) use ($subject_options) {
                if (!array_key_exists($value, $subject_options)) {
                    return __('Invalid subject', 'hacklabr');
                }
                return true;
            },
        ],
        'financeiro' => [
            'type' => 'hidden',
            'class' => '-hidden',
            'required' => false,
        ],
        'relacionamento' => [
            'type' => 'hidden',
            'class' => '-hidden',
            'required' => false,
        ],
        'plano_desejado' => [
            'type' => 'select',
            'class' => '-colspan-12',
            'label' => __('Plano desejado', 'hacklabr'),
            'placeholder' => __('Selecione o plano desejado', 'hacklabr'),
            'options' => $is_plan_change ? get_request_plan_options() : [],
            'required' => $is_plan_change,
            'conditional' => function () use ($is_plan_change) {
                return $is_plan_change;
            },
            'validate' => function ($value, $context) {
                if (!array_key_exists($value, get_request_plan_options())) {
                    return __('Invalid plan', 'hacklabr');
                }
                return true;
            },
        ],
        'titulo' => [
            'type' => 'text',
            'class' => '-colspan-12',
            'label' => __('Title', 'hacklabr'),
            'placeholder' => __('Request title', 'hacklabr'),
            'required' => true,
            'validate' => function ($value, $context) {
                if (mb_strlen($value) > 200) {
                    return __('Title is too long (max 200 characters)', 'hacklabr');
                }
                return true;
            },
        ],
        'descricao' => [
            'type' => 'textarea',
            'class' => '-colspan-12',
            'label' => __('Describe your requirements', 'hacklabr'),
            'placeholder' => __("Describe briefly your demand", 'hacklabr'),
            'required' => true,
            'validate' => function ($value, $context) {
                if (mb_strlen($value) > 5000) {
                    return __('Description is too long (max 5000 characters)', 'hacklabr');
                }
                return true;
            },
        ],
        'politica_privacidade' => [
            'type' => 'checkbox',
            'class' => '-colspan-12',
            'label' => sprintf(__('I have read and agreed with the <a href="%s" target="_blank">Privacy Policy</a>', 'hacklabr'), $privacy_policy_url),
            'required' => true,
        ],
    ];

    return $fields;
}

function get_request_occurrence_params () {
    $params = sanitize_form_params();

    if (empty($params['financeiro']) & !empty($_GET['financeiro'])) {
        $params['assunto'] = 'financeiro';
    }

    if (empty($params['assunto']) & !empty($_GET['assunto'])) {
        $params['assunto'] = filter_input(INPUT_GET, 'assunto');
    }

    return $params;
}

function register_request_occurrence_form () {
    $fields = get_request_occurrence_fields();

    register_form('request-occurrence', __('Requests', 'hacklabr'), [
        'fields' => $fields,
        'get_params' => 'hacklabr\\get_request_occurrence_params',
    ]);
}
add_action('init', 'hacklabr\\register_request_occurrence_form');

function validate_request_occurrence_form ($form_id, $form, $params) {
    if ($form_id === 'request-occurrence') {
        $validation = validate_form($form['fields'], $params);

        if ($validation !== true) {
            return;
        }

        $current_user = get_associated_user_id();

        $account_id = get_user_meta($current_user, '_ethos_crm_account_id', true);
        $contact_id = get_user_meta($current_user, '_ethos_crm_contact_id', true);

        $subject = $params['assunto'];

        /*
         * Sanitização na fronteira de uso: os textos livres alimentam o
         * CRM e o e-mail em HTML - strip de tags (o corpo do e-mail só
         * injeta <br/> de quebras legítimas) e colapso de espaços, com
         * CRLF removido do título (proteção extra contra header/subject
         * injection). A validação (whitelist/limites) acontece no
         * validate_form; a sanitização aqui garante o dado limpo mesmo
         * com campos sem closure própria.
         */
        $title = sanitize_text_field(str_replace(["\r", "\n"], ' ', $params['titulo']));
        $description = sanitize_textarea_field($params['descricao']);
        $params['titulo'] = $title;
        $params['descricao'] = $description;

        /*
         * Novo modelo: mudança de plano - a escolha estruturada do plano
         * encabeça a descrição do incident (e do e-mail ao comercial),
         * que opera a troca via renovação da parceria no CRM. Sem
         * dependência de schema: texto legível no case.
         */
        if ('alteracao-plano' === $subject && !empty($params['plano_desejado'])) {
            $plan_options = get_request_plan_options();
            $plano_nome = $plan_options[$params['plano_desejado']] ?? null;

            if ('orientacao' === $params['plano_desejado']) {
                $plano_nome = __('a definir com o time comercial', 'hacklabr');
            }

            if (!empty($plano_nome)) {
                $description = sprintf(__('Plano desejado: %s', 'hacklabr'), $plano_nome) . "\n\n" . $description;
                $params['descricao'] = $description;
            }
        }

        $attributes = [
            'caseorigincode' => 3, // Site
            'contactid'      => create_crm_reference('contact', $contact_id),
            'customerid'     => create_crm_reference('account', $account_id),
            'description'    => $description,
            'title'          => $title
        ];

        $crm_area = get_request_crm_area($subject);
        if (!empty($crm_area)) {
            $attributes['fut_pl_area'] = $crm_area;
        }

        $crm_project = get_request_crm_project($subject);
        if (!empty($crm_project)) {
            $attributes['fut_pl_projeto'] = $crm_project;
        }

        $crm_type = get_request_crm_type($subject);
        if (!empty($crm_type)) {
            $attributes['fut_pl_tipo_de_atendimento'] = $crm_type;
        }

        try {
            $incident_id = create_crm_entity('incident', $attributes);
            do_action('logger', 'Created request (incident) with ID = ' . $incident_id);

            send_request_occurrence_email($current_user, $incident_id, $params);

            $success_page = get_page_by_path('solicitacao-enviada') ?: get_page_by_path('boas-vindas');
            wp_safe_redirect(get_permalink($success_page));
            exit;
        } catch (\Exception $err) {
            do_action('logger', $err->getMessage());
        }
    }
}
add_action('hacklabr\\form_action', 'hacklabr\\validate_request_occurrence_form', 10, 3);

function send_request_occurrence_email (int $user_id, string $incident_id, array $params) {
    $company =  get_organization_by_user($user_id);
    $manager = get_manager_data($company->ID);

    /*
     * Latent bug fix: get_user() only exists since WP 6.7 — this stack
     * runs 6.6 (and the plugins declare Requires at least 5.2), so the
     * unqualified call inside the namespace resolved to an undefined
     * function and fatalled the request email flow. get_userdata() is
     * the version-safe call (available since WP 2.0).
     */
    $sender = get_userdata($user_id);
    $sender_name = $sender->display_name;
    $sender_email = $sender->user_email;

    $sender_detail = '';
    if (current_user_can('edit_others_associates')) {
        $sender_detail = ' ' . __('(via helpdesk)', 'hacklabr');
    }

    $subject_options = get_request_subject_options();
    $subject = $subject_options[$params['assunto']];

    $title = $params['titulo'];
    $description = str_replace("\n", '<br/>', $params['descricao']);

    $email_to = $manager->email ?? '';

    /* translators: %1$s: Subject, %2$s: Title. */
    $email_subject = sprintf(__('New Request: [%1$s] %2$s', 'hacklabr'), $subject, $title);

    $email_data = [
        __('Sender', 'hacklabr') => sprintf( '<a href="mailto:%s">%s</a>%s', $sender_email, $sender_name, $sender_detail),
        __('Organization', 'hacklabr') => $company->post_title,
        __('Subject', 'hacklabr') => $subject,
        __('Title', 'hacklabr') => $title,
        __('Description', 'hacklabr') => $description,
        __('Occurrence', 'hacklabr') => $incident_id,
    ];

    $email_message = '';
    foreach ($email_data as $key => $value) {
        $email_message .= "<p><b>{$key}:</b> {$value}</p>";
    }

    $email_headers = [
        'Content-Type: text/html; charset=UTF-8',
        sprintf( 'Reply-To: %s <%s>', $sender_name, $sender_email ),
    ];

    return wp_mail($email_to, $email_subject, $email_message, $email_headers);
}
