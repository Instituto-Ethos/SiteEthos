<?php

namespace hacklabr;

function get_my_plan_data () {
    $user_id = get_associated_user_id();

    $plan_slug = get_pmpro_plan($user_id);

    if (empty($plan_slug)) {
        return null;
    }

    if ($plan_slug === 'conexao') {
        $plan = (object) [
            'label' => 'Conexão',
            'slug' => 'conexao',
            'contains' => ['conexao'],
        ];
    } elseif ($plan_slug === 'essencial') {
        $plan = (object) [
            'label' => 'Essencial',
            'slug' => 'essencial',
            'contains' => ['essencial', 'conexao'],
        ];
    } elseif ($plan_slug === 'vivencia') {
        $plan = (object) [
            'label' => 'Vivência',
            'slug' => 'vivencia',
            'contains' => ['vivencia', 'essencial', 'conexao'],
        ];
    } elseif ($plan_slug === 'institucional') {
        $plan = (object) [
            'label' => 'Institucional',
            'slug' => 'institucional',
            'contains' => ['institucional', 'vivencia', 'essencial', 'conexao'],
        ];
    } else {
        /*
         * Planos do novo modelo (ex.: 'ethos-transformar') ou qualquer outro
         * slug não legado: exibe o nome como veio, sem hierarquia legada.
         */
        $plan = (object) [
            'label' => ucwords( str_replace( '-', ' ', $plan_slug ) ),
            'slug' => $plan_slug,
            'contains' => [ $plan_slug ],
        ];
    }

    return $plan;
}

function render_my_plan_callback ($attributes) {
    $plan = get_my_plan_data();

    if (empty($plan)) {
        return '';
    }

    /*
     * Novo modelo: botão de mudança de plano para gestores (a página de
     * solicitações é restrita a eles - mesma guarda de
     * show_associated_page). Leva direto ao formulário com o assunto
     * pré-selecionado; a troca é operada pelo comercial via renovação da
     * parceria no CRM.
     */
    $change_plan_url = '';

    $user_id = get_associated_user_id();

    if (!empty($user_id) && (current_user_can('edit_others_associates') || get_user_meta($user_id, '_ethos_admin', true))) {
        $requests_page = get_page_by_path('minhas-solicitacoes');

        if (!empty($requests_page)) {
            $change_plan_url = add_query_arg('assunto', 'alteracao-plano', get_permalink($requests_page));
        }
    }

    ob_start();
?>
    <div class="my-plan">
        <div class="my-plan__summary">
            <p><?php _ex('Your current plan:', 'membership', 'hacklabr') ?> <strong><?= $plan->label ?></strong>.</p>
            <?php if (!empty($change_plan_url)): ?>
            <a class="button button--outline my-plan__change" href="<?= esc_url($change_plan_url) ?>"><?= __('Alterar plano', 'hacklabr') ?></a>
            <?php endif; ?>
        </div>
    </div>
<?php
    $output = ob_get_clean();

    return $output;
}
