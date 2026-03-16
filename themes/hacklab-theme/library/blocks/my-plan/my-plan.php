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
    } else {
        $plan = (object) [
            'label' => 'Institucional',
            'slug' => 'institucional',
            'contains' => ['institucional', 'vivencia', 'essencial', 'conexao'],
        ];
    }

    return $plan;
}

function render_my_plan_callback ($attributes) {
    $plan = get_my_plan_data();

    if (empty($plan)) {
        return '';
    }

    ob_start();
?>
    <div class="my-plan">
        <div class="my-plan__summary">
            <p><?php _ex('Your current plan:', 'membership', 'hacklabr') ?> <strong><?= $plan->label ?></strong>.</p>
        </div>
    </div>
<?php
    $output = ob_get_clean();

    return $output;
}
