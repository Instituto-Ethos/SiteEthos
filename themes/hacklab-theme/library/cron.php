<?php

namespace hacklabr;

/**
 * Returns the reconciliation cron interval in seconds based on the configured frequency.
 *
 * Reads the `_ethos_reconciliation_frequency` option and maps it to a WordPress time constant.
 * Defaults to weekly if the option is missing or contains an unrecognized value.
 *
 * @since 1.0.0
 *
 * @return int Interval in seconds.
 */
function get_reconciliation_interval() : int {
    $frequency = get_option( '_ethos_reconciliation_frequency', 'weekly' );
    $intervals = [
        'daily'    => DAY_IN_SECONDS,
        'weekly'   => WEEK_IN_SECONDS,
        'biweekly' => 2 * WEEK_IN_SECONDS,
    ];
    return $intervals[ $frequency ] ?? WEEK_IN_SECONDS;
}

function add_cron_schedules (array $schedules) {
    $schedules['hacklabr_every_5_minutes'] = [
        'interval' => 5 * MINUTE_IN_SECONDS,
        'display' => sprintf(__('Every %s minutes', 'hacklabr'), 5),
    ];

    $schedules['hacklabr_hourly'] = [
        'interval' => HOUR_IN_SECONDS,
        'display' => __('Hourly', 'hacklabr'),
    ];

    $schedules['hacklabr_reconciliation'] = [
        'interval' => get_reconciliation_interval(),
        'display' => __('Ethos Reconciliation', 'hacklabr'),
    ];

    return $schedules;
}
add_filter('cron_schedules', 'hacklabr\\add_cron_schedules');

function clean_transactions () {
    $TRANSACTION_KEY = '_ethos_transaction';

    $posts = get_posts([
        'post_type' => ['organizacao'],
        'post_status' => ['draft', 'ethos_under_progress', 'publish'],
        'date_query' => [
            [ 'before' => '1 day ago', 'inclusive' => true ],
        ],
        'meta_query' => [
            [ 'key' => $TRANSACTION_KEY, 'compare' => 'EXISTS' ],
        ],
        'posts_per_page' => 20,
    ]);

    foreach ($posts as $post) {
        delete_post_meta($post->ID, $TRANSACTION_KEY);
    }

    $users = get_users([
        'date_query' => [
            [ 'before' => '1 day ago', 'inclusive' => true ],
        ],
        'meta_query' => [
            [ 'key' => $TRANSACTION_KEY, 'compare' => 'EXISTS' ],
        ],
        'number' => 20,
    ]);

    foreach ($users as $user) {
        delete_user_meta($user->ID, $TRANSACTION_KEY);
    }
}
add_action('hacklabr\\run_every_hour', 'hacklabr\\clean_transactions');

/**
 * Registers and schedules all recurring WP-Cron events.
 *
 * Ensures the 5-minute and hourly jobs are scheduled. For the reconciliation cron,
 * only re-creates the scheduled event when the frequency option has changed or
 * no event exists yet. When frequency is set to "manual", the event is cleared.
 *
 * @since 1.0.0
 */
function schedule_recurring_tasks () {
    if (!wp_next_scheduled('hacklabr\\run_every_5_minutes')) {
		wp_schedule_event(time(), 'hacklabr_every_5_minutes', 'hacklabr\\run_every_5_minutes');
	}

    if (!wp_next_scheduled('hacklabr\\run_every_hour')) {
		wp_schedule_event(time(), 'hacklabr_hourly', 'hacklabr\\run_every_hour');
	}

    $frequency = get_option( '_ethos_reconciliation_frequency', 'weekly' );

    if ( $frequency === 'manual' ) {
        if ( wp_next_scheduled( 'hacklabr\\run_reconciliation' ) ) {
            wp_clear_scheduled_hook( 'hacklabr\\run_reconciliation' );
        }
    } else {
        $last_freq = get_option( '_ethos_reconciliation_frequency_last', '' );
        if ( $frequency !== $last_freq || ! wp_next_scheduled( 'hacklabr\\run_reconciliation' ) ) {
            wp_clear_scheduled_hook( 'hacklabr\\run_reconciliation' );
            wp_schedule_event( time(), 'hacklabr_reconciliation', 'hacklabr\\run_reconciliation' );
            update_option( '_ethos_reconciliation_frequency_last', $frequency );
        }
    }
}
add_action('wp', 'hacklabr\\schedule_recurring_tasks');
