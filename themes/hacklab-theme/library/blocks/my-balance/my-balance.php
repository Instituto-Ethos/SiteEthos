<?php

namespace hacklabr;

function get_my_balance_data () {
    /*
     * Depende da camada de acesso às Custom APIs do plano de associação
     * (plugin Ethos Dynamics 365 Integration, includes/association-api.php).
     */
    if ( ! function_exists( 'hacklabr\consultar_saldo' ) ) {
        return null;
    }

    $organization = get_organization_by_user();

    if ( empty( $organization ) ) {
        return null;
    }

    $cnpj = get_post_meta( $organization->ID, 'cnpj', true );

    if ( empty( $cnpj ) ) {
        return null;
    }

    $balance = consultar_saldo( $cnpj );

    if ( is_wp_error( $balance ) || empty( $balance->saldos ) ) {
        return null;
    }

    return $balance;
}

function render_my_balance_callback ( $attributes ) {
    $balance = get_my_balance_data();

    if ( empty( $balance ) ) {
        return '';
    }

    $plano = $balance->plano->nome ?? '';
    $total = (int) ( $balance->resumo->quantidadeTotalCreditos ?? 0 );

    ob_start();
?>
    <div class="my-balance">
        <div class="my-balance__summary">
            <p><?php esc_html_e( 'Seus créditos disponíveis no plano', 'hacklabr' ); ?> <strong><?= esc_html( $plano ) ?></strong>.</p>
        </div>

        <ul class="my-balance__faixas">
            <?php foreach ( $balance->saldos as $saldo ) :
                $faixa = $saldo->faixaNome ?? '';
                $creditos = (int) ( $saldo->quantidadeCreditos ?? 0 );
            ?>
                <li class="my-balance__faixa my-balance__faixa--<?= esc_attr( strtolower( $faixa ) ) ?>">
                    <span class="my-balance__faixa-label"><?php printf( esc_html__( 'Faixa %s', 'hacklabr' ), esc_html( $faixa ) ); ?></span>
                    <strong class="my-balance__faixa-value"><?= esc_html( $creditos ) ?></strong>
                </li>
            <?php endforeach; ?>
        </ul>

        <p class="my-balance__total">
            <?php printf( esc_html__( 'Total de participações disponíveis: %d', 'hacklabr' ), $total ); ?>
        </p>
    </div>
<?php
    $output = ob_get_clean();

    return $output;
}
