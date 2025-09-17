<?php

namespace hacklabr;

function get_subscription_data ($attributes): array {
    $account_id = $attributes['account_id'];

    $cached_data = get_block_transient('hacklabr/subscription', $attributes);
    if ($cached_data !== false) {
        return $cached_data;
    }

    $subscriptions_col = \hacklabr\get_crm_entities('fut_participante', [
        'filters' => [
            'fut_lk_empresa' => $account_id,
        ],
    ]);

    $subscriptions = $subscriptions_col->Entities ?? [];

    set_block_transient('hacklabr/subscription', $attributes, $subscriptions);

    return $subscriptions;
}

function render_subscription_callback ($attributes) {
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

    $subscriptions = get_subscription_data([ 'account_id' => $account_id ]);

    ob_start();
    ?>

    <table class="hacklabr-subscription-block">
        <thead>
            <tr>
                <th><?php _e('Date', 'hacklabr') ?></th>
                <th><?php _e('Title', 'hacklabr') ?></th>
                <th><?php _e('Status', 'hacklabr') ?></th>
            </tr>
        </thead>

        <tbody>
        <?php foreach ($subscriptions as $subscription): ?>
            <tr>
                <td><?= $subscription->FormattedValues['fut_dt_iniciodaparticipao'] ?? '' ?></td>
                <td><a href="<?= get_event_url( $subscription ) ?>"><?= $subscription->FormattedValues['fut_lk_projeto'] ?? '' ?></a></td>
                <td class="status status--pendente"><span><?= $subscription->FormattedValues['fut_set_statusoperacao'] ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php
    $output = ob_get_clean();

    return $output;
}
