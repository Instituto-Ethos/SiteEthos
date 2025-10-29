<?php

namespace ethos\crm;

function generate_unique_user_login( string $user_name ) {
	$login_base = substr( sanitize_title( $user_name ), 0, 60 );

    if ( empty( get_user_by( 'login', $login_base ) ) ) {
        return $login_base;
    }

    $i = 2;

    while ( true ) {
        $login = $login_base . '-' . $i;

        if ( empty( get_user_by( 'login', $login ) ) ) {
            return $login;
        }

        $i++;
    }
}

function get_contact( string $contact_id, string $account_id ) {
    $existing_user = get_single_user( [
        'meta_query' => [
            [ 'key' => '_ethos_crm_account_id', 'value' => $account_id ],
            [ 'key' => '_ethos_crm_contact_id', 'value' => $contact_id ],
        ],
    ] );

    if ( empty( $existing_user ) ) {
        $account = \hacklabr\get_crm_entity_by_id( 'account', $account_id );
        $contact = \hacklabr\get_crm_entity_by_id( 'contact', $contact_id );

        if ( ! empty( $contact ) ) {
            return create_from_contact( $contact, $account, false );
        }
    } else {
        return $existing_user->ID;
    }

    return null;
}

function get_post_id_by_account( string $account_id ) {
    $existing_post = get_single_post( [
        'post_type' => 'organizacao',
        'meta_query' => [
            [ 'key' => '_ethos_crm_account_id', 'value' => $account_id ],
        ],
    ] );

    if ( empty( $existing_post ) ) {
        $account = \hacklabr\get_crm_entity_by_id( 'account', $account_id );

        if ( ! empty( $account ) ) {
            return create_from_account( $account );
        }

        return null;
    }

    return $existing_post->ID;
}

/**
 * Retrieves and caches the count and percentage of organizations by company size.
 *
 * This function queries the WordPress database for published 'organizacao' posts,
 * grouping them by the 'porte' (company size) meta value. It returns the total count,
 * counts per size category (micro/small, medium, large), and their respective percentages.
 * Results are cached using WordPress transients for 12 hours to improve performance.
 *
 * @since 1.0.0
 *
 * @return array {
 *     @type int   $total        Total number of organizations.
 *     @type array $counts       Counts per company size category.
 *     @type array $percentages  Percentages per company size category.
 * }
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @see https://developer.wordpress.org/reference/classes/wpdb/get_results/
 * @see https://developer.wordpress.org/reference/classes/wpdb/prepare/
 */
function get_organizations_company_size_data() : array {
    global $wpdb;

    $transient_key = 'organizations_company_size_data';

    $cached = get_transient( $transient_key );
    if ( $cached !== false ) {
        return $cached;
    }

    $sql = "
        SELECT LOWER(pm.meta_value) AS porte, COUNT(DISTINCT p.ID) AS total
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm
            ON pm.post_id = p.ID AND pm.meta_key = %s
        WHERE p.post_type = %s
          AND p.post_status = %s
        GROUP BY LOWER(pm.meta_value)
    ";

    $rows = $wpdb->get_results(
        $wpdb->prepare( $sql, 'porte', 'organizacao', 'publish' ),
        ARRAY_A
    );

    $counts = [
        'micro'  => 0,
        'small'  => 0,
        'medium' => 0,
        'large'  => 0,
    ];

    foreach ( $rows as $row ) {
        $v = $row['porte'];
        if ( isset( $counts[$v] ) ) {
            $counts[$v] = (int) $row['total'];
        }
    }

    $micro_small = $counts['micro'] + $counts['small'];
    $medium      = $counts['medium'];
    $large       = $counts['large'];

    $total = $micro_small + $medium + $large;

    $porcentage = function ( int $n, int $t ) : string {
        if ( $t <= 0 ) return '0';
        return (string) round( ( $n / $t ) * 100 );
    };

    $result = [
        'total'        => $total,
        'counts'       => [
            'micro_small' => $micro_small,
            'medium'      => $medium,
            'large'       => $large
        ],
        'percentages'  => [
            'micro_small' => $porcentage( $micro_small, $total ),
            'medium'      => $porcentage( $medium, $total ),
            'large'       => $porcentage( $large, $total )
        ],
    ];

    set_transient( $transient_key, $result, 12 * HOUR_IN_SECONDS );

    return $result;
}
