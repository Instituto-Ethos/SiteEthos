<?php

namespace hacklabr;

function get_my_payments_data ($attributes) {
    $account_id = $attributes['account_id'];

    $cached_data = get_block_transient('hacklabr/my-payments', $attributes);
    if ($cached_data !== false) {
        return $cached_data;
    }

    $payment_data = [];

    $account = \hacklabr\get_crm_entity_by_id('account', $account_id);

    if (!empty($account)) {
        $payment_data['plan'] = $account->FormattedValues['fut_pl_tipo_associacao'] ?? null;
        $payment_data['situation'] = $account->FormattedValues['fut_pl_situacaofinanceira'] ?? null;
    }

    $type_association = '45f72b42-c0c8-e411-80dd-c4346bb59994';

    $partnerships_col = \hacklabr\get_crm_entities('fut_parceria', [
        'filters' => [
            'fut_lk_empresa' => $account_id,
            'fut_lk_tipodeparceria' =>  $type_association,
        ],
    ]);

    $partnerships = $partnerships_col?->Entities ?? [];

    if (!empty($partnerships)) {
        usort($partnerships, function ($a, $b) {
            return strcmp($b->Attributes['fut_dt_iniciovigencia'], $a->Attributes['fut_dt_iniciovigencia']);
        });

        $partnership = $partnerships[0] ?? null;

        if (!empty($partnership)) {
            $payment_data['periodicity'] = $partnership->FormattedValues['fut_pl_periodicidade'] ?? null;
            $payment_data['start'] = $partnership->FormattedValues['fut_dt_iniciovigencia'] ?? null;
            $payment_data['value'] = $partnership->FormattedValues['fut_mn_valor'] ?? null;
        }
    }

    set_block_transient('hacklabr/my-payments', $attributes, $payment_data);

    return $payment_data;
}

function render_my_payments_callback ($attributes) {
    $user_id = get_current_user_id();

    /**
     * Checks if the current user has permission to edit other associates.
     * If so, retrieves the organization ID from the GET request and validates its post type.
     * If the organization is valid, fetches the user ID of the organization author.
     */
    if ( current_user_can( 'edit_others_associates' ) ) {
        $organization_id = isset( $_GET['organization'] ) ? intval( $_GET['organization'] ) : 0;

        if ( $organization_id && get_post_type( $organization_id ) === 'organizacao' ) {
            $user_id = get_post_field( 'post_author', $organization_id );
        }
    }

    $organization = get_organization_by_user($user_id);

    if (empty($organization)) {
        return '';
    }

    $account_id = get_post_meta($organization->ID, '_ethos_crm_account_id', true);

    $payment_data = get_my_payments_data([ 'account_id' => $account_id ]);

    ob_start();
?>
    <table class="my-payments">
        <tbody>
        <?php if (!empty($payment_data['plan'])): ?>
            <tr>
                <td><?php _ex('Plan', 'membership', 'hacklabr') ?></td>
                <td><?= $payment_data['plan'] ?></td>
            </tr>
            <p></p>
        <?php endif; ?>
        <?php if (!empty($payment_data['periodicity'])): ?>
            <tr>
                <td><?php _e('Periodicity', 'hacklabr') ?></td>
                <td><?= $payment_data['periodicity'] ?></td>
            </tr>
        <?php endif; ?>
        <?php if (!empty($payment_data['start'])): ?>
            <tr>
                <td><?php _e('Start of validity', 'hacklabr') ?></td>
                <td><?= $payment_data['start'] ?></td>
            </tr>
        <?php endif; ?>
        <?php if (!empty($payment_data['value'])): ?>
            <tr>
                <td><?php _e('Value', 'hacklabr') ?></td>
                <td><?= $payment_data['value'] ?></td>
            </tr>
        <?php endif; ?>
        <?php if (!empty($payment_data['situation'])): ?>
            <?php $modifier = $payment_data['situation'] === 'Adimplente' ? 'adimplente' : 'inadimplente'; ?>
            <tr class="my-payments__situation my-payments__situation--<?= $modifier ?>">
                <td><?php _ex('Situation', 'membership', 'hacklabr') ?></td>
                <td><span><?= $payment_data['situation'] ?></span></td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
<?php
    $output = ob_get_clean();

    return $output;
}
