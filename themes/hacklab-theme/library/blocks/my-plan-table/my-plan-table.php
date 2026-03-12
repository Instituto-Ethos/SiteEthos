<?php

namespace hacklabr;

function get_my_plan_table_data () {
    $user_id = get_associated_user_id();

    $plan = get_pmpro_plan($user_id);

    return $plan ?: 'conexao';
}

function render_my_plan_table_callback ($attributes, $content) {
    $plan = get_my_plan_table_data();

    ob_start();
?>
    <div class="my-plan-table my-plan-table--<?= $plan ?>">
        <table>
            <thead>
                <tr>
                    <th><?php _e('Benefits', 'hacklabr') ?></th>
                    <th class="my-plan-table__conexao">
                        <img src="<?= get_stylesheet_directory_uri() ?>/assets/images/circle-conexao.png"  alt="">
                        <span>Conexão</span>
                    </th>
                    <th class="my-plan-table__essencial">
                        <img src="<?= get_stylesheet_directory_uri() ?>/assets/images/circle-essencial.png" alt="">
                        <span>Essencial</span>
                    </th>
                    <th class="my-plan-table__vivencia">
                        <img src="<?= get_stylesheet_directory_uri() ?>/assets/images/circle-vivencia.png" alt="">
                        <span>Vivência</span>
                    </th>
                    <th class="my-plan-table__institucional">
                        <img src="<?= get_stylesheet_directory_uri() ?>/assets/images/circle-institucional.png" alt="">
                        <span>Institucional</span>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?= $content ?>
            </tbody>
        </table>
    </div>
<?php
    $output = ob_get_clean();

    return $output;
}
